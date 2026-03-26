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
        $code = $request->param('code', '');
        if (empty($code)) {
            return ResponseHelper::badRequest('参数错误：code不能为空');
        }
        
        try {
            $result = $this->authService->wechatLogin($code);
            if (!$result) {
                return ResponseHelper::error('微信登录失败', 402);
            }
            // 只打印 user_id和token 和openid
            Log::info('微信登录成功 result=' . json_encode(['user_id' => $result['user_id'], 'token' => $result['token'], 'openid' => $result['openid']]));
            return ResponseHelper::success($result, '登录成功');
        } catch (\Exception $e) {
            \think\facade\Log::error('微信登录异常 error=' . $e->getMessage() . ' trace=' . str_replace(["\r\n", "\n"], ' ', $e->getTraceAsString()));
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
        
        $data = [
            'nickname' => $request->post('nickname', ''),
            'avatar' => $request->post('avatar', ''),
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

