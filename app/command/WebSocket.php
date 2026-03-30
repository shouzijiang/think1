<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
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
            ->setDescription('Start WebSocket Server for 1v1 Battle');
    }

    protected function execute(Input $input, Output $output)
    {
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
                $payload = JwtHelper::verifyToken($token);
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
        if (!$record || $record->status >= 2) {
            $connection->send(json_encode(['action' => 'error', 'msg' => 'Room not found or already ended']));
            return;
        }

        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [
                'creator' => null,
                'challenger' => null,
                'creator_ready' => false,
                'challenger_ready' => false,
                'status' => 'waiting' // waiting, playing, finished
            ];
        }

        $connection->roomId = $roomId;
        $room = &$this->rooms[$roomId];

        if ($record->creator_id === $connection->userId) {
            $room['creator'] = $connection;
            $connection->role = 'creator';
        } else {
            // 如果挑战者位空，且不是房主，则成为挑战者
            if (empty($record->challenger_id) || $record->challenger_id === $connection->userId) {
                if (empty($record->challenger_id)) {
                    $record->challenger_id = $connection->userId;
                    $record->save();
                }
                $room['challenger'] = $connection;
                $connection->role = 'challenger';
            } else {
                $connection->send(json_encode(['action' => 'error', 'msg' => 'Room is full']));
                return;
            }
        }

        $this->broadcastRoomInfo($roomId);
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
            
            // 更新数据库状态
            $record = PunGameBattleRecord::where('room_id', $roomId)->find();
            $record->status = 1;
            $record->save();

            // 发送开始游戏和题目数据
            $startData = json_encode([
                'action' => 'start_game',
                'levels' => $record->levels_json
            ]);
            
            if ($room['creator']) $room['creator']->send($startData);
            if ($room['challenger']) $room['challenger']->send($startData);
        }
    }

    private function handleProgress(TcpConnection $connection, int $questionIndex, int $timeMs)
    {
        if (!isset($connection->roomId)) return;
        $roomId = $connection->roomId;
        $room = &$this->rooms[$roomId];
        
        if ($room['status'] !== 'playing') return;

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
        $room = &$this->rooms[$roomId];
        
        $role = $connection->role;
        $room[$role . '_finished'] = true;
        $room[$role . '_time'] = $totalTimeMs;

        $record = PunGameBattleRecord::where('room_id', $roomId)->find();
        if ($role === 'creator') {
            $record->creator_time_ms = $totalTimeMs;
        } else {
            $record->challenger_time_ms = $totalTimeMs;
        }
        $record->save();

        // 检查是否双方都完成了
        if (isset($room['creator_finished']) && isset($room['challenger_finished'])) {
            $this->settleGame($roomId);
        } else {
            // 告诉对方自己已经完成
            $syncData = json_encode([
                'action' => 'opponent_finish',
                'timeMs' => $totalTimeMs
            ]);
            if ($role === 'creator' && $room['challenger']) {
                $room['challenger']->send($syncData);
            } else if ($role === 'challenger' && $room['creator']) {
                $room['creator']->send($syncData);
            }
        }
    }

    private function settleGame(string $roomId)
    {
        $room = &$this->rooms[$roomId];
        $room['status'] = 'finished';

        $record = PunGameBattleRecord::where('room_id', $roomId)->find();
        $record->status = 2; // 已结束
        
        $cTime = $record->creator_time_ms;
        $chTime = $record->challenger_time_ms;

        if ($cTime < $chTime) {
            $record->winner_id = $record->creator_id;
        } else if ($chTime < $cTime) {
            $record->winner_id = $record->challenger_id;
        } else {
            $record->winner_id = null; // 平局
        }
        $record->save();

        $resultData = json_encode([
            'action' => 'game_over',
            'winnerId' => $record->winner_id,
            'creatorTime' => $cTime,
            'challengerTime' => $chTime
        ]);

        if ($room['creator']) $room['creator']->send($resultData);
        if ($room['challenger']) $room['challenger']->send($resultData);

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
            // 有人中途掉线，直接判对方赢
            $record = PunGameBattleRecord::where('room_id', $roomId)->find();
            if ($record && $record->status === 1) {
                if ($role === 'creator' && $room['challenger']) {
                    $record->winner_id = $record->challenger_id;
                    $room['challenger']->send(json_encode([
                        'action' => 'game_over',
                        'msg' => 'Opponent disconnected, you win!',
                        'winnerId' => $record->challenger_id
                    ]));
                } else if ($role === 'challenger' && $room['creator']) {
                    $record->winner_id = $record->creator_id;
                    $room['creator']->send(json_encode([
                        'action' => 'game_over',
                        'msg' => 'Opponent disconnected, you win!',
                        'winnerId' => $record->creator_id
                    ]));
                }
                $record->status = 2;
                $record->save();
            }
            unset($this->rooms[$roomId]);
        } else {
            $this->broadcastRoomInfo($roomId);
        }
    }

    private function broadcastRoomInfo(string $roomId)
    {
        if (!isset($this->rooms[$roomId])) return;
        $room = $this->rooms[$roomId];
        
        $info = [
            'action' => 'room_info',
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
