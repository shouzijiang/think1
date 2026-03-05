<?php

namespace app\controller;

use app\BaseController;
use think\facade\Log;

class Index extends BaseController
{
    public function index()
    {
        Log::info('首页进入成功 ip=' . request()->ip());
        return '个人学习站点';
    }

    public function hello($name = 'ThinkPHP8')
    {
        Log::info('hello进入成功:' . 'ThinkPHP8');
        return '11hello,' . $name;
    }
}
