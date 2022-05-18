<?php

namespace Modules\GitlabIntegration\Services;

use App\Services\SettingsProviderService;

class SettingsService extends SettingsProviderService
{
    protected string $scope = 'gitlab';

    public function isEnabled(): bool
    {
        return $this->get('enabled', false);
    }

    /**
     * Returns Gitlab api url.
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->get('url');
    }

    /**
     * Returns the period of the synchronization time.
     *
     * @return mixed
     */
    public function getTimeSyncPeriod(): int
    {
        return $this->get('time_sync_period', 0);
    }
}
