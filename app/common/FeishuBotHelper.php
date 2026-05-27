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
    public static function sendText(string $text, string $webhookUrl = '', string $secret = ''): bool
    {
        $cfg = self::getConfig();
        if ($webhookUrl !== '') {
            $cfg['webhook_url'] = $webhookUrl;
            $cfg['secret']      = $secret;
        }
        if ($cfg['webhook_url'] === '') {
            return false;
        }

        $timestamp = (string) time();
        $sign = $cfg['secret'] !== '' ? self::genSign($cfg['secret'], $timestamp) : '';

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

    public static function notifyBattleRoomCreated(string $roomId, int $creatorId, string $creatorNickname, array $levelIds): void
    {
        $levelsStr = implode(',', array_map('strval', $levelIds));
        $text = sprintf(
            "[谐音对战] 新开房间\n房间号：%s\n房主 userId：%d\n房主：%s\n题目关卡ID：%s",
            $roomId,
            $creatorId,
            $creatorNickname !== '' ? $creatorNickname : '(未知)',
            $levelsStr
        );
        self::sendText($text);
    }

    public static function notifyBattleStarted(
        string $roomId,
        int $creatorId,
        int $challengerId,
        string $creatorNickname,
        string $challengerNickname,
        string $questionBank = '',
        string $creatorRemark = '',
        string $challengerRemark = ''
    ): void {
        $bankLabel = match ($questionBank) {
            'mid', 'intermediate' => '经典题库',
            'xhs'                 => '小红书题库',
            default               => $questionBank !== '' ? $questionBank : '未知',
        };
        $safeCreatorNickname = $creatorNickname !== '' ? $creatorNickname : '(未知)';
        $safeChallengerNickname = $challengerNickname !== '' ? $challengerNickname : '(未知)';
        $safeCreatorRemark = $creatorRemark !== '' ? $creatorRemark : '-';
        $safeChallengerRemark = $challengerRemark !== '' ? $challengerRemark : '-';
        $text = sprintf(
            "[谐音对战] 对局开始\n房间号：%s\n题库：%s\n房主 userId：%d nickname：%s remark：%s\n挑战者 userId：%d nickname：%s remark：%s",
            $roomId,
            $bankLabel,
            $creatorId,
            $safeCreatorNickname,
            $safeCreatorRemark,
            $challengerId,
            $safeChallengerNickname,
            $safeChallengerRemark
        );
        self::sendText($text);
    }

    public static function notifyFeedbackSubmitted(int $userId, string $type, string $content, string $contact): void
    {
        $typeLabel = match ($type) {
            'bug'     => '🐛 Bug 反馈',
            'suggest' => '💡 建议',
            'other'   => '💬 其他',
            default   => '💬 意见反馈',
        };
        $text = sprintf(
            "[谐音梗] 收到新反馈\n类型：%s\nuserId：%d\n内容：%s%s",
            $typeLabel,
            $userId,
            mb_substr($content, 0, 200, 'UTF-8') . (mb_strlen($content, 'UTF-8') > 200 ? '…' : ''),
            $contact !== '' ? "\n联系方式：{$contact}" : ''
        );
        self::sendText(
            $text,
            (string) env('FEISHU_FEEDBACK_WEBHOOK_URL', ''),
            (string) env('FEISHU_FEEDBACK_WEBHOOK_SECRET', '')
        );
    }
}
