<?php


namespace Modules\GitlabIntegration\Helpers;

use App\Models\TimeInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Log;

class TimeIntervalsHelper
{
    public const GIS_TABLE = 'gitlab_intervals_sync';
    public const GTR_TABLE = 'gitlab_tasks_relations';
    public const GPR_TABLE = 'gitlab_projects_relations';

    public static function createUnsyncedInterval(TimeInterval $interval): TimeInterval
    {
        if (optional(self::getTasksById($interval->task_id))->task_id) {
            DB::table(self::GIS_TABLE)->insert([
                'task_id' => $interval->task_id,
                'time_interval_id' => $interval->id,
                'is_synced' => 0
            ]);
        }

        return $interval;
    }

    public static function getTasksById(int $taskId)
    {
        return DB::table(self::GTR_TABLE)
            ->where('task_id', '=', $taskId)
            ->first();
    }

    public static function markAsSyncedIntervalByTaskId(int $taskId): int
    {
        return DB::table(self::GIS_TABLE)->where('task_id', '=', $taskId)->update([
            'is_synced' => 1
        ]);
    }

    public static function clearSyncedIntervals(): int
    {
        return DB::table(self::GIS_TABLE)
            ->where('is_synced', '=', true)
            ->delete();
    }

    public static function getNotSyncedCollection(): Collection
    {
        return DB::table(self::GIS_TABLE)
            ->where('is_synced', '=', false)
            ->orWhereNull('is_synced')
            ->get();
    }

    public static function getGitlabIssueProjectRelation(Collection $tasks): array
    {
        $result = [];

        foreach ($tasks as $task) {
            if (isset($result[$task->id])) {
                continue;
            }

            $projectRelation = self::getProjectRelation($task->project_id);
            $taskRelation = self::getTasksById($task->id);

            if (!$projectRelation) {
                Log::info("Can`t relation from project id: {$task->project_id} \n");
                continue;
            }

            if (!$taskRelation) {
                Log::info("Can`t relation from task id: {$task->id} \n");
                continue;
            }

            $result[$task->id] = [
                'gl_project_id' => $projectRelation->gitlab_id,
                'gl_issue_iid' => $taskRelation->gitlab_issue_iid,
            ];
        }

        return $result;
    }

    public static function getProjectRelation(int $projectId)
    {
        return DB::table(self::GPR_TABLE)
            ->where('project_id', '=', $projectId)
            ->first();
    }
}
