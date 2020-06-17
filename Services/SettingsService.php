<?php

namespace Modules\GitlabIntegration\Services;

use App\Contracts\Settings;

class SettingsService
{
    /**
     *
     */
    protected const MODULE_NAME = 'gitlab';
    /**
     * @var Settings
     */
    protected Settings $settings;

    /**
     * SettingsService constructor.
     * @param Settings $settings
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->settings->all(self::MODULE_NAME);
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->settings->get(self::MODULE_NAME, $key, $default);
    }

    /**
     * @param $key
     * @param null $value
     * @return array
     */
    public function set($key, $value = null): array
    {
        return $this->settings->set(self::MODULE_NAME, $key, $value);
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->settings->get(self::MODULE_NAME, 'enabled', false);
    }

    /**
     * Returns Gitlab api url.
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->settings->get(self::MODULE_NAME, 'url');
    }

    /**
     * Returns the period of the synchronization time.
     *
     * @return mixed
     */
    public function getTimeSyncPeriod(): int
    {
        return $this->settings->get(self::MODULE_NAME, 'time_sync_period');
    }
}
