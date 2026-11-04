<?php

namespace Modules\GitlabIntegration\Jobs;

use App\Models\TimeInterval;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Log;
use Modules\GitlabIntegration\Entities\ProjectRelation;
use Modules\GitlabIntegration\Entities\TaskRelation;
use Modules\GitlabIntegration\Helpers\GitlabApi;
use Throwable;

class ReassignTaskToEditedInterval implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private const LOCK_TTL = 120;
    private const LOCK_WAIT = 60;
    private const ORDER_RETRY_DELAY = 10;
    private const ORDER_STATE_TTL_DAYS = 1;

    /**
     * @param int $sequence Per-interval move number; moves of one interval are applied strictly in this order (0 = unordered)
     */
    public function __construct(
        private TimeInterval $interval,
        private int $previousTaskId,
        private int $sequence = 0,
    ) {
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHour();
    }

    public static function sequenceKey(int $intervalId): string
    {
        return "gitlab-interval-move-seq:{$intervalId}";
    }

    private function doneKey(): string
    {
        return "gitlab-interval-move-done:{$this->interval->id}";
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        // Wait until all earlier moves of this interval have been applied
        if ($this->sequence > (int)Cache::get($this->doneKey(), 0) + 1) {
            $this->release(self::ORDER_RETRY_DELAY);

            return;
        }

        $this->reassign();
        $this->markDone();
    }

    /** Unblock later moves of this interval when this one has permanently failed */
    public function failed(?Throwable $exception = null): void
    {
        $this->markDone();
    }

    private function markDone(): void
    {
        if ($this->sequence > 0) {
            Cache::put($this->doneKey(), $this->sequence, now()->addDays(self::ORDER_STATE_TTL_DAYS));
        }
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
        // Do nothing if the previous and the new task are the same
        if ($this->previousTaskId === $this->interval->task_id) {
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

        $prevTaskRel = TaskRelation::whereTaskId($this->previousTaskId)->firstOrFail();
        $prevProjRel = ProjectRelation::whereProjectId($prevTaskRel->task->project_id)->firstOrFail();
        $newTaskRel = TaskRelation::whereTaskId($this->interval->task_id)->firstOrFail();
        $newProjRel = ProjectRelation::whereProjectId($newTaskRel->task->project_id)->firstOrFail();

        // GitLab time is rewritten with read-reset-add, so every touched issue is locked for the whole move
        $this->withIssueLocks(
            [
                "gitlab-issue-time:{$prevProjRel->gitlab_id}:{$prevTaskRel->gitlab_issue_iid}",
                "gitlab-issue-time:{$newProjRel->gitlab_id}:{$newTaskRel->gitlab_issue_iid}",
            ],
            static function () use ($prevProjRel, $prevTaskRel, $newProjRel, $newTaskRel, $duration, $api) {
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
            }
        );
    }
}
