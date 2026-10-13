<?php
// api/heartbeat.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;

$userId = $input['user_id'] ?? ($_SESSION['user_id'] ?? null);
$deviceType = $input['device_type'] ?? 'web';

if ($userId) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("UPDATE users SET last_active = datetime('now'), status_active = 1, device_type = ? WHERE id = ?");
    $stmt->execute([$deviceType, $userId]);
    echo json_encode(['success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
} else {
    echo json_encode(['success' => false, 'message' => 'No active user session.']);
}
