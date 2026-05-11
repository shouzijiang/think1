<?php

namespace app\controller;

use app\common\ResponseHelper;
use app\middleware\Auth;
use app\service\ChannelService;
use think\Request;

class Channel
{
    public function report(Request $request): \think\Response
    {
        $userId  = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::error('未登录', 401);
        }
        $channel = trim($request->post('channel', ''));

        if (!$channel || strlen($channel) > 64) {
            return ResponseHelper::error('参数错误');
        }

        (new ChannelService())->report($userId, $channel);
        return ResponseHelper::success();
    }

    public function stats(Request $request): \think\Response
    {
        $channel   = $request->get('channel', '');
        $startDate = $request->get('start', date('Y-m-01'));
        $endDate   = $request->get('end', date('Y-m-d'));

        $data = (new ChannelService())->stats($channel, $startDate, $endDate);
        return ResponseHelper::success($data);
    }
}
