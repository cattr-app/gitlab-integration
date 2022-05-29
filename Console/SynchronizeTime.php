<?php


namespace Modules\GitlabIntegration\Console;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Modules\GitlabIntegration\Helpers\GitlabApi;
use Modules\GitlabIntegration\Helpers\TimeIntervalsHelper;
use Modules\GitlabIntegration\Helpers\UserProperties;

class SynchronizeTime extends Command
{
    protected $name = 'gitlab:sync-time';

    protected $description = 'Synchronize time for Gitlab Tasks for all users, who activate the Gitlab integration.';

    public function handle(): void
    {
        $self = $this;
        $timeIntervals = TimeIntervalsHelper::getNotSyncedCollection();

        $this->withProgressBar(
            UserProperties::getUsersWithApiKeys()->lazy(),
            static function (User $user) use ($self, $timeIntervals) {
                $api = GitlabApi::buildFromUser($user);

                if (!$api) {
                    Log::error('Can`t instantiate an API for user');
                    $self->error(' Can`t instantiate an API for user');
                    return;
                }

                $groupedIntervals = $user->timeIntervals()
                    ->whereIn('id', $timeIntervals->pluck('time_interval_id'))
                    ->get()
                    ->groupBy('task_id');

                $durations = $self->calculateDuration($groupedIntervals);
                $issueProjectRelations = TimeIntervalsHelper::getGitlabIssueProjectRelation(
                    Task::whereIn(
                        'id',
                        $groupedIntervals->keys()
                    )->get()
                );

                foreach ($issueProjectRelations as $taskId => $relation) {
                    $glProjectId = $relation['gl_project_id'];
                    $glIssueIid = $relation['gl_issue_iid'];

                    Log::withContext([
                        'task' => [
                            'gitlabId' => $glIssueIid,
                            'id' => $taskId,
                        ]
                    ]);

                    $response = $api->sendUserTime($glProjectId, $glIssueIid, $durations[$taskId]['humanDuration']);
                    if ($response !== null) {
                        $self->info("Sent {$durations[$taskId]['humanDuration']} spent");
                        if ($response && isset($response['total_time_spent'])) {
                            TimeIntervalsHelper::markAsSyncedIntervalByTaskId($taskId);
                        }
                    }
                }

                TimeIntervalsHelper::clearSyncedIntervals();
            });

        $this->newLine();
    }

    private function calculateDuration(Collection $groupedIntervals): array
    {
        $durations = [];
        foreach ($groupedIntervals as $taskId => $intervals) {
            if (!isset($durations[$taskId])) {
                $durations[$taskId]['duration'] = 0;
            }

            foreach ($intervals as $interval) {
                $durations[$taskId]['duration'] += Carbon::parse($interval->end_at)
                    ->diffInSeconds(Carbon::parse($interval->start_at));
            }

            // Set parts = 3 to see hours (if exists) minutes and seconds
            $durations[$taskId]['humanDuration'] = Carbon::now()
                ->subSeconds($durations[$taskId]['duration'])
                ->diffForHumans(null, true, true, 3);
        }

        return $durations;
    }
}
