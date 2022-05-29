<?php

namespace Modules\GitlabIntegration\Subscribers;

use App\Models\TimeInterval;
use Illuminate\Support\Facades\DB;
use Modules\GitlabIntegration\Helpers\TimeIntervalsHelper;
use Modules\GitlabIntegration\Jobs\ReassignTaskToEditedInterval;

class EventObserver
{
    public function taskEdition(TimeInterval $data): void
    {
        abort_if(
            DB::table('gitlab_tasks_relations')
                ->where('task_id', $data->id)->exists(),
            403,
            'Access denied to edit a task from GitLab integration'
        );
    }

    public function taskDeletion(mixed $taskId): void
    {
        abort_if(
            DB::table('gitlab_tasks_relations')
                ->where('task_id', $taskId)->exists(),
            403,
            'Access denied to delete a task from GitLab integration'
        );
    }

    public function intervalCreation(TimeInterval $interval): void
    {
        dispatch(static fn() => TimeIntervalsHelper::createUnsyncedInterval($interval));
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
