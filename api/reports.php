<?php
// api/reports.php
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

// Create new report
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;

    $userId = $_SESSION['user_id'] ?? ($input['user_id'] ?? 1);
    $categoryId = (int)($input['category_id'] ?? 1);
    $title = trim($input['title'] ?? '');
    $targetName = trim($input['target_name'] ?? '');
    $frequency = trim($input['frequency'] ?? '');
    $location = trim($input['location'] ?? '');
    $lat = (float)($input['latitude'] ?? -0.502106);
    $lng = (float)($input['longitude'] ?? 117.153709);
    $dateMonitored = $input['date_monitored'] ?? date('Y-m-d');
    $status = $input['status'] ?? 'submitted'; // default submitted
    $technicalData = is_array($input['technical_data'] ?? null) 
        ? json_encode($input['technical_data']) 
        : ($input['technical_data'] ?? '{}');

    if (empty($title) || empty($targetName) || empty($location)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Judul, Target/Stasiun, dan Lokasi wajib diisi.']);
        exit;
    }

    // Generate unique report code
    $prefixMap = [
        1 => 'SPF', 2 => 'MWL', 3 => 'RFM', 4 => 'TVD',
        5 => 'HFR', 6 => 'APT', 7 => 'PSF', 8 => 'PAT', 9 => 'TGS'
    ];
    $codePrefix = 'MON-' . ($prefixMap[$categoryId] ?? 'GEN') . '-' . date('Y');
    
    $stmtSeq = $pdo->prepare("SELECT COUNT(*) FROM monitoring_reports WHERE report_code LIKE ?");
    $stmtSeq->execute([$codePrefix . '%']);
    $seq = (int)$stmtSeq->fetchColumn() + 1;
    $reportCode = sprintf("%s-%03d", $codePrefix, $seq);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO monitoring_reports (
                report_code, category_id, title, user_id, target_name, frequency,
                location, latitude, longitude, date_monitored, status, technical_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $reportCode, $categoryId, $title, $userId, $targetName, $frequency,
            $location, $lat, $lng, $dateMonitored, $status, $technicalData
        ]);
        $newId = $pdo->lastInsertId();

        updateOfficerHeartbeat($userId);

        echo json_encode([
            'success' => true,
            'message' => 'Laporan monitoring berhasil disimpan dengan kode: ' . $reportCode,
            'report_id' => $newId,
            'report_code' => $reportCode
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan laporan: ' . $e->getMessage()]);
        exit;
    }
}

// GET reports
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$status = isset($_GET['status']) ? trim($_GET['status']) : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;

try {
    if ($reportId) {
        $stmt = $pdo->prepare("
            SELECT r.*, c.name as category_name, c.short_title, c.code as category_code, c.badge_color,
                   u.name as officer_name, u.nip as officer_nip, u.avatar as officer_avatar,
                   v.name as verifier_name
            FROM monitoring_reports r
            JOIN monitoring_categories c ON r.category_id = c.id
            JOIN users u ON r.user_id = u.id
            LEFT JOIN users v ON r.verified_by = v.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();

        if ($report) {
            $report['technical_data_parsed'] = json_decode($report['technical_data'], true) ?? [];
            echo json_encode(['success' => true, 'report' => $report]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Laporan tidak ditemukan.']);
        }
        exit;
    }

    $sql = "
        SELECT r.*, c.name as category_name, c.short_title, c.code as category_code, c.badge_color,
               u.name as officer_name, u.nip as officer_nip,
               v.name as verifier_name
        FROM monitoring_reports r
        JOIN monitoring_categories c ON r.category_id = c.id
        JOIN users u ON r.user_id = u.id
        LEFT JOIN users v ON r.verified_by = v.id
        WHERE 1=1
    ";
    $params = [];

    if ($categoryId) {
        $sql .= " AND r.category_id = ?";
        $params[] = $categoryId;
    }
    if ($status) {
        $sql .= " AND r.status = ?";
        $params[] = $status;
    }
    if ($search) {
        $sql .= " AND (r.title LIKE ? OR r.target_name LIKE ? OR r.report_code LIKE ? OR r.location LIKE ?)";
        $term = "%$search%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql .= " ORDER BY r.date_monitored DESC, r.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();

    foreach ($reports as &$rep) {
        $rep['technical_data_parsed'] = json_decode($rep['technical_data'], true) ?? [];
    }

    echo json_encode([
        'success' => true,
        'count' => count($reports),
        'reports' => $reports
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
