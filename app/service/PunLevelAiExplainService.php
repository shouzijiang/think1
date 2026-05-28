<?php

declare(strict_types=1);

namespace app\service;

use app\model\PunLevelAiExplain;
use think\facade\Config;
use think\facade\Log;

/**
 * 关卡 AI 趣味解读：答对时实时调 AI 生成并入库；失败则读表内历史；均无则返回兜底文案。
 */
class PunLevelAiExplainService
{
    public const DEFAULT_FALLBACK = '请点击进入下一关吧~';
    /** @var array<string, array<int, array>>|null */
    private static ?array $issueListCache = null;

    /**
     * 将 gameTier / mode 规范为表字段 game_tier：beginner | mid | xhs
     */
    public static function normalizeGameTier(string $mode): string
    {
        $normalized = PunService::normalizeMode($mode);
        if ($normalized === 'intermediate') {
            return 'mid';
        }
        if ($normalized === 'xhs') {
            return 'xhs';
        }
        if ($normalized === 'beginner') {
            return 'beginner';
        }

        return '';
    }

    /**
     * 答对后解析趣味解读：优先实时 AI 生成并写入表；失败则读历史；无历史则兜底。
     */
    public function resolvePassExplain(string $mode, int $levelNo): string
    {
        $tier = self::normalizeGameTier($mode);
        if ($tier === '' || $levelNo < 0 || ($tier !== 'mid' && $levelNo <= 0)) {
            return $this->fallbackText();
        }

        try {
            $meta = $this->getLevelMeta($tier, $levelNo);
        } catch (\InvalidArgumentException $e) {
            return $this->fallbackText();
        }

        $aiText = $this->generateExplainText((string) $meta['answer'], (string) $meta['hint']);
        if ($aiText !== '') {
            try {
                $this->upsertExplain($tier, $levelNo, $aiText);
            } catch (\Throwable $e) {
                Log::warning(sprintf(
                    'pun level explain upsert failed: tier=%s level=%d err=%s',
                    $tier,
                    $levelNo,
                    $e->getMessage()
                ));
            }
            return $aiText;
        }

        $cached = $this->getExplainText($mode, $levelNo);
        if ($cached !== '') {
            return $cached;
        }

        return $this->fallbackText();
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
        $tier = self::normalizeGameTier($mode);
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
     * 构建与前端 punPassExplain.js 一致的 prompt。
     */
    public function buildPrompt(string $answer, string $hint): string
    {
        $safeAnswer = $this->normalizeText($answer);
        $safeHint = $this->normalizeText($hint);

        return "要求：仅解读{$safeAnswer}词语含义，结合{$safeHint}范畴作答。用脑洞趣味风格，解释通俗易懂，单句话表述，字数控制50字上下。";
    }

    /**
     * 调用 OpenAI 兼容接口生成解读；未配置或失败时返回空字符串。
     */
    public function generateExplainText(string $answer, string $hint): string
    {
        $cfg = Config::get('pun_ai', []);
        if (empty($cfg['explain_enabled'])) {
            return '';
        }

        $url = trim((string) ($cfg['explain_api_url'] ?? ''));
        $key = trim((string) ($cfg['explain_api_key'] ?? ''));
        $model = trim((string) ($cfg['explain_model'] ?? 'deepseek-v3.2'));
        if ($url === '' || $key === '') {
            return '';
        }

        $payload = [
            'model'    => $model,
            'messages' => [
                ['role' => 'user', 'content' => $this->buildPrompt($answer, $hint)],
            ],
            'temperature' => 0.7,
        ];

        $body = $this->httpPostJson($url, $payload, [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ]);

        if ($body === '') {
            return '';
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return '';
        }

        $content = $json['choices'][0]['message']['content'] ?? '';
        return $this->normalizeText(is_string($content) ? $content : '');
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
        $body = $this->httpRequest('GET', $url, null, []);
        if ($body === '') {
            return null;
        }
        $json = json_decode($body, true);

        return is_array($json) ? $json : null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function httpPostJson(string $url, array $payload, array $headers = []): string
    {
        return $this->httpRequest('POST', $url, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers);
    }

    private function httpRequest(string $method, string $url, ?string $body, array $headers): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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
        curl_close($ch);

        if ($err) {
            Log::warning('PunLevelAiExplain http error: ' . $err);

            return '';
        }

        return is_string($response) ? $response : '';
    }
}
