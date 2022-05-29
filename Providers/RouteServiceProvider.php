<?php

namespace Modules\GitlabIntegration\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Modules\GitlabIntegration\Http\Controllers\SettingsController;
use Modules\GitlabIntegration\Http\Controllers\UserSettingsController;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->as('integration.gitlab.')
            ->prefix('integration/gitlab')
            ->group(static function (Router $router) {
                $router->get('/settings', [SettingsController::class, 'index'])->name('settings.index');
                $router->patch('/settings', [SettingsController::class, 'update'])->name('settings.update');

                $router->get('/user-settings', [UserSettingsController::class, 'index'])->name('settings.user.index');
                $router->patch('/user-settings', [UserSettingsController::class, 'update'])->name('settings.user.update');
            });
    }
}
