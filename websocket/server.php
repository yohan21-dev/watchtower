<?php

/**
 * STI CCTV Portal — real-time status WebSocket server.
 *
 * Run with:  php websocket/server.php
 * (keep alive with supervisor / systemd / pm2 in production)
 *
 * Every WS_STATUS_POLL_SECONDS it re-checks all NVRs/cameras
 * (CameraStatusService) and broadcasts any change to all connected
 * front-end clients as:
 *   { "type": "status_update", "updates": [ { type, id, status }, ... ] }
 *
 * Clients may also send { "type": "ping" } and receive { "type": "pong" }.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Sti\Cctv\Config;
use Sti\Cctv\CameraStatusService;

Config::load();

class StatusBroadcaster implements MessageComponentInterface
{
    /** @var \SplObjectStorage<ConnectionInterface> */
    protected \SplObjectStorage $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        echo "Client connected ({$this->clients->count()} total)\n";
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        if (($data['type'] ?? null) === 'ping') {
            $from->send(json_encode(['type' => 'pong']));
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        echo "Client disconnected ({$this->clients->count()} total)\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    public function broadcast(array $payload): void
    {
        $json = json_encode($payload);
        foreach ($this->clients as $client) {
            $client->send($json);
        }
    }
}

$host = Config::get('WS_HOST', '0.0.0.0');
$port = (int) Config::get('WS_PORT', 2000);
$pollSeconds = (int) Config::get('WS_STATUS_POLL_SECONDS', 30);

$loop = Loop::get();
$broadcaster = new StatusBroadcaster();

$socket = new SocketServer("{$host}:{$port}", [], $loop);
$server = new IoServer(new HttpServer(new WsServer($broadcaster)), $socket, $loop);

// Periodic availability sweep — runs in the same event loop as the WS server.
$loop->addPeriodicTimer($pollSeconds, function () use ($broadcaster) {
    try {
        $updates = CameraStatusService::checkAll();
        if (!empty($updates)) {
            $broadcaster->broadcast(['type' => 'status_update', 'updates' => $updates]);
            echo '[' . date('H:i:s') . "] Broadcast " . count($updates) . " status entries\n";
        }
    } catch (\Throwable $e) {
        echo "Status check failed: {$e->getMessage()}\n";
    }
});

echo "STI CCTV Portal WebSocket server listening on ws://{$host}:{$port}\n";
echo "Polling device availability every {$pollSeconds}s\n";

$server->run();
