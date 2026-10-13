<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function getActiveOfficersOnlineThreshold() {
    // Active if active in the last 10 minutes
    return date('Y-m-d H:i:s', strtotime('-10 minutes'));
}

function updateOfficerHeartbeat($userId = null) {
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    if ($userId) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("UPDATE users SET last_active = datetime('now'), status_active = 1 WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            // Silently ignore
        }
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    // Cache in session if available, but fetch fresh when needed
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user;
}

function requireAuth() {
    if (!isLoggedIn()) {
        // If it's an API request, return JSON
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Silakan login terlebih dahulu.']);
            exit;
        }
        header("Location: login.php");
        exit;
    }
    updateOfficerHeartbeat();
}

function requireRole($roles = []) {
    requireAuth();
    $user = currentUser();
    if (!in_array($user['role'], (array)$roles)) {
        http_response_code(403);
        echo "<h3>Akses Ditolak: Anda tidak memiliki izin untuk mengakses halaman ini.</h3>";
        exit;
    }
}

function getActiveOfficersList() {
    $pdo = getDbConnection();
    $threshold = getActiveOfficersOnlineThreshold();
    $stmt = $pdo->prepare("
        SELECT id, nip, name, email, role, jabatan, pangkat_gol, tim_kerja, phone, avatar, theme_mode,
               last_active, device_type,
               CASE WHEN last_active >= ? THEN 1 ELSE 0 END as is_online
        FROM users
        ORDER BY is_online DESC, id ASC
    ");
    $stmt->execute([$threshold]);
    return $stmt->fetchAll();
}

function getUnreadMessagesCount($userId) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Mendapatkan tahun anggaran aktif aplikasi (multi-year PK architecture)
 * Priority: $_GET['year'] -> $_SESSION['app_year'] -> default 2026
 */
function getActiveAppYear() {
    $availableYears = getAvailableAppYears();
    $validYears = array_map('intval', array_column($availableYears, 'year'));
    if (empty($validYears)) {
        $validYears = [2026];
    }

    if (isset($_GET['year']) && is_numeric($_GET['year'])) {
        $y = (int)$_GET['year'];
        if (in_array($y, $validYears)) {
            $_SESSION['app_year'] = $y;
            return $y;
        }
    }
    if (isset($_SESSION['app_year']) && is_numeric($_SESSION['app_year'])) {
        $sessY = (int)$_SESSION['app_year'];
        if (in_array($sessY, $validYears)) {
            return $sessY;
        }
    }
    return in_array(2026, $validYears) ? 2026 : $validYears[0];
}

/**
 * Mendapatkan daftar tahun anggaran yang terdaftar pada sistem
 */
function getAvailableAppYears() {
    try {
        $pdo = getDbConnection();
        return $pdo->query("SELECT * FROM pk_years_meta ORDER BY year DESC")->fetchAll();
    } catch (Exception $e) {
        return [
            ['year' => 2026, 'title' => 'Perjanjian Kinerja Tahun 2026', 'status' => 'active', 'legal_basis' => 'Nota Dinas No: 224/DJID.6/PR.04.01/02/2026']
        ];
    }
}

/**
 * Mendapatkan judul pendek indikator PK yang ringkas untuk navigasi dan badge
 */
function getPkShortTitle($year, $code, $fullTitle = '') {
    $map = [
        2026 => [
            'PK.1' => 'Monitoring SFR',
            'PK.2' => 'Pemeriksaan Stasiun',
            'PK.3' => 'Gangguan SFR',
            'PK.4' => 'Monitoring APT',
            'PK.5' => 'Tertib APT',
            'PK.6' => 'SLA SMFR',
            'PK.7' => 'Sanksi Denda',
            'PK.8' => 'QoS Seluler',
        ],
        2025 => [
            'PK.1' => 'Monitoring SFR',
            'PK.2' => 'Pemeriksaan Stasiun',
            'PK.3' => 'Gangguan SFR',
            'PK.4' => 'Kepatuhan APT',
            'PK.5' => 'SLA SMFR',
            'PK.6' => 'Sanksi Denda',
            'PK.7' => 'QoS Layanan',
        ],
    ];
    if (isset($map[$year][$code])) {
        return $map[$year][$code];
    }
    // Fallback pembersihan string otomatis
    $clean = preg_replace('/^(Indikator\s+\d+|PK\.\d+)\s*[:\-]\s*/i', '', $fullTitle);
    if (mb_strlen($clean) > 22) {
        return mb_substr($clean, 0, 20) . '...';
    }
    return $clean ?: $code;
}


