<?php

namespace app\common;

/**
 * 响应格式化工具类
 */
class ResponseHelper
{
    /**
     * 成功响应
     */
    public static function success($data = null, string $message = 'success', int $code = 200)
    {
        $response = json([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ]);
        $response->header([
            'Content-Type' => 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization'
        ]);
        return $response;
    }
    
    public static function error(string $message = 'error', int $code = 400, $data = null)
    {
        $response = json([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ]);
        $response->header([
            'Content-Type' => 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization'
        ]);
        return $response;
    }
    
    /**
     * 未授权响应
     */
    public static function unauthorized(string $message = '未授权，请重新登录')
    {
        return self::error($message, 401);
    }
    
    /**
     * 参数错误响应
     */
    public static function badRequest(string $message = '参数错误')
    {
        return self::error($message, 400);
    }
}

