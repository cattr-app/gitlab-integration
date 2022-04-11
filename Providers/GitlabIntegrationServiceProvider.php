<?php

namespace Modules\GitlabIntegration\Providers;

use Settings;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Filter;
use Modules\GitlabIntegration\Console\SynchronizeTime;
use Modules\GitlabIntegration\Console\Syncronize;
use Modules\GitlabIntegration\Entities\SettingEntity;
use Modules\GitlabIntegration\Helpers\TimeIntervalsHelper;
use Modules\GitlabIntegration\Services\SettingsService;

class GitlabIntegrationServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected string $moduleName = 'GitlabIntegration';

    /**
     * @var string $moduleNameLower
     */
    protected string $moduleNameLower = 'gitlabintegration';

    /**
     * @var array
     */
    protected $listen = [
        'answer.success.item.list.result.task' => [
            'Modules\GitlabIntegration\Listeners\IntegrationObserver@taskList',
        ],
        'item.edit.task' => [
            'Modules\GitlabIntegration\Listeners\IntegrationObserver@taskEdition',
        ],
        'item.remove.task' => [
            'Modules\GitlabIntegration\Listeners\IntegrationObserver@taskDeletion',
        ],
        'item.edit.timeinterval' => [
            'Modules\GitlabIntegration\Listeners\IntegrationObserver@timeintervalEdition',
        ],
    ];

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerCommands();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Filter::listen('answer.success.item.create.timeinterval', static function ($data) {
            $timeInterval = $data['interval'];
            $helper = app()->make(TimeIntervalsHelper::class);
            $helper->createUnsyncedInterval($timeInterval);
            return $data;
        });

        parent::boot();
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
        }
    }

    /**
     * Register command
     */
    protected function registerCommands(): void
    {
        $this->commands([
            Syncronize::class,
            SynchronizeTime::class,
        ]);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(ScheduleServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }
}
