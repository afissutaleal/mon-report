<?php
// api/schedules.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;

    $sptNumber = trim($input['spt_number'] ?? '');
    $sptDate = !empty($input['spt_date']) ? trim($input['spt_date']) : date('Y-m-d');
    $title = trim($input['title'] ?? '');
    $categoryId = (int)($input['category_id'] ?? 1);
    $leaderId = (int)($input['leader_id'] ?? ($_SESSION['user_id'] ?? 1));
    $members = trim($input['members'] ?? '');
    $location = trim($input['location'] ?? '');
    $target = trim($input['target_object'] ?? '');
    $start = $input['start_date'] ?? date('Y-m-d');
    $end = $input['end_date'] ?? date('Y-m-d', strtotime('+2 days'));
    $notes = trim($input['notes'] ?? '');

    if (empty($sptNumber) || empty($title) || empty($location)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nomor SPT, Judul Kegiatan, dan Lokasi wajib diisi.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO monitoring_schedules (
                spt_number, spt_date, title, category_id, leader_id, member_names,
                location, target_object, start_date, end_date, status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
        ");
        $stmt->execute([
            $sptNumber, $sptDate, $title, $categoryId, $leaderId, $members,
            $location, $target, $start, $end, $notes
        ]);
        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Jadwal monitoring berhasil ditambahkan.',
            'schedule_id' => $newId
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal membuat jadwal: ' . $e->getMessage()]);
        exit;
    }
}

// GET schedules
try {
    $stmt = $pdo->query("
        SELECT s.*, c.name as category_name, c.short_title, c.badge_color,
               u.name as leader_name, u.avatar as leader_avatar
        FROM monitoring_schedules s
        JOIN monitoring_categories c ON s.category_id = c.id
        JOIN users u ON s.leader_id = u.id
        ORDER BY s.start_date DESC
    ");
    $schedules = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'count' => count($schedules),
        'schedules' => $schedules
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
