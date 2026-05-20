<?php

namespace app\service;

use app\common\JwtHelper;
use app\common\DouyinHelper;
use app\common\WechatHelper;
use app\model\PunUserHintQuota;
use app\model\User;
use think\facade\Db;

/**
 * 认证服务类
 */
class AuthService
{
    /**
     * 更新最近登录时间（供已登录心跳调用）
     * @param int $userId
     * @return bool
     */
    public function touchLastLoginAt(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $user = User::find($userId);
        if (!$user) {
            return false;
        }
        $user->last_login_at = date('Y-m-d H:i:s');
        return (bool)$user->save();
    }

    /**
     * 微信登录
     * @param string $code
     * @return array|false
     */
    public function wechatLogin(string $code)
    {
        return $this->miniProgramLoginByProvider('weixin', $code);
    }

    /**
     * 抖音登录
     * @param string $code
     * @return array|false
     */
    public function douyinLogin(string $code)
    {
        return $this->miniProgramLoginByProvider('douyin', $code);
    }

    /**
     * 小程序统一登录入口（微信/抖音共用）
     * 把共用流程（code 换 openid、查找或创建用户、签发 token）放在一处，避免两套逻辑分叉。
     *
     * @param 'weixin'|'douyin' $provider
     * @param string $code
     * @return array|false
     */
    private function miniProgramLoginByProvider(string $provider, string $code)
    {
        if (empty($code)) {
            \think\facade\Log::error($provider . ' 登录：code为空');
            return false;
        }

        $oauthData = $this->getSessionByProvider($provider, $code);
        if (!$oauthData) {
            \think\facade\Log::error($provider . ' 登录：code2Session返回false code=' . substr($code, 0, 10) . '...');
            return false;
        }

        $rawOpenid = $oauthData['openid'] ?? '';
        $unionid = $oauthData['unionid'] ?? '';
        if (empty($rawOpenid)) {
            return false;
        }

        // users 表当前只有一个 openid 字段，抖音用前缀隔离，避免和微信 openid 产生冲突。
        $storedOpenid = $provider === 'douyin' ? ('douyin:' . $rawOpenid) : $rawOpenid;
        $storedUnionid = $provider === 'douyin'
            ? (empty($unionid) ? null : ('douyin:' . $unionid))
            : ($unionid ?: null);

        // 查询或创建用户
        $mpPlatform = $provider === 'douyin' ? 'douyin' : 'weixin';
        $user = User::where('openid', $storedOpenid)->find();
        if (!$user) {
            $user = User::create([
                'openid' => $storedOpenid,
                'unionid' => $storedUnionid,
                'mp_platform' => $mpPlatform,
            ]);

            PunUserHintQuota::create([
                'user_id' => $user->id,
                'quota'   => PunUserHintQuota::DEFAULT_QUOTA,
            ]);
        }

        $user->last_login_at = date('Y-m-d H:i:s');
        $user->mp_platform = $mpPlatform;
        $user->save();

        // token 里放存储态 openid，保持鉴权层读取一致
        $token = JwtHelper::generate([
            'user_id' => $user->id,
            'openid' => $storedOpenid
        ]);

        return [
            'token' => $token,
            'openid' => $rawOpenid,
            'unionid' => $unionid,
            'user_id' => $user->id,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
            'provider' => $provider,
            'expires_in' => 7200
        ];
    }

    /**
     * 按 provider 获取 code2Session 结果。
     * @param 'weixin'|'douyin' $provider
     * @param string $code
     * @return array|false
     */
    private function getSessionByProvider(string $provider, string $code)
    {
        if ($provider === 'douyin') {
            return DouyinHelper::code2Session($code);
        }
        return WechatHelper::code2Session($code);
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
            // 格式兜底校验：只允许 http/https URL 或 base64 data URL
            $isHttps   = preg_match('#^https?://[^\s]{10,}#i', $avatar);
            $isDataUrl = preg_match('#^data:image/(jpeg|png|gif|webp);base64,[A-Za-z0-9+/=]+$#', $avatar);
            if (!$isHttps && !$isDataUrl) {
                throw new \InvalidArgumentException('头像格式非法');
            }
            $updateData['avatar'] = $avatar;
        }

        if (empty($updateData)) {
            return true;
        }

        return $user->save($updateData);
    }
}

