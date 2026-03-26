<?php

namespace app\controller;

use app\BaseController;
use think\facade\Log;

class Index extends BaseController
{
    public function index()
    {
        // Log::info('首页进入成功 ip=' . request()->ip());
        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <title>跳转中</title>
</head>
<body style="text-align: center;">
    <img src="https://sofun.online/static/punGame/wxcode.jpg" alt="" style="width: 100%;">
    <p>长按保存二维码，微信扫一扫即可体验</p>
</body>
</html>
HTML;
    }

    public function hello($name = 'ThinkPHP8')
    {
        Log::info('hello进入成功:' . 'ThinkPHP8');
        return '11hello,' . $name;
    }
}
