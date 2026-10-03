<?php

namespace Modules\GitlabIntegration\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\GitlabIntegration\Console\Synchronize;
use Modules\GitlabIntegration\Console\SynchronizeTime;
use Modules\GitlabIntegration\Services\SettingsService;

class ScheduleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(static function () {
            $schedule = app(Schedule::class);
            $settingsService = app(SettingsService::class);
            $schedule->command(Synchronize::class)->everyFiveMinutes()->withoutOverlapping();

            switch ($settingsService->getTimeSyncPeriod()) {
                case 5:
                    $schedule->command(SynchronizeTime::class)->everyFiveMinutes()->withoutOverlapping();
                    break;
                case 30:
                    $schedule->command(SynchronizeTime::class)->everyThirtyMinutes()->withoutOverlapping();
                    break;
                case 60:
                    $schedule->command(SynchronizeTime::class)->hourly()->withoutOverlapping();
                    break;
                case 1440:
                    $schedule->command(SynchronizeTime::class)->daily()->withoutOverlapping();
                    break;
                default:
            }
        });
    }
}
