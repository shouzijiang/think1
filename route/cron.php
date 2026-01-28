<?php
use think\facade\Route;

Route::group('cron', function () {
    Route::get('send-remind', 'Cron/sendRemind');
});

