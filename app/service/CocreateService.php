<?php

namespace app\service;

use app\model\User;
use app\model\PunGameCocreate;
use think\facade\Config;
use think\facade\Db;

/**
 * 谐音梗图游戏 - 共创模块
 */
class CocreateService
{
    private const WORD_COUNT = 20;

    /**
     * 生成 20 个选词（必含答案中的每个字）
     * @param string $answer 如「车水马龙」
     * @return array ['wordArray' => string[], 'answerLength' => int]
     */
    public function generateWords(string $answer): array
    {
        $answer = trim($answer);
        if ($answer === '') {
            throw new \InvalidArgumentException('答案不能为空');
        }
        $answerChars = $this->mbStrSplit($answer);
        $answerLength = count($answerChars);
        $pool = Config::get('cocreate_word_pool', []);
        if (empty($pool)) {
            throw new \RuntimeException('选词池未配置');
        }
        $mustInclude = array_values(array_unique($answerChars));
        $rest = array_values(array_diff($pool, $mustInclude));
        shuffle($rest);
        $need = self::WORD_COUNT - count($mustInclude);
        if ($need > 0) {
            $pick = array_slice($rest, 0, $need);
            $wordArray = array_merge($mustInclude, $pick);
        } else {
            $wordArray = $mustInclude;
        }
        shuffle($wordArray);
        $wordArray = array_slice(array_values($wordArray), 0, self::WORD_COUNT);
        return [
            'wordArray'    => $wordArray,
            'answerLength' => $answerLength,
        ];
    }

    /**
     * AI 生成图片（占位实现：返回占位图 URL，实际需接入绘图接口）
     * @param string $prompt 提示词
     * @param string $type hint|answer
     * @return array ['imageUrl' => string]
     */
    public function generateImage(string $prompt, string $type): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new \InvalidArgumentException('提示词不能为空');
        }
        if (!in_array($type, ['hint', 'answer'], true)) {
            throw new \InvalidArgumentException('type 须为 hint 或 answer');
        }
        // 占位：未接入真实绘图接口时返回占位图，便于联调
        $placeholder = 'https://via.placeholder.com/400x300?text=' . urlencode(mb_substr($prompt, 0, 20, 'UTF-8'));
        return ['imageUrl' => $placeholder];
    }

    /**
     * 提交共创关卡
     * @param int $userId
     * @param array $data answer, answerLength, hintImagePrompt, answerExplanation, wordArray, hintImageUrl, answerImageUrl
     * @return int 新记录 id
     */
    public function submit(int $userId, array $data): int
    {
        $answer = trim((string) ($data['answer'] ?? ''));
        if ($answer === '') {
            throw new \InvalidArgumentException('答案不能为空');
        }
        $answerLength = (int) ($data['answerLength'] ?? 0);
        $answerChars = $this->mbStrSplit($answer);
        if ($answerLength !== count($answerChars)) {
            throw new \InvalidArgumentException('answerLength 与答案字数不一致');
        }
        $wordArray = $data['wordArray'] ?? [];
        if (!is_array($wordArray)) {
            $wordArray = [];
        }
        if (count($wordArray) !== self::WORD_COUNT) {
            throw new \InvalidArgumentException('选词须为 20 个');
        }
        foreach ($answerChars as $c) {
            if (!in_array($c, $wordArray, true)) {
                throw new \InvalidArgumentException('选词中须包含答案中的每个字');
            }
        }
        $hintImageUrl = trim((string) ($data['hintImageUrl'] ?? ''));
        $answerImageUrl = trim((string) ($data['answerImageUrl'] ?? ''));
        if ($hintImageUrl === '' || $answerImageUrl === '') {
            throw new \InvalidArgumentException('提示图与答案图 URL 必填');
        }
        $hintImagePrompt = trim((string) ($data['hintImagePrompt'] ?? ''));
        $answerExplanation = trim((string) ($data['answerExplanation'] ?? ''));

        $wordArrayJson = json_encode(array_values($wordArray), JSON_UNESCAPED_UNICODE);
        Db::name('pun_game_cocreate')->insert([
            'user_id'            => $userId,
            'answer'             => $answer,
            'answer_length'      => $answerLength,
            'hint_image_prompt'  => $hintImagePrompt,
            'answer_explanation' => $answerExplanation,
            'word_array'         => $wordArrayJson,
            'hint_image_url'     => $hintImageUrl,
            'answer_image_url'   => $answerImageUrl,
            'status'             => PunGameCocreate::STATUS_APPROVED,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);
        $id = (int) Db::name('pun_game_cocreate')->getLastInsID();
        return $id;
    }

    /**
     * 共创列表（仅已通过），分页
     */
    public function list(int $page = 1, int $pageSize = 20): array
    {
        $pageSize = min(max(1, $pageSize), 100);
        $query = PunGameCocreate::where('status', PunGameCocreate::STATUS_APPROVED)
            ->order('created_at', 'desc');
        $total = $query->count();
        $list = (clone $query)->page($page, $pageSize)->select();
        $userIds = array_unique($list->column('user_id'));
        $users = [];
        if ($userIds) {
            foreach (User::whereIn('id', $userIds)->select() as $u) {
                $users[$u->id] = $u;
            }
        }
        $items = [];
        foreach ($list as $row) {
            $u = $users[$row->user_id] ?? null;
            $items[] = [
                'id'              => (int) $row->id,
                'userId'          => (int) $row->user_id,
                'nickname'        => $u ? ($u->nickname ?? '') : '',
                'avatar'          => $u ? ($u->avatar ?? '') : '',
                'answer'          => $row->answer,
                'hintImageUrl'    => $row->hint_image_url,
                'answerImageUrl'  => $row->answer_image_url,
                'createdAt'       => $row->created_at ? date('Y-m-d H:i', strtotime($row->created_at)) : '',
            ];
        }
        return ['list' => $items, 'total' => $total];
    }

    /**
     * 共创详情（玩某条时拉题），仅已通过
     */
    public function detail(int $id): ?array
    {
        $row = PunGameCocreate::where('id', $id)->where('status', PunGameCocreate::STATUS_APPROVED)->find();
        if (!$row) {
            return null;
        }
        $wordArray = $row->word_array;
        if (!is_array($wordArray)) {
            $wordArray = is_string($wordArray) ? json_decode($wordArray, true) : [];
        }
        return [
            'id'           => (int) $row->id,
            'hintText'     => $row->hint_image_prompt,
            'imageUrl'     => $row->answer_image_url,
            'hintImageUrl' => $row->hint_image_url,
            'wordArray'    => array_values($wordArray),
            'answerLength' => (int) $row->answer_length,
            'answer'       => $row->answer,
        ];
    }

    /**
     * 提交共创题目答案（玩共创时）
     * @return array ['isCorrect' => bool, 'feedback' => [['position'=>int,'isCorrect'=>bool], ...]]
     */
    public function submitAnswer(int $cocreateId, array $userAnswer): array
    {
        $cocreate = PunGameCocreate::where('id', $cocreateId)->where('status', PunGameCocreate::STATUS_APPROVED)->find();
        if (!$cocreate) {
            throw new \InvalidArgumentException('关卡不存在或未通过');
        }
        $correct = $this->mbStrSplit($cocreate->answer);
        $feedback = [];
        $allCorrect = true;
        foreach ($userAnswer as $position => $char) {
            $isCorrect = isset($correct[$position]) && (string) $correct[$position] === (string) $char;
            $feedback[] = ['position' => (int) $position, 'isCorrect' => $isCorrect];
            if (!$isCorrect) {
                $allCorrect = false;
            }
        }
        for ($i = count($userAnswer); $i < count($correct); $i++) {
            $feedback[] = ['position' => $i, 'isCorrect' => false];
            $allCorrect = false;
        }
        return ['isCorrect' => $allCorrect, 'feedback' => $feedback];
    }

    private function mbStrSplit(string $str): array
    {
        $len = mb_strlen($str, 'UTF-8');
        $out = [];
        for ($i = 0; $i < $len; $i++) {
            $out[] = mb_substr($str, $i, 1, 'UTF-8');
        }
        return $out;
    }
}
