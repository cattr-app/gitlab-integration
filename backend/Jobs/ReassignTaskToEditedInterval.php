<?php

namespace Modules\GitlabIntegration\Jobs;

use App\Models\TimeInterval;
use App\Models\User;
use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Log;
use Modules\GitlabIntegration\Entities\ProjectRelation;
use Modules\GitlabIntegration\Entities\TaskRelation;
use Modules\GitlabIntegration\Helpers\GitlabApi;
use Modules\GitlabIntegration\Helpers\TimeIntervalsHelper;
use RuntimeException;
use Throwable;

class ReassignTaskToEditedInterval implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Skip the job if the interval was deleted before it ran */
    public bool $deleteWhenMissingModels = true;

    /** Retries are only safe while GitLab state is untouched; do not loop on repeated failures */
    public int $maxExceptions = 3;

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
     * Never process the same interval concurrently. Order of jobs does not matter: the interval is reloaded from the
     * database for every attempt (SerializesModels), and the job moves its time from the task GitLab currently has
     * it on to the interval's current task.
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

            return;
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
                $this->moveTime(
                    $api,
                    $prevProjRel->gitlab_id,
                    $prevTaskRel->gitlab_issue_iid,
                    $newProjRel->gitlab_id,
                    $newTaskRel->gitlab_issue_iid,
                    $duration
                );

                DB::table(TimeIntervalsHelper::GIA_TABLE)
                    ->where('time_interval_id', $this->interval->id)
                    ->update(['task_id' => $this->interval->task_id]);
            }
        );
    }

    /**
     * Move $duration seconds from one issue to another. GitLab has no transactions, so a failure after the old issue
     * was touched is compensated; if even that fails the job is failed for good (no blind retry) and the state is logged.
     *
     * @throws Throwable
     */
    private function moveTime(GitlabApi $api, $oldProject, $oldIid, $newProject, $newIid, int $duration): void
    {
        // Nothing has changed yet: a failure here is safe to retry
        $original = $api->getUserTime($oldProject, $oldIid);
        $api->resetUserTime($oldProject, $oldIid);

        try {
            $this->sendOrThrow($api, $oldProject, $oldIid, $original - $duration);
        } catch (Throwable $e) {
            $this->compensate($api, $e, [$oldProject, $oldIid, $original]);
        }

        try {
            $this->sendOrThrow($api, $newProject, $newIid, $duration);
        } catch (Throwable $e) {
            $this->compensate($api, $e, [$oldProject, $oldIid, $original]);
        }
    }

    /**
     * Restore the old issue's total. On success the exception is rethrown (state is back to the start, retry is safe),
     * otherwise the job fails permanently.
     *
     * @param array{0: mixed, 1: mixed, 2: int} $oldIssue
     * @throws Throwable
     */
    private function compensate(GitlabApi $api, Throwable $cause, array $oldIssue): void
    {
        [$project, $iid, $original] = $oldIssue;

        try {
            $api->resetUserTime($project, $iid);
            $this->sendOrThrow($api, $project, $iid, $original);
        } catch (Throwable $restoreError) {
            Log::critical('Failed to restore GitLab issue time after a failed move, manual reconciliation required', [
                'interval' => $this->interval->id,
                'project' => $project,
                'issue_iid' => $iid,
                'original_total_seconds' => $original,
                'cause' => $cause->getMessage(),
                'restore_error' => $restoreError->getMessage(),
            ]);

            $this->fail($cause);

            throw $cause;
        }

        throw $cause;
    }

    /**
     * GitlabApi::sendUserTime() swallows errors and returns null
     *
     * @throws RuntimeException
     */
    private function sendOrThrow(GitlabApi $api, $project, $iid, int $seconds): void
    {
        if ($api->sendUserTime($project, $iid, $seconds . 's') === null) {
            throw new RuntimeException('GitLab rejected spent time update');
        }
    }
}
