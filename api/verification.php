<?php
// api/verification.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;

$reportId = (int)($input['report_id'] ?? 0);
$status = trim($input['status'] ?? ''); // 'verified', 'revision', 'rejected'
$notes = trim($input['notes'] ?? '');
$verifierId = $_SESSION['user_id'] ?? ($input['verifier_id'] ?? 2);

if (!$reportId || !in_array($status, ['verified', 'revision', 'rejected', 'submitted'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID laporan atau status verifikasi tidak valid.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE monitoring_reports
        SET status = ?,
            verified_by = ?,
            verified_at = datetime('now'),
            verification_notes = ?,
            updated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$status, $verifierId, $notes, $reportId]);

    // Create notification for the report owner
    $stmtOwner = $pdo->prepare("SELECT user_id, report_code, title FROM monitoring_reports WHERE id = ?");
    $stmtOwner->execute([$reportId]);
    $rep = $stmtOwner->fetch();

    if ($rep) {
        $statusLabels = [
            'verified' => 'Telah Disetujui (Terverifikasi)',
            'revision' => 'Memerlukan Revisi / Perbaikan Data',
            'rejected' => 'Ditolak'
        ];
        $label = $statusLabels[$status] ?? $status;
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
        $stmtNotif->execute([
            $rep['user_id'],
            "Status Laporan: " . $rep['report_code'],
            "Laporan Anda '{$rep['title']}' {$label}. Catatan: {$notes}",
            "report_detail.php?id=" . $reportId
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Status laporan berhasil diperbarui.',
        'status' => $status
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal verifikasi laporan: ' . $e->getMessage()]);
}
