<?php
// api/login.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;

$identifier = trim($input['identifier'] ?? $input['email'] ?? $input['nip'] ?? '');
$password = trim($input['password'] ?? '');
$deviceType = trim($input['device_type'] ?? 'web'); // 'web' or 'mobile'

if (empty($identifier) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'NIP / Email dan kata sandi wajib diisi.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $cleanId = str_replace(' ', '', $identifier);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR nip = ? OR replace(nip, ' ', '') = ?) AND status_active = 1");
    $stmt->execute([$identifier, $identifier, $cleanId]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nip'] = $user['nip'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        // Update last_active and device_type
        $stmtUp = $pdo->prepare("UPDATE users SET last_active = datetime('now'), device_type = ? WHERE id = ?");
        $stmtUp->execute([$deviceType, $user['id']]);

        // Don't leak password hash
        unset($user['password']);

        echo json_encode([
            'success' => true,
            'message' => 'Login berhasil. Selamat datang di Sistem Monitoring Balmon Samarinda!',
            'user' => $user
        ]);
        exit;
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'NIP/Email atau kata sandi tidak sesuai.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()]);
    exit;
}
