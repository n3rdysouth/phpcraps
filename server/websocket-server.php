<?php

/**
 * Simple WebSocket server for Craps game
 * No external dependencies required - uses PHP's built-in socket functions
 */

require_once __DIR__ . '/../autoload.php';

use Craps\Game\GameManager;
use Craps\Database\Database;

class WebSocketServer
{
    private $host = '0.0.0.0';
    private $port = 8080;
    private $socket;
    private $clients = [];
    private $db;
    private $gameManager;

    public function __construct(string $host = '0.0.0.0', int $port = 8080)
    {
        $this->host = $host;
        $this->port = $port;

        // Initialize database
        $config = require __DIR__ . '/../config/config.php';
        $this->db = new Database($config['database']['path']);
        $this->gameManager = new GameManager($this->db, $config['game']['default_game_id']);
    }

    public function start()
    {
        // Create socket
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, $this->host, $this->port);
        socket_listen($this->socket);

        echo "WebSocket server started on {$this->host}:{$this->port}\n";

        // Non-blocking mode
        socket_set_nonblock($this->socket);

        while (true) {
            // Accept new connections
            if (($newClient = @socket_accept($this->socket)) !== false) {
                $this->handleNewConnection($newClient);
            }

            // Handle existing clients
            $this->handleClients();

            // Clean up inactive players every 10 seconds
            static $lastCleanup = 0;
            if (time() - $lastCleanup > 10) {
                $this->gameManager->cleanupInactivePlayers();
                $lastCleanup = time();
            }

            usleep(10000); // 10ms
        }
    }

    private function handleNewConnection($socket)
    {
        echo "New connection\n";
        socket_set_nonblock($socket);

        $client = [
            'socket' => $socket,
            'handshake' => false,
            'player_id' => null,
        ];

        $this->clients[] = $client;
    }

    private function handleClients()
    {
        $toRemove = [];

        foreach ($this->clients as $key => $client) {
            $socket = $client['socket'];

            // Check if socket is still valid (PHP 8+ uses Socket objects, not resources)
            if (!($socket instanceof \Socket) && !is_resource($socket)) {
                echo "Socket is no longer valid for client $key\n";
                $toRemove[] = $key;
                continue;
            }

            $data = @socket_read($socket, 4096);

            if ($data === false) {
                // Check if it's an actual error or just no data available
                $errorCode = socket_last_error($socket);

                // EAGAIN (11) or EWOULDBLOCK (35) means no data available - this is normal for non-blocking sockets
                // Only remove client on actual errors (not EAGAIN/EWOULDBLOCK)
                if ($errorCode !== 0 && $errorCode !== 11 && $errorCode !== 35) {
                    // Actual error - connection closed
                    echo "Client $key error (code $errorCode): " . socket_strerror($errorCode) . " - marking for removal\n";
                    $toRemove[] = $key;
                }

                // Clear the error and continue - this is normal for non-blocking sockets
                socket_clear_error($socket);
                continue;
            }

            if (empty($data)) {
                continue;
            }

            if (!$client['handshake']) {
                // Perform WebSocket handshake
                echo "Attempting handshake for client $key, data length: " . strlen($data) . "\n";
                $this->performHandshake($key, $data);
            } else {
                // Decode and handle message
                $message = $this->decodeMessage($data);
                if ($message !== false) {
                    $this->handleMessage($key, $message);
                }
            }
        }

        // Remove disconnected clients after iteration
        foreach ($toRemove as $key) {
            $this->removeClient($key);
        }
    }

    private function performHandshake(int $clientIndex, string $data)
    {
        $lines = explode("\n", $data);
        $headers = [];

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        if (!isset($headers['Sec-WebSocket-Key'])) {
            return;
        }

        $key = $headers['Sec-WebSocket-Key'];
        $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: $acceptKey\r\n\r\n";

        $written = socket_write($this->clients[$clientIndex]['socket'], $response);
        if ($written === false) {
            echo "Failed to write handshake response\n";
            return;
        }

        $this->clients[$clientIndex]['handshake'] = true;
        echo "Handshake completed for client $clientIndex (wrote $written bytes)\n";
    }

    private function decodeMessage(string $data): string|false
    {
        $length = ord($data[1]) & 127;

        if ($length == 126) {
            $masks = substr($data, 4, 4);
            $payload = substr($data, 8);
        } elseif ($length == 127) {
            $masks = substr($data, 10, 4);
            $payload = substr($data, 14);
        } else {
            $masks = substr($data, 2, 4);
            $payload = substr($data, 6);
        }

        $text = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $text .= $payload[$i] ^ $masks[$i % 4];
        }

        return $text;
    }

    private function encodeMessage(string $message): string
    {
        $length = strlen($message);
        $frame = chr(129); // Text frame

        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }

        return $frame . $message;
    }

    private function handleMessage(int $clientIndex, string $message)
    {
        $data = json_decode($message, true);
        if (!$data) {
            return;
        }

        $action = $data['action'] ?? '';

        try {
            switch ($action) {
                case 'register':
                    // Register player with this connection
                    $this->clients[$clientIndex]['player_id'] = $data['player_id'] ?? null;
                    echo "Player {$data['player_id']} registered\n";
                    break;

                case 'join':
                    $result = $this->gameManager->joinGame($data['name'] ?? 'Player', $data['role'] ?? 'player');
                    $this->sendToClient($clientIndex, ['type' => 'join_result', 'data' => $result]);
                    $this->broadcastGameState();
                    break;

                case 'bet':
                    if (isset($this->clients[$clientIndex]['player_id'])) {
                        $this->gameManager->updatePlayerActivity($this->clients[$clientIndex]['player_id']);
                    }
                    $result = $this->gameManager->placeBet(
                        $data['player_id'],
                        $data['bet_type'],
                        $data['amount']
                    );
                    $this->sendToClient($clientIndex, ['type' => 'bet_result', 'data' => $result]);
                    $this->broadcastGameState();
                    break;

                case 'roll':
                    if (isset($this->clients[$clientIndex]['player_id'])) {
                        $this->gameManager->updatePlayerActivity($this->clients[$clientIndex]['player_id']);
                    }
                    $result = $this->gameManager->rollDice($data['player_id']);
                    $this->broadcastGameState();
                    $this->broadcast(['type' => 'roll_result', 'data' => $result]);
                    break;

                case 'ping':
                    // Update activity for keepalive
                    if (isset($this->clients[$clientIndex]['player_id'])) {
                        $this->gameManager->updatePlayerActivity($this->clients[$clientIndex]['player_id']);
                    }
                    $this->sendToClient($clientIndex, ['type' => 'pong']);
                    break;
            }
        } catch (Exception $e) {
            $this->sendToClient($clientIndex, [
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    private function broadcastGameState()
    {
        $state = $this->gameManager->getGameState();
        $this->broadcast([
            'type' => 'game_state',
            'data' => $state
        ]);
    }

    private function broadcast(array $data)
    {
        $message = $this->encodeMessage(json_encode($data));

        foreach ($this->clients as $client) {
            if ($client['handshake']) {
                @socket_write($client['socket'], $message);
            }
        }
    }

    private function sendToClient(int $clientIndex, array $data)
    {
        if (isset($this->clients[$clientIndex]) && $this->clients[$clientIndex]['handshake']) {
            $message = $this->encodeMessage(json_encode($data));
            @socket_write($this->clients[$clientIndex]['socket'], $message);
        }
    }

    private function removeClient(int $key)
    {
        echo "Client disconnected\n";
        @socket_close($this->clients[$key]['socket']);
        unset($this->clients[$key]);
        $this->clients = array_values($this->clients); // Reindex
    }
}

// Start the server
$host = $argv[1] ?? '0.0.0.0';
$port = intval($argv[2] ?? 8080);

$server = new WebSocketServer($host, $port);
$server->start();
