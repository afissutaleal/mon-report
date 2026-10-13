<?php
// database/migrate_perjanjian_kinerja.php
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();

$pdo->exec("
CREATE TABLE IF NOT EXISTS perjanjian_kinerja (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
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
    notes TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// 8 Indikator Perjanjian Kinerja Tahun 2026 Sesuai Nota Dinas No: 224/DJID.6/PR.04.01/02/2026
$pkItems = [
    [
        'code' => 'PK.1',
        'title' => 'Monitoring Pita Frekuensi Radio di Kabupaten/Kota',
        'indicator' => 'Persentase (%) Monitoring Penggunaan Spektrum Frekuensi Radio dan Pengenaan Sanksi Terhadap Pengguna Spektrum Frekuensi Radio di Kabupaten/Kota',
        'target_annual' => 80.0,
        'unit' => '%',
        'realization' => 80.0,
        'formula' => '(Jumlah Kab/Kota Termonitor / Jumlah Kab/Kota Wilayah Kerja UPT 10 Kab/Kota) x 100%',
        'manual_measurement' => 'Parameter perhitungan: 1) 80% Kab/Kota termonitor; 2) 15 Pita Frekuensi Radio termonitor; 3) 60% ISR termonitor (1.312 dari 2.186 total ISR); 4) 100% Hasil monitor SFR teridentifikasi; 5) 100% Tindak lanjut pengenaan sanksi pelanggaran (temuan pelanggaran = 0). Pelaporan melalui Report Online (ROL).',
        'app_reference' => 'Report Online (ROL)',
        'icon' => 'radar',
        'badge_color' => 'primary',
        'notes' => 'Wilayah Kerja UPT Balmon Samarinda meliputi 10 Kab/Kota: Samarinda, Balikpapan, Bontang, Berau, Paser, PPU, Kutim, Kubar, Kukar, Mahakam Ulu.'
    ],
    [
        'code' => 'PK.2',
        'title' => 'Pemeriksaan Stasiun',
        'indicator' => 'Persentase (%) Pemeriksaan Stasiun Radio Dan Pengenaan Sanksi Pelanggaran Terhadap Pemeriksaan Stasiun Radio',
        'target_annual' => 100.0,
        'unit' => '%',
        'realization' => 91.82,
        'formula' => '(Total Capaian Pemeriksaan Stasiun (FM + TV + Remote Site + Open Shelter) / Total Target 2.383) x 100%',
        'manual_measurement' => 'Target Total: 2.383 Stasiun (FM Siaran: 50, TV Digital: 18, Remote Site: 2.053, Open Shelter: 262). Dilaporkan melalui APSTARD & ROL.',
        'app_reference' => 'APSTARD & ROL',
        'icon' => 'settings_input_antenna',
        'badge_color' => 'info',
        'notes' => 'Total Target: 2.383 Stasiun. Realisasi s.d. September 2026: 2.188 Stasiun (91.82%). Komponen: FM 50/50 (100%), TV 17/18 (94.44%), Remote Site 1.791/2.053 (87.25%), Open Shelter 330/262 (125.86%).'
    ],
    [
        'code' => 'PK.3',
        'title' => 'Penyelesaian Gangguan Frekuensi Radio',
        'indicator' => 'Persentase (%) Penyelesaian Gangguan Spektrum Frekuensi Radio',
        'target_annual' => 95.0,
        'unit' => '%',
        'realization' => 96.2,
        'formula' => '(Jumlah Gangguan SFR yang Terselesaikan / Jumlah Total Gangguan SFR) x 100%',
        'manual_measurement' => '100% aduan tertangani via SPT (1x24 jam untuk dinas keselamatan, 7x24 jam non-keselamatan). 95% terselesaikan dengan status closed di aplikasi trouble ticket / Nota Dinas.',
        'app_reference' => 'Trouble Ticket SFR',
        'icon' => 'warning_amber',
        'badge_color' => 'danger',
        'notes' => 'Tautan sistem: laporgangguansfr.postel.go.id. Khusus HF dikoordinir pusat.'
    ],
    [
        'code' => 'PK.4',
        'title' => 'Monitoring Perangkat Telekomunikasi (APT)',
        'indicator' => 'Persentase (%) Monitoring Alat Perangkat Telekomunikasi',
        'target_annual' => 90.0,
        'unit' => '%',
        'realization' => 88.0,
        'formula' => '(Σ Perangkat Termonitor / Σ Perangkat Target Monitoring) x 100%',
        'manual_measurement' => 'Target 300 perangkat/tahun di 80% Kab/Kota (min 10 Kab/Kota). Wajib min 5 kategori Tabel 1 (perangkat wajib standar teknis) & min 3 kategori Tabel 2 (fitur telekomunikasi). Input Berita Acara via SMART.',
        'app_reference' => 'Aplikasi SMART',
        'icon' => 'devices_other',
        'badge_color' => 'warning',
        'notes' => 'Pengawasan peredaran di toko/distributor/marketplace, penindakan booster/repeater ilegal.'
    ],
    [
        'code' => 'PK.5',
        'title' => 'Kepatuhan Standar Teknis & Sanksi APT',
        'indicator' => 'Persentase (%) Tingkat Kepatuhan Standar Teknis Alat dan/atau Perangkat Telekomunikasi dan Digital',
        'target_annual' => 100.0,
        'unit' => '%',
        'realization' => 100.0,
        'formula' => '(Σ Tindak Lanjut Pelanggaran / Σ Total Data Pelanggaran) x 100%',
        'manual_measurement' => '100% pelanggaran ditindaklanjuti dengan penerbitan Surat Teguran administratif dan pelaporan via aplikasi SMART dan tautan resmi Komdigi.',
        'app_reference' => 'Aplikasi SMART',
        'icon' => 'gavel',
        'badge_color' => 'dark',
        'notes' => 'Pelanggaran wajib dimonitor kembali setelah 7 hari kalender sejak teguran tertulis diterima.'
    ],
    [
        'code' => 'PK.6',
        'title' => 'Operasional & SLA Stasiun Monitor (SMFR)',
        'indicator' => 'Persentase (%) Terjaganya Operasional Dan Fungsi Monitoring Dari Stasiun Monitor Frekuensi Radio di UPT',
        'target_annual' => 86.0,
        'unit' => '% SLA',
        'realization' => 89.5,
        'formula' => '(SLA Stasiun Monitor Tetap/Transportable + SLA Stasiun Bergerak) / 2',
        'manual_measurement' => 'Stasiun Tetap/Transportable: SLA 100% jika upload data okupansi SMSN min 1x/minggu (4x/bulan). Bergerak: SLA 100% jika upload min 1x/bulan. Pelaporan via smfr.postel.go.id/v2/ & SMSN.',
        'app_reference' => 'SMFR v2 & SMSN',
        'icon' => 'cell_tower',
        'badge_color' => 'success',
        'notes' => 'Menghitung ketersediaan perangkat utama (Receiver/RDF, Antena, Software) & pendukung (PC, Router, Mast, PMU).'
    ],
    [
        'code' => 'PK.7',
        'title' => 'Penanganan Sanksi Denda Administratif',
        'indicator' => 'Persentase (%) Penanganan Sanksi Denda Administratif',
        'target_annual' => 85.0,
        'unit' => '%',
        'realization' => 78.5,
        'formula' => '(Σ Surat Tagihan Terbayar + (Bobot x Tindak Lanjut Surat Tagihan Belum Terbayar)) / Σ Total Surat Tagihan x 100%',
        'manual_measurement' => 'Pengelolaan Surat Tagihan denda pelanggaran IPFR/SFR/APT untuk optimalisasi PNBP. Target bertahap: TW I >= 60%, TW II >= 65%, TW III >= 70%, TW IV >= 75%, Des 81%, Tahunan 85%.',
        'app_reference' => 'Aplikasi Denda Administratif',
        'icon' => 'payments',
        'badge_color' => 'secondary',
        'notes' => 'Bobot: Tagihan 1 = 1, Tagihan 2 = 0.9, Tagihan 3 = 0.8, Optimalisasi = 0.7, Pelimpahan KPKNL = 0.6.'
    ],
    [
        'code' => 'PK.8',
        'title' => 'Data Kualitas Layanan Seluler (QoS)',
        'indicator' => 'Persentase (%) Tersedianya Data Hasil Pengukuran Kualitas Layanan',
        'target_annual' => 100.0,
        'unit' => '% Kab/Kota',
        'realization' => 90.0,
        'formula' => '(Jumlah Kab/Kota yang Sudah Diukur / Jumlah 80% Kab/Kota Wilayah Kerja) x 100%',
        'manual_measurement' => 'Pengukuran kualitas layanan telekomunikasi di 80% Kab/Kota wilayah kerja UPT via Drive Test (min 5 jam kecepatan ~40 km/jam) dan Static Test (min 3 PoI per kab/kota). Post-processing via PMT.',
        'app_reference' => 'Pusat Monitoring Telekomunikasi (PMT)',
        'icon' => 'speed',
        'badge_color' => 'info',
        'notes' => 'Mengukur FTP Speed, Browsing, YouTube test, Video Quality, WhatsApp Call/Message, 4G/5G Quality.'
    ]
];

$stmtCheck = $pdo->prepare("SELECT id FROM perjanjian_kinerja WHERE code = ?");
$stmtInsert = $pdo->prepare("
    INSERT INTO perjanjian_kinerja (
        code, title, indicator, target_annual, unit, realization, formula, manual_measurement, app_reference, icon, badge_color, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmtUpdate = $pdo->prepare("
    UPDATE perjanjian_kinerja SET 
        title = ?, indicator = ?, target_annual = ?, unit = ?, formula = ?, manual_measurement = ?, app_reference = ?, icon = ?, badge_color = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
    WHERE code = ?
");

foreach ($pkItems as $item) {
    $stmtCheck->execute([$item['code']]);
    $existing = $stmtCheck->fetch();
    if ($existing) {
        $stmtUpdate->execute([
            $item['title'],
            $item['indicator'],
            $item['target_annual'],
            $item['unit'],
            $item['formula'],
            $item['manual_measurement'],
            $item['app_reference'],
            $item['icon'],
            $item['badge_color'],
            $item['notes'],
            $item['code']
        ]);
        echo "Updated " . $item['code'] . "\n";
    } else {
        $stmtInsert->execute([
            $item['code'],
            $item['title'],
            $item['indicator'],
            $item['target_annual'],
            $item['unit'],
            $item['realization'],
            $item['formula'],
            $item['manual_measurement'],
            $item['app_reference'],
            $item['icon'],
            $item['badge_color'],
            $item['notes']
        ]);
        echo "Inserted " . $item['code'] . "\n";
    }
}

echo "Migrasi Perjanjian Kinerja (PK) 2026 Selesai!\n";
