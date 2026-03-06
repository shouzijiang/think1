<?php

namespace app\service;

use app\model\User;
use app\model\PunGameRank;
use app\model\PunGameLevelProgress;
use think\facade\Config;

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
        $progress = PunGameLevelProgress::where('user_id', $userId)->where('level', $level)->find();
        if ($progress) {
            $progress->passed = 1;
            $progress->save();
        } else {
            PunGameLevelProgress::create([
                'user_id' => $userId,
                'level'   => $level,
                'passed'  => 1,
            ]);
        }
    }

    /**
     * 当前用户关卡进度：当前可玩关卡、已通过关卡列表
     * @param int $userId
     * @return array ['currentLevel' => int, 'passedLevels' => int[]]
     */
    public function getLevelProgress(int $userId): array
    {
        $passedLevels = PunGameLevelProgress::where('user_id', $userId)
            ->where('passed', 1)
            ->order('level', 'asc')
            ->column('level');
        $passedLevels = array_map('intval', $passedLevels);
        $currentLevel = empty($passedLevels) ? 1 : (max($passedLevels) + 1);
        return [
            'currentLevel' => $currentLevel,
            'passedLevels' => array_values($passedLevels),
        ];
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
