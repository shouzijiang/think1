<?php

namespace app\controller;

use app\BaseController;
use app\common\ResponseHelper;
use app\service\PunMailService;
use InvalidArgumentException;
use think\facade\Log;
use think\Request;

/**
 * 谐音梗图游戏 - 邮件（站内信）
 */
class PunMail extends BaseController
{
    protected PunMailService $mailService;

    protected function initialize()
    {
        parent::initialize();
        $this->mailService = new PunMailService();
    }

    /**
     * 信箱列表 GET /pun/mail/list
     */
    public function mailList(Request $request)
    {
        $userId = (int) ($request->user_id ?? 0);
        if ($userId <= 0) {
            return ResponseHelper::unauthorized();
        }

        $page = (int) $request->get('page', 1);
        $pageSize = (int) $request->get('page_size', 20);

        $result = $this->mailService->listMails($userId, $page, $pageSize);
        return ResponseHelper::success($result);
    }

    /**
     * 邮件详情 GET /pun/mail/detail?id=xxx
     */
    public function mailDetail(Request $request)
    {
        $userId = (int) ($request->user_id ?? 0);
        if ($userId <= 0) {
            return ResponseHelper::unauthorized();
        }

        $id = (int) $request->get('id', 0);
        if ($id <= 0) {
            return ResponseHelper::badRequest('参数错误：id');
        }

        try {
            $result = $this->mailService->getMailDetail($userId, $id);
            return ResponseHelper::success($result);
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::badRequest($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('pun/mail/detail 异常：' . $e->getMessage());
            return ResponseHelper::error('获取邮件失败', 500);
        }
    }
}
