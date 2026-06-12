<?php
use think\facade\Route;

Route::group('cron', function () {
    Route::get('send-remind', 'Cron/sendRemind');
    Route::get('gen-daily-challenge', 'Cron/genDailyChallenge');
    Route::get('sync-channel-unit-price', 'Cron/syncChannelUnitPrice');
});

