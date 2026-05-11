<?php

use think\facade\Route;

Route::group('channel', function () {
    Route::post('report', 'Channel/report')->middleware(\app\middleware\Auth::class);
    Route::get('stats',   'Channel/stats');
});
