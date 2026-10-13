<?php
// api/chat.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDbConnection();

// Get current user id from session or authorization/parameter
$currentUserId = $_SESSION['user_id'] ?? null;
if (!$currentUserId && isset($_REQUEST['current_user_id'])) {
    $currentUserId = (int)$_REQUEST['current_user_id'];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;

    $senderId = $currentUserId ?? ($input['sender_id'] ?? null);
    $roomKey = $input['room_key'] ?? ($input['room'] ?? null);
    $receiverId = isset($input['receiver_id']) && !empty($input['receiver_id']) ? (int)$input['receiver_id'] : null;
    $isTeam = !empty($input['is_team']) ? 1 : 0;
    $message = trim($input['message'] ?? '');

    if ($roomKey === 'team' || empty($roomKey)) {
        $roomKey = $isTeam ? 'all' : 'direct';
    }
    if ($receiverId && empty($input['is_team'])) {
        $roomKey = 'direct';
        $isTeam = 0;
    }

    if (!$senderId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
        exit;
    }

    if (empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Pesan tidak boleh kosong.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (sender_id, receiver_id, is_team_chat, room_key, message, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, 0, datetime('now'))
        ");
        $stmt->execute([$senderId, $isTeam ? null : $receiverId, $isTeam, $roomKey, $message]);
        $messageId = $pdo->lastInsertId();

        // Update sender heartbeat
        updateOfficerHeartbeat($senderId);

        echo json_encode([
            'success' => true,
            'message' => 'Pesan terkirim',
            'chat_id' => $messageId,
            'room_key' => $roomKey
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengirim pesan: ' . $e->getMessage()]);
        exit;
    }
}

// GET messages
$room = $_GET['room'] ?? null;
if ($room === 'team') {
    $room = 'all';
}
$partnerId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

try {
    if (in_array($room, ['all', 'sarpras', 'montib', 'kualid']) || (!$partnerId && empty($room))) {
        $targetRoom = in_array($room, ['all', 'sarpras', 'montib', 'kualid']) ? $room : 'all';
        // Fetch team messages for specific work team room
        $stmt = $pdo->prepare("
            SELECT m.*, u.name as sender_name, u.jabatan as sender_jabatan, u.tim_kerja as sender_tim_kerja, u.avatar as sender_avatar, u.role as sender_role
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.is_team_chat = 1
              AND (m.room_key = ? OR (m.room_key IS NULL AND ? = 'all') OR (m.room_key = 'team' AND ? = 'all'))
            ORDER BY m.created_at ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $targetRoom, PDO::PARAM_STR);
        $stmt->bindValue(2, $targetRoom, PDO::PARAM_STR);
        $stmt->bindValue(3, $targetRoom, PDO::PARAM_STR);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
    } else {
        // Fetch direct messages between $currentUserId and $partnerId
        if (!$currentUserId || !$partnerId) {
            echo json_encode(['success' => true, 'messages' => []]);
            exit;
        }

        // Mark as read for messages received by current user
        $stmtRead = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ?");
        $stmtRead->execute([$currentUserId, $partnerId]);

        $stmt = $pdo->prepare("
            SELECT m.*, u.name as sender_name, u.jabatan as sender_jabatan, u.tim_kerja as sender_tim_kerja, u.avatar as sender_avatar, u.role as sender_role
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.is_team_chat = 0
              AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
            ORDER BY m.created_at ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $currentUserId, PDO::PARAM_INT);
        $stmt->bindValue(2, $partnerId, PDO::PARAM_INT);
        $stmt->bindValue(3, $partnerId, PDO::PARAM_INT);
        $stmt->bindValue(4, $currentUserId, PDO::PARAM_INT);
        $stmt->bindValue(5, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
    }

    $formatted = [];
    foreach ($rows as $r) {
        $formatted[] = [
            'id' => (int)$r['id'],
            'sender_id' => (int)$r['sender_id'],
            'receiver_id' => $r['receiver_id'] ? (int)$r['receiver_id'] : null,
            'is_team_chat' => (bool)$r['is_team_chat'],
            'room_key' => $r['room_key'] ?? ($r['is_team_chat'] ? 'all' : 'direct'),
            'message' => $r['message'],
            'created_at' => $r['created_at'],
            'time_formatted' => date('d M, H:i', strtotime($r['created_at'])),
            'sender_name' => $r['sender_name'],
            'sender_jabatan' => $r['sender_jabatan'],
            'sender_tim_kerja' => $r['sender_tim_kerja'] ?? '',
            'avatar' => $r['sender_avatar'] ?: 'assets/images/avatar/1.jpg',
            'is_me' => ($currentUserId && $r['sender_id'] == $currentUserId)
        ];
    }

    echo json_encode([
        'success' => true,
        'messages' => $formatted
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
