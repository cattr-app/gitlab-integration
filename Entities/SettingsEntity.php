<?php

namespace Modules\GitlabIntegration\Entities;

class SettingsEntity
{
    public const TIME_SYNC_PERIOD_KEY = 'gitlab_sync_time_period';

    protected const TIME_SYNC_PERIOD_VALUES = [
        'NEVER' => 0,
        'FIVE_MINUTES' => 5,
        'THIRTY_MINUTES' => 30,
        'HOURLY' => 60,
        'DAILY' => 1440
    ];

    /**
     * @param $key
     * @return int
     */
    public function getTimeSyncPeriodValueByKey($key): int
    {
        return self::TIME_SYNC_PERIODS[$key];
    }

    /**
     * @param $value
     * @return false|int
     */
    public function getTimeSyncPeriodKeyByValue($value): int
    {
        return array_search($value, self::TIME_SYNC_PERIODS);
    }
}
