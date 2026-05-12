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
        $allowedEvents = ['login', 'daily_watch_ad_hint_1', 'reward_video'];
        if (!in_array($eventType, $allowedEvents, true)) return;
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
     * 获取用户的邀请信息
     * pun_game_channel_events.channel = 邀请人渠道，直接过滤即可
     *
     * @return array{channel:string, totalUsers:int, loginCount:int, videoCount:int}
     */
    public function getStreamerInviteInfo(int $userId): array
    {
        $channel = 'streamer_' . $userId;

        // 累计受邀人数（产生过事件的不重复用户数）
        $totalUsers = (int) Db::name('pun_game_channel_events')
            ->where('channel', $channel)
            ->count('DISTINCT user_id');

        // 累计登录次数
        $loginCount = (int) Db::name('pun_game_channel_events')
            ->where('channel', $channel)
            ->where('event_type', 'login')
            ->count();

        // 累计看视频次数
        $videoCount = (int) Db::name('pun_game_channel_events')
            ->where('channel', $channel)
            ->whereIn('event_type', ['reward_video', 'daily_watch_ad_hint_1'])
            ->count();

        return [
            'channel'    => $channel,
            'totalUsers' => $totalUsers,
            'loginCount' => $loginCount,
            'videoCount' => $videoCount,
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
