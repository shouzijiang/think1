<?php

namespace app\controller;

use app\BaseController;
use app\common\ResponseHelper;
use app\service\CocreateService;
use think\Request;

/**
 * 谐音梗图游戏 - 共创模块接口
 */
class Cocreate extends BaseController
{
    protected $cocreateService;

    protected function initialize()
    {
        parent::initialize();
        $this->cocreateService = new CocreateService();
    }

    /**
     * 生成选词 POST /pun/cocreate/words/generate
     * Body: { "answer": "车水马龙" }
     */
    public function wordsGenerate(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        $answer = $request->post('answer', '');
        try {
            $result = $this->cocreateService->generateWords($answer);
            return ResponseHelper::success($result);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \think\facade\Log::error('cocreate/words/generate 异常: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * 生成图片（提示图/答案图）POST /pun/cocreate/image/generate
     * Body: { "prompt": "...", "type": "hint"|"answer" }
     */
    public function imageGenerate(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        $prompt = $request->post('prompt', '');
        $type = $request->post('type', '');
        try {
            $result = $this->cocreateService->generateImage($prompt, $type);
            return ResponseHelper::success($result);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \think\facade\Log::error('cocreate/image/generate 异常: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * 提交共创关卡 POST /pun/cocreate/submit
     */
    public function submit(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        $data = [
            'answer'            => $request->post('answer', ''),
            'answerLength'      => (int) $request->post('answerLength', 0),
            'hintImagePrompt'   => $request->post('hintImagePrompt', ''),
            'answerExplanation' => $request->post('answerExplanation', ''),
            'wordArray'         => $request->post('wordArray', []),
            'hintImageUrl'      => $request->post('hintImageUrl', ''),
            'answerImageUrl'    => $request->post('answerImageUrl', ''),
        ];
        if (!is_array($data['wordArray'])) {
            $data['wordArray'] = [];
        }
        try {
            $id = $this->cocreateService->submit($userId, $data);
            return ResponseHelper::success(['id' => $id]);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \think\facade\Log::error('cocreate/submit 异常: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * 共创列表 GET /pun/cocreate/list?page=1&page_size=20
     */
    public function list(Request $request)
    {
        $page = (int) $request->get('page', 1);
        $pageSize = (int) $request->get('page_size', 20);
        $result = $this->cocreateService->list($page, $pageSize);
        return ResponseHelper::success($result);
    }

    /**
     * 共创详情 GET /pun/cocreate/detail?id=123
     */
    public function detail(Request $request)
    {
        $id = (int) $request->get('id', 0);
        if ($id < 1) {
            return ResponseHelper::badRequest('id 无效');
        }
        $data = $this->cocreateService->detail($id);
        if ($data === null) {
            return ResponseHelper::error('关卡不存在或未通过', 404);
        }
        return ResponseHelper::success($data);
    }

    /**
     * 提交共创题目答案 POST /pun/cocreate/answer/submit
     * Body: { "cocreateId": 123, "userAnswer": ["车","水","马","龙"] }
     */
    public function answerSubmit(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        $cocreateId = (int) $request->post('cocreateId', 0);
        $userAnswer = $request->post('userAnswer', []);
        if (!is_array($userAnswer)) {
            $userAnswer = [];
        }
        if ($cocreateId < 1) {
            return ResponseHelper::badRequest('cocreateId 无效');
        }
        try {
            $result = $this->cocreateService->submitAnswer($cocreateId, $userAnswer);
            return ResponseHelper::success($result);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \think\facade\Log::error('cocreate/answer/submit 异常: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
