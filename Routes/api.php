<?php

use Illuminate\Routing\Router;

Route::middleware('auth:sanctum')->group(static function (Router $router) {
    $router->get('/settings', 'SettingsController@index')->name('settings.index');
    $router->patch('/settings', 'SettingsController@update')->name('settings.update');

    $router->get('/user-settings', 'UserSettingsController@index')->name('user-settings.index');
    $router->patch('/user-settings', 'UserSettingsController@update')->name('user-settings.update');
});
