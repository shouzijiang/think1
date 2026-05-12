<?php

namespace app\controller;

use app\BaseController;
use app\common\ResponseHelper;
use app\common\WechatHelper;
use app\service\ChannelService;
use think\Request;

/**
 * 邀请好友赚收益控制器
 * 用户邀请渠道固定为 streamer_{userId}，通过 wxacode.getUnlimited 生成专属邀请码
 */
class Streamer extends BaseController
{
    private ChannelService $channelService;

    protected function initialize()
    {
        parent::initialize();
        $this->channelService = new ChannelService();
    }

    /**
     * 生成专属邀请小程序码（base64 PNG）
     * 每次调用都重新生成（微信接口本身有频控，前端应缓存展示）
     */
    public function generateQrCode(Request $request): \think\Response
    {
        $userId  = (int) $request->user_id;
        $channel = 'streamer_' . $userId;
        // scene 最大 32 字节
        $scene   = 'channel=' . $channel; // e.g. "channel=streamer_123"

        $base64 = WechatHelper::getUnlimitedQrCode($scene, 'pages/index/index', 430);
        if ($base64 === false) {
            return ResponseHelper::error('生成小程序码失败，请稍后重试', 500);
        }

        return ResponseHelper::success([
            'channel'  => $channel,
            'qrBase64' => $base64,          // 前端：data:image/png;base64,{qrBase64}
        ]);
    }

    /**
     * 获取邀请数据（受邀用户列表 + 行为统计）
     */
    public function inviteStats(Request $request): \think\Response
    {
        $userId = (int) $request->user_id;
        $info   = $this->channelService->getStreamerInviteInfo($userId);
        $info['userId'] = $userId; // 方便前端/调试确认是哪个用户
        return ResponseHelper::success($info);
    }
}
