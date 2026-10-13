<?php
// api/get_import_detail.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID riwayat impor tidak valid.']);
    exit;
}

$pdo = getDbConnection();

$stmt = $pdo->prepare("
    SELECT h.*, u.name as user_name, u.nip as user_nip, u.avatar as user_avatar, u.role as user_role
    FROM import_history h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.id = ?
");
$stmt->execute([$id]);
$history = $stmt->fetch();

if (!$history) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Data riwayat impor tidak ditemukan.']);
    exit;
}

$stmtLogs = $pdo->prepare("
    SELECT * FROM import_history_logs 
    WHERE import_history_id = ? 
    ORDER BY row_number ASC
");
$stmtLogs->execute([$id]);
$logs = $stmtLogs->fetchAll();

foreach ($logs as &$log) {
    $log['row_data_parsed'] = json_decode($log['row_data'], true) ?? [];
}

echo json_encode([
    'success' => true,
    'history' => $history,
    'logs' => $logs
]);
