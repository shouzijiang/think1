<?php

namespace app\common;

use think\facade\Cache;
use think\facade\Log;

/**
 * 微信API工具类
 */
class WechatHelper
{
    /**
     * 获取微信配置
     */
    private static function getConfig(): array
    {
        $appid = env('WECHAT_APPID', '');
        $secret = env('WECHAT_SECRET', '');
        return [
            'appid'  => $appid,
            'secret' => $secret,
        ];
    }
    
    /**
     * 通过 code 获取 openid 和 session_key
     * @param string
         $code
     * @return array|false
     */
    public static function code2Session(string $code)
    {
        $config = self::getConfig();
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$config['appid']}&secret={$config['secret']}&js_code={$code}&grant_type=authorization_code";
        $response = self::httpGet($url);
        
        if (empty($response)) {
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['errcode']) && $data['errcode'] != 0) {
            Log::error('微信登录失败 errcode=' . ($data['errcode'] ?? '') . ' errmsg=' . ($data['errmsg'] ?? '未知错误'));
            return false;
        }
        
        if (empty($data['openid'])) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * 获取 access_token（带缓存）
     * @return string|false
     */
    public static function getAccessToken()
    {
        // 从缓存读取
        $cacheKey = 'wechat_access_token';
        $token = Cache::get($cacheKey);
        if ($token) {
            return $token;
        }
        
        // 重新获取
        $config = self::getConfig();
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$config['appid']}&secret={$config['secret']}";
        
        $response = self::httpGet($url);
        $result = json_decode($response, true);
        
        if (!isset($result['access_token'])) {
            Log::error('获取access_token失败 ' . json_encode($result, JSON_UNESCAPED_UNICODE));
            return false;
        }
        
        // 缓存 access_token（提前5分钟过期）
        $expire = ($result['expires_in'] ?? 7200) - 300;
        Cache::set($cacheKey, $result['access_token'], $expire);
        
        return $result['access_token'];
    }
    
    /**
     * 生成带参数的小程序码（wxacode.getUnlimited，适合动态场景）
     * scene 最大 32 字节，page 须已发布；返回 base64 字符串（不含前缀），失败返回 false
     *
     * @param string $scene  如 "channel=streamer_123"
     * @param string $page   如 "pages/index/index"
     * @param int    $width  二维码宽度（默认 430px）
     * @return string|false
     */
    public static function getUnlimitedQrCode(string $scene, string $page = 'pages/index/index', int $width = 430)
    {
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            Log::error('getUnlimitedQrCode: 获取access_token失败');
            return false;
        }

        $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token={$accessToken}";
        $payload = [
            'scene' => $scene,
            'page'  => $page,
            'width' => $width,
            'check_path' => false,
            'env_version' => 'release',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || empty($body)) {
            Log::error('getUnlimitedQrCode curl error: ' . $err);
            return false;
        }

        // 微信出错时返回 JSON；成功时返回 PNG 二进制流
        if ($body[0] === '{') {
            $json = json_decode($body, true);
            Log::error('getUnlimitedQrCode error: ' . json_encode($json, JSON_UNESCAPED_UNICODE));
            return false;
        }

        return base64_encode($body);
    }

    /**
     * 发送订阅消息
     * @param string $openid 用户openid
     * @param string $templateId 模板ID
     * @param array $data 消息数据
     * @param string $page 跳转页面
     * @return array
     */
    public static function sendSubscribeMessage(string $openid, string $templateId, array $data, string $page = 'pages/index/index'): array
    {
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'error' => '获取access_token失败'
            ];
        }
        
        $url = "https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token={$accessToken}";
        
        $postData = [
            'touser' => $openid,
            'template_id' => $templateId,
            'page' => $page,
            'data' => $data
        ];
        
        $response = self::httpPost($url, $postData);
        $result = json_decode($response, true);
        
        if (isset($result['errcode']) && $result['errcode'] == 0) {
            return ['success' => true];
        } else {
            return [
                'success' => false,
                'error' => $result['errmsg'] ?? '未知错误',
                'errcode' => isset($result['errcode']) ? (int) $result['errcode'] : null,
            ];
        }
    }
    
    /**
     * HTTP GET 请求
     */
    private static function httpGet(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            Log::error('HTTP请求失败 error=' . $error . ' url=' . $url . ' http_code=' . $httpCode);
            return '';
        }

        return $response;
    }

    /**
     * HTTP POST 请求
     */
    private static function httpPost(string $url, array $data): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            Log::error('HTTP请求失败 error=' . $error . ' url=' . $url . ' http_code=' . $httpCode);
            return '';
        }

        return $response;
    }
}

