<?php

declare(strict_types=1);

namespace app\common;

use think\facade\Log;

/**
 * 飞书自定义机器人（Webhook v2 + 签名校验）
 * @see https://open.feishu.cn/document/client-docs/bot-v3/add-custom-bot
 */
class FeishuBotHelper
{
    /**
     * 签名校验：密钥为 timestamp + "\n" + secret，对空串做 HmacSHA256 再 Base64
     */
    private static function genSign(string $secret, string $timestamp): string
    {
        $key = $timestamp . "\n" . $secret;

        return base64_encode(hash_hmac('sha256', '', $key, true));
    }

    private static function getConfig(): array
    {
        return [
            'webhook_url' => (string) env('FEISHU_WEBHOOK_URL', ''),
            'secret'      => (string) env('FEISHU_WEBHOOK_SECRET', ''),
        ];
    }

    /**
     * 发送纯文本（失败仅打日志，不影响业务）
     */
    public static function sendText(string $text): bool
    {
        $cfg = self::getConfig();
        if ($cfg['webhook_url'] === '' || $cfg['secret'] === '') {
            return false;
        }

        $timestamp = (string) time();
        $sign = self::genSign($cfg['secret'], $timestamp);

        $payload = [
            'timestamp' => $timestamp,
            'sign'      => $sign,
            'msg_type'  => 'text',
            'content'   => [
                'text' => $text,
            ],
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            Log::error('FeishuBot json_encode failed');
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $cfg['webhook_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            Log::error('FeishuBot curl error: ' . $err);
            return false;
        }

        $decoded = json_decode((string) $response, true);
        if (is_array($decoded)) {
            $code = $decoded['code'] ?? $decoded['StatusCode'] ?? null;
            if ($code === 0) {
                return true;
            }
        }

        Log::warning('FeishuBot unexpected response http=' . $httpCode . ' body=' . $response);

        return false;
    }

    public static function notifyBattleRoomCreated(string $roomId, string $creatorNickname, array $levelIds): void
    {
        $levelsStr = implode(',', array_map('strval', $levelIds));
        $text = sprintf(
            "[谐音对战] 新开房间\n房间号：%s\n房主：%s\n题目关卡ID：%s",
            $roomId,
            $creatorNickname !== '' ? $creatorNickname : '(未知)',
            $levelsStr
        );
        self::sendText($text);
    }

    public static function notifyBattleStarted(string $roomId, string $creatorNickname, string $challengerNickname): void
    {
        $text = sprintf(
            "[谐音对战] 对局开始\n房间号：%s\n房主：%s\n挑战者：%s",
            $roomId,
            $creatorNickname !== '' ? $creatorNickname : '(未知)',
            $challengerNickname !== '' ? $challengerNickname : '(未知)'
        );
        self::sendText($text);
    }
}
