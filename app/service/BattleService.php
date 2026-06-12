<?php
declare (strict_types = 1);

namespace app\service;

use app\common\FeishuBotHelper;
use app\model\PunGameBattleRecord;
use app\model\User;
use think\facade\Cache;
use think\facade\Db;

class BattleService extends \think\Service
{
    /** 允许的对战题库标识 */
    public const ALLOWED_BANKS = ['xhs', 'mid'];

    /**
     * 随机获取5道题目
     * @param string $bank 题库标识：xhs=小红书专辑, mid=经典
     */
    private function getRandomLevels(string $bank = 'xhs', int $count = 5): array
    {
        $configKey = $bank === 'mid' ? 'pun_levels_issue2' : 'pun_levels_issue3';
        $allLevels = \think\facade\Config::get($configKey, []);
        $keys = array_keys($allLevels);
        shuffle($keys);
        return array_slice($keys, 0, $count);
    }

    /**
     * 创建房间
     * @param string $questionBank 题库：xhs | mid
     */
    public function createRoom(int $userId, string $questionBank = 'xhs'): array
    {
        if (!in_array($questionBank, self::ALLOWED_BANKS, true)) {
            $questionBank = 'xhs';
        }

        // 生成6位不重复的房间号
        do {
            $roomId = mt_rand(100000, 999999) . '';
            $exists = PunGameBattleRecord::where('room_id', $roomId)->find();
        } while ($exists);

        $levels = $this->getRandomLevels($questionBank);

        $record = PunGameBattleRecord::create([
            'room_id' => $roomId,
            'creator_id' => $userId,
            'levels_json' => $levels,
            'question_bank' => $questionBank,
            'status' => 0, // 等待中
        ]);

        $creator = User::find($userId);
        $creatorName = $creator ? (string) ($creator->nickname ?? '') : '';
        // FeishuBotHelper::notifyBattleRoomCreated($roomId, $userId, $creatorName, $levels);

        return [
            'roomId' => $roomId,
            'recordId' => $record->id,
            'levels' => $levels
        ];
    }

    /**
     * 更新房间题库
     */
    public function updateBank(int $userId, string $roomId, string $questionBank): void
    {
        if (!in_array($questionBank, self::ALLOWED_BANKS, true)) {
            throw new \Exception('不支持的题库');
        }
        $record = PunGameBattleRecord::where('room_id', $roomId)
            ->where('creator_id', $userId)
            ->where('status', 0)
            ->find();
        if (!$record) {
            throw new \Exception('房间不存在或无权操作');
        }
        // 重新抽题
        $levels = $this->getRandomLevels($questionBank);
        $record->save([
            'question_bank' => $questionBank,
            'levels_json' => json_encode($levels),
        ]);
    }

    /**
     * 获取历史记录
     */
    public function getHistory(int $userId, int $page = 1, int $pageSize = 20): array
    {
        // 查询我是房主或者挑战者的记录，且状态是已结束(2)的
        $list = PunGameBattleRecord::with(['creator', 'challenger'])
            ->where(function ($query) use ($userId) {
                $query->where('creator_id', $userId)
                      ->whereOr('challenger_id', $userId);
            })
            ->where('status', 2)
            ->order('id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $pageSize,
            ]);

        $result = [];
        foreach ($list->items() as $item) {
            $isCreator = $item->creator_id === $userId;
            
            $opponentNickname = $isCreator ? ($item->challenger_nickname ?? '神秘对手') : ($item->creator_nickname ?? '神秘对手');
            $opponentAvatar = $isCreator ? ($item->challenger_avatar ?? '') : ($item->creator_avatar ?? '');
            
            $isWin = $item->winner_id === $userId;
            $isDraw = $item->winner_id === null; // 已结束且没赢家表示平局

            $result[] = [
                'id' => $item->id,
                'roomId' => $item->room_id,
                'opponentName' => $opponentNickname,
                'opponentAvatar' => $opponentAvatar,
                'totalTimeMs' => (int) ($item->total_time_ms ?? 0),
                'result' => $isDraw ? 'draw' : ($isWin ? 'win' : 'lose'),
                'createdAt' => $item->created_at,
            ];
        }

        return [
            'list' => $result,
            'total' => $list->total()
        ];
    }

    /**
     * 1V1 全局排行榜：按已结束且有胜负的记录统计胜场、负场（不含平局）
     *
     * @return array{list: list<array{user_id:int,nickname:string,avatar:string,win_count:int,lose_count:int,updated_at:string}>, total:int}
     */
    public function getBattleRankList(int $page = 1, int $pageSize = 20): array
    {
        $page = max(1, $page);
        $pageSize = min(max(1, $pageSize), 100);

        // 本地缓存：2 分钟过期（对战数据变化更快）
        $cacheKey = 'battle_rank_list:' . $page . ':' . $pageSize;
        $cached = Cache::get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        $winRows = Db::name('pun_game_battle_record')
            ->field('winner_id AS user_id, COUNT(*) AS win_count')
            ->where('status', 2)
            ->whereNotNull('challenger_id')
            ->whereNotNull('winner_id')
            ->group('winner_id')
            ->select()
            ->toArray();

        $loseSql = 'SELECT loser_id AS user_id, COUNT(*) AS lose_count FROM ('
            . ' SELECT CASE WHEN winner_id = creator_id THEN challenger_id ELSE creator_id END AS loser_id'
            . ' FROM pun_game_battle_record'
            . ' WHERE status = 2 AND challenger_id IS NOT NULL AND winner_id IS NOT NULL'
            . ') AS t GROUP BY loser_id';

        /** @var list<array{user_id: string|int, lose_count: string|int}> $loseRows */
        $loseRows = Db::query($loseSql);

        $stats = [];
        foreach ($winRows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $stats[$uid] = [
                'win_count'  => (int) ($row['win_count'] ?? 0),
                'lose_count' => 0,
            ];
        }
        foreach ($loseRows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            if (!isset($stats[$uid])) {
                $stats[$uid] = [
                    'win_count'  => 0,
                    'lose_count' => (int) ($row['lose_count'] ?? 0),
                ];
            } else {
                $stats[$uid]['lose_count'] = (int) ($row['lose_count'] ?? 0);
            }
        }

        $rankRows = [];
        foreach ($stats as $uid => $s) {
            $rankRows[] = [
                'user_id'    => $uid,
                'win_count'  => $s['win_count'],
                'lose_count' => $s['lose_count'],
            ];
        }

        usort($rankRows, static function (array $a, array $b): int {
            if ($a['win_count'] !== $b['win_count']) {
                return $b['win_count'] <=> $a['win_count'];
            }
            if ($a['lose_count'] !== $b['lose_count']) {
                return $a['lose_count'] <=> $b['lose_count'];
            }
            return $a['user_id'] <=> $b['user_id'];
        });

        $total = count($rankRows);
        $offset = ($page - 1) * $pageSize;
        $pageSlice = array_slice($rankRows, $offset, $pageSize);

        $ids = array_column($pageSlice, 'user_id');
        $usersById = [];
        if ($ids !== []) {
            $users = User::where('id', 'in', $ids)->field(['id', 'nickname', 'avatar'])->select();
            foreach ($users as $u) {
                $usersById[(int) $u->id] = [
                    'nickname' => (string) ($u->nickname ?? ''),
                    'avatar'   => (string) ($u->avatar ?? ''),
                ];
            }
        }

        $list = [];
        foreach ($pageSlice as $row) {
            $uid = (int) $row['user_id'];
            $u = $usersById[$uid] ?? ['nickname' => '', 'avatar' => ''];
            $list[] = [
                'user_id'    => $uid,
                'nickname'   => $u['nickname'],
                'avatar'     => $u['avatar'],
                'win_count'  => (int) $row['win_count'],
                'lose_count' => (int) $row['lose_count'],
                'updated_at' => '',
            ];
        }

        $result = [
            'list'  => $list,
            'total' => $total,
        ];
        Cache::set($cacheKey, $result, 120);
        return $result;
    }
}
