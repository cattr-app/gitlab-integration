<?php

namespace Modules\GitlabIntegration\Subscribers;

use App\Models\TimeInterval;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\GitlabIntegration\Helpers\TimeIntervalsHelper;
use Modules\GitlabIntegration\Jobs\ReassignTaskToEditedInterval;

class EventObserver
{
    /** @var array<int, int|null> Previous task_id by interval id, captured before bulk edit saves */
    private static array $previousTaskIds = [];

    public function taskEdition(Task $data): void
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

    public function intervalsBulkEditing(Collection $intervals, mixed $request = null): void
    {
        foreach ($intervals as $interval) {
            self::$previousTaskIds[$interval->id] = $interval->getOriginal('task_id');
        }
    }

    public function intervalsBulkEdited(Collection $intervals, mixed $request = null): void
    {
        foreach ($intervals as $interval) {
            $previousTaskId = self::$previousTaskIds[$interval->id] ?? null;

            if ($previousTaskId === null || $previousTaskId === $interval->task_id) {
                continue;
            }

            ReassignTaskToEditedInterval::dispatch($interval, $previousTaskId);
        }

        self::$previousTaskIds = [];
    }

    public function subscribe(): array
    {
        return [
            'event.before.action.tasks.edit' => [[__CLASS__, 'taskEdition']],
            'event.before.action.tasks.destroy' => [[__CLASS__, 'taskDeletion']],
            'event.before.action.intervals.edit.bulk' => [[__CLASS__, 'intervalsBulkEditing']],
            'event.after.action.intervals.edit.bulk' => [[__CLASS__, 'intervalsBulkEdited']],
            'event.after.action.intervals.create' => [[__CLASS__, 'intervalCreation']]
        ];
    }
}
