<?php

namespace app\service;

use app\model\PunGameMail;
use app\model\PunGameMailRead;
use InvalidArgumentException;
use think\facade\Db;

/**
 * 谐音梗图游戏 - 邮件业务
 */
class PunMailService
{
    private const SCOPE_ALL = 'all';
    private const SCOPE_USER = 'user';

    /**
     * 获取当前用户可见邮件列表
     *
     * 返回字段约定：
     * - id, title, contentPreview, scope, targetUserId, senderUserId, createdAt
     * - isRead, readAt
     *
     * @return array{list: array<int, array>, total: int}
     */
    public function listMails(int $userId, int $page = 1, int $pageSize = 20): array
    {
        $page = max(1, (int) $page);
        $pageSize = min(max(1, (int) $pageSize), 50);

        $baseQuery = Db::name('pun_game_mail')
            ->where('is_published', 1)
            ->where(function ($q) use ($userId) {
                $q->where('scope', self::SCOPE_ALL)
                    ->whereOr('target_user_id', $userId);
            })
            ->order('id', 'desc');

        $total = (clone $baseQuery)->count();

        $rows = (clone $baseQuery)->page($page, $pageSize)->select();
        $mailIds = [];
        foreach ($rows as $row) {
            $mailIds[] = (int) ($row['id'] ?? 0);
        }

        $readMap = [];
        if ($mailIds !== []) {
            $reads = Db::name('pun_game_mail_reads')
                ->where('user_id', $userId)
                ->whereIn('mail_id', $mailIds)
                ->select();
            foreach ($reads as $read) {
                $mid = (int) ($read['mail_id'] ?? 0);
                if ($mid > 0) {
                    $readMap[$mid] = $read['read_at'] ?? null;
                }
            }
        }

        $list = [];
        foreach ($rows as $row) {
            $content = (string) ($row['content'] ?? '');
            $preview = '';
            if ($content !== '') {
                $preview = mb_substr($content, 0, 80, 'UTF-8');
                if (mb_strlen($content, 'UTF-8') > 80) {
                    $preview .= '...';
                }
            }

            $mid = (int) ($row['id'] ?? 0);
            $readAt = $readMap[$mid] ?? null;

            $list[] = [
                'id' => $mid,
                'title' => (string) ($row['title'] ?? ''),
                'contentPreview' => $preview,
                'scope' => (string) ($row['scope'] ?? ''),
                'targetUserId' => isset($row['target_user_id']) ? (int) $row['target_user_id'] : null,
                'senderUserId' => isset($row['sender_user_id']) ? (int) $row['sender_user_id'] : 0,
                'createdAt' => !empty($row['created_at']) ? date('Y-m-d H:i', strtotime((string) $row['created_at'])) : '',
                'isRead' => $readAt !== null && $readAt !== '',
                'readAt' => !empty($readAt) ? date('Y-m-d H:i', strtotime((string) $readAt)) : null,
            ];
        }

        return [
            'list' => $list,
            'total' => (int) $total,
        ];
    }

    /**
     * 获取单条邮件详情（并标记已读）
     *
     * @return array<string, mixed>
     */
    public function getMailDetail(int $userId, int $mailId): array
    {
        $mailId = (int) $mailId;
        if ($mailId <= 0) {
            throw new InvalidArgumentException('参数错误：邮件ID');
        }

        $mail = PunGameMail::where('id', $mailId)
            ->where('is_published', 1)
            ->find();

        if (!$mail) {
            throw new InvalidArgumentException('邮件不存在');
        }

        $scope = (string) ($mail->scope ?? '');
        $targetUserId = (int) ($mail->target_user_id ?? 0);
        if ($scope === self::SCOPE_USER && $targetUserId !== $userId) {
            throw new InvalidArgumentException('没有权限查看该邮件');
        }

        // 标记已读：幂等写入
        $readRow = PunGameMailRead::where('mail_id', $mailId)
            ->where('user_id', $userId)
            ->find();

        if (!$readRow) {
            PunGameMailRead::create([
                'mail_id' => $mailId,
                'user_id' => $userId,
                // read_at 由模型/数据库默认提供
            ]);
            $readRow = PunGameMailRead::where('mail_id', $mailId)
                ->where('user_id', $userId)
                ->find();
        }

        $readAt = $readRow ? ($readRow->read_at ?? null) : null;

        return [
            'id' => $mailId,
            'title' => (string) ($mail->title ?? ''),
            'content' => (string) ($mail->content ?? ''),
            'scope' => $scope,
            'targetUserId' => $targetUserId > 0 ? $targetUserId : null,
            'senderUserId' => (int) ($mail->sender_user_id ?? 0),
            'createdAt' => !empty($mail->created_at) ? date('Y-m-d H:i', strtotime((string) $mail->created_at)) : '',
            'isRead' => true,
            'readAt' => !empty($readAt) ? date('Y-m-d H:i', strtotime((string) $readAt)) : null,
        ];
    }
}

