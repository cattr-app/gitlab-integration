<?php

namespace Modules\GitlabIntegration\Entities;

use App\Models\Setting;

/**
 * Modules\GitlabIntegration\Entities\SettingEntity
 *
 * @property int $id
 * @property string $module_name
 * @property string $key
 * @property string $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|SettingEntity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SettingEntity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SettingEntity query()
 * @method static \Illuminate\Database\Eloquent\Builder|SettingEntity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingEntity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingEntity whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingEntity whereModuleName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingEntity whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingEntity whereValue($value)
 * @mixin \Eloquent
 */
class SettingEntity extends Setting
{
    protected const TIME_SYNC_PERIOD_VALUES = [
        'NEVER' => 0,
        'FIVE_MINUTES' => 5,
        'THIRTY_MINUTES' => 30,
        'HOURLY' => 60,
        'DAILY' => 1440
    ];

    protected $table = 'settings';

    protected $casts = [
        'value' => 'string',
        'enabled' => 'bool',
        'time_sync_period' => 'int',
    ];

    /**
     * Get the value for the sync time period by key.
     *
     * @param $key
     * @return int
     */
    public function getTimeSyncPeriodValueByKey($key): int
    {
        return self::TIME_SYNC_PERIOD_VALUES[$key];
    }

    /**
     * Get the key for the sync time period by value.
     *
     * @param $value
     * @return false|int
     */
    public function getTimeSyncPeriodKeyByValue($value): int
    {
        return array_search($value, self::TIME_SYNC_PERIOD_VALUES);
    }
}
