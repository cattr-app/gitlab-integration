<?php

namespace Modules\GitlabIntegration\Subscribers;

use App\Models\TimeInterval;
use App\Models\User;
use Filter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\GitlabIntegration\Entities\ProjectRelation;
use Modules\GitlabIntegration\Entities\TaskRelation;
use Modules\GitlabIntegration\Helpers\GitlabApi;
use Modules\GitlabIntegration\Helpers\TimeIntervalsHelper;
use Modules\GitlabIntegration\Jobs\ReassignTaskToEditedInterval;

class EventObserver
{
    /**
     * Observe task edition
     *
     * @param $task
     *
     * @return void
     */
    public function taskEdition($task): void
    {
        abort_if(
            DB::table('gitlab_tasks_relations')
                ->where('task_id', $task->id)->count(),
            403,
            'Access denied to edit a task from GitLab integration'
        );
    }

    /**
     * Observe task deletion
     *
     * @param $task
     *
     * @return void
     */
    public function taskDeletion($task): void
    {
        abort_if(
            DB::table('gitlab_tasks_relations')
                ->where('task_id', $task->id)->count(),
            403,
            'Access denied to delete a task from GitLab integration'
        );
    }

    public function intervalCreation(array $data): void
    {
        dispatch(static fn() => TimeIntervalsHelper::createUnsyncedInterval($data));
    }

    public function subscribe(): array
    {
        return [
            'event.before.action.tasks.edit' => [[__CLASS__, 'taskEdition']],
            'event.before.action.task.destroy' => [[__CLASS__, 'taskDeletion']],
            'event.after.action.intervals.edit' => [ReassignTaskToEditedInterval::class],
            'event.after.action.intervals.create' => [[__CLASS__, 'intervalCreation']]
        ];
    }
}
