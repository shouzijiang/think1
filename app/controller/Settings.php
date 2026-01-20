<?php

namespace app\controller;

use app\BaseController;
use app\common\ResponseHelper;
use app\service\SettingsService;
use think\Request;

/**
 * 用户设置控制器
 */
class Settings extends BaseController
{
    protected $settingsService;
    
    protected function initialize()
    {
        parent::initialize();
        $this->settingsService = new SettingsService();
    }
    
    /**
     * 保存用户设置
     */
    public function save(Request $request)
    {
        $userId = $request->user_id ?? 0;
        
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        
        $data = [
            'enabled' => $request->post('enabled'),
            'work_start_time' => $request->post('work_start_time', ''),
            'work_end_time' => $request->post('work_end_time', ''),
            'remind_interval' => $request->post('remind_interval'),
        ];
        
        // 验证必填参数
        if (!isset($data['enabled'])) {
            return ResponseHelper::badRequest('参数错误：enabled不能为空');
        }
        if (empty($data['work_start_time'])) {
            return ResponseHelper::badRequest('参数错误：work_start_time不能为空');
        }
        if (empty($data['work_end_time'])) {
            return ResponseHelper::badRequest('参数错误：work_end_time不能为空');
        }
        if (!isset($data['remind_interval'])) {
            return ResponseHelper::badRequest('参数错误：remind_interval不能为空');
        }
        
        // 验证时间格式
        if (!preg_match('/^\d{2}:\d{2}$/', $data['work_start_time'])) {
            return ResponseHelper::badRequest('参数错误：work_start_time格式不正确，应为HH:mm');
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $data['work_end_time'])) {
            return ResponseHelper::badRequest('参数错误：work_end_time格式不正确，应为HH:mm');
        }
        
        // 验证提醒间隔
        $remindInterval = (int)$data['remind_interval'];
        if ($remindInterval < 1 || $remindInterval > 6) {
            return ResponseHelper::badRequest('参数错误：remind_interval应在1-6之间');
        }
        
        $result = $this->settingsService->save($userId, $data);
        
        if (!$result) {
            return ResponseHelper::error('保存失败', 500);
        }
        
        return ResponseHelper::success($result, '保存成功');
    }
    
    /**
     * 获取用户设置
     */
    public function get(Request $request)
    {
        $userId = $request->user_id ?? 0;
        
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        
        $result = $this->settingsService->get($userId);
        
        return ResponseHelper::success($result);
    }
}

