<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();

// Update users
$stmt1 = $pdo->prepare('UPDATE users SET name = ?, jabatan = ?, email = ? WHERE nip = ?');
$stmt1->execute([
    'Edi Mulyono, S.Kom, M.M',
    'Kepala Balai Monitor Spektrum Frekuensi Radio & Infrastruktur Digital Kelas I Samarinda',
    'kepala.balmon@komdigi.go.id',
    '198504122008121001'
]);

$stmt2 = $pdo->prepare('UPDATE users SET name = ?, jabatan = ?, email = ? WHERE nip = ?');
$stmt2->execute([
    'Andreo Yustiantoro Anjaya',
    'Ketua Tim Kerja Monitoring & Penertiban',
    'andreo@komdigi.go.id',
    '198809152010121002'
]);

$stmt3 = $pdo->prepare('UPDATE users SET name = ?, jabatan = ?, email = ? WHERE nip = ?');
$stmt3->execute([
    'Ade Ulfah Laelatussifa',
    'Admin Tim Kerja & Pengadministrasi Umum',
    'ade.ulfah@komdigi.go.id',
    '199401012019031005'
]);

// Update schedules member_names
$pdo->exec("UPDATE monitoring_schedules SET member_names = REPLACE(member_names, 'Ade Ulfian', 'Ade Ulfah Laelatussifa')");
$pdo->exec("UPDATE monitoring_schedules SET member_names = REPLACE(member_names, 'Bambang Haryanto', 'Andreo Yustiantoro Anjaya')");

// Update chat messages
$pdo->exec("UPDATE chat_messages SET message = REPLACE(message, 'Pak Bambang', 'Pak Andreo')");

echo "Database updated successfully!\n";

// Verify users
$stmt = $pdo->query('SELECT id, nip, name, jabatan, role FROM users ORDER BY id ASC');
while ($u = $stmt->fetch()) {
    echo "[#{$u['id']}] {$u['name']} - {$u['jabatan']} ({$u['role']})\n";
}
