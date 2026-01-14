<?php

/**
 * API endpoints for Craps game
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../autoload.php';

use Craps\Game\GameManager;
use Craps\Database\Database;

// Load config
$config = require __DIR__ . '/../config/config.php';

// Initialize database
try {
    $db = new Database($config['database']['path']);
    $gameManager = new GameManager($db, $config['game']['default_game_id']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Handle OPTIONS for CORS
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Route requests
try {
    switch ($action) {
        case 'join':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $name = $data['name'] ?? 'Player';
            $role = $data['role'] ?? 'player';

            $result = $gameManager->joinGame($name, $role);

            // Store player_id in session
            session_start();
            $_SESSION['player_id'] = $result['player_id'] ?? null;

            echo json_encode($result);
            break;

        case 'bet':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $playerId = $data['player_id'] ?? null;
            $betType = $data['bet_type'] ?? '';
            $amount = floatval($data['amount'] ?? 0);

            if (!$playerId || !$betType || $amount <= 0) {
                throw new Exception('Invalid bet parameters');
            }

            // Update player activity
            $gameManager->updatePlayerActivity($playerId);

            $result = $gameManager->placeBet($playerId, $betType, $amount);
            echo json_encode($result);
            break;

        case 'roll':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $playerId = $data['player_id'] ?? null;

            if (!$playerId) {
                throw new Exception('Player ID required');
            }

            // Update player activity
            $gameManager->updatePlayerActivity($playerId);

            $result = $gameManager->rollDice($playerId);
            echo json_encode($result);
            break;

        case 'state':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed');
            }

            // Clean up inactive players periodically
            $gameManager->cleanupInactivePlayers();

            $state = $gameManager->getGameState();
            echo json_encode(['success' => true, 'state' => $state]);
            break;

        case 'player':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed');
            }
            $playerId = intval($_GET['player_id'] ?? 0);

            if (!$playerId) {
                throw new Exception('Player ID required');
            }

            // Update player activity
            $gameManager->updatePlayerActivity($playerId);

            $info = $gameManager->getPlayerInfo($playerId);
            echo json_encode(['success' => true, 'info' => $info]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
