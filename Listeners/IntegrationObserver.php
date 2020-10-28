<?php

namespace Modules\GitlabIntegration\Listeners;

use App\Models\TimeInterval;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\GitlabIntegration\Entities\ProjectRelation;
use Modules\GitlabIntegration\Entities\TaskRelation;
use Modules\GitlabIntegration\Helpers\GitlabApi;

class IntegrationObserver
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    /**
     * Observe task edition
     *
     * @param $task
     *
     * @return mixed
     */
    public function taskEdition($task)
    {
        $relation = DB::table('gitlab_tasks_relations')
            ->where('task_id', $task->id)
            ->first();
        if (isset($relation)) {
            abort(403, 'Access denied to edit a task from GitLab integration');
        }

        return $task;
    }

    /**
     * Observe task deletion
     *
     * @param $task
     *
     * @return mixed
     */
    public function taskDeletion($task)
    {
        $relation = DB::table('gitlab_tasks_relations')
            ->where('task_id', $task->id)
            ->first();
        if (isset($relation)) {
            abort(403, 'Access denied to delete a task from GitLab integration');
        }

        return $task;
    }

    /**
     * Observe task list
     *
     * @param Collection|Paginator $tasks
     *
     * @return array
     */
    public function taskList($tasks)
    {
        if ($tasks instanceof Paginator) {
            $items = $tasks->getCollection();
        } else {
            $items = $tasks;
        }

        $taskIds = $items->map(static function ($task) {
            return $task->id;
        })->toArray();
        $gitlabTaskIds = DB::table('gitlab_tasks_relations')
            ->whereIn('task_id', $taskIds)
            ->get(['task_id'])
            ->pluck('task_id')
            ->toArray();

        $items->transform(static function ($item) use ($gitlabTaskIds) {
            if (in_array($item->id, $gitlabTaskIds)) {
                $item->integration = 'gitlab';
            }

            return $item;
        });

        if ($tasks instanceof Paginator) {
            $tasks->setCollection($items);
        } else {
            $tasks = $items;
        }

        return $tasks;
    }

    /**
     * Observe timeinterval edition
     *
     * @param TimeInterval $interval
     *
     * @return TimeInterval
     */
    public function timeintervalEdition($interval)
    {
        // Do nothing if the previous and the new task are the same
        $prevTaskId = (int)$interval->getOriginal('task_id');
        $newTaskId = (int)$interval->task_id;
        if ($prevTaskId === $newTaskId) {
            return $interval;
        }

        // Do nothing if the interval haven't associated user
        $user = User::where(['id' => $interval->user_id])->first();
        if (!isset($user)) {
            return $interval;
        }

        // Do nothing if GitLab integration not activated for the user
        $api = GitlabApi::buildFromUser($user);
        if (!isset($api)) {
            Log::info('Can`t instantiate an API for user ' . $user->full_name . "\n");
            return $interval;
        }

        // Do nothing if the interval has not yet been sent
        $notSynced = DB::table('gitlab_intervals_sync')->where([
            'time_interval_id' => $interval->id,
            'is_synced' => 0,
        ])->first();
        if (isset($notSynced)) {
            return $interval;
        }

        $duration = Carbon::parse($interval->end_at)->diffInSeconds($interval->start_at);

        // Remove interval duration from the previous task
        /** @var null|TaskRelation $prevTaskRel */
        $prevTaskRel = TaskRelation::where(['task_id' => $prevTaskId])->first();
        if (isset($prevTaskRel)) {
            /** @var null|ProjectRelation $projRel */
            $projRel = ProjectRelation::where(['project_id' => $prevTaskRel->task->project_id])->first();
            if (isset($projRel)) {
                $time = $api->getUserTime($projRel->gitlab_id, $prevTaskRel->gitlab_issue_iid);
                $api->resetUserTime($projRel->gitlab_id, $prevTaskRel->gitlab_issue_iid);
                $api->sendUserTime($projRel->gitlab_id, $prevTaskRel->gitlab_issue_iid, ($time - $duration) . "s");
            }
        }

        // Add interval duration to the new task
        /** @var null|TaskRelation $newTaskRel */
        $newTaskRel = TaskRelation::where(['task_id' => $newTaskId])->first();
        if (isset($newTaskRel)) {
            /** @var null|ProjectRelation $projRel */
            $projRel = ProjectRelation::where(['project_id' => $newTaskRel->task->project_id])->first();
            if (isset($projRel)) {
                $api->sendUserTime($projRel->gitlab_id, $newTaskRel->gitlab_issue_iid, $duration . "s");
            }
        }

        return $interval;
    }
}
