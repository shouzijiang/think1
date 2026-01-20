<?php

namespace app\middleware;

use app\common\JwtHelper;
use app\common\ResponseHelper;
use think\Request;
use think\Response;

/**
 * JWT 认证中间件
 */
class Auth
{
    /**
     * 处理请求
     *
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next)
    {
        $token = $this->getBearerToken($request);
        
        if (empty($token)) {
            return ResponseHelper::unauthorized('缺少认证令牌');
        }
        
        $payload = JwtHelper::verify($token);
        if (!$payload) {
            return ResponseHelper::unauthorized('认证令牌无效或已过期');
        }
        
        // 将用户信息注入到请求中
        $request->user_id = $payload['user_id'] ?? 0;
        $request->openid = $payload['openid'] ?? '';
        
        return $next($request);
    }
    
    /**
     * 从请求头获取 Bearer Token
     */
    private function getBearerToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization', '');
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}

