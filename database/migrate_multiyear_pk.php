<?php
// database/migrate_multiyear_pk.php
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();

echo "Memigrasikan skema Perjanjian Kinerja (PK) menjadi Multi-Year...\n";

// 1. Buat tabel pk_years_meta
$pdo->exec("
CREATE TABLE IF NOT EXISTS pk_years_meta (
    year INTEGER PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    legal_basis VARCHAR(255) NOT NULL,
    status VARCHAR(30) DEFAULT 'active', -- 'archived', 'active', 'planning'
    description TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// 2. Cek apakah kolom 'year' sudah ada di perjanjian_kinerja
$cols = $pdo->query("PRAGMA table_info(perjanjian_kinerja)")->fetchAll(PDO::FETCH_COLUMN, 1);

// Backup data yang sudah ada
$existingData = $pdo->query("SELECT * FROM perjanjian_kinerja")->fetchAll(PDO::FETCH_ASSOC);

// Re-create table perjanjian_kinerja dengan UNIQUE(year, code) dan kolom year
$pdo->exec("PRAGMA foreign_keys = OFF;");
$pdo->exec("DROP TABLE IF EXISTS perjanjian_kinerja_backup;");
$pdo->exec("ALTER TABLE perjanjian_kinerja RENAME TO perjanjian_kinerja_backup;");

$pdo->exec("
CREATE TABLE perjanjian_kinerja (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    year INTEGER NOT NULL DEFAULT 2026,
    code VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    indicator TEXT NOT NULL,
    target_annual REAL NOT NULL,
    unit VARCHAR(50) DEFAULT '%',
    realization REAL DEFAULT 0,
    formula TEXT NOT NULL,
    manual_measurement TEXT NOT NULL,
    app_reference VARCHAR(100) DEFAULT '',
    icon VARCHAR(50) DEFAULT 'insights',
    badge_color VARCHAR(30) DEFAULT 'primary',
    order_num INTEGER DEFAULT 1,
    notes TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(year, code)
);
");

// 3. Masukkan kembali data 2026 dari backup
$stmtIns = $pdo->prepare("
    INSERT INTO perjanjian_kinerja (
        year, code, title, indicator, target_annual, unit, realization,
        formula, manual_measurement, app_reference, icon, badge_color, order_num, notes
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?
    )
");

$order = 1;
foreach ($existingData as $row) {
    $yr = isset($row['year']) ? (int)$row['year'] : 2026;
    $stmtIns->execute([
        $yr,
        $row['code'],
        $row['title'],
        $row['indicator'],
        $row['target_annual'],
        $row['unit'] ?? '%',
        $row['realization'] ?? 0,
        $row['formula'],
        $row['manual_measurement'],
        $row['app_reference'] ?? '',
        $row['icon'] ?? 'insights',
        $row['badge_color'] ?? 'primary',
        $order++,
        $row['notes'] ?? ''
    ]);
}

$pdo->exec("DROP TABLE IF EXISTS perjanjian_kinerja_backup;");
$pdo->exec("PRAGMA foreign_keys = ON;");

// 4. Masukkan metadata tahun
$yearsMeta = [
    [
        'year' => 2025,
        'title' => 'Perjanjian Kinerja Tahun 2025',
        'legal_basis' => 'Nota Dinas No: 180/DJID.6/PR.04.01/01/2025',
        'status' => 'archived',
        'description' => 'Arsip evaluasi dan pelaporan tahunan Balmon Samarinda periode 2025 (Tutup Buku).'
    ],
    [
        'year' => 2026,
        'title' => 'Perjanjian Kinerja Tahun 2026',
        'legal_basis' => 'Nota Dinas No: 224/DJID.6/PR.04.01/02/2026',
        'status' => 'active',
        'description' => 'Target dan realisasi operasional berjalan Balmon Samarinda TA 2026.'
    ],
    [
        'year' => 2027,
        'title' => 'Perjanjian Kinerja Tahun 2027 (Proyeksi)',
        'legal_basis' => 'Rencana Kerja & Alokasi Anggaran (Pagu Indikatif 2027)',
        'status' => 'planning',
        'description' => 'Perencanaan indikator dan target sasaran strategis Balmon Samarinda TA 2027.'
    ]
];

$stmtYear = $pdo->prepare("
    INSERT OR REPLACE INTO pk_years_meta (year, title, legal_basis, status, description, updated_at)
    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
");

foreach ($yearsMeta as $ym) {
    $stmtYear->execute([$ym['year'], $ym['title'], $ym['legal_basis'], $ym['status'], $ym['description']]);
}

// 5. Tambahkan Data Indikator Historis Tahun 2025 (Arsip)
$pk2025 = [
    [
        'code' => 'PK.1',
        'title' => 'Monitoring Penggunaan SFR di Kab/Kota',
        'indicator' => 'Persentase pengawasan penggunaan frekuensi radio di wilayah kerja Balmon Samarinda periode 2025',
        'target_annual' => 80.0,
        'unit' => '% Kab/Kota',
        'realization' => 85.0,
        'formula' => '(Jumlah Kab/Kota Termonitor / Total Kab/Kota) x 100%',
        'manual_measurement' => 'Termonitor minimal 10 pita frekuensi prioritas pada 10 Kab/Kota di Kaltim.',
        'app_reference' => 'Report Online (ROL)',
        'icon' => 'radar',
        'badge_color' => 'primary',
        'notes' => 'Capaian 2025 melampaui target (85% terealisasi).'
    ],
    [
        'code' => 'PK.2',
        'title' => 'Pemeriksaan Stasiun Radio (Inspeksi Lapangan)',
        'indicator' => 'Tingkat kepatuhan teknis stasiun radio hasil pengukuran lapangan periode 2025',
        'target_annual' => 100.0,
        'unit' => '%',
        'realization' => 94.2,
        'formula' => '(Stasiun Radio Sesuai / Total Target Inspeksi) x 100%',
        'manual_measurement' => 'Sampling microwave link 65 ISR, Radio FM 50 ISR, TV digital 15 ISR.',
        'app_reference' => 'APSTARD',
        'icon' => 'settings_input_antenna',
        'badge_color' => 'info',
        'notes' => 'Realisasi 94.2% dari target 100%.'
    ],
    [
        'code' => 'PK.3',
        'title' => 'Penyelesaian Kasus Gangguan Frekuensi Radio',
        'indicator' => 'Persentase penanganan dan lokalisir gangguan frekuensi penerbangan & maritim',
        'target_annual' => 90.0,
        'unit' => '%',
        'realization' => 100.0,
        'formula' => '(Kasus Gangguan Tuntas / Total Laporan Masuk) x 100%',
        'manual_measurement' => 'SLA penanganan gangguan maksimal 3x24 jam sejak laporan diterima.',
        'app_reference' => 'SIMS Gangguan',
        'icon' => 'warning',
        'badge_color' => 'danger',
        'notes' => '100% gangguan terselesaikan tuntas di 2025.'
    ],
    [
        'code' => 'PK.4',
        'title' => 'Pengawasan Perangkat Telekomunikasi (APT)',
        'indicator' => 'Persentase pengawasan peredaran alat dan perangkat telekomunikasi tersertifikasi',
        'target_annual' => 85.0,
        'unit' => '%',
        'realization' => 88.5,
        'formula' => '(Gerai/Toko Terpantau / Target Gerai) x 100%',
        'manual_measurement' => 'Survei pasar retail dan e-commerce wilayah Kaltim.',
        'app_reference' => 'SIMS APT',
        'icon' => 'devices',
        'badge_color' => 'warning',
        'notes' => 'Target 85% terlampaui (88.5%).'
    ],
    [
        'code' => 'PK.5',
        'title' => 'Penanganan Sanksi & Penertiban',
        'indicator' => 'Persentase tindak lanjut surat peringatan dan penertiban administratif',
        'target_annual' => 100.0,
        'unit' => '%',
        'realization' => 96.0,
        'formula' => '(Pelanggaran Ditindak / Total Pelanggaran) x 100%',
        'manual_measurement' => 'Penerbitan SP-1, SP-2 hingga penghentian pancaran ilegal.',
        'app_reference' => 'SIPERKASA',
        'icon' => 'gavel',
        'badge_color' => 'secondary',
        'notes' => '96% kasus tertib regulasi.'
    ],
    [
        'code' => 'PK.6',
        'title' => 'Ketersediaan Stasiun Monitor (SLA SMFR)',
        'indicator' => 'Uptime operasional sensor stasiun monitor spektrum frekuensi radio tetap',
        'target_annual' => 85.0,
        'unit' => '%',
        'realization' => 87.8,
        'formula' => '(Jam Uptime Sensor / Total Jam Operasional) x 100%',
        'manual_measurement' => 'Monitoring availability sensor tetap Samarinda & Balikpapan.',
        'app_reference' => 'SMFR Controller',
        'icon' => 'cell_tower',
        'badge_color' => 'success',
        'notes' => 'Uptime stasiun monitor stabil sepanjang 2025.'
    ]
];

$order = 1;
foreach ($pk2025 as $p) {
    $stmtCheck = $pdo->prepare("SELECT id FROM perjanjian_kinerja WHERE year = 2025 AND code = ?");
    $stmtCheck->execute([$p['code']]);
    if (!$stmtCheck->fetch()) {
        $stmtIns->execute([
            2025,
            $p['code'],
            $p['title'],
            $p['indicator'],
            $p['target_annual'],
            $p['unit'],
            $p['realization'],
            $p['formula'],
            $p['manual_measurement'],
            $p['app_reference'],
            $p['icon'],
            $p['badge_color'],
            $order++,
            $p['notes']
        ]);
    }
}

// 6. Tambahkan Indikator Proyeksi Tahun 2027 (Planning)
$pk2027 = [
    [
        'code' => 'PK.1',
        'title' => 'Monitoring Spektrum Frekuensi Radio Kawasan IKN & Kaltim',
        'indicator' => 'Persentase cakupan pengawasan SFR di seluruh Kab/Kota dan Kawasan Inti Pusat Pemerintahan (KIPP) IKN',
        'target_annual' => 90.0,
        'unit' => '% Wilayah',
        'realization' => 0.0,
        'formula' => '(Jumlah Wilayah Termonitor / Total Wilayah Kerja) x 100%',
        'manual_measurement' => 'Termasuk pengawasan frekuensi kritis smart city & mobilitas otonom IKN.',
        'app_reference' => 'ROL & Smart Spectrum IKN',
        'icon' => 'radar',
        'badge_color' => 'primary',
        'notes' => 'Target dinaikkan menjadi 90% mengantisipasi operasional penuh IKN Nusantara.'
    ],
    [
        'code' => 'PK.2',
        'title' => 'Pemeriksaan Stasiun Radio & Validasi ISR Digital',
        'indicator' => 'Persentase pemeriksaan fisik dan verifikasi izin stasiun radio berizin',
        'target_annual' => 100.0,
        'unit' => '%',
        'realization' => 0.0,
        'formula' => '(Stasiun Radio Diperiksa / Target Sampling) x 100%',
        'manual_measurement' => 'Fokus inspeksi 5G backhaul, microwave link, dan penyiaran digital terpadu.',
        'app_reference' => 'APSTARD v2',
        'icon' => 'settings_input_antenna',
        'badge_color' => 'info',
        'notes' => 'Target tahunan 2027: 100% sampling stasiun radio.'
    ],
    [
        'code' => 'PK.3',
        'title' => 'Penyelesaian Gangguan Frekuensi Radio Kritis (Aviation/Maritime)',
        'indicator' => 'Persentase penyelesaian gangguan frekuensi maritim, penerbangan, dan keamanan negara',
        'target_annual' => 100.0,
        'unit' => '%',
        'realization' => 0.0,
        'formula' => '(Kasus Tuntas / Total Kasus Dilaporkan) x 100%',
        'manual_measurement' => 'Respon cepat maksimal 24 jam untuk sektor keselamatan jiwa.',
        'app_reference' => 'SIMS Gangguan',
        'icon' => 'warning',
        'badge_color' => 'danger',
        'notes' => 'Zero tolerance terhadap gangguan frekuensi penerbangan & maritim.'
    ],
    [
        'code' => 'PK.4',
        'title' => 'Pengawasan Perangkat IoT & Telekomunikasi Cerdas (APT)',
        'indicator' => 'Persentase kepatuhan sertifikasi perangkat telekomunikasi dan IoT',
        'target_annual' => 95.0,
        'unit' => '%',
        'realization' => 0.0,
        'formula' => '(Perangkat Bersertifikat / Total Sampling Beredar) x 100%',
        'manual_measurement' => 'Pengawasan marketplace, importasi, dan vendor perangkat cerdas.',
        'app_reference' => 'e-Sertifikasi APT',
        'icon' => 'devices',
        'badge_color' => 'warning',
        'notes' => 'Standarisasi kepatuhan perangkat IoT perkotaan IKN.'
    ],
    [
        'code' => 'PK.5',
        'title' => 'Penegakan Hukum & Penertiban Administratif',
        'indicator' => 'Persentase pengenaan sanksi administratif dan yustisi atas pelanggaran frekuensi dan perangkat',
        'target_annual' => 100.0,
        'unit' => '%',
        'realization' => 0.0,
        'formula' => '(Tindakan Hukum Tuntas / Total Pelanggaran) x 100%',
        'manual_measurement' => 'Sanksi denda administratif, pencabutan izin, dan penyitaan alat ilegal.',
        'app_reference' => 'SIPERKASA',
        'icon' => 'gavel',
        'badge_color' => 'secondary',
        'notes' => 'Kolaborasi bersama Korwas PPNS dan instansi penegak hukum.'
    ],
    [
        'code' => 'PK.6',
        'title' => 'Keandalan Stasiun Monitoring Frekuensi Terpadu (SMFR AI)',
        'indicator' => 'Ketersediaan sistem pemantauan spektrum otomatis berbasis telemetri cerdas',
        'target_annual' => 92.0,
        'unit' => '% SLA',
        'realization' => 0.0,
        'formula' => '(Jam Uptime Stasiun / Total Jam) x 100%',
        'manual_measurement' => 'Integrasi stasiun monitor tetap Samarinda, Balikpapan, dan IKN.',
        'app_reference' => 'SMFR NextGen',
        'icon' => 'cell_tower',
        'badge_color' => 'success',
        'notes' => 'Target ketersediaan telemetri 92%.'
    ],
    [
        'code' => 'PK.7',
        'title' => 'Pengukuran Kualitas Layanan Seluler & Akses Internet (QoS/QoE)',
        'indicator' => 'Persentase uji petik kualitas layanan telekomunikasi di area publik dan transportasi',
        'target_annual' => 100.0,
        'unit' => '% Target',
        'realization' => 0.0,
        'formula' => '(Drive Test Terlaksana / Target Sampling Drive Test) x 100%',
        'manual_measurement' => 'Pengukuran parameter throughput, latency, dan coverage 4G/5G.',
        'app_reference' => 'SIGMON / QOS System',
        'icon' => 'speed',
        'badge_color' => 'primary',
        'notes' => 'Pengujian berkala di jalur logistik dan pusat aktivitas publik.'
    ]
];

$order = 1;
foreach ($pk2027 as $p) {
    $stmtCheck = $pdo->prepare("SELECT id FROM perjanjian_kinerja WHERE year = 2027 AND code = ?");
    $stmtCheck->execute([$p['code']]);
    if (!$stmtCheck->fetch()) {
        $stmtIns->execute([
            2027,
            $p['code'],
            $p['title'],
            $p['indicator'],
            $p['target_annual'],
            $p['unit'],
            $p['realization'],
            $p['formula'],
            $p['manual_measurement'],
            $p['app_reference'],
            $p['icon'],
            $p['badge_color'],
            $order++,
            $p['notes']
        ]);
    }
}

echo "Migrasi Multi-Year PK Berhasil! Tahun 2025, 2026, dan 2027 siap digunakan.\n";
