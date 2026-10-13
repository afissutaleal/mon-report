<?php
// database/remove_koordinasi_schedules.php
// Mengeluarkan kegiatan koordinasi dari daftar SPT monitoring (monitoring_schedules)
// Disertai backup otomatis ke JSON dan tabel cadangan

require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();

echo "=== MENGELUARKAN KEGIATAN KOORDINASI DARI DAFTAR SPT MONITORING ===\n\n";

// 1. Ambil data kegiatan koordinasi
$stmt = $pdo->query("SELECT * FROM monitoring_schedules WHERE title LIKE '%koordinasi%' ORDER BY id ASC");
$koordinasiRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Ditemukan " . count($koordinasiRows) . " kegiatan koordinasi:\n";
foreach ($koordinasiRows as $row) {
    echo "- ID {$row['id']} | SPT: {$row['spt_number']} | {$row['title']}\n";
}

if (empty($koordinasiRows)) {
    echo "\nTidak ada kegiatan koordinasi yang ditemukan di monitoring_schedules.\n";
    exit;
}

// 2. Simpan backup ke file JSON
$backupFile = __DIR__ . '/backup_spt_koordinasi.json';
file_put_contents($backupFile, json_encode($koordinasiRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n[BACKUP] Berhasil membuat backup di: {$backupFile}\n";

// 3. Buat tabel cadangan coordination_schedules di database jika belum ada
$pdo->exec("
    CREATE TABLE IF NOT EXISTS coordination_schedules (
        id INTEGER PRIMARY KEY,
        spt_number VARCHAR(100),
        spt_date DATE,
        title VARCHAR(255),
        category_id INTEGER,
        category_ids TEXT,
        leader_id INTEGER,
        member_names TEXT,
        location VARCHAR(255),
        target_object VARCHAR(255),
        start_date DATE,
        end_date DATE,
        status VARCHAR(30),
        notes TEXT,
        created_at DATETIME
    )
");

$stmtInsertCoord = $pdo->prepare("
    INSERT OR REPLACE INTO coordination_schedules (
        id, spt_number, spt_date, title, category_id, category_ids, leader_id, member_names,
        location, target_object, start_date, end_date, status, notes, created_at
    ) VALUES (
        :id, :spt_number, :spt_date, :title, :category_id, :category_ids, :leader_id, :member_names,
        :location, :target_object, :start_date, :end_date, :status, :notes, :created_at
    )
");

foreach ($koordinasiRows as $row) {
    $stmtInsertCoord->execute([
        ':id' => $row['id'],
        ':spt_number' => $row['spt_number'],
        ':spt_date' => $row['spt_date'] ?? null,
        ':title' => $row['title'],
        ':category_id' => $row['category_id'],
        ':category_ids' => $row['category_ids'] ?? null,
        ':leader_id' => $row['leader_id'],
        ':member_names' => $row['member_names'],
        ':location' => $row['location'],
        ':target_object' => $row['target_object'],
        ':start_date' => $row['start_date'],
        ':end_date' => $row['end_date'],
        ':status' => $row['status'],
        ':notes' => $row['notes'],
        ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s')
    ]);
}
echo "[BACKUP] Data koordinasi berhasil disalin ke tabel 'coordination_schedules'.\n";

// 4. Hapus dari monitoring_schedules
$idsToDelete = array_column($koordinasiRows, 'id');
$inClause = implode(',', array_map('intval', $idsToDelete));

$deleted = $pdo->exec("DELETE FROM monitoring_schedules WHERE id IN ({$inClause})");
echo "\n[DELETE] Berhasil mengeluarkan {$deleted} kegiatan koordinasi dari 'monitoring_schedules'.\n";

// 5. Hitung sisa agenda di monitoring_schedules
$totalRemaining = $pdo->query("SELECT COUNT(*) FROM monitoring_schedules")->fetchColumn();
echo "Total sisa agenda SPT monitoring saat ini: {$totalRemaining} entri.\n";
