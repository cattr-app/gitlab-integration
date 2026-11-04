<?php

namespace Modules\GitlabIntegration\Jobs;

use App\Models\TimeInterval;
use App\Models\User;
use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Log;
use Modules\GitlabIntegration\Entities\ProjectRelation;
use Modules\GitlabIntegration\Entities\TaskRelation;
use Modules\GitlabIntegration\Helpers\GitlabApi;
use Modules\GitlabIntegration\Helpers\TimeIntervalsHelper;
use Throwable;

class ReassignTaskToEditedInterval implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private const LOCK_TTL = 120;
    private const LOCK_WAIT = 60;

    public function __construct(private TimeInterval $interval)
    {
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHour();
    }

    /**
     * Never process the same interval concurrently. Order of jobs does not matter: every job moves the interval's time
     * from the task GitLab currently has it on to the interval's current task.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->interval->id))->releaseAfter(10)->expireAfter(300)];
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $this->reassign();
    }

    /**
     * Acquire locks for all given keys in a stable order (prevents deadlocks), then run the callback
     *
     * @param list<string> $keys
     */
    private function withIssueLocks(array $keys, Closure $callback): mixed
    {
        $keys = array_values(array_unique($keys));
        sort($keys);

        $run = $callback;
        foreach (array_reverse($keys) as $key) {
            $next = $run;
            $run = static fn() => Cache::lock($key, self::LOCK_TTL)->block(self::LOCK_WAIT, $next);
        }

        return $run();
    }

    /**
     * @throws Throwable
     */
    private function reassign(): void
    {
        // Task whose GitLab issue holds the interval's time now (recorded before the first edit after sync)
        $assignment = DB::table(TimeIntervalsHelper::GIA_TABLE)
            ->where('time_interval_id', $this->interval->id)
            ->first();

        // Do nothing if the interval is not tracked in GitLab or is already on its current task
        if ($assignment === null || (int)$assignment->task_id === $this->interval->task_id) {
            return;
        }

        Log::withContext([
            'interval' => $this->interval->id,
            'task' => [
                'id' => $this->interval->task->id,
                'name' => $this->interval->task->task_name,
            ]
        ]);

        throw_unless($this->interval->user);

        Log::withContext([
            'user' => [
                'id' => $this->interval->user->id,
                'email' => $this->interval->user->email,
            ]
        ]);

        // Do nothing if the interval has not yet been sent
        if (
            DB::table('gitlab_intervals_sync')
                ->where([
                    'time_interval_id' => $this->interval->id,
                    'is_synced' => false,
                ])
                ->exists()
        ) {
            return;
        }

        $api = GitlabApi::buildFromUser($this->interval->user);

        if (!isset($api)) {
            Log::warning('Can`t instantiate an API for user');

            $this->fail();
        }

        $duration = Carbon::parse($this->interval->end_at)->diffInSeconds($this->interval->start_at);

        $prevTaskRel = TaskRelation::whereTaskId($assignment->task_id)->firstOrFail();
        $prevProjRel = ProjectRelation::whereProjectId($prevTaskRel->task->project_id)->firstOrFail();
        $newTaskRel = TaskRelation::whereTaskId($this->interval->task_id)->firstOrFail();
        $newProjRel = ProjectRelation::whereProjectId($newTaskRel->task->project_id)->firstOrFail();

        // GitLab time is rewritten with read-reset-add, so every touched issue is locked for the whole move
        $this->withIssueLocks(
            [
                "gitlab-issue-time:{$prevProjRel->gitlab_id}:{$prevTaskRel->gitlab_issue_iid}",
                "gitlab-issue-time:{$newProjRel->gitlab_id}:{$newTaskRel->gitlab_issue_iid}",
            ],
            function () use ($prevProjRel, $prevTaskRel, $newProjRel, $newTaskRel, $duration, $api) {
                DB::transaction(static function () use ($prevProjRel, $prevTaskRel, $duration, $api) {
                    $time = $api->getUserTime($prevProjRel->gitlab_id, $prevTaskRel->gitlab_issue_iid);
                    $api->resetUserTime($prevProjRel->gitlab_id, $prevTaskRel->gitlab_issue_iid);
                    $api->sendUserTime(
                        $prevProjRel->gitlab_id,
                        $prevTaskRel->gitlab_issue_iid,
                        ($time - $duration) . 's'
                    );
                });

                DB::transaction(static function () use ($newProjRel, $newTaskRel, $duration, $api) {
                    $api->sendUserTime($newProjRel->gitlab_id, $newTaskRel->gitlab_issue_iid, $duration . 's');
                });

                DB::table(TimeIntervalsHelper::GIA_TABLE)
                    ->where('time_interval_id', $this->interval->id)
                    ->update(['task_id' => $this->interval->task_id]);
            }
        );
    }
}
