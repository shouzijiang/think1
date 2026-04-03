<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use app\common\FeishuBotHelper;
use app\model\PunGameBattleRecord;
use app\model\User;
use app\common\JwtHelper;

class WebSocket extends Command
{
    // 保存房间信息与连接映射
    // $rooms[roomId] = ['creator' => connection, 'challenger' => connection, 'status' => 'waiting/playing', 'creator_ready' => bool, 'challenger_ready' => bool, 'creator_progress' => 0, 'challenger_progress' => 0]
    protected $rooms = [];

    protected function configure()
    {
        $this->setName('websocket:start')
            ->setDescription('Start WebSocket Server for 1v1 Battle')
            ->addArgument('action', \think\console\input\Argument::OPTIONAL, "start|stop|restart|reload|status", 'start')
            ->addOption('daemon', 'd', \think\console\input\Option::VALUE_NONE, 'Run in daemon mode');
    }

    protected function execute(Input $input, Output $output)
    {
        // 将 action 和 mode 传递给 Workerman
        global $argv;
        $action = $input->getArgument('action');
        $daemon = $input->getOption('daemon');

        // 重写 $argv 数组让 Workerman 解析
        $argv[0] = 'think';
        $argv[1] = $action;
        if ($daemon) {
            $argv[2] = '-d';
        }

        $worker = new Worker('websocket://0.0.0.0:2345');
        
        // 允许的连接数等配置
        $worker->count = 1; // 简单起见，单进程保存内存状态
        $worker->name = 'PunBattleWebSocket';

        $worker->onConnect = function(TcpConnection $connection) {
            $connection->send(json_encode(['action' => 'connected', 'msg' => 'Please auth']));
        };

        $worker->onMessage = function(TcpConnection $connection, $data) {
            $data = json_decode($data, true);
            if (!$data || !isset($data['action'])) return;

            $action = $data['action'];

            // 1. 鉴权
            if ($action === 'auth') {
                $token = $data['token'] ?? '';
                $payload = JwtHelper::verify($token);
                if (!$payload) {
                    $connection->send(json_encode(['action' => 'error', 'msg' => 'Auth failed']));
                    $connection->close();
                    return;
                }
                $connection->userId = $payload['user_id'];
                $user = User::find($connection->userId);
                $connection->userInfo = [
                    'id' => $user->id,
                    'nickname' => $user->nickname ?? 'Mysterious',
                    'avatar' => $user->avatar ?? ''
                ];
                $connection->send(json_encode(['action' => 'auth_success', 'userInfo' => $connection->userInfo]));
                return;
            }

            // 必须先鉴权
            if (!isset($connection->userId)) {
                $connection->send(json_encode(['action' => 'error', 'msg' => 'Need auth first']));
                return;
            }

            switch ($action) {
                case 'join':
                    $this->handleJoin($connection, $data['roomId'] ?? '');
                    break;
                case 'ready':
                    $this->handleReady($connection);
                    break;
                case 'progress':
                    $this->handleProgress($connection, (int)($data['questionIndex'] ?? 0), (int)($data['timeMs'] ?? 0));
                    break;
                case 'finish':
                    $this->handleFinish($connection, (int)($data['totalTimeMs'] ?? 0));
                    break;
            }
        };

        $worker->onClose = function(TcpConnection $connection) {
            $this->handleClose($connection);
        };

        Worker::runAll();
    }

    private function handleJoin(TcpConnection $connection, string $roomId)
    {
        if (empty($roomId)) return;

        $record = PunGameBattleRecord::where('room_id', $roomId)->find();
        if (!$record) {
            $connection->send(json_encode(['action' => 'error', 'msg' => '房间不存在或已解散~']));
            return;
        }
        // 已结束的房间：允许前端重进时直接拿到结算态，避免卡在等待页面
        if ((int) $record->status >= 2) {
            $myUid = (int) $connection->userId;
            $creatorUid = (int) ($record->creator_id ?? 0);
            $challengerUid = (int) ($record->challenger_id ?? 0);
            $creatorProgress = (int) ($record->creator_progress ?? 0);
            $challengerProgress = (int) ($record->challenger_progress ?? 0);

            // 相对当前连接的进度：myProgress/opponentProgress
            if ($myUid === $creatorUid) {
                $myProgress = $creatorProgress;
                $opponentProgress = $challengerProgress;
            } elseif ($myUid === $challengerUid) {
                $myProgress = $challengerProgress;
                $opponentProgress = $creatorProgress;
            } else {
                // 理论上不会发生：join 进来的人应该是 creator/challenger
                $myProgress = 0;
                $opponentProgress = 0;
            }

            $connection->send(
                json_encode([
                    'action' => 'game_over',
                    'winnerId' => $record->winner_id,
                    'totalTimeMs' => (int) ($record->total_time_ms ?? 0),
                    'myProgress' => $myProgress,
                    'opponentProgress' => $opponentProgress,
                ])
            );
            return;
        }

        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [
                'creator' => null,
                'challenger' => null,
                'creator_ready' => false,
                'challenger_ready' => false,
                'creator_progress' => 0,
                'challenger_progress' => 0,
                'creator_finished' => false,
                'challenger_finished' => false,
                'creator_time' => 0,
                'challenger_time' => 0,
                'start_time' => 0,
                'status' => $record->status == 1 ? 'playing' : 'waiting'
            ];
        }

        $room = &$this->rooms[$roomId];

        if ((int)$record->creator_id === (int)$connection->userId) {
            $room['creator'] = $connection;
            $connection->role = 'creator';
        } else {
            if (empty($record->challenger_id) || (int)$record->challenger_id === (int)$connection->userId) {
                if (empty($record->challenger_id)) {
                    $record->challenger_id = $connection->userId;
                    $record->save();
                }
                $room['challenger'] = $connection;
                $connection->role = 'challenger';
            } else {
                $connection->send(json_encode(['action' => 'error', 'msg' => '房间已满']));
                return;
            }
        }
        $connection->roomId = $roomId;

        // 检查是否断线重连（游戏正在进行中）
        if ($room['status'] === 'playing') {
            $myRole = $connection->role;
            $opponentRole = $myRole === 'creator' ? 'challenger' : 'creator';
            $timePassed = (microtime(true) * 1000) - ($room['start_time'] ?: (microtime(true) * 1000));
            $connection->send(json_encode([
                'action' => 'resume_game',
                'levels' => $record->levels_json,
                'myProgress' => $room[$myRole . '_progress'] ?? 0,
                'opponentProgress' => $room[$opponentRole . '_progress'] ?? 0,
                'timePassed' => max(0, (int)$timePassed),
                'myName' => $connection->userInfo['nickname'] ?? '我',
                'opponentName' => ($room[$opponentRole] && isset($room[$opponentRole]->userInfo['nickname']))
                    ? $room[$opponentRole]->userInfo['nickname']
                    : '对手'
            ]));
        } else {
            $this->broadcastRoomInfo($roomId);
        }
    }

    private function handleReady(TcpConnection $connection)
    {
        if (!isset($connection->roomId) || !isset($connection->role)) return;
        $roomId = $connection->roomId;
        $room = &$this->rooms[$roomId];

        if ($connection->role === 'creator') {
            $room['creator_ready'] = true;
        } else {
            $room['challenger_ready'] = true;
        }

        $this->broadcastRoomInfo($roomId);

        if ($room['creator_ready'] && $room['challenger_ready'] && $room['status'] === 'waiting') {
            $room['status'] = 'playing';
            $room['start_time'] = microtime(true) * 1000;
            
            // 更新数据库状态
            $record = PunGameBattleRecord::where('room_id', $roomId)->find();
            $record->status = 1;
            $record->save();

            // 发送开始游戏和题目数据（按接收方区分 myName/opponentName）
            if ($room['creator']) {
                $room['creator']->send(json_encode([
                    'action' => 'start_game',
                    'levels' => $record->levels_json,
                    'myName' => $room['creator']->userInfo['nickname'] ?? '我',
                    'opponentName' => ($room['challenger'] && isset($room['challenger']->userInfo['nickname']))
                        ? $room['challenger']->userInfo['nickname']
                        : '对手'
                ]));
            }
            if ($room['challenger']) {
                $room['challenger']->send(json_encode([
                    'action' => 'start_game',
                    'levels' => $record->levels_json,
                    'myName' => $room['challenger']->userInfo['nickname'] ?? '我',
                    'opponentName' => ($room['creator'] && isset($room['creator']->userInfo['nickname']))
                        ? $room['creator']->userInfo['nickname']
                        : '对手'
                ]));
            }

            $creatorName = ($room['creator']->userInfo['nickname'] ?? '') ?: '房主';
            $challengerName = ($room['challenger']->userInfo['nickname'] ?? '') ?: '挑战者';
            $creatorId = (int) ($record->creator_id ?? 0);
            $challengerId = (int) ($record->challenger_id ?? 0);
            FeishuBotHelper::notifyBattleStarted($roomId, $creatorId, $challengerId, $creatorName, $challengerName);
        }
    }

    private function handleProgress(TcpConnection $connection, int $questionIndex, int $timeMs)
    {
        if (!isset($connection->roomId)) return;
        $roomId = $connection->roomId;
        if (!isset($this->rooms[$roomId])) return;
        $room = &$this->rooms[$roomId];
        
        if ($room['status'] !== 'playing') return;

        // 记录进度，用于断线重连恢复
        $room[$connection->role . '_progress'] = $questionIndex;

        // 进度持久化：用于结束后 join 仍能拿到真实 dots
        $progressField = $connection->role === 'creator' ? 'creator_progress' : 'challenger_progress';
        PunGameBattleRecord::where('room_id', $roomId)->update([$progressField => $questionIndex]);

        // 广播给对方
        $syncData = json_encode([
            'action' => 'sync_progress',
            'role' => $connection->role,
            'questionIndex' => $questionIndex,
            'timeMs' => $timeMs
        ]);

        if ($connection->role === 'creator' && $room['challenger']) {
            $room['challenger']->send($syncData);
        } else if ($connection->role === 'challenger' && $room['creator']) {
            $room['creator']->send($syncData);
        }
    }

    private function handleFinish(TcpConnection $connection, int $totalTimeMs)
    {
        if (!isset($connection->roomId) || !isset($connection->role)) return;
        $roomId = $connection->roomId;
        if (!isset($this->rooms[$roomId])) return;
        $room = &$this->rooms[$roomId];
        if ($room['status'] !== 'playing') return;
        
        $role = $connection->role;
        $room[$role . '_finished'] = true;
        $room[$role . '_time'] = $totalTimeMs;

        // 只要有一方完成，立刻判赢并结束游戏（总耗时仅记胜方通关用时）
        $this->settleGameImmediately($roomId, $connection->userId, $totalTimeMs);
    }

    private function settleGameImmediately(string $roomId, int $winnerId, int $winnerTimeMs)
    {
        if (!isset($this->rooms[$roomId])) return;
        $room = &$this->rooms[$roomId];
        $room['status'] = 'finished';

        $record = PunGameBattleRecord::where('room_id', $roomId)->find();
        $record->status = 2; // 已结束
        $record->winner_id = $winnerId;
        $record->total_time_ms = $winnerTimeMs;

        // 结束态写入：确保 join/game_over 仍能拿到真实 dots
        $record->creator_progress = (int) ($room['creator_progress'] ?? 0);
        $record->challenger_progress = (int) ($room['challenger_progress'] ?? 0);
        $record->save();

        $creatorResultData = json_encode([
            'action' => 'game_over',
            'winnerId' => $record->winner_id,
            'totalTimeMs' => (int) $record->total_time_ms,
            'myProgress' => (int) ($record->creator_progress ?? 0),
            'opponentProgress' => (int) ($record->challenger_progress ?? 0),
        ]);

        $challengerResultData = json_encode([
            'action' => 'game_over',
            'winnerId' => $record->winner_id,
            'totalTimeMs' => (int) $record->total_time_ms,
            'myProgress' => (int) ($record->challenger_progress ?? 0),
            'opponentProgress' => (int) ($record->creator_progress ?? 0),
        ]);

        if ($room['creator']) $room['creator']->send($creatorResultData);
        if ($room['challenger']) $room['challenger']->send($challengerResultData);

        // 清理房间内存
        unset($this->rooms[$roomId]);
    }

    private function handleClose(TcpConnection $connection)
    {
        if (!isset($connection->roomId)) return;
        $roomId = $connection->roomId;
        if (!isset($this->rooms[$roomId])) return;

        $room = &$this->rooms[$roomId];
        $role = $connection->role ?? '';

        if ($role === 'creator') {
            $room['creator'] = null;
        } else if ($role === 'challenger') {
            $room['challenger'] = null;
        }

        if ($room['status'] === 'playing') {
            // 不再自动判负，保留房间状态，等待玩家重连
            // 对方可以通过对方发来的消息获知你掉线了，不过这里为了简单，不通知了
        } else {
            $this->broadcastRoomInfo($roomId);
        }
    }

    private function broadcastRoomInfo(string $roomId)
    {
        if (!isset($this->rooms[$roomId])) return;
        $room = $this->rooms[$roomId];
        $record = PunGameBattleRecord::where('room_id', $roomId)->find();
        $creatorId = $record ? (int) $record->creator_id : 0;
        $challengerId = ($record && $record->challenger_id) ? (int) $record->challenger_id : null;

        $info = [
            'action' => 'room_info',
            'creatorId' => $creatorId,
            'challengerId' => $challengerId,
            'creator' => $room['creator'] ? $room['creator']->userInfo : null,
            'creatorReady' => $room['creator_ready'] ?? false,
            'challenger' => $room['challenger'] ? $room['challenger']->userInfo : null,
            'challengerReady' => $room['challenger_ready'] ?? false,
        ];

        $json = json_encode($info);
        if ($room['creator']) $room['creator']->send($json);
        if ($room['challenger']) $room['challenger']->send($json);
    }
}
