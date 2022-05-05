<?php

namespace Modules\GitlabIntegration\Jobs;

use App\Models\TimeInterval;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Log;
use Modules\GitlabIntegration\Entities\ProjectRelation;
use Modules\GitlabIntegration\Entities\TaskRelation;
use Modules\GitlabIntegration\Helpers\GitlabApi;
use Throwable;

class ReassignTaskToEditedInterval implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 60;
    private TimeInterval $interval;

    public function __construct(protected array $data)
    {
        $this->interval = $data[0];
    }

    public function uniqueId(): string
    {
        return $this->interval->id;
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        // Do nothing if the previous and the new task are the same
        if ($this->interval->getOriginal('task_id') === $this->interval->task_id) {
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

        $interval = $this->interval;

        DB::transaction(static function() use ($interval, $duration, $api) {
            $prevTaskRel = TaskRelation::whereTaskId($interval->getOriginal('task_id'))->firstOrFail();
            $projRel = ProjectRelation::whereProjectId($prevTaskRel->task->project_id)->firstOrFail();

            $time = $api->getUserTime($projRel->gitlab_id, $prevTaskRel->gitlab_issue_iid);
            $api->resetUserTime($projRel->gitlab_id, $prevTaskRel->gitlab_issue_iid);
            $api->sendUserTime($projRel->gitlab_id, $prevTaskRel->gitlab_issue_iid, ($time - $duration) . 's');
        });

        DB::transaction(static function() use ($interval, $duration, $api) {
            $newTaskRel = TaskRelation::whereTaskId($interval->task_id)->firstOrFail();
            $projRel = ProjectRelation::whereProjectId($newTaskRel->task->project_id)->firstOrFail();
            $api->sendUserTime($projRel->gitlab_id, $newTaskRel->gitlab_issue_iid, $duration . 's');
        });
    }
}
