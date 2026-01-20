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
            \think\facade\Log::error('微信登录：code2Session返回false', ['code' => substr($code, 0, 10) . '...']);
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
    
    /**
     * 更新用户信息
     * @param int $userId
     * @param array $data
     * @return bool
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
            $updateData['avatar'] = $data['avatar'];
        }
        
        if (empty($updateData)) {
            return true;
        }
        
        return $user->save($updateData);
    }
}

