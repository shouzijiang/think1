<?php

namespace app\service;

use think\facade\Db;

class ChannelService
{
    /**
     * 登录时上报渠道（首次才落库 channel 字段，每次都记录 login 事件）
     */
    public function report(int $userId, string $channel): void
    {
        $user = Db::name('users')->where('id', $userId)->field('channel')->find();

        // 首次来源才写 channel，不覆盖历史渠道
        if (empty($user['channel'])) {
            Db::name('users')->where('id', $userId)->update([
                'channel'    => $channel,
                'channel_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // 每次都记录登录事件（用于活跃追踪）
        $this->track($userId, $channel, 'login');
    }

    /**
     * 记录行为事件（静默：渠道为空则忽略）
     */
    public function track(int $userId, string $channel, string $eventType, array $extra = []): void
    {
        if (!$channel) return;
        Db::name('pun_game_channel_events')->insert([
            'user_id'    => $userId,
            'channel'    => $channel,
            'event_type' => $eventType,
            'extra'      => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 根据 user_id 获取渠道（空字符串表示非买量用户）
     */
    public function getChannel(int $userId): string
    {
        return (string) Db::name('users')->where('id', $userId)->value('channel');
    }

    /**
     * 获取主播的邀请信息：受邀用户列表 + 行为汇总
     * 主播渠道固定为 streamer_{userId}
     *
     * @return array{channel:string, totalUsers:int, todayUsers:int, events:array, recentUsers:array}
     */
    public function getStreamerInviteInfo(int $userId): array
    {
        $channel = 'streamer_' . $userId;

        // 受邀总人数（通过该渠道来的唯一用户数）
        $totalUsers = (int) Db::name('users')
            ->where('channel', $channel)
            ->count();

        // 今日新增
        $todayUsers = (int) Db::name('users')
            ->where('channel', $channel)
            ->whereDay('channel_at')
            ->count();

        // 行为事件汇总（近30天）
        $since = date('Y-m-d H:i:s', strtotime('-30 days'));
        $eventRows = Db::name('pun_game_channel_events')
            ->where('channel', $channel)
            ->where('created_at', '>=', $since)
            ->group('event_type')
            ->field('event_type, COUNT(*) as cnt, COUNT(DISTINCT user_id) as uv')
            ->select()
            ->toArray();
        $events = [];
        foreach ($eventRows as $row) {
            $events[$row['event_type']] = ['cnt' => (int)$row['cnt'], 'uv' => (int)$row['uv']];
        }

        // 最近20名受邀用户
        $recentUsers = Db::name('users')
            ->where('channel', $channel)
            ->order('channel_at', 'desc')
            ->limit(20)
            ->field('id as user_id, nickname, avatar, channel_at')
            ->select()
            ->toArray();

        return [
            'channel'     => $channel,
            'totalUsers'  => $totalUsers,
            'todayUsers'  => $todayUsers,
            'events'      => $events,
            'recentUsers' => $recentUsers,
        ];
    }

    /**
     * 统计接口：按渠道 + 事件类型汇总
     */
    public function stats(string $channel, string $startDate, string $endDate): array
    {
        $query = Db::name('pun_game_channel_events')->alias('e')
            ->join('users u', 'u.id = e.user_id')
            ->whereBetween('e.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($channel) {
            $query->where('u.channel', $channel);
        }

        $rows = $query->group('u.channel, e.event_type')
            ->field('u.channel, e.event_type, COUNT(*) as cnt, COUNT(DISTINCT e.user_id) as uv')
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['channel']][$row['event_type']] = [
                'cnt' => (int) $row['cnt'],
                'uv'  => (int) $row['uv'],
            ];
        }
        return $result;
    }
}
