<?php

namespace app\controller;

use app\common\ResponseHelper;
use app\service\CosService;
use think\facade\Db;
use think\facade\Log;
use think\Request;

/**
 * 文件上传控制器
 */
class Upload
{
    private CosService $cosService;

    public function __construct()
    {
        $this->cosService = new CosService();
    }

    /**
     * 上传用户头像
     * POST /upload/avatar
     * multipart/form-data: file=<图片文件>
     */
    public function avatar(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }

        $file = $request->file('file');
        if (!$file) {
            return ResponseHelper::badRequest('请上传文件');
        }

        try {
            $url = $this->cosService->uploadAvatar(
                $file->getPathname(),
                $file->getOriginalName(),
                (int) $userId
            );
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::badRequest($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('upload/avatar 异常: ' . $e->getMessage());
            return ResponseHelper::error('上传失败', 500);
        }

        // 将 COS URL 落库到 users.avatar
        try {
            Db::name('users')->where('id', $userId)->update(['avatar' => $url]);
        } catch (\Throwable $e) {
            Log::error('upload/avatar 落库失败 user_id=' . $userId . ' err=' . $e->getMessage());
            // 上传已成功，落库失败不阻断，返回 URL 让前端持有
        }

        return ResponseHelper::success(['url' => $url], '上传成功');
    }
}
