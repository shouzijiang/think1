<?php

use think\facade\Route;

Route::group('upload', function () {
    Route::post('avatar', 'Upload/avatar');
})->middleware(\app\middleware\Auth::class);
