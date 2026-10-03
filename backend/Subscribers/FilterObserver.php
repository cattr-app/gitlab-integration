<?php

namespace Modules\GitlabIntegration\Subscribers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FilterObserver
{
    public static function taskList(array $tasks): array
    {
        $taskList = Arr::keyBy($tasks, 'id');

        DB::table('gitlab_tasks_relations')
            ->whereIn(
                'task_id',
                array_keys($taskList),
            )
            ->get()
            ->pluck('task_id')
            ->each(static function($id) use (&$taskList) {
                $taskList[$id]['integration'] = 'gitlab';
            });

        return array_values($taskList);
    }

    public function subscribe(): array
    {
        return [
            'filter.response.success.tasks.list' => [[__CLASS__, 'taskList']],
        ];
    }
}
