<?php
// api/delete_import.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$currentUser = currentUser();
if (!in_array($currentUser['role'], ['admin', 'koordinator'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Admin dan Koordinator yang berhak menghapus riwayat impor.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
    exit;
}

$pdo = getDbConnection();

$stmt = $pdo->prepare("SELECT * FROM import_history WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
    exit;
}

// Hapus file fisik jika ada
if (!empty($item['file_path'])) {
    $fullPath = __DIR__ . '/../' . $item['file_path'];
    if (file_exists($fullPath)) {
        @unlink($fullPath);
    }
}

// Hapus dari database (logs terhapus otomatis via CASCADE atau manual)
$pdo->prepare("DELETE FROM import_history_logs WHERE import_history_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM import_history WHERE id = ?")->execute([$id]);

echo json_encode([
    'success' => true,
    'message' => 'Riwayat impor dan berkas terkait berhasil dihapus.'
]);
