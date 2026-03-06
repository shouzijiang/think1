<?php

namespace app\service;

use app\model\User;
use app\model\PunGameRank;
use app\model\PunGameLevelProgress;
use app\model\PunGameFeedback;
use think\facade\Config;
use think\facade\Db;

/**
 * 谐音梗图游戏 - 业务逻辑
 */
class PunService
{
    /**
     * 排行榜列表（按 max_level 降序、updated_at 降序）
     * @param int $page
     * @param int $pageSize
     * @return array ['list' => [...], 'total' => int]
     */
    public function getRankList(int $page = 1, int $pageSize = 20): array
    {
        $pageSize = min(max(1, $pageSize), 100);
        $query = PunGameRank::order('max_level', 'desc')
            ->order('updated_at', 'desc');
        $total = $query->count();
        $list = (clone $query)->page($page, $pageSize)
            ->select()
            ->map(function ($row) {
                return [
                    'user_id'   => (int) $row->user_id,
                    'nickname'  => $row->nickname ?? '',
                    'avatar'    => $row->avatar ?? '',
                    'max_level' => (int) $row->max_level,
                    'updated_at' => $row->updated_at ? date('m-d H:i', strtotime($row->updated_at)) : '',
                ];
            })
            ->toArray();
        return ['list' => $list, 'total' => $total];
    }

    /**
     * 提交答案：校验并返回 isCorrect、feedback；正确时更新排行榜与关卡进度
     * @param int $userId
     * @param int $level
     * @param array $userAnswer 用户答案数组，如 ['弟','分']
     * @return array ['isCorrect' => bool, 'feedback' => [['position'=>int,'isCorrect'=>bool], ...]]
     */
    public function submitAnswer(int $userId, int $level, array $userAnswer): array
    {
        $answers = Config::get('pun_levels', []);
        $correct = $answers[$level] ?? [];
        $feedback = [];
        $allCorrect = true;
        foreach ($userAnswer as $position => $char) {
            $isCorrect = isset($correct[$position]) && (string) $correct[$position] === (string) $char;
            $feedback[] = ['position' => (int) $position, 'isCorrect' => $isCorrect];
            if (!$isCorrect) {
                $allCorrect = false;
            }
        }
        // 若正确答案长度大于用户答案长度，多出的位置算错
        for ($i = count($userAnswer); $i < count($correct); $i++) {
            $feedback[] = ['position' => $i, 'isCorrect' => false];
            $allCorrect = false;
        }
        if ($allCorrect) {
            $this->updateRankAndProgress($userId, $level);
        }
        return ['isCorrect' => $allCorrect, 'feedback' => $feedback];
    }

    /**
     * 更新排行榜并写入/更新关卡进度
     */
    /** 排行榜昵称/头像最大字符数，MySQL varchar 按字符计，取保守值兼容各环境 */
    private const RANK_NICKNAME_MAX_CHARS = 60;
    private const RANK_AVATAR_MAX_CHARS = 200;

    protected function updateRankAndProgress(int $userId, int $level): void
    {
        Db::startTrans();
        try {
            $user = User::find($userId);
            $nickname = $user ? $this->truncateToChars($user->nickname ?? '', self::RANK_NICKNAME_MAX_CHARS) : '';
            $avatar = $user ? $this->truncateToChars($user->avatar ?? '', self::RANK_AVATAR_MAX_CHARS) : '';
            $rank = PunGameRank::where('user_id', $userId)->find();
            if ($rank) {
                $rank->max_level = max($rank->max_level, $level);
                $rank->nickname = $nickname;
                $rank->avatar = $avatar;
                $rank->save();
            } else {
                PunGameRank::create([
                    'user_id'   => $userId,
                    'nickname'  => $nickname,
                    'avatar'    => $avatar,
                    'max_level' => $level,
                ]);
            }
            $progress = PunGameLevelProgress::where('user_id', $userId)->find();
            $passedLevels = $this->normalizePassedLevels($progress ? $progress->passed_levels : null);
            if (!in_array($level, $passedLevels, true)) {
                $passedLevels[] = $level;
                sort($passedLevels);
            }
            $passedLevels = array_values($passedLevels);
            $jsonValue = json_encode($passedLevels, JSON_UNESCAPED_UNICODE);
            if ($progress) {
                Db::name('pun_game_level_progress')
                    ->where('id', $progress->id)
                    ->update(['passed_levels' => $jsonValue, 'updated_at' => date('Y-m-d H:i:s')]);
            } else {
                Db::name('pun_game_level_progress')->insert([
                    'user_id'       => $userId,
                    'passed_levels' => $jsonValue,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 将 passed_levels 规范为 int[]（兼容 JSON 字符串、对象、数组）
     */
    private function normalizePassedLevels($value): array
    {
        if (is_array($value)) {
            return array_map('intval', array_values($value));
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_map('intval', array_values($decoded)) : [];
        }
        if (is_object($value)) {
            return array_map('intval', array_values((array) $value));
        }
        return [];
    }

    /**
     * 当前用户关卡进度：当前可玩关卡、已通过关卡列表、总关卡数
     * @param int $userId
     * @return array ['currentLevel' => int, 'passedLevels' => int[], 'totalLevels' => int]
     */
    public function getLevelProgress(int $userId): array
    {
        $totalLevels = count(Config::get('pun_levels', []));
        $progress = PunGameLevelProgress::where('user_id', $userId)->find();
        $passedLevels = $this->normalizePassedLevels($progress ? $progress->passed_levels : null);
        $passedLevels = array_values(array_filter($passedLevels, fn($n) => $n >= 1));
        $currentLevel = empty($passedLevels) ? 1 : (min($totalLevels, max($passedLevels) + 1));
        return [
            'currentLevel'  => $currentLevel,
            'passedLevels'  => $passedLevels,
            'totalLevels'   => $totalLevels,
        ];
    }

    /**
     * 提交意见反馈
     * @param int $userId
     * @param string $type 反馈类型：'' / bug / suggest / other
     * @param string $content 反馈内容 2~500 字
     * @param string $contact 联系方式 0~128 字
     * @return array ['error' => string|null] 有 error 时表示校验失败
     */
    public function submitFeedback(int $userId, string $type, string $content, string $contact): array
    {
        $content = trim($content);
        if ($content === '') {
            return ['error' => '反馈内容不能为空'];
        }
        $len = mb_strlen($content, 'UTF-8');
        if ($len < 2) {
            return ['error' => '反馈内容至少 2 个字'];
        }
        if ($len > 500) {
            return ['error' => '反馈内容最多 500 个字'];
        }
        $type = trim($type);
        if ($type !== '' && !in_array($type, PunGameFeedback::allowedTypes(), true)) {
            return ['error' => '反馈类型不合法'];
        }
        $contact = trim($contact);
        if (mb_strlen($contact, 'UTF-8') > 128) {
            return ['error' => '联系方式最多 128 个字'];
        }
        PunGameFeedback::create([
            'user_id' => $userId,
            'type'    => $type,
            'content' => $content,
            'contact' => $contact,
        ]);
        return [];
    }

    /** 按字符数截断，与 MySQL varchar(N) 的“字符数”一致，避免 Data too long */
    private function truncateToChars(string $s, int $maxChars): string
    {
        if ($maxChars <= 0) {
            return '';
        }
        $len = mb_strlen($s, 'UTF-8');
        if ($len <= $maxChars) {
            return $s;
        }
        return mb_substr($s, 0, $maxChars, 'UTF-8');
    }
}
