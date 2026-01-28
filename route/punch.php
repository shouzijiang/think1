<?php
use think\facade\Route;

Route::group('punch', function () {
    Route::post('submit', 'Punch/submit');
    Route::get('records', 'Punch/records');
    Route::get('statistics', 'Punch/statistics');
})->middleware(\app\middleware\Auth::class);

