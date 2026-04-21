<?php

declare(strict_types=1);

namespace app\middleware;

use app\common\ResponseHelper;
use think\facade\Config;
use think\facade\Log;
use think\Request;
use think\Response;

/**
 * 全局：IP 黑名单拦截 + 访问审计日志（IP、UA、路径、耗时等）
 */
class AccessLogAndIpBlacklist
{
    public function handle(Request $request, \Closure $next): Response
    {
        $cfg = Config::get('ip_guard', []);
        $clientIp = $this->resolveClientIp($request);
        $request->real_client_ip = $clientIp;

        if (!empty($cfg['blacklist_enabled']) && $this->isBlacklisted($clientIp, (array) ($cfg['blacklist'] ?? []))) {
            // 拦截命中始终写一条日志，便于审计（与 access_log_enabled 无关）
            $this->writeAccessLog($request, $cfg, $clientIp, null, 403, microtime(true), true);
            return ResponseHelper::forbidden('访问被拒绝');
        }

        $t0 = microtime(true);
        $response = $next($request);
        $status = method_exists($response, 'getCode') ? (int) $response->getCode() : 200;

        if (!empty($cfg['access_log_enabled'])) {
            $this->writeAccessLog($request, $cfg, $clientIp, $response, $status, $t0, false);
        }

        return $response;
    }

    private function resolveClientIp(Request $request): string
    {
        // ThinkPHP 会结合代理配置解析；无配置时退回 REMOTE_ADDR
        if (method_exists($request, 'ip')) {
            $ip = (string) $request->ip();
            if ($ip !== '') {
                return $ip;
            }
        }
        return (string) ($request->server('REMOTE_ADDR') ?? '');
    }

    private function isBlacklisted(string $ip, array $blacklist): bool
    {
        if ($ip === '') {
            return false;
        }
        foreach ($blacklist as $rule) {
            $rule = trim((string) $rule);
            if ($rule === '') {
                continue;
            }
            if ($rule === $ip) {
                return true;
            }
            if (str_ends_with($rule, '*')) {
                $prefix = substr($rule, 0, -1);
                if ($prefix !== '' && str_starts_with($ip, $prefix)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function writeAccessLog(
        Request $request,
        array $cfg,
        string $clientIp,
        ?Response $response,
        int $httpStatus,
        float $t0,
        bool $blocked
    ): void {
        $maxParam = (int) ($cfg['max_param_json_length'] ?? 2000);
        $channel = (string) ($cfg['access_log_channel'] ?? 'request_audit');

        // 仅记录已解析的 GET/POST，避免再读 raw body（部分环境下 php://input 仅可读一次）
        $params = array_merge($request->get(), $request->post());
        $paramJson = json_encode($params, JSON_UNESCAPED_UNICODE);
        if ($paramJson !== false && strlen($paramJson) > $maxParam) {
            $paramJson = substr($paramJson, 0, $maxParam) . '…(truncated)';
        }

        $line = json_encode([
            'ts' => date('Y-m-d H:i:s'),
            'blocked' => $blocked,
            'ip' => $clientIp,
            'xff' => $request->header('x-forwarded-for', ''),
            'xri' => $request->header('x-real-ip', ''),
            'method' => $request->method(),
            'path' => $request->pathinfo(),
            'url' => $request->url(true),
            'query_string' => (string) $request->server('QUERY_STRING', ''),
            'ua' => $request->header('user-agent', ''),
            'referer' => $request->header('referer', ''),
            'status' => $httpStatus,
            'ms' => round((microtime(true) - $t0) * 1000, 2),
            'has_auth' => $request->header('authorization', '') !== '',
            'user_id' => $request->user_id ?? null,
            'params' => $paramJson === false ? '' : $paramJson,
        ], JSON_UNESCAPED_UNICODE);

        if ($line === false) {
            $line = '{"error":"json_encode_failed"}';
        }

        try {
            Log::channel($channel)->info($line);
        } catch (\Throwable) {
            Log::info('[request_audit_fallback] ' . $line);
        }
    }
}
