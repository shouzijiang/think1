<?php
declare (strict_types = 1);

namespace app\service;

use app\model\PunGameBattleRecord;
use think\facade\Db;

class BattleService extends \think\Service
{
    /**
     * 随机获取5道题目
     */
    private function getRandomLevels(int $count = 5): array
    {
        // 假设从 config/pun_levels.php 中获取（根据你现有的实现方式调整，这里假设从1~250随机抽5题）
        $allLevels = range(1, 250); 
        shuffle($allLevels);
        return array_slice($allLevels, 0, $count);
    }

    /**
     * 创建房间
     */
    public function createRoom(int $userId): array
    {
        // 生成6位不重复的房间号
        do {
            $roomId = mt_rand(100000, 999999) . '';
            $exists = PunGameBattleRecord::where('room_id', $roomId)->find();
        } while ($exists);

        $levels = $this->getRandomLevels();

        $record = PunGameBattleRecord::create([
            'room_id' => $roomId,
            'creator_id' => $userId,
            'levels_json' => $levels,
            'status' => 0, // 等待中
        ]);

        return [
            'roomId' => $roomId,
            'recordId' => $record->id,
            'levels' => $levels
        ];
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
            $myTime = $isCreator ? $item->creator_time_ms : $item->challenger_time_ms;
            $opponentTime = $isCreator ? $item->challenger_time_ms : $item->creator_time_ms;
            
            $opponentNickname = $isCreator ? ($item->challenger_nickname ?? '神秘对手') : ($item->creator_nickname ?? '神秘对手');
            $opponentAvatar = $isCreator ? ($item->challenger_avatar ?? '') : ($item->creator_avatar ?? '');
            
            $isWin = $item->winner_id === $userId;
            $isDraw = $item->winner_id === null; // 已结束且没赢家表示平局

            $result[] = [
                'id' => $item->id,
                'roomId' => $item->room_id,
                'opponentName' => $opponentNickname,
                'opponentAvatar' => $opponentAvatar,
                'myTimeMs' => $myTime,
                'opponentTimeMs' => $opponentTime,
                'result' => $isDraw ? 'draw' : ($isWin ? 'win' : 'lose'),
                'createdAt' => $item->created_at,
            ];
        }

        return [
            'list' => $result,
            'total' => $list->total()
        ];
    }
}
