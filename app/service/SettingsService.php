<?php

namespace app\service;

use app\model\UserSetting;

/**
 * 用户设置服务类
 */
class SettingsService
{
    /**
     * 保存用户设置
     * @param int $userId
     * @param array $data
     * @return array|false
     */
    public function save(int $userId, array $data)
    {
        $setting = UserSetting::where('user_id', $userId)->find();
        
        if (!$setting) {
            $setting = UserSetting::create([
                'user_id' => $userId,
                'enabled' => $data['enabled'] ?? 1,
                'work_start_time' => $data['work_start_time'] ?? '09:00',
                'work_end_time' => $data['work_end_time'] ?? '18:00',
                'remind_interval' => $data['remind_interval'] ?? 2,
            ]);
        } else {
            $setting->enabled = $data['enabled'] ?? $setting->enabled;
            $setting->work_start_time = $data['work_start_time'] ?? $setting->work_start_time;
            $setting->work_end_time = $data['work_end_time'] ?? $setting->work_end_time;
            $setting->remind_interval = $data['remind_interval'] ?? $setting->remind_interval;
            $setting->save();
        }
        
        return [
            'settings_id' => $setting->id,
            'enabled' => $setting->enabled,
            'work_start_time' => $setting->work_start_time,
            'work_end_time' => $setting->work_end_time,
            'remind_interval' => $setting->remind_interval,
            'updated_at' => $setting->updated_at,
        ];
    }
    
    /**
     * 获取用户设置
     * @param int $userId
     * @return array|null
     */
    public function get(int $userId): ?array
    {
        $setting = UserSetting::where('user_id', $userId)->find();
        
        if (!$setting) {
            // 返回默认设置
            return [
                'enabled' => 1,
                'work_start_time' => '09:00',
                'work_end_time' => '18:00',
                'remind_interval' => 2,
            ];
        }
        
        return [
            'enabled' => $setting->enabled,
            'work_start_time' => $setting->work_start_time,
            'work_end_time' => $setting->work_end_time,
            'remind_interval' => $setting->remind_interval,
        ];
    }
}

