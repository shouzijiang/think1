<?php

namespace app\service;

use app\model\UserSubscribe;

/**
 * 订阅消息服务类
 */
class SubscribeService
{
    /**
     * 保存订阅消息授权
     * @param int $userId
     * @param string $templateId
     * @param string $subscribeStatus
     * @return array
     */
    public function save(int $userId, string $templateId, string $subscribeStatus): array
    {
        $subscribe = UserSubscribe::where('user_id', $userId)
            ->where('template_id', $templateId)
            ->find();
        
        if ($subscribe) {
            $subscribe->subscribe_status = $subscribeStatus;
            $subscribe->save();
        } else {
            $subscribe = UserSubscribe::create([
                'user_id' => $userId,
                'template_id' => $templateId,
                'subscribe_status' => $subscribeStatus,
            ]);
        }
        
        return [
            'subscribe_id' => $subscribe->id
        ];
    }
}

