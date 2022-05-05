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
