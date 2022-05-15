<?php

namespace Modules\GitlabIntegration\Providers;

use Filter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\GitlabIntegration\Console\SynchronizeTime;
use Modules\GitlabIntegration\Console\Synchronize;
use Modules\GitlabIntegration\Subscribers\EventObserver;
use Modules\GitlabIntegration\Subscribers\FilterObserver;

class GitlabIntegrationServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected string $moduleName = 'GitlabIntegration';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        $this->commands([
            Synchronize::class,
            SynchronizeTime::class,
        ]);
    }

    public function register(): void
    {
        $this->app->register(ScheduleServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        $this->booting(static fn() => Event::subscribe(EventObserver::class));
        $this->booting(static fn() => Filter::subscribe(FilterObserver::class));
    }
}
