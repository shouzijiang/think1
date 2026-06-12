<?php

declare(strict_types=1);

namespace app\service;

use app\model\PunLevelAiExplain;
use think\facade\Config;
use think\facade\Log;

/**
 * 关卡 AI 趣味解读：submit 优先读表；表无记录时才调 AI 并落库。
 */
class PunLevelAiExplainService
{
    public const DEFAULT_FALLBACK = '请点击进入下一关吧~';

    /** @var array<string, array<int, array>>|null */
    private static ?array $issueListCache = null;

    /**
     * 将 gameTier / mode 规范为表字段 game_tier：beginner | mid | xhs
     *
     * battle 模式需结合关卡编号反查所属题库。
     */
    public static function normalizeGameTier(string $mode, int $levelNo = 0): string
    {
        $normalized = PunService::normalizeMode($mode);

        // battle / daily_challenge 模式：按关卡编号反查所属题库
        if ($normalized === 'battle' || $normalized === 'daily_challenge') {
            return self::resolveBattleTier($levelNo);
        }

        if ($normalized === 'intermediate') {
            return 'mid';
        }
        if ($normalized === 'xhs' || $normalized === 'album') {
            return 'xhs';
        }
        if ($normalized === 'beginner') {
            return 'beginner';
        }

        return '';
    }

    /**
     * 战斗模式：根据关卡编号反查所属题库。
     * xhs 关卡编号 >3000 不与其他题库重叠；mid 与 beginner 有重叠时优先匹配 mid。
     */
    private static function resolveBattleTier(int $levelNo): string
    {
        if ($levelNo <= 0) {
            return '';
        }

        // xhs (issue3)：关卡编号通常 3001+
        $xhsLevels = Config::get('pun_levels_issue3', []);
        if (is_array($xhsLevels) && isset($xhsLevels[$levelNo])) {
            return 'xhs';
        }

        // mid (issue2)
        $midLevels = Config::get('pun_levels_issue2', []);
        if (is_array($midLevels) && isset($midLevels[$levelNo])) {
            return 'mid';
        }

        // beginner（battle 理论上不用，兜底）
        $beginnerLevels = Config::get('pun_levels', []);
        if (is_array($beginnerLevels) && isset($beginnerLevels[$levelNo])) {
            return 'beginner';
        }

        return '';
    }

    /**
     * 答对后解析趣味解读：优先读表；无记录再调 AI 生成并写入；仍失败则兜底。
     */
    public function resolvePassExplain(string $mode, int $levelNo): string
    {
        $tier = self::normalizeGameTier($mode, $levelNo);
        if ($tier === '' || $levelNo < 0 || ($tier !== 'mid' && $levelNo <= 0)) {
            $this->logExplainFail('invalid_params', ['mode' => $mode, 'level' => $levelNo]);
            return $this->fallbackText();
        }

        $cached = $this->getExplainText($mode, $levelNo);
        if ($cached !== '') {
            return $cached;
        }

        try {
            $meta = $this->getLevelMeta($tier, $levelNo);
        } catch (\InvalidArgumentException $e) {
            $this->logExplainFail('level_meta_not_found', [
                'tier'  => $tier,
                'level' => $levelNo,
                'err'   => $e->getMessage(),
            ]);
            return $this->fallbackText();
        }

        $aiText = $this->generateExplainText((string) $meta['answer'], (string) $meta['hint'], [
            'tier'  => $tier,
            'level' => $levelNo,
        ]);
        if ($aiText !== '') {
            try {
                $this->upsertExplain($tier, $levelNo, $aiText);
            } catch (\Throwable $e) {
                $this->logExplainFail('upsert_failed', [
                    'tier'  => $tier,
                    'level' => $levelNo,
                    'err'   => $e->getMessage(),
                ]);
            }
            return $aiText;
        }

        $this->logExplainFail('fallback', [
            'tier'   => $tier,
            'level'  => $levelNo,
            'reason' => 'ai_empty_and_no_cache',
        ]);

        return $this->fallbackText();
    }

    /**
     * 批量预生成：遍历指定轨道全部关卡，缺失则生成并入库。
     *
     * @return array{generated:int,skipped:int,failed:int}
     */
    public function generateMissingForTier(string $gameTier, int $limit = 0, bool $force = false): array
    {
        $tier = self::normalizeGameTier($gameTier);
        if ($tier === '') {
            throw new \InvalidArgumentException('gameTier 须为 beginner / mid / xhs');
        }

        $cfg = Config::get('pun_ai', []);
        if (empty($cfg['explain_enabled'])) {
            throw new \RuntimeException('PUN_EXPLAIN_AI_ENABLED 未开启，无法批量生成');
        }

        $stats = ['generated' => 0, 'skipped' => 0, 'failed' => 0];
        $levelNos = $this->listLevelNumbers($tier);
        $attempted = 0;

        foreach ($levelNos as $levelNo) {
            if (!$force && $this->getExplainText($tier, $levelNo) !== '') {
                $stats['skipped']++;
                continue;
            }

            if ($limit > 0 && $attempted >= $limit) {
                break;
            }
            $attempted++;

            try {
                $meta = $this->getLevelMeta($tier, $levelNo);
            } catch (\InvalidArgumentException $e) {
                $stats['failed']++;
                $this->logExplainFail('batch_level_meta_not_found', [
                    'tier'  => $tier,
                    'level' => $levelNo,
                    'err'   => $e->getMessage(),
                ]);
                continue;
            }

            $aiText = $this->generateExplainText((string) $meta['answer'], (string) $meta['hint'], [
                'tier'  => $tier,
                'level' => $levelNo,
            ]);
            if ($aiText === '') {
                $stats['failed']++;
                continue;
            }

            try {
                $this->upsertExplain($tier, $levelNo, $aiText);
                $stats['generated']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                $this->logExplainFail('batch_upsert_failed', [
                    'tier'  => $tier,
                    'level' => $levelNo,
                    'err'   => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @return list<int>
     */
    private function listLevelNumbers(string $gameTier): array
    {
        $tier = self::normalizeGameTier($gameTier);
        $configKey = match ($tier) {
            'beginner' => 'pun_levels',
            'mid'      => 'pun_levels_issue2',
            'xhs'      => 'pun_levels_issue3',
            default    => '',
        };
        if ($configKey === '') {
            return [];
        }

        $answers = Config::get($configKey, []);
        if (!is_array($answers)) {
            return [];
        }

        $levels = array_map('intval', array_keys($answers));
        sort($levels, SORT_NUMERIC);

        return $levels;
    }

    private function fallbackText(): string
    {
        $cfg = Config::get('pun_ai', []);
        $text = trim((string) ($cfg['explain_fallback'] ?? ''));

        return $text !== '' ? $text : self::DEFAULT_FALLBACK;
    }

    /**
     * 读取表内历史解读；无记录返回空字符串。
     */
    public function getExplainText(string $mode, int $levelNo): string
    {
        $tier = self::normalizeGameTier($mode, $levelNo);
        if ($tier === '' || $levelNo < 0 || ($tier !== 'mid' && $levelNo <= 0)) {
            return '';
        }

        $row = PunLevelAiExplain::where('game_tier', $tier)
            ->where('level_no', $levelNo)
            ->find();

        if (!$row) {
            return '';
        }

        return trim((string) ($row->explain_text ?? ''));
    }

    /**
     * 写入或更新解读文案。
     */
    public function upsertExplain(string $gameTier, int $levelNo, string $explainText): void
    {
        $tier = self::normalizeGameTier($gameTier);
        if ($tier === '') {
            throw new \InvalidArgumentException('不支持的关卡类型');
        }
        if ($levelNo < 0 || ($tier !== 'mid' && $levelNo <= 0)) {
            throw new \InvalidArgumentException('关卡编号无效');
        }

        $text = trim($explainText);
        if ($text === '') {
            throw new \InvalidArgumentException('解读文案不能为空');
        }

        $existing = PunLevelAiExplain::where('game_tier', $tier)
            ->where('level_no', $levelNo)
            ->find();

        if ($existing) {
            $existing->save(['explain_text' => $text]);
            return;
        }

        PunLevelAiExplain::create([
            'game_tier'    => $tier,
            'level_no'     => $levelNo,
            'explain_text' => $text,
        ]);
    }

    /**
     * 构建趣味解读 prompt（与历史小程序端一致）。
     */
    public function buildPrompt(string $answer, string $hint): string
    {
        $safeAnswer = $this->normalizeText($answer);
        $safeHint = $this->normalizeText($hint);

        return "要求：仅解读{$safeAnswer}词语含义，结合{$safeHint}范畴作答。用脑洞趣味风格，解释通俗易懂，单句话表述，字数控制50字上下。";
    }

    /**
     * 调用 CloudBase OpenAI 兼容接口生成解读；失败时返回空字符串。
     *
     * @param array{tier?:string,level?:int} $ctx
     */
    public function generateExplainText(string $answer, string $hint, array $ctx = []): string
    {
        $cfg = Config::get('pun_ai', []);
        if (empty($cfg['explain_enabled'])) {
            $this->logExplainFail('ai_disabled', $ctx);
            return '';
        }

        $key = trim((string) ($cfg['explain_api_key'] ?? ''));
        $provider = trim((string) ($cfg['explain_provider'] ?? 'hunyuan-v3'));
        $model = trim((string) ($cfg['explain_model'] ?? 'hy3-preview'));
        $url = $this->resolveChatCompletionsUrl($cfg, $provider);

        if ($url === '' || $key === '') {
            $this->logExplainFail('missing_config', array_merge($ctx, [
                'has_url' => $url !== '',
                'has_key' => $key !== '',
            ]));
            return '';
        }

        $payload = [
            'model'       => $model,
            'messages'    => [
                ['role' => 'user', 'content' => $this->buildPrompt($answer, $hint)],
            ],
            'temperature' => 0.7,
            'stream'      => false,
        ];

        $result = $this->httpPostJson($url, $payload, [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ]);

        $body = $result['body'];
        $httpCode = $result['http_code'];
        $curlError = $result['curl_error'];

        if ($curlError !== '') {
            $this->logExplainFail('curl_error', array_merge($ctx, [
                'url'   => $this->maskUrl($url),
                'err'   => $curlError,
                'model' => $model,
            ]));
            return '';
        }

        if ($body === '') {
            $this->logExplainFail('empty_body', array_merge($ctx, [
                'url'       => $this->maskUrl($url),
                'http_code' => $httpCode,
                'model'     => $model,
                'provider'  => $provider,
            ]));
            return '';
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            $this->logExplainFail('invalid_json', array_merge($ctx, [
                'url'       => $this->maskUrl($url),
                'http_code' => $httpCode,
                'model'     => $model,
                'snippet'   => $this->snippet($body),
            ]));
            return '';
        }

        if (!empty($json['error']) || (!empty($json['code']) && is_string($json['code']))) {
            $errDetail = '';
            if (!empty($json['error'])) {
                $errDetail = is_array($json['error'])
                    ? json_encode($json['error'], JSON_UNESCAPED_UNICODE)
                    : (string) $json['error'];
            } else {
                $errDetail = (string) $json['code'] . ': ' . (string) ($json['message'] ?? '');
            }
            $this->logExplainFail('api_error', array_merge($ctx, [
                'url'       => $this->maskUrl($url),
                'http_code' => $httpCode,
                'model'     => $model,
                'provider'  => $provider,
                'error'     => $errDetail,
            ]));
            return '';
        }

        if ($httpCode >= 400) {
            $this->logExplainFail('http_error', array_merge($ctx, [
                'url'       => $this->maskUrl($url),
                'http_code' => $httpCode,
                'model'     => $model,
                'snippet'   => $this->snippet($body),
            ]));
            return '';
        }

        $content = $this->extractMessageContent($json);
        $text = $this->normalizeText($content);
        if ($text === '') {
            $this->logExplainFail('empty_content', array_merge($ctx, [
                'url'       => $this->maskUrl($url),
                'http_code' => $httpCode,
                'model'     => $model,
                'provider'  => $provider,
                'snippet'   => $this->snippet($body),
            ]));
            return '';
        }

        return $text;
    }

    /**
     * 解析 chat/completions 完整 URL。
     * - 已含 chat/completions：原样使用
     * - 仅为网关根域名：拼 /v1/ai/{provider}/chat/completions（对齐 CloudBase OpenAPI ai_model）
     * - 若域名子段与 API Key JWT 中 project_id 不一致，自动替换为 Key 对应环境 ID
     *
     * @param array<string,mixed> $cfg
     */
    private function resolveChatCompletionsUrl(array $cfg, string $provider): string
    {
        $raw = trim((string) ($cfg['explain_api_url'] ?? ''));
        if ($raw === '') {
            return '';
        }

        if (stripos($raw, 'chat/completions') !== false) {
            return rtrim($raw, '/');
        }

        $key = trim((string) ($cfg['explain_api_key'] ?? ''));
        $base = $this->normalizeGatewayBaseUrl(rtrim($raw, '/'), $key);
        $group = $provider !== '' ? $provider : 'hunyuan-v3';

        return $base . '/v1/ai/' . rawurlencode($group) . '/chat/completions';
    }

    /**
     * 从 CloudBase API Key（JWT）解析环境 ID，用于修正网关域名。
     */
    private function extractEnvIdFromApiKey(string $key): string
    {
        $token = trim($key);
        if ($token === '') {
            return '';
        }

        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return '';
        }

        $payload = $parts[1];
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        if ($json === false) {
            return '';
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return '';
        }

        foreach (['project_id', 'aud', 'env_id'] as $field) {
            if (!empty($data[$field]) && is_string($data[$field])) {
                return $data[$field];
            }
        }

        return '';
    }

    /**
     * 规范化 CloudBase 网关根 URL（补 scheme、用 Key 修正 env 子域）。
     */
    private function normalizeGatewayBaseUrl(string $raw, string $apiKey): string
    {
        if (!preg_match('#^https?://#i', $raw)) {
            return 'https://' . $raw . '.api.tcloudbasegateway.com';
        }

        $envFromKey = $this->extractEnvIdFromApiKey($apiKey);
        if ($envFromKey === '') {
            return rtrim($raw, '/');
        }

        $parts = parse_url($raw);
        if (!is_array($parts) || empty($parts['host'])) {
            return rtrim($raw, '/');
        }

        $host = (string) $parts['host'];
        if (preg_match('#^([^.]+)\.(api(?:\.intl)?\.tcloudbasegateway\.com)$#i', $host, $m)) {
            if ($m[1] !== $envFromKey) {
                $scheme = $parts['scheme'] ?? 'https';
                $host = $envFromKey . '.' . $m[2];

                return $scheme . '://' . $host;
            }
        }

        return rtrim($raw, '/');
    }

    /**
     * @param array<string,mixed> $json
     */
    private function extractMessageContent(array $json): string
    {
        $content = $json['choices'][0]['message']['content'] ?? '';
        if (is_string($content) && $content !== '') {
            return $content;
        }

        // 部分网关把文本放在 choices[0].text
        $legacy = $json['choices'][0]['text'] ?? '';
        if (is_string($legacy) && $legacy !== '') {
            return $legacy;
        }

        return '';
    }

    /**
     * @param array<string,mixed> $context
     */
    private function logExplainFail(string $reason, array $context = []): void
    {
        $msg = '[pun-explain] ' . $reason;
        if ($context !== []) {
            $msg .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        Log::warning($msg);
    }

    private function maskUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '[invalid-url]';
        }
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';

        return $scheme . '://' . $host . $path;
    }

    private function snippet(string $body, int $max = 400): string
    {
        $text = preg_replace('/\s+/u', ' ', $body) ?? '';
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max) . '...';
    }

    /**
     * @return array{level_no:int,answer:string,hint:string}
     */
    public function getLevelMeta(string $gameTier, int $levelNo): array
    {
        $tier = self::normalizeGameTier($gameTier);
        if ($tier === '') {
            throw new \InvalidArgumentException('不支持的关卡类型');
        }

        if ($tier === 'beginner') {
            $answers = Config::get('pun_levels', []);
            $chars = $answers[$levelNo] ?? null;
            if (!is_array($chars)) {
                throw new \InvalidArgumentException('关卡不存在');
            }

            return [
                'level_no' => $levelNo,
                'answer'   => implode('', array_map('strval', $chars)),
                'hint'     => $this->fetchBeginnerHint($levelNo),
            ];
        }

        if ($tier === 'mid') {
            $answers = Config::get('pun_levels_issue2', []);
            $chars = $answers[$levelNo] ?? null;
            if (!is_array($chars)) {
                throw new \InvalidArgumentException('关卡不存在');
            }
            $list = $this->fetchIssueJson('issue2.json');
            $hintMap = $this->buildIssueHintMap($list, 'answerType');

            return [
                'level_no' => $levelNo,
                'answer'   => implode('', array_map('strval', $chars)),
                'hint'     => $hintMap[$levelNo] ?? '',
            ];
        }

        $answers = Config::get('pun_levels_issue3', []);
        $chars = $answers[$levelNo] ?? null;
        if (!is_array($chars)) {
            throw new \InvalidArgumentException('关卡不存在');
        }
        $list = $this->fetchIssueJson('issue3.json');
        $hintMap = $this->buildIssueHintMap($list, 'answerType');

        return [
            'level_no' => $levelNo,
            'answer'   => implode('', array_map('strval', $chars)),
            'hint'     => $hintMap[$levelNo] ?? '',
        ];
    }

    private function normalizeText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function fetchBeginnerHint(int $levelNo): string
    {
        $base = rtrim((string) Config::get('pun_ai.issue_base', 'https://sofun.online/static/punGame'), '/');
        $url = $base . '/issue/' . $levelNo . '.json';
        $json = $this->httpGetJson($url);
        if (!is_array($json)) {
            return '';
        }

        $hint = $json['hintText'] ?? $json['category'] ?? $json['keywordHint'] ?? '';

        return $this->normalizeText(is_string($hint) ? $hint : '');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchIssueJson(string $filename): array
    {
        if (self::$issueListCache === null) {
            self::$issueListCache = [];
        }
        if (isset(self::$issueListCache[$filename])) {
            return self::$issueListCache[$filename];
        }

        $base = rtrim((string) Config::get('pun_ai.issue_base', 'https://sofun.online/static/punGame'), '/');
        $url = $base . '/' . ltrim($filename, '/');
        $json = $this->httpGetJson($url);
        $list = is_array($json) ? $json : [];
        self::$issueListCache[$filename] = $list;

        return $list;
    }

    /**
     * @param list<array<string,mixed>> $list
     * @return array<int, string>
     */
    private function buildIssueHintMap(array $list, string $field): array
    {
        $map = [];
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lv = isset($item['level']) ? (int) $item['level'] : 0;
            if ($lv < 0) {
                continue;
            }
            $hint = $item[$field] ?? '';
            $map[$lv] = $this->normalizeText(is_string($hint) ? $hint : '');
        }

        return $map;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function httpGetJson(string $url): ?array
    {
        $result = $this->httpRequest('GET', $url, null, []);
        if ($result['body'] === '') {
            return null;
        }
        $json = json_decode($result['body'], true);

        return is_array($json) ? $json : null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{body:string,http_code:int,curl_error:string}
     */
    private function httpPostJson(string $url, array $payload, array $headers = []): array
    {
        return $this->httpRequest('POST', $url, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers);
    }

    /**
     * @return array{body:string,http_code:int,curl_error:string}
     */
    private function httpRequest(string $method, string $url, ?string $body, array $headers): array
    {
        $empty = ['body' => '', 'http_code' => 0, 'curl_error' => ''];

        $ch = curl_init();
        if ($ch === false) {
            return ['body' => '', 'http_code' => 0, 'curl_error' => 'curl_init failed'];
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 35);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        }

        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'body'       => is_string($response) ? $response : '',
            'http_code'  => $httpCode,
            'curl_error' => is_string($err) ? $err : '',
        ];
    }
}
