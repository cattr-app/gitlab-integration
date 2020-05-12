<?php

namespace Modules\GitlabIntegration\Entities;

class SettingsEntity
{
    public const TIME_SYNC_PERIOD_KEY = 'gitlab_time_sync_period';

    protected const TIME_SYNC_PERIOD_VALUES = [
        'NEVER' => 0,
        'FIVE_MINUTES' => 5,
        'THIRTY_MINUTES' => 30,
        'HOURLY' => 60,
        'DAILY' => 1440
    ];

    /**
     * Get the value for the sync time period by key.
     *
     * @param $key
     * @return int
     */
    public function getTimeSyncPeriodValueByKey($key): int
    {
        return self::TIME_SYNC_PERIODS[$key];
    }

    /**
     * Get the key for the sync time period by value.
     *
     * @param $value
     * @return false|int
     */
    public function getTimeSyncPeriodKeyByValue($value): int
    {
        return array_search($value, self::TIME_SYNC_PERIODS);
    }
}
