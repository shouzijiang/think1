<?php

namespace app\service;

use app\model\PunGameBattleRecord;
use app\model\PunGameRank;
use app\model\PunGameLevelProgress;
use app\model\PunGameFeedback;
use app\model\PunGameChangelog;
use app\model\PunUserHintQuota;
use app\model\UserSubscribe;
use app\common\FeishuBotHelper;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;

/**
 * 谐音梗猜一猜游戏 - 业务逻辑
 */
class PunService
{
    /** 领取类型：分享奖励（基础 +1，可按入参 delta 增量） */
    public const REWARD_TYPE_SHARE = 'share';

    /** 领取类型：激励视频奖励（基础 +1，可按入参 delta 增量） */
    public const REWARD_TYPE_VIDEO = 'reward_video';

    /**
     * 每日任务配置（按任务类型分组）
     * 键名: noon（答题任务）| ad（看广告任务）| battle（1V1 对战任务）
     */
    public const DAILY_TASKS = [
        'noon' => [
            'type'        => 'daily_noon_hint_5',    // 领取类型标识
            'reward_add'  => 5,                       // 单次发放次数
            'min_count'   => 20,                      // 达标门槛（当日答题数）
            'template_id' => 'rzQtKuen_qo-NivwIWEaQStbjgWZUokIKChNsZiVwfE', // 订阅模板 ID（个人小程序场景不强依赖）
        ],
        'ad' => [
            'type'       => 'daily_watch_ad_hint_1', // 领取类型标识
            'reward_add' => 1,                        // 单次发放次数
        ],
        'battle' => [
            'type'       => 'daily_battle_3_hint_3', // 领取类型标识
            'reward_add' => 3,                        // 单次发放次数
            'min_count'  => 3,                        // 达标门槛（当日对战局数）
        ],
    ];

    /**
     * 永久任务配置（全生命周期仅可领取一次）
     * 键名: avatar（设置头像）| nickname（设置昵称）| my_mini_program（我的小程序/收藏入口）
     */
    public const PERMANENT_TASKS = [
        'avatar' => [
            'type'       => 'permanent_set_avatar',  // 领取类型标识
            'reward_add' => 3,                        // 单次发放次数
        ],
        'nickname' => [
            'type'       => 'permanent_set_nickname', // 领取类型标识
            'reward_add' => 3,                         // 单次发放次数
        ],
        'my_mini_program' => [
            'type'       => 'permanent_my_mini_program_hint_3',
            'reward_add' => 3,
        ],
        'rate_app' => [
            'type'       => 'permanent_rate_app',
            'reward_add' => 3,
        ],
    ];

    /** 分享领奖最小间隔（秒） */
    private const SHARE_REWARD_MIN_INTERVAL_SEC = 60;

    /** 分享领奖单日上限（自然日，Asia/Shanghai） */
    private const SHARE_REWARD_DAILY_MAX = 5;

    /** 跳关一次扣除查看答案次数 */
    private const SKIP_LEVEL_HINT_COST = 2;

    /** 跳关标记有效期（48小时） */
    private const SKIP_LEVEL_TTL_SEC = 172800;

    /**
     * 玩法模式归一化
     *
     * 前端统一传 'mid' / 'xhs' / 'battle' / 'beginner'。
     * 保留少量历史别名兼容旧客户端缓存。
     *
     * @return string beginner|intermediate|xhs|battle
     */
    public static function normalizeMode($mode): string
    {
        if (!is_string($mode)) {
            return 'beginner';
        }
        $m = strtolower(trim($mode));
        // 'mid' 是前端当前统一值；其余为历史别名
        if (in_array($m, ['mid', 'intermediate', 'issue2'], true)) {
            return 'intermediate';
        }
        if (in_array($m, ['xhs', 'issue3'], true)) {
            return 'xhs';
        }
        if ($m === 'battle') {
            return 'battle';
        }
        return 'beginner';
    }

    /**
     * 将模式映射到跳关缓存分桶
     */
    private function skipBucketByMode(string $mode): string
    {
        if ($mode === 'intermediate') {
            return 'mid';
        }
        if ($mode === 'xhs') {
            return 'xhs';
        }
        return 'beg';
    }

    private function skipCacheKey(int $userId, string $mode): string
    {
        return 'pun_skip:' . $userId . ':' . $this->skipBucketByMode($mode);
    }

    /**
     * 读取跳关标记（自动按当前题库过滤无效关卡）
     *
     * @param array<int|string, array> $answersRaw
     * @return int[]
     */
    private function getSkipLevels(int $userId, string $mode, array $answersRaw): array
    {
        $key = $this->skipCacheKey($userId, $mode);
        $raw = Cache::get($key, []);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }

        $set = [];
        foreach ($raw as $lv) {
            $id = (int) $lv;
            if (isset($answersRaw[$id])) {
                $set[$id] = true;
            }
        }

        $result = array_map('intval', array_keys($set));
        sort($result, SORT_NUMERIC);

        return $result;
    }

    /**
     * 写入跳关标记（24小时过期）
     *
     * @param int[] $levels
     */
    private function putSkipLevels(int $userId, string $mode, array $levels): void
    {
        $set = [];
        foreach ($levels as $lv) {
            $id = (int) $lv;
            if ($id > 0) {
                $set[$id] = true;
            }
        }
        $result = array_map('intval', array_keys($set));
        sort($result, SORT_NUMERIC);
        Cache::set($this->skipCacheKey($userId, $mode), $result, self::SKIP_LEVEL_TTL_SEC);
    }

    private function removeSkipLevel(int $userId, string $mode, int $level, array $answersRaw): void
    {
        $levels = $this->getSkipLevels($userId, $mode, $answersRaw);
        $target = (int) $level;
        if (!in_array($target, $levels, true)) {
            return;
        }
        $next = array_values(array_filter($levels, fn ($n) => (int) $n !== $target));
        $this->putSkipLevels($userId, $mode, $next);
    }

    /**
     * 揭字剩余次数：无记录则按 {@see PunUserHintQuota::DEFAULT_QUOTA} 插入并返回
     */
    private function getOrCreateHintAnswerQuota(int $userId): int
    {
        $row = Db::name('pun_user_hint_quota')->where('user_id', $userId)->find();
        if ($row) {
            return (int) $row['quota'];
        }
        $default = PunUserHintQuota::DEFAULT_QUOTA;
        PunUserHintQuota::create([
            'user_id'    => $userId,
            'quota'      => $default,
            'total_used' => 0,
        ]);

        return $default;
    }

    private function shanghaiNow(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone('Asia/Shanghai'));
    }

    private function todayShanghai(): string
    {
        return $this->shanghaiNow()->format('Y-m-d');
    }

    private function saveSubscribeStatus(int $userId, string $templateId, string $status): void
    {
        if ($templateId === '' || !in_array($status, ['accept', 'reject'], true)) {
            return;
        }
        $row = UserSubscribe::where('user_id', $userId)
            ->where('template_id', $templateId)
            ->find();
        if ($row) {
            $row->subscribe_status = $status;
            $row->save();
            return;
        }
        UserSubscribe::create([
            'user_id' => $userId,
            'template_id' => $templateId,
            'subscribe_status' => $status,
        ]);
    }

    private function createRewardClaimRecord(
        int $userId,
        string $claimType,
        int $addQuota,
        string $status,
        string $reason = '',
        array $meta = []
    ): void {
        // 拒绝/异常失败不落库，仅成功领取写审计记录
        if ($status === 'rejected' || $status === 'failed') {
            return;
        }
        Db::name('pun_reward_claim_record')->insert([
            'user_id' => $userId,
            'claim_type' => $claimType,
            'claim_date' => $this->todayShanghai(),
            'add_quota' => $addQuota,
            'status' => $status,
            'reason' => $reason,
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 统计用户当天某类型奖励已成功领取的「累计增加次数」。
     */
    private function getTodayRewardAddedCount(int $userId, string $claimType): int
    {
        $sum = Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', $claimType)
            ->where('claim_date', $this->todayShanghai())
            ->where('status', 'success')
            ->sum('add_quota');

        return max(0, (int) $sum);
    }

    /**
     * 记录用户当日答题次数（按 submitAnswer 调用计数，自然日 Asia/Shanghai）。
     */
    private function incrementDailyAnswerCount(int $userId): void
    {
        $today = $this->todayShanghai();
        try {
            Db::execute(
                "INSERT INTO pun_daily_answer_stat (user_id, stat_date, answer_count, created_at, updated_at)
                 VALUES (:uid, :d, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE answer_count = answer_count + 1, updated_at = NOW()",
                ['uid' => $userId, 'd' => $today]
            );
        } catch (\Throwable $e) {
            // 计数失败不影响答题主流程，避免用户端答题不可用
            \think\facade\Log::error('incrementDailyAnswerCount失败 user_id=' . $userId . ' err=' . $e->getMessage());
        }
    }

    /**
     * 查询用户当日答题次数（自然日 Asia/Shanghai）。
     */
    private function getDailyAnswerCount(int $userId): int
    {
        $today = $this->todayShanghai();
        try {
            $count = Db::name('pun_daily_answer_stat')
                ->where('user_id', $userId)
                ->where('stat_date', $today)
                ->value('answer_count');
            return max(0, (int) $count);
        } catch (\Throwable $e) {
            \think\facade\Log::error('getDailyAnswerCount失败 user_id=' . $userId . ' err=' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 查询用户当日已完成 1V1 对局数（自然日 Asia/Shanghai，按对局结束时间 updated_at 统计）。
     */
    private function getTodayBattleFinishedCount(int $userId): int
    {
        $start = $this->todayShanghai() . ' 00:00:00';
        $end = $this->todayShanghai() . ' 23:59:59';
        try {
            $count = Db::name('pun_game_battle_record')
                ->where('status', 2)
                ->where(function ($query) use ($userId) {
                    $query->where('creator_id', $userId)->whereOr('challenger_id', $userId);
                })
                ->whereBetweenTime('updated_at', $start, $end)
                ->count();
            return max(0, (int) $count);
        } catch (\Throwable $e) {
            \think\facade\Log::error('getTodayBattleFinishedCount失败 user_id=' . $userId . ' err=' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 统一增加答案次数（事务内加锁）。
     */
    private function increaseHintQuota(int $userId, int $delta): int
    {
        $delta = max(1, (int) $delta);
        $this->getOrCreateHintAnswerQuota($userId);
        $newQuota = 0;
        Db::transaction(function () use ($userId, $delta, &$newQuota) {
            $row = Db::name('pun_user_hint_quota')->where('user_id', $userId)->lock(true)->find();
            if (!$row) {
                throw new \RuntimeException('揭字配额数据异常');
            }
            $quota = (int) $row['quota'];
            $newQuota = $quota + $delta;
            Db::name('pun_user_hint_quota')->where('user_id', $userId)->update(['quota' => $newQuota]);
        });

        return $newQuota;
    }

    /**
     * 统一领取接口：按 type 路由到不同领取逻辑；成功时写领取记录。
     *
     * @param array<string,mixed> $extra
     * @return array{hintAnswerQuota:int,added:int,type:string}
     */
    public function claimReward(int $userId, string $type, int $delta = 1, array $extra = []): array
    {
        $type = strtolower(trim((string) $type));
        // 历史客户端仍传 permanent_my_mini_program_hint_5，与 hint_3 为同一永久任务
        if ($type === 'permanent_my_mini_program_hint_5') {
            $type = self::PERMANENT_TASKS['my_mini_program']['type'];
        }
        if (!in_array($type, [
            self::REWARD_TYPE_SHARE,
            self::REWARD_TYPE_VIDEO,
            self::DAILY_TASKS['noon']['type'],
            self::DAILY_TASKS['ad']['type'],
            self::DAILY_TASKS['battle']['type'],
            self::PERMANENT_TASKS['avatar']['type'],
            self::PERMANENT_TASKS['nickname']['type'],
            self::PERMANENT_TASKS['my_mini_program']['type'],
            self::PERMANENT_TASKS['rate_app']['type'],
        ], true)) {
            throw new \InvalidArgumentException('不支持的领取类型');
        }

        $meta = $extra;
        $meta['type'] = $type;
        $meta['requested_add'] = $delta;

        if ($type === self::REWARD_TYPE_SHARE) {
            $result = $this->claimByShare($userId, $delta);
        } elseif ($type === self::REWARD_TYPE_VIDEO) {
            $result = $this->claimByRewardVideo($userId, $delta);
        } elseif ($type === self::DAILY_TASKS['noon']['type']) {
            $result = $this->claimByDailyNoon($userId, $extra);
        } elseif ($type === self::DAILY_TASKS['ad']['type']) {
            $result = $this->claimByDailyAdTask($userId);
        } elseif ($type === self::PERMANENT_TASKS['avatar']['type']) {
            $result = $this->claimByPermanentAvatar($userId);
        } elseif ($type === self::PERMANENT_TASKS['nickname']['type']) {
            $result = $this->claimByPermanentNickname($userId);
        } elseif ($type === self::PERMANENT_TASKS['my_mini_program']['type']) {
            $result = $this->claimByPermanentMyMiniProgram($userId, $extra);
        } elseif ($type === self::PERMANENT_TASKS['rate_app']['type']) {
            $result = $this->claimByPermanentRateApp($userId);
        } else {
            $result = $this->claimByDailyBattleTask($userId);
        }

        $this->createRewardClaimRecord($userId, $type, (int) $result['added'], 'success', '', $meta);
        $this->invalidateLevelProgressCache($userId);

        // 买量渠道追踪
        try {
            $channelService = new \app\service\ChannelService();
            $channel = $channelService->getChannel($userId);
            if ($channel) {
                $channelService->track($userId, $channel, $type, [
                    'added' => (int) $result['added'],
                ]);
            }
        } catch (\Throwable $ignored) {}

        return $result;
    }

    /**
     * 分享奖励：给当前用户揭字次数 +1（或指定增量）
     * @return array{hintAnswerQuota:int,added:int}
     */
    public function addHintAnswerQuotaByShare(int $userId, int $delta = 1): array
    {
        return $this->claimReward($userId, self::REWARD_TYPE_SHARE, $delta);
    }

    /**
     * 分享领奖核心实现（不含记录）
     * @return array{hintAnswerQuota:int,added:int,type:string}
     */
    private function claimByShare(int $userId, int $delta = 1): array
    {
        $delta = max(1, (int) $delta);
        $dailyKey = $this->shareRewardDailyCountCacheKey($userId);
        $dailyCount = (int) Cache::get($dailyKey, 0);
        if ($dailyCount + $delta > self::SHARE_REWARD_DAILY_MAX) {
            throw new \InvalidArgumentException(
                '今日分享领取次数已达上限（' . self::SHARE_REWARD_DAILY_MAX . '次），请明日再试'
            );
        }

        $cooldownKey = $this->shareRewardCooldownCacheKey($userId);
        $lastRewardAt = (int) Cache::get($cooldownKey, 0);
        $now = time();
        $nextAllowedAt = $lastRewardAt + self::SHARE_REWARD_MIN_INTERVAL_SEC;
        if ($lastRewardAt > 0 && $now < $nextAllowedAt) {
            $leftSec = max(1, $nextAllowedAt - $now);
            throw new \InvalidArgumentException("领取过于频繁，请{$leftSec}秒后再试");
        }

        $this->getOrCreateHintAnswerQuota($userId);

        $newQuota = 0;
        Db::transaction(function () use ($userId, $delta, &$newQuota) {
            $row = Db::name('pun_user_hint_quota')->where('user_id', $userId)->lock(true)->find();
            if (!$row) {
                throw new \RuntimeException('揭字配额数据异常');
            }
            $quota = (int) $row['quota'];
            $newQuota = $quota + $delta;
            Db::name('pun_user_hint_quota')->where('user_id', $userId)->update(['quota' => $newQuota]);
        });
        Cache::set($cooldownKey, $now, self::SHARE_REWARD_MIN_INTERVAL_SEC + 5);
        Cache::set($dailyKey, $dailyCount + $delta, $this->shareRewardDailyCacheTtlSeconds());

        return [
            'hintAnswerQuota' => $newQuota,
            'added' => $delta,
            'type' => self::REWARD_TYPE_SHARE,
        ];
    }

    private function shareRewardCooldownCacheKey(int $userId): string
    {
        return 'pun:share_reward:cooldown:' . $userId;
    }

    private function shareRewardDailyCountCacheKey(int $userId): string
    {
        $tz = new \DateTimeZone('Asia/Shanghai');
        $date = (new \DateTime('now', $tz))->format('Y-m-d');

        return 'pun:share_reward:daily_count:' . $userId . ':' . $date;
    }

    /** 缓存保留到当前自然日（上海时区）结束，略有余量 */
    private function shareRewardDailyCacheTtlSeconds(): int
    {
        $tz = new \DateTimeZone('Asia/Shanghai');
        $now = new \DateTime('now', $tz);
        $end = new \DateTime('tomorrow', $tz);
        $sec = (int) ($end->getTimestamp() - $now->getTimestamp());

        return max(300, $sec + 60);
    }

    /**
     * 激励视频单日领取上限（与分享独立计数）。
     * 设为 **0** 表示不限制（默认，便于变现）；改为正整数则启用「自然日内最多 N 次」校验，并写入 {@see videoRewardDailyCountCacheKey}。
     */
    private const VIDEO_REWARD_DAILY_MAX = 0;

    /**
     * 激励视频奖励：揭字次数 +delta（默认 1），风控独立于分享接口
     *
     * @return array{hintAnswerQuota:int,added:int}
     */
    public function addHintAnswerQuotaByRewardedVideo(int $userId, int $delta = 1): array
    {
        return $this->claimReward($userId, self::REWARD_TYPE_VIDEO, $delta);
    }

    /**
     * 激励视频领奖核心实现（不含记录）
     * @return array{hintAnswerQuota:int,added:int,type:string}
     */
    private function claimByRewardVideo(int $userId, int $delta = 1): array
    {
        $delta = max(1, (int) $delta);
        if (self::VIDEO_REWARD_DAILY_MAX > 0) {
            $dailyKey = $this->videoRewardDailyCountCacheKey($userId);
            $dailyCount = (int) Cache::get($dailyKey, 0);
            if ($dailyCount + $delta > self::VIDEO_REWARD_DAILY_MAX) {
                throw new \InvalidArgumentException(
                    '今日观看激励视频领取次数已达上限（' . self::VIDEO_REWARD_DAILY_MAX . '次），请明日再试'
                );
            }
        }

        $this->getOrCreateHintAnswerQuota($userId);

        $newQuota = 0;
        Db::transaction(function () use ($userId, $delta, &$newQuota) {
            $row = Db::name('pun_user_hint_quota')->where('user_id', $userId)->lock(true)->find();
            if (!$row) {
                throw new \RuntimeException('揭字配额数据异常');
            }
            $quota = (int) $row['quota'];
            $newQuota = $quota + $delta;
            Db::name('pun_user_hint_quota')->where('user_id', $userId)->update(['quota' => $newQuota]);
        });
        if (self::VIDEO_REWARD_DAILY_MAX > 0) {
            $dailyKey = $this->videoRewardDailyCountCacheKey($userId);
            $dailyCount = (int) Cache::get($dailyKey, 0);
            Cache::set($dailyKey, $dailyCount + $delta, $this->shareRewardDailyCacheTtlSeconds());
        }

        return [
            'hintAnswerQuota' => $newQuota,
            'added' => $delta,
            'type' => self::REWARD_TYPE_VIDEO,
        ];
    }

    /**
     * 永久任务：修改头像领取 +3（全生命周期仅可领取一次）
     */
    private function claimByPermanentAvatar(int $userId): array
    {
        $already = Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', self::PERMANENT_TASKS['avatar']['type'])
            ->where('status', 'success')
            ->find();
        if ($already) {
            throw new \InvalidArgumentException('头像奖励已领取过');
        }
        $user = Db::name('users')->where('id', $userId)->field('avatar')->find();
        if (empty($user['avatar'])) {
            throw new \InvalidArgumentException('请先设置头像后再领取');
        }
        $newQuota = $this->increaseHintQuota($userId, self::PERMANENT_TASKS['avatar']['reward_add']);
        return [
            'hintAnswerQuota' => $newQuota,
            'added' => self::PERMANENT_TASKS['avatar']['reward_add'],
            'type' => self::PERMANENT_TASKS['avatar']['type'],
        ];
    }

    /**
     * 永久任务：修改昵称领取 +3（全生命周期仅可领取一次）
     */
    private function claimByPermanentNickname(int $userId): array
    {
        $already = Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', self::PERMANENT_TASKS['nickname']['type'])
            ->where('status', 'success')
            ->find();
        if ($already) {
            throw new \InvalidArgumentException('昵称奖励已领取过');
        }
        $user = Db::name('users')->where('id', $userId)->field('nickname')->find();
        $nickname = trim((string) ($user['nickname'] ?? ''));
        if ($nickname === '' || in_array($nickname, ['微信用户', '用户'], true)) {
            throw new \InvalidArgumentException('请先设置昵称后再领取');
        }
        $newQuota = $this->increaseHintQuota($userId, self::PERMANENT_TASKS['nickname']['reward_add']);
        return [
            'hintAnswerQuota' => $newQuota,
            'added' => self::PERMANENT_TASKS['nickname']['reward_add'],
            'type' => self::PERMANENT_TASKS['nickname']['type'],
        ];
    }

    /**
     * 永久任务：从微信「我的小程序」或抖音「我的-收藏」入口进入后可领 +3（全生命周期仅一次）
     *
     * @param array<string,mixed> $extra 需含客户端上报的 launchScene（与 uni.getLaunchOptionsSync().scene 一致）
     */
    private function claimByPermanentMyMiniProgram(int $userId, array $extra): array
    {
        $already = Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', self::PERMANENT_TASKS['my_mini_program']['type'])
            ->where('status', 'success')
            ->find();
        if ($already) {
            throw new \InvalidArgumentException('该奖励已领取过');
        }
        $scene = $extra['launchScene'] ?? null;
        if (!$this->isAllowedMyMiniProgramLaunchScene($scene)) {
            throw new \InvalidArgumentException('请从微信「我的小程序」或抖音「我的收藏」进入小程序后再领取');
        }
        $newQuota = $this->increaseHintQuota($userId, self::PERMANENT_TASKS['my_mini_program']['reward_add']);

        return [
            'hintAnswerQuota' => $newQuota,
            'added' => self::PERMANENT_TASKS['my_mini_program']['reward_add'],
            'type' => self::PERMANENT_TASKS['my_mini_program']['type'],
        ];
    }

    /**
     * 永久任务：给小程序评分后领取 +3（全生命周期仅一次）
     */
    private function claimByPermanentRateApp(int $userId): array
    {
        $already = Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', self::PERMANENT_TASKS['rate_app']['type'])
            ->where('status', 'success')
            ->find();
        if ($already) {
            throw new \InvalidArgumentException('评分奖励已领取过');
        }
        $newQuota = $this->increaseHintQuota($userId, self::PERMANENT_TASKS['rate_app']['reward_add']);
        return [
            'hintAnswerQuota' => $newQuota,
            'added' => self::PERMANENT_TASKS['rate_app']['reward_add'],
            'type' => self::PERMANENT_TASKS['rate_app']['type'],
        ];
    }

    /**
     * 校验启动场景是否为「我的小程序」或抖音收藏入口（供领奖与文档约定）
     *
     * @param mixed $scene uni.getLaunchOptionsSync().scene
     */
    public function isAllowedMyMiniProgramLaunchScene($scene): bool
    {
        if ($scene === null || $scene === '' || is_bool($scene)) {
            return false;
        }
        $asString = trim((string) $scene);
        if ($asString === '') {
            return false;
        }
        // 抖音文档：021003 ——「我的-收藏」tab（字符串或数值 21003）
        if ($asString === '021003' || $asString === '21003') {
            return true;
        }
        // 微信：1103 发现页「我的小程序」列表；1104 下拉「我的小程序」栏（基础库 2.29.1+）
        if (is_numeric($asString)) {
            $n = (int) $asString;
            if (in_array($n, [1103, 1104, 21003], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 看广告任务：每次完整观看后领 +1（不做每日次数上限）
     * @return array{hintAnswerQuota:int,added:int,type:string}
     */
    private function claimByDailyAdTask(int $userId): array
    {
        $newQuota = $this->increaseHintQuota($userId, self::DAILY_TASKS['ad']['reward_add']);
        return [
            'hintAnswerQuota' => $newQuota,
            'added' => self::DAILY_TASKS['ad']['reward_add'],
            'type' => self::DAILY_TASKS['ad']['type'],
        ];
    }

    /**
     * 每日任务：当日完成 1V1 对局满 3 局，领 +3（自然日限一次）
     * @return array{hintAnswerQuota:int,added:int,type:string}
     */
    private function claimByDailyBattleTask(int $userId): array
    {
        $today = $this->todayShanghai();
        $already = Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', self::DAILY_TASKS['battle']['type'])
            ->where('claim_date', $today)
            ->where('status', 'success')
            ->find();
        if ($already) {
            throw new \InvalidArgumentException('今日1V1任务已领取');
        }

        $battleCount = $this->getTodayBattleFinishedCount($userId);
        if ($battleCount < self::DAILY_TASKS['battle']['min_count']) {
            throw new \InvalidArgumentException(
                '今日1V1已完成' . $battleCount . '/' . self::DAILY_TASKS['battle']['min_count'] . '局，达标后可领取'
            );
        }

        $newQuota = $this->increaseHintQuota($userId, self::DAILY_TASKS['battle']['reward_add']);
        return [
            'hintAnswerQuota' => $newQuota,
            'added' => self::DAILY_TASKS['battle']['reward_add'],
            'type' => self::DAILY_TASKS['battle']['type'],
        ];
    }

    /**
     * 每日任务领取 +5 次揭字（自然日限一次；不限制具体时段；个人小程序场景下不要求订阅模板授权）
     * @param array<string,mixed> $extra
     * @return array{hintAnswerQuota:int,added:int,type:string}
     */
    private function claimByDailyNoon(int $userId, array $extra): array
    {
        // 个人小程序不支持长期订阅消息模板：每日登录奖励不依赖 subscribeStatus/templateId
        $dailyAnswerCount = $this->getDailyAnswerCount($userId);
        if ($dailyAnswerCount < self::DAILY_TASKS['noon']['min_count']) {
            throw new \InvalidArgumentException(
                '今日已答' . $dailyAnswerCount . '/' . self::DAILY_TASKS['noon']['min_count'] . '题，答满后可领取'
            );
        }

        $today = $this->todayShanghai();
        $already = Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', self::DAILY_TASKS['noon']['type'])
            ->where('claim_date', $today)
            ->where('status', 'success')
            ->find();
        if ($already) {
            throw new \InvalidArgumentException('今日已领取，请明天再来');
        }

        $delta = self::DAILY_TASKS['noon']['reward_add'];
        $this->getOrCreateHintAnswerQuota($userId);

        $newQuota = 0;
        Db::transaction(function () use ($userId, $delta, &$newQuota) {
            $row = Db::name('pun_user_hint_quota')->where('user_id', $userId)->lock(true)->find();
            if (!$row) {
                throw new \RuntimeException('揭字配额数据异常');
            }
            $quota = (int) $row['quota'];
            $newQuota = $quota + $delta;
            Db::name('pun_user_hint_quota')->where('user_id', $userId)->update(['quota' => $newQuota]);
        });

        return [
            'hintAnswerQuota' => $newQuota,
            'added' => $delta,
            'type' => self::DAILY_TASKS['noon']['type'],
        ];
    }

    private function videoRewardDailyCountCacheKey(int $userId): string
    {
        $tz = new \DateTimeZone('Asia/Shanghai');
        $date = (new \DateTime('now', $tz))->format('Y-m-d');

        return 'pun:reward_video:daily_count:' . $userId . ':' . $date;
    }

    /**
     * 分步揭字提示：第 k 次请求显示前 k 个字，其余为 X；由服务端递增步数
     * 每成功揭一步扣揭字配额 1（见 pun_user_hint_quota），单题步数仍不超过答案字数 n
     *
     * @param int|null $questionIndex 对战模式 0-4
     * @return array{hintText:string,step:int,maxSteps:int,isComplete:bool,hintAnswerQuota:int}
     */
    public function revealHint(int $userId, int $level, string $mode, ?string $roomId, ?int $questionIndex): array
    {
        $mode = $this->normalizeMode($mode);
        // 中级/对战题库存在 level=0（新手引导）；其余模式最小关卡为 1。
        $allowZeroLevel = ($mode === 'intermediate' || $mode === 'battle');
        if (($allowZeroLevel && $level < 0) || (!$allowZeroLevel && $level <= 0)) {
            throw new \InvalidArgumentException('关卡参数无效');
        }

        if ($mode === 'intermediate') {
            $answersRaw = Config::get('pun_levels_issue2', []);
            $correct = isset($answersRaw[$level]) && is_array($answersRaw[$level]) ? $answersRaw[$level] : [];
        } elseif ($mode === 'battle') {
            // 对战模式：根据房间题库决定用哪个配置
            $battleBank = 'xhs';
            if ($roomId) {
                $rec = PunGameBattleRecord::where('room_id', $roomId)->find();
                if ($rec && in_array($rec->question_bank ?? '', ['mid', 'intermediate'], true)) {
                    $battleBank = 'mid';
                }
            }
            $configKey = $battleBank === 'mid' ? 'pun_levels_issue2' : 'pun_levels_issue3';
            $answersRaw = Config::get($configKey, []);
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

        $this->getOrCreateHintAnswerQuota($userId);

        $newStep = 0;
        $remainingQuota = 0;
        Db::transaction(function () use ($userId, $cacheKey, $n, &$newStep, &$remainingQuota) {
            $row = Db::name('pun_user_hint_quota')->where('user_id', $userId)->lock(true)->find();
            if (!$row) {
                throw new \RuntimeException('揭字配额数据异常');
            }
            $quota = (int) $row['quota'];
            if ($quota < 1) {
                throw new \InvalidArgumentException('次数不足，请前往首页获取更多次数');
            }
            $hintsUsedBefore = (int) Cache::get($cacheKey, 0);
            if ($hintsUsedBefore >= $n) {
                throw new \InvalidArgumentException('本题提示已用尽');
            }
            $newStep = $hintsUsedBefore + 1;
            $totalUsed = (int) ($row['total_used'] ?? 0);
            Db::name('pun_user_hint_quota')->where('user_id', $userId)->update([
                'quota'     => $quota - 1,
                'total_used' => $totalUsed + 1,
            ]);
            $remainingQuota = $quota - 1;
        });

        Cache::set($cacheKey, $newStep, 7 * 86400);
        $this->invalidateLevelProgressCache($userId);

        $hintText = $this->buildHintMask($correct, $newStep);
        $isComplete = $newStep >= $n;

        return [
            'hintText'          => $hintText,
            'step'              => $newStep,
            'maxSteps'          => $n,
            'isComplete'        => $isComplete,
            'hintAnswerQuota'   => $remainingQuota,
        ];
    }

    /**
     * 跳关：扣除查看答案次数并记录 24 小时跳关标记，返回下一关。
     *
     * @return array{nextLevel:?int,hintAnswerQuota:int,cost:int,skipTtlSeconds:int,skippedLevels:int[]}
     */
    public function skipLevel(int $userId, int $level, string $mode = 'beginner'): array
    {
        $mode = $this->normalizeMode($mode);
        if ($mode === 'battle') {
            throw new \InvalidArgumentException('对战模式不支持跳关');
        }

        if ($mode === 'intermediate') {
            $answersRaw = Config::get('pun_levels_issue2', []);
        } elseif ($mode === 'xhs') {
            $answersRaw = Config::get('pun_levels_issue3', []);
        } else {
            $answersRaw = Config::get('pun_levels', []);
        }
        if (!isset($answersRaw[$level])) {
            throw new \InvalidArgumentException('关卡不存在');
        }

        $this->getOrCreateHintAnswerQuota($userId);
        $remainingQuota = 0;
        Db::transaction(function () use ($userId, &$remainingQuota) {
            $row = Db::name('pun_user_hint_quota')->where('user_id', $userId)->lock(true)->find();
            if (!$row) {
                throw new \RuntimeException('揭字配额数据异常');
            }
            $quota = (int) ($row['quota'] ?? 0);
            if ($quota < self::SKIP_LEVEL_HINT_COST) {
                throw new \InvalidArgumentException('查看答案次数不足，无法跳关');
            }
            $totalUsed = (int) ($row['total_used'] ?? 0);
            $remainingQuota = $quota - self::SKIP_LEVEL_HINT_COST;
            Db::name('pun_user_hint_quota')->where('user_id', $userId)->update([
                'quota' => $remainingQuota,
                'total_used' => $totalUsed + self::SKIP_LEVEL_HINT_COST,
            ]);
        });

        $skippedLevels = $this->getSkipLevels($userId, $mode, $answersRaw);
        if (!in_array($level, $skippedLevels, true)) {
            $skippedLevels[] = (int) $level;
        }
        $this->putSkipLevels($userId, $mode, $skippedLevels);
        $this->invalidateLevelProgressCache($userId);

        $allKeys = array_keys($answersRaw);
        if ($mode === 'xhs' || $mode === 'beginner') {
            sort($allKeys, SORT_NUMERIC);
        }
        $idx = $this->indexOfLevelIdInOrderedKeys($allKeys, $level);
        $skipSet = array_fill_keys($skippedLevels, true);
        $nextLevel = null;
        for ($i = max(0, $idx + 1); $i < count($allKeys); $i++) {
            $candidate = (int) $allKeys[$i];
            if (!isset($skipSet[$candidate])) {
                $nextLevel = $candidate;
                break;
            }
        }

        return [
            'nextLevel' => $nextLevel,
            'hintAnswerQuota' => $remainingQuota,
            'cost' => self::SKIP_LEVEL_HINT_COST,
            'skipTtlSeconds' => self::SKIP_LEVEL_TTL_SEC,
            'skippedLevels' => array_values(array_unique(array_map('intval', $skippedLevels))),
        ];
    }

    private function hintCacheKeySolo(int $userId, string $bucket, int $level): string
    {
        return 'pun_hint:' . $userId . ':' . $bucket . ':' . $level;
    }

    private function levelProgressCacheKey(int $userId, string $normalizedMode): string
    {
        return 'pun:lp:' . $userId . ':' . $normalizedMode;
    }

    private function invalidateLevelProgressCache(int $userId): void
    {
        foreach (['beginner', 'intermediate', 'xhs'] as $m) {
            Cache::delete($this->levelProgressCacheKey($userId, $m));
        }
        Cache::delete('task_status_' . $userId);
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
    public function submitAnswer(int $userId, int $level, array $userAnswer, string $mode = 'beginner', string $questionBank = ''): array
    {
        $this->incrementDailyAnswerCount($userId);
        $mode = $this->normalizeMode($mode);
        if ($mode === 'intermediate') {
            $answersRaw = Config::get('pun_levels_issue2', []);
            $correct = isset($answersRaw[$level]) && is_array($answersRaw[$level]) ? $answersRaw[$level] : [];
        } elseif ($mode === 'battle') {
            // 对战模式：根据房间题库决定用哪个配置
            $configKey = in_array($questionBank, ['mid', 'intermediate'], true) ? 'pun_levels_issue2' : 'pun_levels_issue3';
            $answersRaw = Config::get($configKey, []);
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
            $this->removeSkipLevel($userId, $mode, $level, $mode === 'intermediate' ? $answersRaw : ($mode === 'xhs' || $mode === 'battle' ? $answersRaw : $answers));
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
        $before = $this->buildMidTierProgressState($userId, $answersRaw);
        // 计算跳关后的真实 currentLevel（与 getLevelProgress 逻辑一致）
        $skippedLevels = $this->getSkipLevels($userId, 'intermediate', $answersRaw);
        $passedSet  = array_fill_keys(array_map('intval', $before['passedLevels']), true);
        $skipSet    = array_fill_keys(array_map('intval', $skippedLevels), true);
        $effectiveCurrentLevel = null;
        foreach ($before['allKeys'] as $k) {
            $kid = (int) $k;
            if (!isset($passedSet[$kid]) && !isset($skipSet[$kid])) {
                $effectiveCurrentLevel = $kid;
                break;
            }
        }
        if ($effectiveCurrentLevel === null || $effectiveCurrentLevel !== $level) {
            return;
        }

        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s');
            $rank = PunGameRank::where('user_id', $userId)->find();
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

            $progress = Db::name('pun_game_level_progress')->where('user_id', $userId)->find();
            $passedLevels = $before['passedLevels'];
            if (!in_array($level, $passedLevels, true)) {
                $passedLevels[] = $level;
            }
            $passedLevels = $this->orderPassedLevelsByCatalog($passedLevels, $before['allKeys']);
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
            Db::commit();
            $this->invalidateLevelProgressCache($userId);
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
        $before = $this->buildXhsProgressState($userId, $answersRaw);
        // 小红书专辑允许回跳/跨关练习：只要题目存在且答对，就记录为已通过
        // （此前仅允许 currentLevel 记通过，导致非当前关如 1234 不写入 passed_levels_xhs）
        if (!isset($answersRaw[$level])) {
            return;
        }

        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s');
            $rank = PunGameRank::where('user_id', $userId)->find();
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
            $passedLevels = $before['passedLevels'];
            if (!in_array($level, $passedLevels, true)) {
                $passedLevels[] = $level;
            }
            $passedLevels = $this->orderPassedLevelsByCatalog($passedLevels, $before['allKeys']);
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
            Db::commit();
            $this->invalidateLevelProgressCache($userId);
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
            $this->invalidateLevelProgressCache($userId);
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
     * 关卡 ID 在配置有序列表中的下标（与 array_search 严格模式不同，兼容 int / 数字字符串键）
     * @param array<int|string, mixed> $orderedKeys
     * @return int 未找到返回 -1
     */
    private function indexOfLevelIdInOrderedKeys(array $orderedKeys, int $levelId): int
    {
        foreach ($orderedKeys as $i => $kid) {
            if ((int) $kid === $levelId) {
                return (int) $i;
            }
        }

        return -1;
    }

    /**
     * 按题库顺序重排 passedLevels，保证返回/存储顺序与关卡定义顺序一致
     *
     * @param int[] $passedLevels
     * @param array<int|string, mixed> $orderedKeys
     * @return int[]
     */
    private function orderPassedLevelsByCatalog(array $passedLevels, array $orderedKeys): array
    {
        if ($passedLevels === [] || $orderedKeys === []) {
            return [];
        }

        $set = [];
        foreach ($passedLevels as $lv) {
            $set[(int) $lv] = true;
        }

        $orderedPassed = [];
        foreach ($orderedKeys as $kid) {
            $levelId = (int) $kid;
            if (isset($set[$levelId])) {
                $orderedPassed[] = $levelId;
            }
        }

        return $orderedPassed;
    }

    /**
     * 中级：与 {@see getLevelProgress} 一致的已通过列表与当前关（有序前缀上的下一关）
     *
     * @param array<int|string, array> $answersRaw pun_levels_issue2
     * @param array<string, mixed>|null $progressRow pun_game_level_progress 行，null 时内部查询
     * @return array{allKeys: array, passedLevels: int[], currentLevel: ?int, totalLevels: int}
     */
    private function buildMidTierProgressState(int $userId, array $answersRaw, ?array $progressRow = null): array
    {
        $allKeys = array_keys($answersRaw);
        $totalLevels = count($allKeys);
        if ($progressRow === null) {
            $progressRow = Db::name('pun_game_level_progress')->where('user_id', $userId)->find();
        }

        $rawPassed = $this->normalizePassedLevels($progressRow ? $progressRow['passed_levels_mid'] : null);
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

        if ($passedLevels === []) {
            $rank = PunGameRank::where('user_id', $userId)->find();
            $storedMaxLevelId = $rank ? (int) $rank->max_level_mid : -1;
            $storedIdx = $this->indexOfLevelIdInOrderedKeys($allKeys, $storedMaxLevelId);
            if ($storedIdx >= 0) {
                $passedLevels = array_map('intval', array_slice($allKeys, 0, $storedIdx + 1));
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
            'allKeys'      => $allKeys,
            'passedLevels' => $passedLevels,
            'currentLevel' => $currentLevel,
            'totalLevels'  => $totalLevels,
        ];
    }

    /**
     * 小红书专辑：与 {@see getLevelProgress} 一致
     *
     * @param array<int|string, array> $answersRaw pun_levels_issue3
     * @param array<string, mixed>|null $progressRow
     * @return array{allKeys: array, passedLevels: int[], currentLevel: ?int, totalLevels: int}
     */
    private function buildXhsProgressState(int $userId, array $answersRaw, ?array $progressRow = null): array
    {
        $allKeys = array_keys($answersRaw);
        sort($allKeys, SORT_NUMERIC);
        $totalLevels = count($allKeys);
        if ($progressRow === null) {
            $progressRow = Db::name('pun_game_level_progress')->where('user_id', $userId)->find();
        }

        $rawPassed = $this->normalizePassedLevels($progressRow ? $progressRow['passed_levels_xhs'] : null);
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

        if ($passedLevels === []) {
            $rank = PunGameRank::where('user_id', $userId)->find();
            $storedMaxLevelId = $rank ? (int) ($rank->max_level_xhs ?? -1) : -1;
            $storedIdx = $this->indexOfLevelIdInOrderedKeys($allKeys, $storedMaxLevelId);
            if ($storedIdx >= 0) {
                $passedLevels = array_map('intval', array_slice($allKeys, 0, $storedIdx + 1));
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
            'allKeys'      => $allKeys,
            'passedLevels' => $passedLevels,
            'currentLevel' => $currentLevel,
            'totalLevels'  => $totalLevels,
        ];
    }

    /**
     * 获取任务状态（独立接口，不含关卡进度）
     * @param int $userId
     * @return array
     */
    public function getTaskStatus(int $userId): array
    {
        $cacheKey = 'task_status_' . $userId;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $this->getOrCreateHintAnswerQuota($userId);
        $hintRow = Db::name('pun_user_hint_quota')->where('user_id', $userId)->find();
        $hintAnswerQuota = $hintRow ? (int) $hintRow['quota'] : PunUserHintQuota::DEFAULT_QUOTA;
        $hintAnswerTotalUsed = $hintRow ? (int) ($hintRow['total_used'] ?? 0) : 0;
        $hintAnswerShareDailyClaimed = $this->getTodayRewardAddedCount($userId, self::REWARD_TYPE_SHARE);
        $dailyAnswerCount = $this->getDailyAnswerCount($userId);
        $dailyNoonTaskClaimed = $this->getTodayRewardAddedCount($userId, self::DAILY_TASKS['noon']['type']) > 0 ? 1 : 0;
        $dailyAdTaskCount = $this->getTodayRewardAddedCount($userId, self::DAILY_TASKS['ad']['type']);
        $dailyBattleCount = $this->getTodayBattleFinishedCount($userId);
        $dailyBattleTaskClaimed = $this->getTodayRewardAddedCount($userId, self::DAILY_TASKS['battle']['type']) > 0 ? 1 : 0;
        $avatarTaskClaimed = Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', self::PERMANENT_TASKS['avatar']['type'])
            ->where('status', 'success')
            ->count() > 0 ? 1 : 0;
        $nicknameTaskClaimed = Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', self::PERMANENT_TASKS['nickname']['type'])
            ->where('status', 'success')
            ->count() > 0 ? 1 : 0;
        $myMiniProgramTaskClaimed = Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', self::PERMANENT_TASKS['my_mini_program']['type'])
            ->where('status', 'success')
            ->count() > 0 ? 1 : 0;
        $rateTaskClaimed = Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', self::PERMANENT_TASKS['rate_app']['type'])
            ->where('status', 'success')
            ->count() > 0 ? 1 : 0;
        $dailyReminderSubscribed = UserSubscribe::where('user_id', $userId)
            ->where('template_id', self::DAILY_TASKS['noon']['template_id'])
            ->where('subscribe_status', 'accept')
            ->count() > 0 ? 1 : 0;

        $result = [
            'hintAnswerQuota'  => $hintAnswerQuota,
            'hintAnswerTotalUsed' => $hintAnswerTotalUsed,
            'hintAnswerShareDailyMax' => self::SHARE_REWARD_DAILY_MAX,
            'hintAnswerShareDailyClaimed' => $hintAnswerShareDailyClaimed,
            'dailyAnswerCount' => $dailyAnswerCount,
            'dailyAnswerRequired' => self::DAILY_TASKS['noon']['min_count'],
            'dailyNoonTaskClaimed' => $dailyNoonTaskClaimed,
            'dailyAdTaskCount' => $dailyAdTaskCount,
            'dailyBattleCount' => $dailyBattleCount,
            'dailyBattleRequired' => self::DAILY_TASKS['battle']['min_count'],
            'dailyBattleTaskClaimed' => $dailyBattleTaskClaimed,
            'avatarTaskClaimed' => $avatarTaskClaimed,
            'nicknameTaskClaimed' => $nicknameTaskClaimed,
            'myMiniProgramTaskClaimed' => $myMiniProgramTaskClaimed,
            'rateTaskClaimed' => $rateTaskClaimed,
            'dailyReminderSubscribed' => $dailyReminderSubscribed,
        ];

        Cache::set($cacheKey, $result, 60);
        return $result;
    }

    /**
     * 当前用户关卡进度：当前可玩关卡、已通过关卡列表、总关卡数 
     * @param int $userId
     * @param string $mode beginner=初级 | intermediate=中级
     * @return array {currentLevel, passedLevels, totalLevels, hintAnswerQuota, hintAnswerTotalUsed}
     */
    public function getLevelProgress(int $userId, string $mode = 'beginner'): array
    {
        $mode = $this->normalizeMode($mode);
        $cacheKey = $this->levelProgressCacheKey($userId, $mode);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        // 仅取提示次数，任务状态走独立接口 getTaskStatus
        $this->getOrCreateHintAnswerQuota($userId);
        $hintRow = Db::name('pun_user_hint_quota')->where('user_id', $userId)->find();
        $common = [
            'hintAnswerQuota'    => $hintRow ? (int) $hintRow['quota'] : PunUserHintQuota::DEFAULT_QUOTA,
            'hintAnswerTotalUsed'=> $hintRow ? (int) ($hintRow['total_used'] ?? 0) : 0,
        ];
        $progress = Db::name('pun_game_level_progress')->where('user_id', $userId)->find();

        if ($mode === 'intermediate') {
            $answersRaw = Config::get('pun_levels_issue2', []);
            $state = $this->buildMidTierProgressState($userId, $answersRaw, $progress);
            $skippedLevels = $this->getSkipLevels($userId, $mode, $answersRaw);
            $passedSet = array_fill_keys(array_map('intval', $state['passedLevels']), true);
            $skipSet = array_fill_keys(array_map('intval', $skippedLevels), true);
            $currentLevel = null;
            foreach ($state['allKeys'] as $k) {
                $kid = (int) $k;
                if (!isset($passedSet[$kid]) && !isset($skipSet[$kid])) {
                    $currentLevel = $kid;
                    break;
                }
            }

            $result = array_merge($common, [
                'currentLevel'     => $currentLevel,
                'passedLevels'     => array_map('intval', $state['passedLevels']),
                'skippedLevels'    => array_map('intval', $skippedLevels),
                'totalLevels'      => $state['totalLevels'],
            ]);
            Cache::set($cacheKey, $result, 60);
            return $result;
        }
        if ($mode === 'xhs') {
            $answersRaw = Config::get('pun_levels_issue3', []);
            $state = $this->buildXhsProgressState($userId, $answersRaw, $progress);
            $skippedLevels = $this->getSkipLevels($userId, $mode, $answersRaw);
            $passedSet = array_fill_keys(array_map('intval', $state['passedLevels']), true);
            $skipSet = array_fill_keys(array_map('intval', $skippedLevels), true);
            $currentLevel = null;
            foreach ($state['allKeys'] as $k) {
                $kid = (int) $k;
                if (!isset($passedSet[$kid]) && !isset($skipSet[$kid])) {
                    $currentLevel = $kid;
                    break;
                }
            }

            $result = array_merge($common, [
                'currentLevel'     => $currentLevel,
                'passedLevels'     => array_map('intval', $state['passedLevels']),
                'skippedLevels'    => array_map('intval', $skippedLevels),
                'totalLevels'      => $state['totalLevels'],
            ]);
            Cache::set($cacheKey, $result, 60);
            return $result;
        } else {
            $answersRaw = Config::get('pun_levels', []);
            $allKeys = array_keys($answersRaw);
            sort($allKeys);
            // 初级（beginner）题号约定 ≥1，排除配置里误带的 0 等非法键，避免 currentLevel=0 导致前端拉 issue/0.json
            $allKeys = array_values(array_filter($allKeys, static function ($k) use ($answersRaw) {
                $id = (int) $k;
                return $id >= 1 && isset($answersRaw[$k]) && is_array($answersRaw[$k]);
            }));
            $totalLevels = count($allKeys);
            $lastLevel = $totalLevels > 0 ? (int) $allKeys[$totalLevels - 1] : -1;
            $passedLevels = $this->normalizePassedLevels($progress ? $progress['passed_levels'] : null);
            $skippedLevels = $this->getSkipLevels($userId, $mode, $answersRaw);
            // 确保进度里只保留配置中确实存在的题目ID
            $passedLevels = array_values(array_filter($passedLevels, fn($n) => $n >= 1 && isset($answersRaw[$n])));
            $skipSet = array_fill_keys(array_map('intval', $skippedLevels), true);

            $maxPassed = empty($passedLevels) ? 0 : max($passedLevels);
            $currentLevel = null;
            foreach ($allKeys as $k) {
                $kid = (int) $k;
                if (!in_array($kid, $passedLevels, true) && !isset($skipSet[$kid])) {
                    $currentLevel = $kid;
                    break;
                }
            }
            if ($currentLevel === null && $lastLevel >= 1 && $maxPassed >= $lastLevel && in_array($lastLevel, $passedLevels, true)) {
                $currentLevel = $maxPassed; // 初级原逻辑：全部通关后停留在最后一关
            }
            if ($currentLevel === null) {
                $currentLevel = $totalLevels > 0 ? (int) $allKeys[0] : 1;
            }

            $result = array_merge($common, [
                'currentLevel'     => $currentLevel,
                'passedLevels'     => $passedLevels,
                'skippedLevels'    => array_map('intval', $skippedLevels),
                'totalLevels'      => $totalLevels,
            ]);
            Cache::set($cacheKey, $result, 60);
            return $result;
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
        try {
            FeishuBotHelper::notifyFeedbackSubmitted($userId, $type, $content, $contact);
        } catch (\Throwable $ignored) {}
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
        $cacheKey = 'home_progress_stats';
        $cached = Cache::get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

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

        $result = [
            'players' => $players,
            'answers' => $answers,
        ];
        // 缓存 5 分钟，避免全表 JSON 聚合查询频繁执行
        Cache::set($cacheKey, $result, 300);

        return $result;
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
