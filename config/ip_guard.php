<?php
// +----------------------------------------------------------------------
// | IP 黑名单与访问审计（由 app\middleware\AccessLogAndIpBlacklist 使用）
// +----------------------------------------------------------------------

return [
    // 是否启用黑名单拦截（关闭后仅记录日志，不拦截）
    'blacklist_enabled' => true,

    /**
     * 黑名单 IP 列表（精确匹配，区分 IPv4/IPv6 写法）。
     * 支持「前缀 + *」规则，仅对 IPv4 简单前缀匹配，例如：192.168.1.*
     */
    'blacklist' => [
        // '1.2.3.4',
        // '192.168.0.*',
    ],

    // 是否记录访问日志
    'access_log_enabled' => true,

    // 日志通道名（需在 config/log.php 的 channels 中配置）
    'access_log_channel' => 'request_audit',

    // GET/POST 参数写入日志时的最大 JSON 长度（避免日志过大）
    'max_param_json_length' => 2000,
];
