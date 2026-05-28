<?php

/**
 * 关卡 AI 趣味解读（答对时 submit 实时调用）
 *
 * CloudBase 对齐官方 Node 示例：
 *   ai.createModel("hunyuan-v3") + model.streamText({ model: "hy3-preview", ... })
 * PHP 侧走 OpenAI 兼容 HTTP（CloudBase OpenAPI ai_model）：
 *   {gateway}/v1/ai/{provider}/chat/completions
 */
return [
    'explain_enabled'  => (bool) env('PUN_EXPLAIN_AI_ENABLED', false),
    /** 网关根域名或完整 chat/completions URL */
    'explain_api_url'  => env('PUN_EXPLAIN_AI_URL', ''),
    'explain_api_key'  => env('PUN_EXPLAIN_AI_KEY', ''),
    /** 对应 createModel 的 provider，如 hunyuan-v3 / cloudbase */
    'explain_provider' => env('PUN_EXPLAIN_AI_PROVIDER', 'hunyuan-v3'),
    /** 对应 streamText 里的 model 字段，如 hy3-preview */
    'explain_model'    => env('PUN_EXPLAIN_AI_MODEL', 'hy3-preview'),
    'explain_fallback' => env('PUN_EXPLAIN_FALLBACK', '请点击进入下一关吧~'),
    'issue_base'       => env('PUN_ISSUE_JSON_BASE', 'https://sofun.online/static/punGame'),
];
