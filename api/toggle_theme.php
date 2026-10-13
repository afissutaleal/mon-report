<?php
// api/toggle_theme.php
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

$raw = file_get_contents('php://input');
$input = [];
if (!empty($raw)) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if (empty($input)) {
    $input = !empty($_POST) ? $_POST : $_GET;
}

$theme = $input['theme'] ?? null;
if (!$theme || !in_array(strtolower($theme), ['light', 'dark'])) {
    $theme = 'light';
} else {
    $theme = strtolower($theme);
}

// Set cookie
setcookie('version', $theme, time() + (365 * 24 * 60 * 60), '/');

// If user logged in, persist to database
$userId = $input['user_id'] ?? ($_SESSION['user_id'] ?? null);
if ($userId) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE users SET theme_mode = ? WHERE id = ?");
        $stmt->execute([$theme, $userId]);
    } catch (Exception $e) {}
}

echo json_encode(['success' => true, 'theme' => $theme]);
