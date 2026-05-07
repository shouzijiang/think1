<?php

namespace app\common;

use think\facade\Log;

/**
 * 抖音小程序 API 工具类
 */
class DouyinHelper
{
    /**
     * 获取抖音小程序配置
     */
    private static function getConfig(): array
    {
        $appid = env('DOUYIN_APPID', '');
        $secret = env('DOUYIN_SECRET', '');
        return [
            'appid'  => $appid,
            'secret' => $secret,
        ];
    }

    /**
     * 通过 code 获取 openid 和 session_key
     * 文档: https://developer.open-douyin.com/docs/resource/zh-CN/mini-game/develop/server/log-in/code-2-session
     * @param string $code
     * @return array|false
     */
    public static function code2Session(string $code)
    {
        $config = self::getConfig();
        if (empty($config['appid']) || empty($config['secret'])) {
            Log::error('抖音登录失败: DOUYIN_APPID/DOUYIN_SECRET 未配置');
            return false;
        }

        $url = 'https://developer.toutiao.com/api/apps/v2/jscode2session';
        // 抖音该接口使用 POST + JSON；使用 GET 容易返回 404 page not found。
        $response = self::httpPost($url, [
            'appid' => $config['appid'],
            'secret' => $config['secret'],
            'code' => $code,
            // 按官方字段保留，普通登录场景可为空字符串。
            'anonymous_code' => '',
        ]);

        if (empty($response)) {
            return false;
        }

        $json = json_decode($response, true);
        if (!is_array($json)) {
            Log::error('抖音登录失败: 响应 JSON 解析失败 response=' . $response);
            return false;
        }

        $errNo = (int)($json['err_no'] ?? 0);
        if ($errNo !== 0) {
            Log::error('抖音登录失败 err_no=' . $errNo . ' err_tips=' . ($json['err_tips'] ?? ''));
            return false;
        }

        $data = $json['data'] ?? [];
        if (!is_array($data) || empty($data['openid'])) {
            Log::error('抖音登录失败: 返回缺少 openid');
            return false;
        }

        return $data;
    }

    /**
     * HTTP POST 请求（JSON）
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
            Log::error('抖音 HTTP 请求失败 error=' . $error . ' url=' . $url . ' http_code=' . $httpCode);
            return '';
        }

        return (string)$response;
    }
}
