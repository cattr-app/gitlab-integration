<?php

namespace Modules\GitlabIntegration\Helpers;

use App\Models\User;
use Gitlab\Client;
use Gitlab\ResultPager;
use Http\Client\Exception;
use Illuminate\Support\Facades\Log;
use Modules\GitlabIntegration\Services\SettingsService;
use Throwable;

class GitlabApi
{
    protected User $user;
    protected string $apiUrl;
    protected string $apiKey;
    protected Client $client;
    protected ResultPager $pager;

    public function __construct(protected SettingsService $settings)
    {
    }

    public static function buildFromUser(User $user): ?GitlabApi
    {
        return app(self::class)->init($user);
    }

    protected function init(User $user): ?GitlabApi
    {
        $this->user = $user;

        $this->apiUrl = $this->settings->getApiUrl();
        $this->apiKey = UserProperties::getApiKey($user);

        if (empty($this->apiUrl) || empty($this->apiKey)) {
            return null;
        }

        try {
            $this->client = new Client();
            $this->client->setUrl($this->apiUrl);
            $this->client->authenticate($this->apiKey, Client::AUTH_HTTP_TOKEN);
            $this->pager = new ResultPager($this->client);
            $this->pager->fetch($this->client->users(), 'me');
        } catch (Throwable $throwable) {
            if ($throwable->getMessage() === 'invalid_token' || strpos($throwable->getMessage(), 'Token is expired') !== false) {
                UserProperties::removeApiKey($user);
                Log::info('Removing user GitLab API key due to expiration of key');
            } elseif (strpos($throwable->getMessage(), 'Your account has been blocked')) {
                UserProperties::removeApiKey($user);
                Log::info('Removing user GitLab API key due to account block');
            } else {
                Log::error($throwable->getMessage());
            }
            return null;
        }
        return $this;
    }

    public function getUserProjects(): array
    {
        return $this->pager->fetchAll($this->client->projects(), 'all');
    }

    public function getUserTasks(): array
    {
        return $this->pager->fetchAll($this->client->issues(), 'all', [null, [
            'scope' => 'assigned-to-me',
            'state' => 'opened',
        ]]);
    }

    /**
     * @param int[] $iids
     * @throws Exception
     */
    public function getClosedUserTasks(array $iids = []): array
    {
        $params = [
            'scope' => 'assigned-to-me',
            'state' => 'closed',
        ];

        if (!empty($iids)) {
            $params['iids'] = $iids;
        }

        return $this->pager->fetchAll($this->client->issues(), 'all', [null, $params]);
    }

    public function sendUserTime($projectId, $issue_iid, $duration)
    {
        try {
            return $this->client->issues()->addSpentTime($projectId, $issue_iid, $duration);
        } catch (Throwable $throwable) {
            Log::error($throwable->getMessage());
            return null;
        }
    }

    public function getUserTime($projectId, $issue_iid): int
    {
        return (int)$this->client->issues()->getTimeStats($projectId, $issue_iid)['total_time_spent'];
    }

    public function resetUserTime($projectId, $issue_iid)
    {
        return $this->client->issues()->resetSpentTime($projectId, $issue_iid);
    }
}
