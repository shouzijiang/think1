<?php

namespace app\controller;

use app\BaseController;
use app\common\ResponseHelper;
use app\service\AuthService;
use think\Request;

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
            
            return ResponseHelper::success($result, '登录成功');
        } catch (\Exception $e) {
            \think\facade\Log::error('微信登录异常', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
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
        
        $result = $this->authService->updateUser($userId, $data);
        
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

