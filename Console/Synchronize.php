<?php

namespace Modules\GitlabIntegration\Console;

use App\Models\Priority;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Illuminate\Console\Command;
use Log;
use Modules\GitlabIntegration\Entities\ProjectRelation;
use Modules\GitlabIntegration\Entities\TaskRelation;
use Modules\GitlabIntegration\Helpers\GitlabApi;
use Modules\GitlabIntegration\Helpers\UserProperties;
use Illuminate\Support\Arr;
use Settings;
use Throwable;

class Synchronize extends Command
{
    public const COMPANY_ID = 'company_id';
    public const NAME = 'name';
    public const DESCRIPTION = 'description';
    public const IMPORTANT = 'important';
    public const SOURCE = 'source';

    public const PROJECT_ID = 'project_id';
    public const TASK_NAME = 'task_name';
    public const ACTIVE = 'active';
    public const USER_ID = 'user_id';
    public const ASSIGNED_BY = 'assigned_by';
    public const URL = 'url';
    public const PRIORITY_ID = 'priority_id';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'gitlab:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize projects from Gitlab for all users, who activate the Gitlab integration.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $self = $this;
        $this->withProgressBar(
            UserProperties::getUsersWithApiKeys()->lazy(),
            static function (User $user) use ($self) {
                $api = GitlabApi::buildFromUser($user);

                if (!$api) {
                    Log::error('Can`t instantiate an API for user');
                    $self->error(' Can`t instantiate an API for user');
                    return;
                }

                try {
                    $gitlabProjects = $api->getUserProjects();
                } catch (Throwable $throwable) {
                    Log::error('Projects cant be fetched for user', $throwable);
                    return;
                }

                $self->syncProjects($gitlabProjects);

                try {
                    $gitlabTasks = $api->getUserTasks();
                } catch (Throwable $throwable) {
                    Log::error('Tasks cant be fetched for user', $throwable);
                    return;
                }

                $self->checkClosedTasks($api, $user->id);
                $self->syncTasks($gitlabTasks, $user->id);
            }
        );

        $this->newLine();
    }

    private function syncProjects(array $gitlabProjects): void
    {
        foreach ($gitlabProjects as $gitlabProject) {
            $projectMapping = [
                self::COMPANY_ID => 0,
                self::NAME => $gitlabProject['name_with_namespace'] ?? 'Gitlab Project without Name ?!',
                self::DESCRIPTION => $gitlabProject['description'] ?? '',
                self::IMPORTANT => false,
                self::SOURCE => 'gitlab',
            ];

            $relation = ProjectRelation::whereGitlabId($gitlabProject['id'])->firstOr(
                callback: static fn() => ProjectRelation::create([
                    'gitlab_id' => $gitlabProject['id'],
                    'project_id' => Project::create($projectMapping)->id,
                ]),
            );

            Project::whereId($relation->project_id)
                ->firstOr(callback: static fn() => optional($relation->delete()))
                ->update([
                    'name' => $projectMapping[self::NAME],
                    'description' => $projectMapping[self::DESCRIPTION],
                ]);
        }
    }

    private function checkClosedTasks(GitlabApi $api, int $userID): void
    {
        // Get Gitlab's iids of active user's tasks, to check if they were closed
        $relations = TaskRelation::whereHas(
            'task',
            static fn($query) => $query
                ->whereRelation('users', 'id', $userID)
                ->whereRelation('status', 'active', true)
        )->get()->unique('gitlab_issue_iid');

        // Fetch closed Gitlab's tasks by iids, and get internal task ids
        $iids = $relations->pluck('gitlab_issue_iid')->chunk(30)->toArray();

        while (!empty($iids)) {
            $iidsChunk = array_pop($iids);
            $gitlabTasks = $api->getClosedUserTasks($iidsChunk);

            $internalIds = $relations->whereIn('gitlab_id', Arr::pluck($gitlabTasks, 'id'))
                ->pluck('task_id')->toArray();

            if (!empty($internalIds)) {
                Task::whereIn('id', $internalIds)
                    ->lazyById()
                    ->each(static fn(Task $task) => $task->status()->associate(Status::whereActive(true)->firstOrFail()));
            }
        }
    }

    private function syncTasks(array $gitlabTasks, int $userID): void
    {
        $defaultPriorityId = Settings::scope('core')->get('default_priority_id') ?? Priority::firstOrFail()->id;

        foreach ($gitlabTasks as $gitlabTask) {
            $projectID = ProjectRelation::where('gitlab_id', $gitlabTask['project_id'])->first()->project_id;
            if (!$projectID) {
                Log::error("Project ID for gilab issue wasn`t found! {$gitlabTask['name'] }");
                $this->error("Project ID for gilab issue wasn`t found! {$gitlabTask['name'] }");
                continue;
            }

            $taskMapping = [
                self::TASK_NAME => $gitlabTask['title'] ?? 'Gitlab Issue without Name',
                self::DESCRIPTION => $gitlabTask['description'] ?? '',
                self::PROJECT_ID => $projectID,
                self::ACTIVE => true,
                self::ASSIGNED_BY => 0,
                self::URL => $gitlabTask['web_url'] ?? '',
                self::PRIORITY_ID => $defaultPriorityId,
                self::IMPORTANT => false,
                self::USER_ID => $userID,
            ];

            $taskRelation = TaskRelation::whereGitlabId($gitlabTask['id'])->firstOr(
                callback: static fn() => TaskRelation::create([
                    'gitlab_id' => $gitlabTask['id'],
                    'task_id' => Task::create($taskMapping)->id,
                    'gitlab_issue_iid' => $gitlabTask['iid'],
                ]),
            );

            $task = Task::find($taskRelation->task_id);

            // If task was deleted in our system we have to remove relation as well
            if (!$task) {
                $taskRelation->delete();
                continue;
            }

            $taskRelation->update([
                'gitlab_issue_iid' => $gitlabTask['iid']
            ]);

            $task->status()->associate(Status::whereActive(true)->firstOrFail());
            $task->update([
                'task_name' => $taskMapping[self::TASK_NAME],
                'description' => $taskMapping[self::DESCRIPTION],
            ]);

            $task->users()->sync([$userID]);
        }
    }
}
