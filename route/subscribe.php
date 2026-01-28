<?php
use think\facade\Route;

Route::group('subscribe', function () {
    Route::post('save', 'Subscribe/save');
})->middleware(\app\middleware\Auth::class);

