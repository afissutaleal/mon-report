<?php
// database/migrate_import_history.php
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();

echo "Membuat tabel riwayat impor dan jejak audit (import_history & import_history_logs)...\n";

$pdo->exec("
CREATE TABLE IF NOT EXISTS import_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    import_type VARCHAR(50) NOT NULL, -- 'pk1_monitoring_sfr', 'pk_all_targets', 'field_monitoring'
    indicator_code VARCHAR(20) DEFAULT 'PK.1',
    batch_code VARCHAR(50) UNIQUE NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    saved_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INTEGER DEFAULT 0,
    file_extension VARCHAR(10) DEFAULT '',
    total_rows INTEGER DEFAULT 0,
    valid_rows INTEGER DEFAULT 0,
    invalid_rows INTEGER DEFAULT 0,
    realization_before REAL DEFAULT NULL,
    realization_after REAL DEFAULT NULL,
    summary_result TEXT DEFAULT '',
    status VARCHAR(20) DEFAULT 'success', -- 'success', 'warning', 'failed'
    notes TEXT DEFAULT '',
    user_id INTEGER NOT NULL,
    uploader_name VARCHAR(150) NOT NULL,
    uploader_nip VARCHAR(50) DEFAULT '',
    ip_address VARCHAR(50) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS import_history_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    import_history_id INTEGER NOT NULL,
    row_number INTEGER NOT NULL,
    row_identifier VARCHAR(150) DEFAULT '', -- Nama Kab/Kota atau Kode PK
    row_data TEXT NOT NULL, -- JSON representasi baris
    status VARCHAR(20) DEFAULT 'valid', -- 'valid', 'skipped', 'error'
    evaluation_notes TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (import_history_id) REFERENCES import_history(id) ON DELETE CASCADE
);
");

// Buat direktori penyimpanan upload jika belum ada
$uploadDir = __DIR__ . '/../uploads/imports';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Tambahkan data riwayat awal (seed) sebagai contoh tracing bila tabel masih kosong
$checkCount = $pdo->query("SELECT COUNT(*) FROM import_history")->fetchColumn();
if ($checkCount == 0) {
    // Ambil admin/koordinator dari users
    $user = $pdo->query("SELECT id, name, nip FROM users WHERE role IN ('admin', 'koordinator') ORDER BY id ASC LIMIT 1")->fetch();
    $userId = $user['id'] ?? 1;
    $userName = $user['name'] ?? 'Admin Balmon';
    $userNip = $user['nip'] ?? '198801012010011001';

    $batchCode = 'IMP-' . date('Ymd') . '-001';
    $stmt = $pdo->prepare("
        INSERT INTO import_history (
            import_type, indicator_code, batch_code, original_filename, saved_filename, 
            file_path, file_size, file_extension, total_rows, valid_rows, invalid_rows, 
            realization_before, realization_after, summary_result, status, notes, 
            user_id, uploader_name, uploader_nip, ip_address, created_at
        ) VALUES (
            'pk1_monitoring_sfr', 'PK.1', ?, 'Hasil_Monitoring_SFR_Kaltim_TW1_2026.xlsx', 'seed_pk1_monitoring.xlsx',
            'uploads/imports/seed_pk1_monitoring.xlsx', 18432, 'xlsx', 10, 10, 0,
            75.0, 82.5, '8 dari 10 Kabupaten/Kota telah memenuhi 4 kriteria Nota Dinas No. 224 (80% target tercapai)', 
            'success', 'Impor data baseline capaian monitoring spektrum frekuensi radio Triwulan I 2026',
            ?, ?, ?, '127.0.0.1', datetime('now', '-3 days')
        )
    ");
    $stmt->execute([$batchCode, $userId, $userName, $userNip]);
    $historyId = $pdo->lastInsertId();

    // Insert sample row logs
    $kabKotaData = [
        ['Samarinda', '18 Pita (>=15), ROL 72% (>=60%), 100% Identifikasi, 100% Sanksi', 'valid', 'Memenuhi 4 Syarat ND 224'],
        ['Balikpapan', '22 Pita (>=15), ROL 81% (>=60%), 100% Identifikasi, 100% Sanksi', 'valid', 'Memenuhi 4 Syarat ND 224'],
        ['Kutai Kartanegara', '16 Pita (>=15), ROL 65% (>=60%), 100% Identifikasi, 100% Sanksi', 'valid', 'Memenuhi 4 Syarat ND 224'],
        ['Kutai Timur', '15 Pita (>=15), ROL 62% (>=60%), 100% Identifikasi, 100% Sanksi', 'valid', 'Memenuhi 4 Syarat ND 224'],
        ['Bontang', '17 Pita (>=15), ROL 68% (>=60%), 100% Identifikasi, 100% Sanksi', 'valid', 'Memenuhi 4 Syarat ND 224'],
        ['Penajam Paser Utara (IKN)', '19 Pita (>=15), ROL 75% (>=60%), 100% Identifikasi, 100% Sanksi', 'valid', 'Memenuhi 4 Syarat ND 224'],
        ['Paser', '15 Pita (>=15), ROL 60% (>=60%), 100% Identifikasi, 100% Sanksi', 'valid', 'Memenuhi 4 Syarat ND 224'],
        ['Berau', '16 Pita (>=15), ROL 64% (>=60%), 100% Identifikasi, 100% Sanksi', 'valid', 'Memenuhi 4 Syarat ND 224'],
        ['Kutai Barat', '12 Pita (<15), ROL 55% (<60%), 100% Identifikasi, 100% Sanksi', 'valid', 'Belum Memenuhi (Pita < 15 & ROL < 60%)'],
        ['Mahakam Ulu', '9 Pita (<15), ROL 48% (<60%), 100% Identifikasi, 100% Sanksi', 'valid', 'Belum Memenuhi (Pita < 15 & ROL < 60%)']
    ];

    $logStmt = $pdo->prepare("
        INSERT INTO import_history_logs (import_history_id, row_number, row_identifier, row_data, status, evaluation_notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, datetime('now', '-3 days'))
    ");

    $rNo = 1;
    foreach ($kabKotaData as $row) {
        $rowDataJson = json_encode([
            'no' => $rNo,
            'kabupaten_kota' => $row[0],
            'evaluasi' => $row[1],
            'keterangan' => $row[3]
        ]);
        $logStmt->execute([$historyId, $rNo++, $row[0], $rowDataJson, $row[2], $row[3]]);
    }
}

echo "Migrasi tabel import_history dan import_history_logs selesai berhasil!\n";
