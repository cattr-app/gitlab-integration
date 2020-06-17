<?php

namespace Modules\GitlabIntegration\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\GitlabIntegration\Entities\SettingEntity;
use Modules\GitlabIntegration\Services\SettingsService;

class ScheduleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->command('gitlab:sync')->everyFiveMinutes()->withoutOverlapping();

            // Synchronize time every 5 minutes
            $schedule->command('gitlab:sync-time')->everyFiveMinutes()->when(
                function (SettingsService $settingsService, SettingEntity $settingsEntity) {
                    $periodValue = $settingsEntity->getTimeSyncPeriodValueByKey('FIVE_MINUTES');
                    return $settingsService->getTimeSyncPeriod() === $periodValue;
                }
            )->withoutOverLapping();

            // Synchronize time every 30 minutes
            $schedule->command('gitlab:sync-time')->everyThirtyMinutes()->when(
                function (SettingsService $settingsService, SettingEntity $settingsEntity) {
                    $periodValue = $settingsEntity->getTimeSyncPeriodValueByKey('THIRTY_MINUTES');
                    return $settingsService->getTimeSyncPeriod() === $periodValue;
                }
            )->withoutOverLapping();

            // Synchronize time every hour
            $schedule->command('gitlab:sync-time')->hourly()->when(
                function (SettingsService $settingsService, SettingEntity $settingsEntity) {
                    $periodValue = $settingsEntity->getTimeSyncPeriodValueByKey('HOURLY');
                    return $settingsService->getTimeSyncPeriod() === $periodValue;
                }
            )->withoutOverLapping();

            // Synchronize time every day
            $schedule->command('gitlab:sync-time')->daily()->when(
                function (SettingsService $settingsService, SettingEntity $settingsEntity) {
                    $periodValue = $settingsEntity->getTimeSyncPeriodValueByKey('DAILY');
                    return $settingsService->getTimeSyncPeriod() === $periodValue;
                }
            )->withoutOverLapping();
        });
    }
}
