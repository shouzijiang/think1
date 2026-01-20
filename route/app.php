<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

// 测试路由
Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});

Route::get('hello/:name', 'index/hello');

// ==================== API 路由 ====================

// 需要JWT认证的路由组
Route::group(function () {
    // 用户相关
    Route::post('auth/user/update', 'Auth/updateUser');
    
    // 打卡相关
    Route::post('punch/submit', 'Punch/submit');
    Route::get('punch/records', 'Punch/records');
    Route::get('punch/statistics', 'Punch/statistics');
    
    // 设置相关
    Route::post('settings/save', 'Settings/save');
    Route::get('settings/get', 'Settings/get');
    
    // 订阅消息相关
    Route::post('subscribe/save', 'Subscribe/save');
})->middleware(\app\middleware\Auth::class);

// 定时任务路由（内部调用，不需要JWT认证，但可以添加IP白名单或其他安全措施）
Route::get('cron/send-remind', 'Cron/sendRemind');
