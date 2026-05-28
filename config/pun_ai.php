<?php

/**
 * 关卡 AI 趣味解读（答对时 submit 实时调用）
 */
return [
    'explain_enabled' => (bool) env('PUN_EXPLAIN_AI_ENABLED', false),
    'explain_api_url' => env('PUN_EXPLAIN_AI_URL', ''),
    'explain_api_key' => env('PUN_EXPLAIN_AI_KEY', ''),
    'explain_model'   => env('PUN_EXPLAIN_AI_MODEL', 'deepseek-v3.2'),
    'explain_fallback' => env('PUN_EXPLAIN_FALLBACK', '请点击进入下一关吧~'),
    'issue_base'      => env('PUN_ISSUE_JSON_BASE', 'https://sofun.online/static/punGame'),
];
