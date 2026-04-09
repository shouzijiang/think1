<?php

namespace app\service;

use app\model\PunGameBattleRecord;
use app\model\PunGameRank;
use app\model\PunGameLevelProgress;
use app\model\PunGameFeedback;
use app\model\PunGameChangelog;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;

/**
 * 谐音梗图游戏 - 业务逻辑
 */
class PunService
{
    /**
     * 玩法模式归一化
     * @return string beginner|intermediate|xhs|battle
     */
    private function normalizeMode($mode): string
    {
        if (!is_string($mode)) {
            return 'beginner';
        }
        $m = strtolower(trim($mode));
        if (in_array($m, ['issue2', 'intermediate', 'mid', 'middle', '2', '中级', '中級'], true)) {
            return 'intermediate';
        }
        if (in_array($m, ['xhs', 'issue3', 'xiaohongshu', '小红书'], true)) {
            return 'xhs';
        }
        if ($m === 'battle') {
            return 'battle';
        }
        return 'beginner';
    }

    /**
     * 分步揭字提示：第 k 次请求显示前 k 个字，其余为 X；由服务端递增步数
     *
     * @param int|null $questionIndex 对战模式 0-4
     * @return array{hintText:string,step:int,maxSteps:int,isComplete:bool}
     */
    public function revealHint(int $userId, int $level, string $mode, ?string $roomId, ?int $questionIndex): array
    {
        $mode = $this->normalizeMode($mode);
        if ($level <= 0) {
            throw new \InvalidArgumentException('关卡参数无效');
        }

        if ($mode === 'intermediate' || $mode === 'battle') {
            $answersRaw = Config::get('pun_levels_issue2', []);
            $correct = isset($answersRaw[$level]) && is_array($answersRaw[$level]) ? $answersRaw[$level] : [];
        } elseif ($mode === 'xhs') {
            $answersRaw = Config::get('pun_levels_issue3', []);
            $correct = isset($answersRaw[$level]) && is_array($answersRaw[$level]) ? $answersRaw[$level] : [];
        } else {
            $answers = Config::get('pun_levels', []);
            $correct = isset($answers[$level]) && is_array($answers[$level]) ? $answers[$level] : [];
        }
        if ($correct === []) {
            throw new \InvalidArgumentException('关卡不存在');
        }

        if ($mode === 'battle') {
            if ($roomId === null || $roomId === '' || $questionIndex === null) {
                throw new \InvalidArgumentException('对战模式需传房间号与题号');
            }
            $this->assertBattleRoomQuestion($userId, $roomId, $questionIndex, $level);
            $cacheKey = $this->hintCacheKeyBattle($userId, $roomId, $questionIndex, $level);
        } elseif ($mode === 'intermediate') {
            $cacheKey = $this->hintCacheKeySolo($userId, 'mid', $level);
        } elseif ($mode === 'xhs') {
            $cacheKey = $this->hintCacheKeySolo($userId, 'xhs', $level);
        } else {
            $cacheKey = $this->hintCacheKeySolo($userId, 'beg', $level);
        }

        $n = count($correct);
        $hintsUsed = (int) Cache::get($cacheKey, 0);
        if ($hintsUsed >= $n) {
            throw new \InvalidArgumentException('本题提示已用尽');
        }

        $hintsUsed++;
        Cache::set($cacheKey, $hintsUsed, 7 * 86400);

        $hintText = $this->buildHintMask($correct, $hintsUsed);
        $isComplete = $hintsUsed >= $n;

        return [
            'hintText'   => $hintText,
            'step'       => $hintsUsed,
            'maxSteps'   => $n,
            'isComplete' => $isComplete,
        ];
    }

    private function hintCacheKeySolo(int $userId, string $bucket, int $level): string
    {
        return 'pun_hint:' . $userId . ':' . $bucket . ':' . $level;
    }

    private function hintCacheKeyBattle(int $userId, string $roomId, int $questionIndex, int $level): string
    {
        return 'pun_hint:' . $userId . ':bt:' . md5($roomId) . ':q' . $questionIndex . ':lv' . $level;
    }

    /**
     * @param int[] $correct 按字的答案数组
     * 展示为「已揭示字 + 空格 + 未揭示位为 _」，例如：一 _ _ _
     */
    private function buildHintMask(array $correct, int $revealCount): string
    {
        $n = count($correct);
        if ($n === 0) {
            return '';
        }
        $k = min(max(1, $revealCount), $n);
        $parts = [];
        for ($i = 0; $i < $n; $i++) {
            $parts[] = $i < $k ? (string) $correct[$i] : '_';
        }

        return implode(' ', $parts);
    }

    private function assertBattleRoomQuestion(int $userId, string $roomId, int $questionIndex, int $level): void
    {
        if ($questionIndex < 0 || $questionIndex > 4) {
            throw new \InvalidArgumentException('题号无效');
        }
        $record = PunGameBattleRecord::where('room_id', $roomId)->find();
        if (!$record) {
            throw new \InvalidArgumentException('房间不存在');
        }
        $uid = (int) $userId;
        $cid = (int) $record->creator_id;
        $hid = (int) ($record->challenger_id ?? 0);
        if ($uid !== $cid && $uid !== $hid) {
            throw new \InvalidArgumentException('无权访问该房间');
        }
        $arr = $record->levels_json;
        if (!is_array($arr)) {
            $arr = is_string($arr) ? (json_decode($arr, true) ?: []) : [];
        }
        if (!isset($arr[$questionIndex]) || (int) $arr[$questionIndex] !== $level) {
            throw new \InvalidArgumentException('题目与房间不匹配');
        }
    }

    /**
     * 排行榜同分时的第二排序列（该模式最近一次通关时间；空则回退行级 updated_at）
     */
    private function rankTiebreakTimeColumn(string $mode): string
    {
        $mode = $this->normalizeMode($mode);
        if ($mode === 'intermediate') {
            return 'last_pass_at_mid';
        }
        if ($mode === 'xhs') {
            return 'last_pass_at_xhs';
        }

        return 'last_pass_at_beginner';
    }

    /**
     * 排行榜列表（按该模式 max_level 降序、该模式最近通关时间降序）
     * nickname/avatar 来自 users 表，单一数据源
     * @param int $page
     * @param int $pageSize
     * @return array ['list' => [...], 'total' => int]
     */
    public function getRankList(int $page = 1, int $pageSize = 20, string $mode = 'beginner'): array
    {
        $mode = $this->normalizeMode($mode);
        $pageSize = min(max(1, $pageSize), 100);
        $orderField = $mode === 'intermediate'
            ? 'max_level_mid'
            : ($mode === 'xhs' ? 'max_level_xhs' : 'max_level');
        $tieCol = $this->rankTiebreakTimeColumn($mode);

        $query = PunGameRank::with('user')
            ->where($orderField, '>=', 0)
            ->order($orderField, 'desc')
            ->orderRaw('COALESCE(`' . $tieCol . '`, `updated_at`) DESC');
        $total = $query->count();
        $list = (clone $query)->page($page, $pageSize)
            ->select()
            ->map(function ($row) use ($orderField, $tieCol) {
                $user = $row->user;
                $tieRaw = $row->{$tieCol} ?? null;
                $showAt = $tieRaw ?: ($row->updated_at ?? null);

                return [
                    'user_id'   => (int) $row->user_id,
                    'nickname'  => $user ? ($user->nickname ?? '') : '',
                    'avatar'    => $user ? ($user->avatar ?? '') : '',
                    'max_level' => (int) $row->{$orderField},
                    'updated_at' => $showAt ? date('m-d H:i', strtotime((string) $showAt)) : '',
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
     * @param string $mode beginner=初级 | intermediate=中级
     * @return array ['isCorrect' => bool, 'feedback' => [['position'=>int,'isCorrect'=>bool], ...]]
     */
    public function submitAnswer(int $userId, int $level, array $userAnswer, string $mode = 'beginner'): array
    {
        $mode = $this->normalizeMode($mode);
        if ($mode === 'intermediate' || $mode === 'battle') {
            $answersRaw = Config::get('pun_levels_issue2', []);
            $correct = isset($answersRaw[$level]) && is_array($answersRaw[$level]) ? $answersRaw[$level] : [];
        } elseif ($mode === 'xhs') {
            $answersRaw = Config::get('pun_levels_issue3', []);
            $correct = isset($answersRaw[$level]) && is_array($answersRaw[$level]) ? $answersRaw[$level] : [];
        } else {
            $answers = Config::get('pun_levels', []);
            $correct = isset($answers[$level]) && is_array($answers[$level]) ? $answers[$level] : [];
        }
        $feedback = [];
        $allCorrect = true;

        if (!is_array($userAnswer)) {
            $userAnswer = [];
        }
        foreach ($userAnswer as $position => $char) {
            $isCorrect = isset($correct[$position]) && (string) $correct[$position] === (string) $char;
            $feedback[] = ['position' => (int) $position, 'isCorrect' => $isCorrect];
            if (!$isCorrect) {
                $allCorrect = false;
            }
        }
        // 若正确答案长度大于用户答案长度，多出的位置算错
        if (!is_array($correct)) {
            $correct = [];
        }
        for ($i = count($userAnswer); $i < count($correct); $i++) {
            $feedback[] = ['position' => $i, 'isCorrect' => false];
            $allCorrect = false;
        }
        if ($allCorrect) {
            if ($mode === 'intermediate') {
                $this->updateMidProgress($userId, $level, $answersRaw);
            } else if ($mode === 'xhs') {
                $this->updateXhsProgress($userId, $level, $answersRaw);
            } else if ($mode === 'beginner') {
                $this->updateRankAndProgress($userId, $level, $mode);
            }
            // mode === 'battle' 时，不更新个人进度和排行榜，对战逻辑在 WebSocket 中处理
        }
        return ['isCorrect' => $allCorrect, 'feedback' => $feedback];
    }

    /**
     * 中级更新排行榜并写入/更新关卡进度，按有序前缀递增
     */
    protected function updateMidProgress(int $userId, int $level, array $answersRaw): void
    {
        $midLevelIds = array_keys($answersRaw);
        
        Db::startTrans();
        try {
            $rank = PunGameRank::where('user_id', $userId)->find();
            $storedMaxLevelId = $rank ? (int) $rank->max_level_mid : -1;
            
            $storedIdx = array_search($storedMaxLevelId, $midLevelIds, true);
            if ($storedIdx === false) {
                $storedIdx = -1;
            }
            
            $submitIdx = array_search($level, $midLevelIds, true);
            
            // 仅当满足“前缀下一关”时才推进：
            if ($submitIdx !== false && $submitIdx === $storedIdx + 1) {
                $now = date('Y-m-d H:i:s');
                if ($rank) {
                    $rank->max_level_mid = $level;
                    $rank->last_pass_at_mid = $now;
                    $rank->save();
                } else {
                    PunGameRank::create([
                        'user_id'          => $userId,
                        'max_level'        => 0,
                        'max_level_mid'    => $level,
                        'last_pass_at_mid' => $now,
                    ]);
                }
                
                // 同步记录到进度表中（虽前端以 max_level_mid 的索引为准，此处记录供备用）
                $progress = Db::name('pun_game_level_progress')->where('user_id', $userId)->find();
                $passedLevels = $this->normalizePassedLevels($progress ? $progress['passed_levels_mid'] : null);
                if (!in_array($level, $passedLevels, true)) {
                    $passedLevels[] = $level;
                    // 对于中级不要按数值sort了，按出现顺序也没必要sort，因为只用于兜底。这里简单放最后。
                }
                $jsonValue = json_encode(array_values($passedLevels), JSON_UNESCAPED_UNICODE);
                
                if ($progress) {
                    Db::name('pun_game_level_progress')
                        ->where('id', $progress['id'])
                        ->update(['passed_levels_mid' => $jsonValue, 'updated_at' => date('Y-m-d H:i:s')]);
                } else {
                    Db::name('pun_game_level_progress')->insert([
                        'user_id'           => $userId,
                        'passed_levels'     => json_encode([], JSON_UNESCAPED_UNICODE),
                        'passed_levels_mid' => $jsonValue,
                        'created_at'        => date('Y-m-d H:i:s'),
                        'updated_at'        => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 小红书专辑：独立排行与进度，按有序前缀递增
     */
    protected function updateXhsProgress(int $userId, int $level, array $answersRaw): void
    {
        $xhsLevelIds = array_keys($answersRaw);

        Db::startTrans();
        try {
            $rank = PunGameRank::where('user_id', $userId)->find();
            $storedMaxLevelId = $rank ? (int) ($rank->max_level_xhs ?? -1) : -1;

            $storedIdx = array_search($storedMaxLevelId, $xhsLevelIds, true);
            if ($storedIdx === false) {
                $storedIdx = -1;
            }

            $submitIdx = array_search($level, $xhsLevelIds, true);

            if ($submitIdx !== false && $submitIdx === $storedIdx + 1) {
                $now = date('Y-m-d H:i:s');
                if ($rank) {
                    $rank->max_level_xhs = $level;
                    $rank->last_pass_at_xhs = $now;
                    $rank->save();
                } else {
                    PunGameRank::create([
                        'user_id'           => $userId,
                        'max_level'         => 0,
                        'max_level_mid'     => -1,
                        'max_level_xhs'     => $level,
                        'last_pass_at_xhs'  => $now,
                    ]);
                }

                $progress = Db::name('pun_game_level_progress')->where('user_id', $userId)->find();
                $passedLevels = $this->normalizePassedLevels($progress ? $progress['passed_levels_xhs'] : null);
                if (!in_array($level, $passedLevels, true)) {
                    $passedLevels[] = $level;
                }
                $jsonValue = json_encode(array_values($passedLevels), JSON_UNESCAPED_UNICODE);

                if ($progress) {
                    Db::name('pun_game_level_progress')
                        ->where('id', $progress['id'])
                        ->update(['passed_levels_xhs' => $jsonValue, 'updated_at' => date('Y-m-d H:i:s')]);
                } else {
                    Db::name('pun_game_level_progress')->insert([
                        'user_id'           => $userId,
                        'passed_levels'     => json_encode([], JSON_UNESCAPED_UNICODE),
                        'passed_levels_mid' => json_encode([], JSON_UNESCAPED_UNICODE),
                        'passed_levels_xhs' => $jsonValue,
                        'created_at'        => date('Y-m-d H:i:s'),
                        'updated_at'        => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 更新排行榜并写入/更新关卡进度（排行榜仅存 user_id + max_level，昵称/头像读时从 users 表取）
     */
    protected function updateRankAndProgress(int $userId, int $level, string $mode): void
    {
        $mode = $this->normalizeMode($mode);
        Db::startTrans();
        try {
            $rank = PunGameRank::where('user_id', $userId)->find();
            $now  = date('Y-m-d H:i:s');
            if ($rank) {
                if ($mode === 'intermediate') {
                    $rank->max_level_mid = max($rank->max_level_mid ?? -1, $level);
                    $rank->last_pass_at_mid = $now;
                } else {
                    $rank->max_level = max($rank->max_level, $level);
                    $rank->last_pass_at_beginner = $now;
                }
                $rank->save();
            } else {
                $create = [
                    'user_id'       => $userId,
                    'max_level'     => $mode === 'beginner' ? $level : 0,
                    'max_level_mid' => $mode === 'intermediate' ? $level : -1,
                ];
                if ($mode === 'intermediate') {
                    $create['last_pass_at_mid'] = $now;
                } else {
                    $create['last_pass_at_beginner'] = $now;
                }
                PunGameRank::create($create);
            }
            $progress = Db::name('pun_game_level_progress')->where('user_id', $userId)->find();

            if ($mode === 'intermediate') {
                $passedLevels = $this->normalizePassedLevels($progress ? $progress['passed_levels_mid'] : null);
            } else {
                $passedLevels = $this->normalizePassedLevels($progress ? $progress['passed_levels'] : null);
            }

            if (!in_array($level, $passedLevels, true)) {
                $passedLevels[] = $level;
                sort($passedLevels);
            }
            $passedLevels = array_values($passedLevels);
            $jsonValue = json_encode($passedLevels, JSON_UNESCAPED_UNICODE);

            if ($progress) {
                $updateField = $mode === 'intermediate' ? 'passed_levels_mid' : 'passed_levels';
                Db::name('pun_game_level_progress')
                    ->where('id', $progress['id'])
                    ->update([$updateField => $jsonValue, 'updated_at' => date('Y-m-d H:i:s')]);
            } else {
                Db::name('pun_game_level_progress')->insert([
                    'user_id'           => $userId,
                    'passed_levels'     => $mode === 'beginner' ? $jsonValue : json_encode([], JSON_UNESCAPED_UNICODE),
                    'passed_levels_mid' => $mode === 'intermediate' ? $jsonValue : json_encode([], JSON_UNESCAPED_UNICODE),
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s'),
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
     * @param string $mode beginner=初级 | intermediate=中级
     * @return array ['currentLevel' => int, 'passedLevels' => int[], 'totalLevels' => int]
     */
    public function getLevelProgress(int $userId, string $mode = 'beginner'): array
    {
        $progress = Db::name('pun_game_level_progress')->where('user_id', $userId)->find();
        $mode = $this->normalizeMode($mode);

        if ($mode === 'intermediate') {
            $answersRaw = Config::get('pun_levels_issue2', []);
            $allKeys = array_keys($answersRaw); // 真实关卡ID（保持配置顺序）
            $totalLevels = count($allKeys);

            // 统一读取中级已通过关卡。兼容历史数据：
            // - 已存真实关卡ID（优先直接使用）
            // - 误存为下标（映射到 allKeys 对应的真实关卡ID）
            $rawPassed = $this->normalizePassedLevels($progress ? $progress['passed_levels_mid'] : null);
            $mapped = [];
            foreach ($rawPassed as $n) {
                $id = (int) $n;
                if (isset($answersRaw[$id])) {
                    $mapped[] = $id;
                    continue;
                }
                if ($id >= 0 && $id < $totalLevels && isset($allKeys[$id])) {
                    $mapped[] = (int) $allKeys[$id];
                }
            }
            $passedLevels = array_values(array_unique($mapped));

            // 若旧数据为空，回退到排行榜 max_level_mid 推导“有序前缀”
            if (empty($passedLevels)) {
                $rank = PunGameRank::where('user_id', $userId)->find();
                $storedMaxLevelId = $rank ? (int) $rank->max_level_mid : -1;
                $storedIdx = array_search($storedMaxLevelId, $allKeys, true);
                if ($storedIdx !== false) {
                    $passedLevels = array_slice($allKeys, 0, $storedIdx + 1);
                }
            }

            $passedSet = array_fill_keys($passedLevels, true);
            $currentLevel = null;
            foreach ($allKeys as $k) {
                if (!isset($passedSet[$k])) {
                    $currentLevel = (int) $k;
                    break;
                }
            }

            return [
                'currentLevel' => $currentLevel,
                'passedLevels' => array_map('intval', $passedLevels),
                'totalLevels'  => $totalLevels,
            ];
        } elseif ($mode === 'xhs') {
            $answersRaw = Config::get('pun_levels_issue3', []);
            $allKeys = array_keys($answersRaw);
            $totalLevels = count($allKeys);

            $rawPassed = $this->normalizePassedLevels($progress ? $progress['passed_levels_xhs'] : null);
            $mapped = [];
            foreach ($rawPassed as $n) {
                $id = (int) $n;
                if (isset($answersRaw[$id])) {
                    $mapped[] = $id;
                    continue;
                }
                if ($id >= 0 && $id < $totalLevels && isset($allKeys[$id])) {
                    $mapped[] = (int) $allKeys[$id];
                }
            }
            $passedLevels = array_values(array_unique($mapped));

            if (empty($passedLevels)) {
                $rank = PunGameRank::where('user_id', $userId)->find();
                $storedMaxLevelId = $rank ? (int) ($rank->max_level_xhs ?? -1) : -1;
                $storedIdx = array_search($storedMaxLevelId, $allKeys, true);
                if ($storedIdx !== false) {
                    $passedLevels = array_slice($allKeys, 0, $storedIdx + 1);
                }
            }

            $passedSet = array_fill_keys($passedLevels, true);
            $currentLevel = null;
            foreach ($allKeys as $k) {
                if (!isset($passedSet[$k])) {
                    $currentLevel = (int) $k;
                    break;
                }
            }

            return [
                'currentLevel' => $currentLevel,
                'passedLevels' => array_map('intval', $passedLevels),
                'totalLevels'  => $totalLevels,
            ];
        } else {
            $answersRaw = Config::get('pun_levels', []);
            $allKeys = array_keys($answersRaw);
            sort($allKeys);
            $totalLevels = count($allKeys); // 使用总数量而不是最大的 key
            $lastLevel = empty($allKeys) ? -1 : end($allKeys);
            reset($allKeys);
            $passedLevels = $this->normalizePassedLevels($progress ? $progress['passed_levels'] : null);
            // 确保进度里只保留配置中确实存在的题目ID
            $passedLevels = array_values(array_filter($passedLevels, fn($n) => $n >= 1 && isset($answersRaw[$n])));

            $maxPassed = empty($passedLevels) ? 0 : max($passedLevels);
            $currentLevel = $allKeys[0] ?? 1;
            foreach ($allKeys as $k) {
                if (!in_array($k, $passedLevels, true)) {
                    $currentLevel = $k;
                    break;
                }
            }
            if ($currentLevel === ($allKeys[0] ?? 1) && $maxPassed >= $lastLevel && in_array($lastLevel, $passedLevels, true)) {
                $currentLevel = $maxPassed; // 初级原逻辑：全部通关后停留在最后一关
            }

            return [
                'currentLevel'  => $currentLevel,
                'passedLevels'  => $passedLevels,
                'totalLevels'   => $totalLevels,
            ];
        }
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

    /**
     * 获取论坛帖子列表
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getForumList(int $page = 1, int $pageSize = 20): array
    {
        $pageSize = min(max(1, $pageSize), 50);
        $query = \app\model\PunForumTopic::with('user')
            ->where('status', 1)
            ->order('updated_at', 'desc')
            ->order('id', 'desc');

        $total = $query->count();
        $list = (clone $query)->page($page, $pageSize)
            ->select()
            ->map(function ($row) {
                $user = $row->user;
                return [
                    'id'          => $row->id,
                    'user_id'     => $row->user_id,
                    'nickname'    => $user ? ($user->nickname ?? '') : '',
                    'avatar'      => $user ? ($user->avatar ?? '') : '',
                    'title'       => $row->title,
                    'content'     => mb_substr($row->content, 0, 100, 'UTF-8') . (mb_strlen($row->content, 'UTF-8') > 100 ? '...' : ''), // 列表只返回摘要
                    'view_count'  => $row->view_count,
                    'reply_count' => $row->reply_count,
                    'created_at'  => $row->created_at ? date('Y-m-d H:i', strtotime($row->created_at)) : '',
                    'updated_at'  => $row->updated_at ? date('Y-m-d H:i', strtotime($row->updated_at)) : '',
                ];
            })
            ->toArray();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 发布新帖子
     * @param int $userId
     * @param string $content
     * @param string $title
     * @return array ['error' => string|null, 'id' => int|null]
     */
    public function createForumTopic(int $userId, string $content, string $title = ''): array
    {
        $content = trim($content);
        $title = trim($title);

        if ($content === '') {
            return ['error' => '帖子内容不能为空'];
        }
        if (mb_strlen($content, 'UTF-8') > 2000) {
            return ['error' => '帖子内容过长(最多2000字)'];
        }
        if (mb_strlen($title, 'UTF-8') > 100) {
            return ['error' => '帖子标题过长(最多100字)'];
        }

        $topic = \app\model\PunForumTopic::create([
            'user_id' => $userId,
            'title'   => $title,
            'content' => $content,
            'status'  => 1,
        ]);

        return ['error' => null, 'id' => $topic->id];
    }

    /**
     * 获取帖子详情及回复列表
     * @param int $topicId
     * @param int $page
     * @param int $pageSize
     * @return array|null 返回null表示帖子不存在
     */
    public function getForumTopicDetail(int $topicId, int $page = 1, int $pageSize = 20): ?array
    {
        $topic = \app\model\PunForumTopic::with('user')->where('id', $topicId)->where('status', 1)->find();
        if (!$topic) {
            return null;
        }

        // 浏览量+1
        $topic->view_count = $topic->view_count + 1;
        $topic->save();

        $user = $topic->user;
        $topicData = [
            'id'          => $topic->id,
            'user_id'     => $topic->user_id,
            'nickname'    => $user ? ($user->nickname ?? '') : '',
            'avatar'      => $user ? ($user->avatar ?? '') : '',
            'title'       => $topic->title,
            'content'     => $topic->content,
            'view_count'  => $topic->view_count,
            'reply_count' => $topic->reply_count,
            'created_at'  => $topic->created_at ? date('Y-m-d H:i', strtotime($topic->created_at)) : '',
        ];

        // 获取回复列表
        $pageSize = min(max(1, $pageSize), 100);
        $query = \app\model\PunForumReply::with(['user', 'targetReply.user'])
            ->where('topic_id', $topicId)
            ->where('status', 1)
            ->order('created_at', 'asc'); // 评论按时间正序

        $totalReplies = $query->count();
        $replies = (clone $query)->page($page, $pageSize)
            ->select()
            ->map(function ($row) {
                $rUser = $row->user;
                $tReply = $row->targetReply;
                $tUser = $tReply ? $tReply->user : null;
                
                return [
                    'id'              => $row->id,
                    'user_id'         => $row->user_id,
                    'nickname'        => $rUser ? ($rUser->nickname ?? '') : '',
                    'avatar'          => $rUser ? ($rUser->avatar ?? '') : '',
                    'content'         => $row->content,
                    'reply_to_id'     => $row->reply_to_id,
                    'reply_to_user'   => $tUser ? ($tUser->nickname ?? '') : '',
                    'created_at'      => $row->created_at ? date('Y-m-d H:i', strtotime($row->created_at)) : '',
                ];
            })
            ->toArray();

        return [
            'topic'   => $topicData,
            'replies' => ['list' => $replies, 'total' => $totalReplies]
        ];
    }

    /**
     * 回复帖子或回复别人的评论
     * @param int $userId
     * @param int $topicId
     * @param string $content
     * @param int $replyToId
     * @return array ['error' => string|null]
     */
    public function createForumReply(int $userId, int $topicId, string $content, int $replyToId = 0): array
    {
        $content = trim($content);
        if ($content === '') {
            return ['error' => '回复内容不能为空'];
        }
        if (mb_strlen($content, 'UTF-8') > 1000) {
            return ['error' => '回复内容过长(最多1000字)'];
        }

        Db::startTrans();
        try {
            $topic = \app\model\PunForumTopic::where('id', $topicId)->where('status', 1)->lock(true)->find();
            if (!$topic) {
                Db::rollback();
                return ['error' => '帖子不存在或已被删除'];
            }

            if ($replyToId > 0) {
                $target = \app\model\PunForumReply::where('id', $replyToId)->where('topic_id', $topicId)->where('status', 1)->find();
                if (!$target) {
                    Db::rollback();
                    return ['error' => '目标回复不存在'];
                }
            }

            \app\model\PunForumReply::create([
                'topic_id'    => $topicId,
                'user_id'     => $userId,
                'reply_to_id' => $replyToId,
                'content'     => $content,
                'status'      => 1,
            ]);

            // 更新帖子的回复数和最新动态时间
            $topic->reply_count = $topic->reply_count + 1;
            $topic->updated_at = date('Y-m-d H:i:s');
            $topic->save();

            Db::commit();
            return ['error' => null];
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 首页统计：基于 pun_game_level_progress
     * - players：表行数（有进度记录的用户数）
     * - answers：全表 passed_levels、passed_levels_mid、passed_levels_xhs 三个 JSON 数组元素个数之和
     *
     * @return array{players:int, answers:int}
     */
    public function getHomeProgressStats(): array
    {
        $players = (int) Db::name('pun_game_level_progress')->count();
        $aggRows = Db::name('pun_game_level_progress')
            ->fieldRaw(
                'COALESCE(SUM(IFNULL(JSON_LENGTH(`passed_levels`), 0) + IFNULL(JSON_LENGTH(`passed_levels_mid`), 0) + IFNULL(JSON_LENGTH(`passed_levels_xhs`), 0)), 0) AS agg_total'
            )
            ->select();
        $answers = 0;
        $first = $aggRows[0] ?? null;
        if ($first !== null) {
            $arr = is_array($first) ? $first : (method_exists($first, 'toArray') ? $first->toArray() : []);
            $answers = (int) ($arr['agg_total'] ?? 0);
        }

        return [
            'players' => $players,
            'answers' => $answers,
        ];
    }

    /**
     * 首页「本期更新」：取最新一条已发布说明（无则返回 null）
     *
     * @return array{versionCode:string,title:string,lines:string[]}|null
     */
    public function getLatestChangelog(): ?array
    {
        $row = PunGameChangelog::where('is_published', 1)
            ->order('published_at', 'desc')
            ->order('id', 'desc')
            ->find();
        if (!$row) {
            return null;
        }
        $body = (string) ($row->body ?? '');
        $lines = [];
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $lines[] = trim($item);
                }
            }
        }
        if ($lines === []) {
            foreach (preg_split("/\r\n|\n|\r/", $body) as $line) {
                $t = trim($line);
                if ($t !== '') {
                    $lines[] = $t;
                }
            }
        }

        return [
            'versionCode' => (string) $row->version_code,
            'title'       => (string) $row->title,
            'lines'       => $lines,
        ];
    }

}
