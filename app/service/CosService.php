<?php

namespace app\service;

use Qcloud\Cos\Client;
use think\facade\Config;
use think\facade\Log;

/**
 * 腾讯云 COS 上传服务
 */
class CosService
{
    private Client $client;
    private string $bucket;
    private string $region;
    private string $cdnDomain;
    private int $maxSize;
    private array $allowMime;
    private string $avatarPrefix;

    public function __construct()
    {
        $cfg = Config::get('cos');

        $this->bucket      = (string) ($cfg['bucket'] ?? '');
        $this->region      = (string) ($cfg['region'] ?? 'ap-guangzhou');
        $this->cdnDomain   = rtrim((string) ($cfg['cdn_domain'] ?? ''), '/');
        $this->maxSize     = (int) ($cfg['max_size'] ?? 5 * 1024 * 1024);
        $this->allowMime   = (array) ($cfg['allow_mime'] ?? ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        $this->avatarPrefix = (string) ($cfg['avatar_prefix'] ?? 'avatars/');

        $this->client = new Client([
            'region'      => $this->region,
            'credentials' => [
                'secretId'  => (string) ($cfg['secret_id'] ?? ''),
                'secretKey' => (string) ($cfg['secret_key'] ?? ''),
            ],
        ]);
    }

    /**
     * 上传头像文件到 COS
     *
     * @param string $localPath 本地临时文件路径
     * @param string $originalName 原始文件名（用于判断扩展名）
     * @param int $userId
     * @return string COS 文件 URL
     * @throws \InvalidArgumentException 文件校验失败
     * @throws \RuntimeException 上传失败
     */
    public function uploadAvatar(string $localPath, string $originalName, int $userId): string
    {
        // 文件大小校验
        $size = filesize($localPath);
        if ($size === false || $size > $this->maxSize) {
            throw new \InvalidArgumentException('文件大小超出限制（最大 ' . round($this->maxSize / 1024 / 1024, 1) . ' MB）');
        }

        // MIME 校验（读取文件头，防止伪造扩展名）
        $mime = mime_content_type($localPath);
        if (!in_array($mime, $this->allowMime, true)) {
            throw new \InvalidArgumentException('不支持的文件类型：' . $mime);
        }

        // 生成唯一 COS key
        $ext = $this->mimeToExt($mime);
        $key = $this->avatarPrefix . $userId . '/' . date('Ymd') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;

        try {
            $this->client->putObject([
                'Bucket'     => $this->bucket,
                'Key'        => $key,
                'Body'       => fopen($localPath, 'rb'),
                'ContentType' => $mime,
            ]);
        } catch (\Throwable $e) {
            Log::error('COS 上传失败 key=' . $key . ' err=' . $e->getMessage());
            throw new \RuntimeException('文件上传失败，请稍后重试');
        }

        return $this->buildUrl($key);
    }

    /**
     * 根据 COS key 拼装访问 URL
     */
    private function buildUrl(string $key): string
    {
        if ($this->cdnDomain !== '') {
            return $this->cdnDomain . '/' . ltrim($key, '/');
        }
        // 使用 COS 默认域名
        return 'https://' . $this->bucket . '.cos.' . $this->region . '.myqcloud.com/' . ltrim($key, '/');
    }

    /**
     * MIME → 扩展名
     */
    private function mimeToExt(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
    }
}
