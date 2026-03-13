<?php

namespace app\service;

use app\common\JwtHelper;
use app\common\WechatHelper;
use app\model\User;
use app\model\UserSetting;
use think\facade\Db;

/**
 * 认证服务类
 */
class AuthService
{
    /**
     * 微信登录
     * @param string $code
     * @return array|false
     */
    public function wechatLogin(string $code)
    {
        if (empty($code)) {
            \think\facade\Log::error('微信登录：code为空');
            return false;
        }
        
        $wechatData = WechatHelper::code2Session($code);
        if (!$wechatData) {
            \think\facade\Log::error('微信登录：code2Session返回false code=' . substr($code, 0, 10) . '...');
            return false;
        }
        $openid = $wechatData['openid'] ?? '';
        $sessionKey = $wechatData['session_key'] ?? '';
        $unionid = $wechatData['unionid'] ?? '';
        
        if (empty($openid)) {
            return false;
        }
        
        // 查询或创建用户
        $user = User::where('openid', $openid)->find();
        if (!$user) {
            // 创建新用户
            $user = User::create([
                'openid' => $openid,
                'unionid' => $unionid ?: null,
            ]);
            
            // 创建默认设置
            UserSetting::create([
                'user_id' => $user->id,
                'enabled' => 1,
                'work_start_time' => '09:00',
                'work_end_time' => '18:00',
                'remind_interval' => 2,
            ]);
        }
        
        // 生成 JWT token
        $token = JwtHelper::generate([
            'user_id' => $user->id,
            'openid' => $openid
        ]);
        
        return [
            'token' => $token,
            'openid' => $openid,
            'unionid' => $unionid,
            'user_id' => $user->id,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
            'expires_in' => 7200
        ];
    }
    
    /** 头像（URL 或 base64）最大长度，对应 MySQL MEDIUMTEXT 约 16MB */
    private const AVATAR_MAX_LENGTH = 16777215;

    /**
     * 更新用户信息
     * @param int $userId
     * @param array $data ['nickname' => ?, 'avatar' => ?] avatar 可为 URL 或 base64 data URL
     * @return bool
     * @throws \InvalidArgumentException 头像超长时抛出
     */
    public function updateUser(int $userId, array $data): bool
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        $updateData = [];
        if (isset($data['nickname'])) {
            $updateData['nickname'] = $data['nickname'];
        }
        if (isset($data['avatar'])) {
            $avatar = $data['avatar'];
            if (mb_strlen($avatar, 'UTF-8') > self::AVATAR_MAX_LENGTH) {
                throw new \InvalidArgumentException('头像数据过长，请压缩后重试');
            }
            $updateData['avatar'] = $avatar;
        }

        if (empty($updateData)) {
            return true;
        }

        return $user->save($updateData);
    }
}

