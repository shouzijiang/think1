<?php
use think\facade\Route;

Route::group('settings', function () {
    Route::post('save', 'Settings/save');
    Route::get('get', 'Settings/get');
})->middleware(\app\middleware\Auth::class);

