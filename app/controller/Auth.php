<?php

namespace app\controller;

use app\BaseController;
use app\common\ResponseHelper;
use app\service\AuthService;
use think\Request;
use think\facade\Log;

/**
 * 认证控制器
 */
class Auth extends BaseController
{
    protected $authService;
    
    protected function initialize()
    {
        parent::initialize();
        $this->authService = new AuthService();
    }
    
    /**
     * 微信登录
     */
    public function wechatLogin(Request $request)
    {
        return $this->handleMiniProgramLogin($request, 'weixin');
    }

    /**
     * 抖音登录
     */
    public function douyinLogin(Request $request)
    {
        return $this->handleMiniProgramLogin($request, 'douyin');
    }

    /**
     * 小程序统一登录处理（微信/抖音共用）
     * @param Request $request
     * @param string $provider weixin|douyin
     */
    private function handleMiniProgramLogin(Request $request, string $provider)
    {
        $code = $request->param('code', '');
        if (empty($code)) {
            return ResponseHelper::badRequest('参数错误：code不能为空');
        }
        
        try {
            $result = $provider === 'douyin'
                ? $this->authService->douyinLogin($code)
                : $this->authService->wechatLogin($code);
            if (!$result) {
                return ResponseHelper::error(($provider === 'douyin' ? '抖音' : '微信') . '登录失败', 402);
            }
            // 仅记录关键字段，避免日志泄露隐私
            Log::info(($provider === 'douyin' ? '抖音' : '微信') . '登录成功 result=' . json_encode([
                'user_id' => $result['user_id'],
                'token' => $result['token'],
                'openid' => $result['openid']
            ]));
            return ResponseHelper::success($result, '登录成功');
        } catch (\Exception $e) {
            \think\facade\Log::error(($provider === 'douyin' ? '抖音' : '微信') . '登录异常 error=' . $e->getMessage() . ' trace=' . str_replace(["\r\n", "\n"], ' ', $e->getTraceAsString()));
            return ResponseHelper::error('登录异常：' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 更新用户信息
     */
    public function updateUser(Request $request)
    {
        $userId = $request->user_id ?? 0;
        
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        
        $rawNickname = $request->post('nickname', '');
        $rawAvatar   = $request->post('avatar', '');

        // 昵称长度校验
        if ($rawNickname !== '' && mb_strlen($rawNickname, 'UTF-8') > 50) {
            return ResponseHelper::badRequest('昵称不能超过50字');
        }

        // 头像格式校验：只允许 https URL 或 base64 data URL
        if ($rawAvatar !== '') {
            $isHttps    = preg_match('#^https?://[^\s]{10,}#i', $rawAvatar);
            $isDataUrl  = preg_match('#^data:image/(jpeg|png|gif|webp);base64,[A-Za-z0-9+/=]+$#', $rawAvatar);
            if (!$isHttps && !$isDataUrl) {
                return ResponseHelper::badRequest('头像格式非法');
            }
        }

        $data = [
            'nickname' => $rawNickname,
            'avatar'   => $rawAvatar,
        ];

        // 过滤空值
        $data = array_filter($data, function($value) {
            return $value !== '';
        });

        if (empty($data)) {
            return ResponseHelper::badRequest('没有需要更新的数据');
        }

        try {
            $result = $this->authService->updateUser($userId, $data);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \think\facade\Log::error('auth/user/update 异常: ' . $e->getMessage() . ' trace: ' . $e->getTraceAsString());
            $msg = $e->getMessage();
            if (stripos($msg, 'Data too long') !== false || stripos($msg, '4146') !== false) {
                return ResponseHelper::badRequest('头像数据过长。若需存 base64，请先执行：ALTER TABLE users MODIFY COLUMN avatar mediumtext DEFAULT NULL;');
            }
            return ResponseHelper::error('更新失败：' . $msg, 500);
        }
        
        if (!$result) {
            return ResponseHelper::error('更新失败', 500);
        }
        
        return ResponseHelper::success([
            'user_id' => $userId,
            'nickname' => $data['nickname'] ?? null,
            'avatar' => $data['avatar'] ?? null,
        ], '更新成功');
    }
}

