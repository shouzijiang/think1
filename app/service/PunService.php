<?php

namespace app\service;

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
     * 玩法模式归一化
     * @return string beginner|intermediate
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
        if ($m === 'battle') {
            return 'battle';
        }
        return 'beginner';
    }

    /**
     * 排行榜列表（按 max_level 降序、updated_at 降序）
     * nickname/avatar 来自 users 表，单一数据源
     * @param int $page
     * @param int $pageSize
     * @return array ['list' => [...], 'total' => int]
     */
    public function getRankList(int $page = 1, int $pageSize = 20, string $mode = 'beginner'): array
    {
        $mode = $this->normalizeMode($mode);
        $pageSize = min(max(1, $pageSize), 100);
        $orderField = $mode === 'intermediate' ? 'max_level_mid' : 'max_level';

        $query = PunGameRank::with('user')
            ->where($orderField, '>=', 0)
            ->order($orderField, 'desc')
            ->order('updated_at', 'desc');
        $total = $query->count();
        $list = (clone $query)->page($page, $pageSize)
            ->select()
            ->map(function ($row) use ($orderField) {
                $user = $row->user;
                return [
                    'user_id'   => (int) $row->user_id,
                    'nickname'  => $user ? ($user->nickname ?? '') : '',
                    'avatar'    => $user ? ($user->avatar ?? '') : '',
                'max_level' => (int) $row->{$orderField},
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
     * @param string $mode beginner=初级 | intermediate=中级
     * @return array ['isCorrect' => bool, 'feedback' => [['position'=>int,'isCorrect'=>bool], ...]]
     */
    public function submitAnswer(int $userId, int $level, array $userAnswer, string $mode = 'beginner'): array
    {
        $mode = $this->normalizeMode($mode);
        if ($mode === 'intermediate' || $mode === 'battle') {
            $answersRaw = Config::get('pun_levels_issue2', []);
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
                if ($rank) {
                    $rank->max_level_mid = $level;
                    $rank->save();
                } else {
                    PunGameRank::create([
                        'user_id'       => $userId,
                        'max_level'     => 0,
                        'max_level_mid' => $level,
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
     * 更新排行榜并写入/更新关卡进度（排行榜仅存 user_id + max_level，昵称/头像读时从 users 表取）
     */
    protected function updateRankAndProgress(int $userId, int $level, string $mode): void
    {
        $mode = $this->normalizeMode($mode);
        Db::startTrans();
        try {
            $rank = PunGameRank::where('user_id', $userId)->find();
            if ($rank) {
                if ($mode === 'intermediate') {
                    $rank->max_level_mid = max($rank->max_level_mid ?? -1, $level);
                } else {
                    $rank->max_level = max($rank->max_level, $level);
                }
                $rank->save();
            } else {
                PunGameRank::create([
                    'user_id'       => $userId,
                    'max_level'     => $mode === 'beginner' ? $level : 0,
                    'max_level_mid' => $mode === 'intermediate' ? $level : -1,
                ]);
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

}
