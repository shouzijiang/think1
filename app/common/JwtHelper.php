<?php

namespace app\common;

/**
 * JWT 工具类
 */
class JwtHelper
{
    /**
     * JWT密钥（从环境变量读取，如果没有则使用默认值）
     */
    private static function getSecret(): string
    {
        return env('JWT_SECRET', 'your-secret-key-here-change-in-production');
    }
    
    /**
     * 生成 JWT Token
     * @param array $payload 载荷数据
     * @param int $expire 过期时间（秒），默认7200（2小时）
     * @return string
     */
    public static function generate(array $payload, int $expire = 7200): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        
        $payload['iat'] = time();
        $payload['exp'] = time() + $expire;
        
        $headerEncoded = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        
        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", self::getSecret(), true);
        $signatureEncoded = self::base64UrlEncode($signature);
        
        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }
    
    /**
     * 验证 JWT Token
     * @param string $token
     * @return array|false
     */
    public static function verify(string $token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        $signature = self::base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", self::getSecret(), true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return false; // token已过期
        }
        
        return $payload;
    }
    
    /**
     * Base64 URL 编码
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL 解码
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
}

