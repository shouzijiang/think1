<?php

namespace app\service;

use app\model\PunchRecord;
use think\facade\Db;

/**
 * 打卡服务类
 */
class PunchService
{
    /**
     * 提交打卡记录
     * @param int $userId
     * @param int|null $timestamp 时间戳（毫秒）
     * @return array
     */
    public function submit(int $userId, ?int $timestamp = null): array
    {
        if ($timestamp === null) {
            $timestamp = time() * 1000;
        }
        
        // 保存打卡记录
        $record = PunchRecord::create([
            'user_id' => $userId,
            'timestamp' => $timestamp,
        ]);
        
        // 计算统计数据
        $stats = $this->getStatistics($userId);
        
        return [
            'record_id' => $record->id,
            'timestamp' => $timestamp,
            'today_count' => $stats['today_count'],
            'total_count' => $stats['total_count'],
            'streak_days' => $stats['streak_days'],
        ];
    }
    
    /**
     * 获取打卡记录列表
     * @param int $userId
     * @param int $page
     * @param int $pageSize
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getRecords(int $userId, int $page = 1, int $pageSize = 20, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = PunchRecord::where('user_id', $userId);
        
        // 日期筛选
        if ($startDate) {
            $startTimestamp = strtotime($startDate) * 1000;
            $query->where('timestamp', '>=', $startTimestamp);
        }
        if ($endDate) {
            $endTimestamp = (strtotime($endDate) + 86400) * 1000 - 1;
            $query->where('timestamp', '<=', $endTimestamp);
        }
        $total = $query->count();
        $list = $query->order('timestamp', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->map(function ($record) {
                $timestamp = (int)($record->timestamp / 1000);
                $date = date('Y-m-d', $timestamp);
                $time = date('H:i', $timestamp);
                return [
                    'record_id' => $record->id,
                    'timestamp' => $record->timestamp,
                    'date' => $date,
                    'time' => $time,
                ];
            })
            ->toArray();
        
        return [
            'list' => $list,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => ceil($total / $pageSize),
            ]
        ];
    }
    
    /**
     * 获取打卡统计数据
     * @param int $userId
     * @param string|null $date 查询日期，格式：Y-m-d，不传则查询今天
     * @return array
     */
    public function getStatistics(int $userId, ?string $date = null): array
    {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        $dateTimestamp = strtotime($date) * 1000;
        $nextDateTimestamp = (strtotime($date) + 86400) * 1000;
        
        // 今日打卡次数
        $todayCount = PunchRecord::where('user_id', $userId)
            ->where('timestamp', '>=', $dateTimestamp)
            ->where('timestamp', '<', $nextDateTimestamp)
            ->count();
        
        // 总打卡次数
        $totalCount = PunchRecord::where('user_id', $userId)->count();
        
        // 连续打卡天数
        $streakDays = $this->calculateStreakDays($userId);
        
        // 本周打卡次数
        $weekStart = strtotime('monday this week') * 1000;
        $weekEnd = (strtotime('monday next week')) * 1000;
        $weekCount = PunchRecord::where('user_id', $userId)
            ->where('timestamp', '>=', $weekStart)
            ->where('timestamp', '<', $weekEnd)
            ->count();
        // 本月打卡次数
        $monthStart = strtotime(date('Y-m-01')) * 1000;
        $monthEnd = strtotime(date('Y-m-t 23:59:59')) * 1000 + 999;
        $monthCount = PunchRecord::where('user_id', $userId)
            ->where('timestamp', '>=', $monthStart)
            ->where('timestamp', '<=', $monthEnd)
            ->count();
        
        return [
            'today_count' => (int)$todayCount,
            'total_count' => (int)$totalCount,
            'streak_days' => $streakDays,
            'week_count' => (int)$weekCount,
            'month_count' => (int)$monthCount,
        ];
    }
    
    /**
     * 计算连续打卡天数
     * @param int $userId
     * @return int
     */
    private function calculateStreakDays(int $userId): int
    {
        // 获取所有打卡日期（去重）
        $dates = Db::query("
            SELECT DATE(FROM_UNIXTIME(timestamp/1000)) as date 
            FROM punch_records 
            WHERE user_id = ? 
            GROUP BY DATE(FROM_UNIXTIME(timestamp/1000))
            ORDER BY date DESC
        ", [$userId]);
        $dates = array_column($dates, 'date');
        
        if (empty($dates)) {
            return 0;
        }
        
        $streak = 0;
        $today = date('Y-m-d');
        $checkDate = $today;
        
        foreach ($dates as $date) {
            if ($date === $checkDate) {
                $streak++;
                $checkDate = date('Y-m-d', strtotime($checkDate . ' -1 day'));
            } else {
                break;
            }
        }
        
        return $streak;
    }
}

