<?php
// api/active_users.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$officers = getActiveOfficersList();

$onlineCount = 0;
$result = [];

foreach ($officers as $off) {
    if ($off['is_online']) $onlineCount++;
    $result[] = [
        'id' => (int)$off['id'],
        'nip' => $off['nip'],
        'name' => $off['name'],
        'email' => $off['email'],
        'role' => $off['role'],
        'jabatan' => $off['jabatan'],
        'pangkat_gol' => $off['pangkat_gol'] ?? '',
        'tim_kerja' => $off['tim_kerja'] ?? '',
        'phone' => $off['phone'],
        'avatar' => $off['avatar'],
        'is_online' => (bool)$off['is_online'],
        'last_active' => $off['last_active'],
        'device_type' => $off['device_type'] ?? 'web',
    ];
}

echo json_encode([
    'success' => true,
    'total_officers' => count($result),
    'online_count' => $onlineCount,
    'officers' => $result
]);
