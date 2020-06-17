<?php

namespace Modules\GitlabIntegration\Helpers;

use App\Models\Property;
use App\Models\User;
use Gitlab\Client;
use Gitlab\ResultPager;
use Illuminate\Support\Facades\Log;
use Modules\GitlabIntegration\Services\SettingsService;
use Throwable;

class GitlabApi
{
    protected User $user;
    protected UserProperties $userProperties;
    protected string $apiUrl;
    protected string $apiKey;
    protected Client $client;
    protected ResultPager $pager;
    protected SettingsService $settings;

    public function __construct(UserProperties $userProperties, SettingsService $settings)
    {
        $this->userProperties = $userProperties;
        $this->settings = $settings;
    }

    public static function buildFromUser(User $user): ?GitlabApi
    {
        return app()->make(self::class)->init($user);
    }

    protected function init(User $user): ?GitlabApi
    {
        $this->user = $user;

        $this->apiUrl = $this->settings->getApiUrl();
        $this->apiKey = $this->userProperties->getApiKey($user->id);

        if (empty($this->apiUrl) || empty($this->apiKey)) {
            return null;
        }

        try {
            $this->client = Client::create($this->apiUrl)->authenticate($this->apiKey, Client::AUTH_URL_TOKEN);
            $this->pager = new ResultPager($this->client);
            $this->pager->fetch($this->client->api('users'), 'me');
        } catch (Throwable $throwable) {
            if ($throwable->getMessage() === 'invalid_token') {
                $this->userProperties->removeApiKey($user->id);
                Log::info('Removing user GitLab API key due to expiration of key');
            } elseif (strpos($throwable->getMessage(), 'Your account has been blocked')) {
                $this->userProperties->removeApiKey($user->id);
                Log::info('Removing user GitLab API key due to account block');
            } else {
                Log::error($throwable->getMessage());
            }
            return null;
        }
        return $this;
    }

    public function getUserProjects()
    {
        return $this->pager->fetchAll($this->client->api('projects'), 'all');
    }

    public function getUserTasks()
    {
        return $this->pager->fetchAll($this->client->api('issues'), 'all', [null, [
            'scope' => 'assigned-to-me',
            'state' => 'opened',
        ]]);
    }

    /**
     * @param int[] $iids
     */
    public function getClosedUserTasks(array $iids = [])
    {
        $params = [
            'scope' => 'assigned-to-me',
            'state' => 'closed',
        ];

        if (!empty($iids)) {
            $params['iids'] = $iids;
        }

        return $this->pager->fetchAll($this->client->api('issues'), 'all', [null, $params]);
    }

    public function sendUserTime($projectId, $issue_iid, $duration)
    {
        return $this->client->issues->addSpentTime($projectId, $issue_iid, $duration);
    }
}
