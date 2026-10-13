<?php
// pk_view.php
// Dedicated Capaian Kinerja & Rekap Bulanan 8 Indikator Kinerja (PK)
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();

// Parameter Tahun & Metadata PK
$selectedYear = function_exists('getActiveAppYear') ? getActiveAppYear() : (int)($_GET['year'] ?? 2026);
$availableYearsList = function_exists('getAvailableAppYears') ? getAvailableAppYears() : [];

// Ambil metadata tahun aktif
$stmtYearMeta = $pdo->prepare("SELECT * FROM pk_years_meta WHERE year = ?");
$stmtYearMeta->execute([$selectedYear]);
$currentYearMeta = $stmtYearMeta->fetch() ?: [
    'year' => $selectedYear,
    'title' => "Perjanjian Kinerja Tahun $selectedYear",
    'legal_basis' => "Nota Dinas Penetapan PK TA $selectedYear",
    'status' => ($selectedYear < 2026 ? 'archived' : ($selectedYear == 2026 ? 'active' : 'planning'))
];

// Ambil semua Indikator PK untuk Tahun Terpilih
$stmtAll = $pdo->prepare("SELECT * FROM perjanjian_kinerja WHERE year = ? ORDER BY order_num ASC");
$stmtAll->execute([$selectedYear]);
$allPkList = $stmtAll->fetchAll();

// Mapping indikator berdasarkan kode
$pkByCode = [];
foreach ($allPkList as $p) {
    $pkByCode[$p['code']] = $p;
}

// Validasi kode PK sesuai ketersediaan pada tahun bersangkutan
$validCodes = !empty($allPkList) ? array_column($allPkList, 'code') : ['PK.1'];
$selectedCode = strtoupper(trim($_GET['code'] ?? 'PK.1'));
if (!in_array($selectedCode, $validCodes)) {
    $selectedCode = $validCodes[0] ?? 'PK.1';
}

// Indikator aktif saat ini
$activePk = $pkByCode[$selectedCode] ?? ($allPkList[0] ?? null);
if (!$activePk) {
    die("Indikator kinerja untuk tahun {$selectedYear} tidak ditemukan.");
}

// Parameter Bulan (0 = Akumulatif, 1-12 = Bulan Tertentu. Default: 0 = Akumulatif)
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : 0;
if ($selectedMonth < 0 || $selectedMonth > 12) {
    $selectedMonth = 0;
}


// ============================================================
// POST HANDLER: INPUT & UPDATE REALISASI MONITORING APT (PK.4)
// ============================================================
$flashSuccess = '';
$flashError = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_pk_mon_apt') {
    $postMonth = (int)($_POST['month_num'] ?? 1);
    $postKab = trim($_POST['kab_kota'] ?? 'Samarinda');
    $postDeviceType = trim($_POST['device_type'] ?? 'Handy Talkie (HT)');
    $postTargetUnits = (int)($_POST['target_units'] ?? 20);
    $postRealizedUnits = (int)($_POST['realized_units'] ?? 0);
    $postCumulativePct = (float)($_POST['cumulative_pct'] ?? 0);
    $postSptNumber = trim($_POST['spt_number'] ?? '');
    $postReportDate = trim($_POST['report_date'] ?? date('d M Y'));
    $postStatus = trim($_POST['status'] ?? 'achieved');
    $postActivityTitle = trim($_POST['activity_title'] ?? "Pengawasan APT di $postKab");
    $postActivityDetail = trim($_POST['activity_detail'] ?? "Sampling $postRealizedUnits unit $postDeviceType di $postKab");
    $postSmartReport = trim($_POST['smart_report'] ?? 'Aplikasi SMART');
    $postCertStatus = trim($_POST['cert_status'] ?? 'Bersertifikat');

    try {
        $monthNamesArr = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

        // Cek record bulanan di pk_monthly_rekap
        $stmtChk = $pdo->prepare("SELECT id FROM pk_monthly_rekap WHERE pk_code = 'PK.4' AND year = ? AND month_num = ?");
        $stmtChk->execute([$selectedYear, $postMonth]);
        $existingId = $stmtChk->fetchColumn();

        if ($existingId) {
            $stmtUpd = $pdo->prepare("
                UPDATE pk_monthly_rekap SET
                    activity_title = ?,
                    activity_detail = ?,
                    target_val = ?,
                    realization_val = ?,
                    cumulative_pct = ?,
                    status = ?,
                    report_number = ?,
                    report_date = ?,
                    app_link = ?
                WHERE id = ?
            ");
            $stmtUpd->execute([
                $postActivityTitle,
                $postActivityDetail,
                $postTargetUnits . ' Unit',
                $postRealizedUnits . ' Unit',
                $postCumulativePct,
                $postStatus,
                $postSptNumber,
                $postReportDate,
                $postSmartReport,
                $existingId
            ]);
        } else {
            $stmtIns = $pdo->prepare("
                INSERT INTO pk_monthly_rekap (
                    pk_code, year, month_num, month_name, activity_title, activity_detail,
                    target_val, realization_val, cumulative_pct, status, report_number, report_date, app_link
                ) VALUES ('PK.4', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtIns->execute([
                $selectedYear,
                $postMonth,
                $monthNamesArr[$postMonth] ?? 'Bulan ' . $postMonth,
                $postActivityTitle,
                $postActivityDetail,
                $postTargetUnits . ' Unit',
                $postRealizedUnits . ' Unit',
                $postCumulativePct,
                $postStatus,
                $postSptNumber,
                $postReportDate,
                $postSmartReport
            ]);
        }

        // Simpan juga ke monitoring_reports (Kategori 6 - APT) jika opsi sinkronisasi dipilih
        if (!empty($_POST['sync_to_reports'])) {
            $repPrefix = 'MON-APT-' . $selectedYear;
            $stmtSeq = $pdo->prepare("SELECT COUNT(*) FROM monitoring_reports WHERE report_code LIKE ?");
            $stmtSeq->execute([$repPrefix . '%']);
            $seq = (int)$stmtSeq->fetchColumn() + 1;
            $reportCode = sprintf("%s-%03d", $repPrefix, $seq);

            $techData = json_encode([
                'device_type' => $postDeviceType,
                'brand_model' => trim($_POST['brand_model'] ?? 'Berbagai Merek'),
                'certification_sdppi' => $postCertStatus,
                'store_type' => trim($_POST['store_type'] ?? 'Toko / Distributor Elektronik'),
                'units_observed' => $postRealizedUnits,
                'compliance_status' => ($postStatus === 'achieved') ? 'Memenuhi Standar Teknis' : 'Perlu Pembinaan & Edukasi',
                'ba_number' => $postSptNumber,
                'smart_reported' => 'Sudah Dilaporkan di SMART',
                'action_taken' => 'Dokumentasi BAP dan sinkronisasi ke Capaian PK.4'
            ]);

            $stmtRep = $pdo->prepare("
                INSERT INTO monitoring_reports (
                    report_code, category_id, title, user_id, target_name, frequency,
                    location, latitude, longitude, date_monitored, status, technical_data
                ) VALUES (?, 6, ?, ?, ?, '-', ?, -0.502106, 117.153709, ?, 'verified', ?)
            ");
            $stmtRep->execute([
                $reportCode,
                $postActivityTitle,
                $currentUser['id'] ?? 1,
                trim($_POST['target_name'] ?? "Pemeriksaan Perangkat $postKab"),
                $postKab . ', Kalimantan Timur',
                date('Y-m-d'),
                $techData
            ]);
        }

        // Perbarui nilai realisasi tahunan di perjanjian_kinerja untuk PK.4
        $stmtMaxPct = $pdo->prepare("SELECT MAX(cumulative_pct) FROM pk_monthly_rekap WHERE pk_code = 'PK.4' AND year = ? AND status = 'achieved'");
        $stmtMaxPct->execute([$selectedYear]);
        $latestRealization = (float)$stmtMaxPct->fetchColumn() ?: $postCumulativePct;

        $pdo->prepare("UPDATE perjanjian_kinerja SET realization = ?, updated_at = CURRENT_TIMESTAMP WHERE code = 'PK.4' AND year = ?")
            ->execute([$latestRealization, $selectedYear]);
        
        $activePk['realization'] = $latestRealization;
        $flashSuccess = "Data Pengawasan APT Bulan " . ($monthNamesArr[$postMonth] ?? $postMonth) . " berhasil disimpan dan disinkronkan ke Capaian PK.4 ({$latestRealization}%)!";
    } catch (\Exception $e) {
        $flashError = "Gagal menyimpan data: " . $e->getMessage();
    }
}

// Meta navigasi & subtitle untuk ke-8 indikator PK
$pkNavMeta = [
    'PK.1' => [
        'num' => 1,
        'short_nav' => '1. Monitoring SFR',
        'sub_nav' => 'Kab/Kota & Sanksi',
        'icon' => 'radar',
        'cat_name' => 'Spektrum Frekuensi Radio',
        'badge_color' => 'primary'
    ],
    'PK.2' => [
        'num' => 2,
        'short_nav' => '2. Pemeriksaan Stasiun',
        'sub_nav' => 'Sampling & Sanksi',
        'icon' => 'settings_input_antenna',
        'cat_name' => 'Stasiun Radio & Penyiaran',
        'badge_color' => 'info'
    ],
    'PK.3' => [
        'num' => 3,
        'short_nav' => '3. Gangguan SFR',
        'sub_nav' => 'Trouble Ticket SFR',
        'icon' => 'warning_amber',
        'cat_name' => 'Penanganan Interferensi',
        'badge_color' => 'danger'
    ],
    'PK.4' => [
        'num' => 4,
        'short_nav' => '4. Monitoring APT',
        'sub_nav' => 'Pengawasan Pasar & SMART',
        'icon' => 'devices_other',
        'cat_name' => 'Alat & Perangkat Telko',
        'badge_color' => 'warning'
    ],
    'PK.5' => [
        'num' => 5,
        'short_nav' => '5. Tertib APT',
        'sub_nav' => 'Standar Teknis & Sanksi',
        'icon' => 'gavel',
        'cat_name' => 'Kepatuhan & Penertiban',
        'badge_color' => 'dark'
    ],
    'PK.6' => [
        'num' => 6,
        'short_nav' => '6. SLA SMFR',
        'sub_nav' => 'Sensor Tetap/Bergerak',
        'icon' => 'cell_tower',
        'cat_name' => 'Stasiun Monitor Frekuensi',
        'badge_color' => 'success'
    ],
    'PK.7' => [
        'num' => 7,
        'short_nav' => '7. Sanksi Denda',
        'sub_nav' => 'Optimalisasi PNBP & SPP',
        'icon' => 'payments',
        'cat_name' => 'Denda Administratif',
        'badge_color' => 'secondary'
    ],
    'PK.8' => [
        'num' => 8,
        'short_nav' => '8. QOS',
        'sub_nav' => 'Drive Test & PMT Seluler',
        'icon' => 'speed',
        'cat_name' => 'Kualitas Layanan Telekomunikasi',
        'badge_color' => 'info'
    ],
];

// ============================================================
// DATA KHUSUS RESMI PK.2: PEMERIKSAAN STASIUN RADIO TAHUN 2026
// Sumber: Rekapitulasi Bulanan Capaian PK Pemeriksaan Stasiun Radio Balmon Samarinda
// Total Target: 2.383 Stasiun (FM: 50, TV: 18, Remote: 2053, Shelter: 262)
// Realisasi s.d. September 2026: 2.188 Stasiun (91.82%)
// ============================================================
$pk2OfficialTable = [
    1 => [
        'month_name' => 'Januari',
        'fm_target' => 50, 'fm_capaian' => 0, 'fm_pct' => 0.00,
        'tv_target' => 18, 'tv_capaian' => 0, 'tv_pct' => 0.00,
        'remote_target' => 2053, 'remote_capaian' => 0, 'remote_pct' => 0.00,
        'shelter_target' => 262, 'shelter_capaian' => 0, 'shelter_pct' => 0.00,
        'total_capaian' => 0,
        'pk_pct' => 0.00,
        'status' => 'pending'
    ],
    2 => [
        'month_name' => 'Februari',
        'fm_target' => 50, 'fm_capaian' => 19, 'fm_pct' => 38.00,
        'tv_target' => 18, 'tv_capaian' => 2, 'tv_pct' => 11.11,
        'remote_target' => 2053, 'remote_capaian' => 0, 'remote_pct' => 0.00,
        'shelter_target' => 262, 'shelter_capaian' => 0, 'shelter_pct' => 0.00,
        'total_capaian' => 21,
        'pk_pct' => 0.88,
        'status' => 'achieved'
    ],
    3 => [
        'month_name' => 'Maret',
        'fm_target' => 50, 'fm_capaian' => 23, 'fm_pct' => 46.00,
        'tv_target' => 18, 'tv_capaian' => 4, 'tv_pct' => 22.22,
        'remote_target' => 2053, 'remote_capaian' => 0, 'remote_pct' => 0.00,
        'shelter_target' => 262, 'shelter_capaian' => 0, 'shelter_pct' => 0.00,
        'total_capaian' => 27,
        'pk_pct' => 1.13,
        'status' => 'achieved'
    ],
    4 => [
        'month_name' => 'April',
        'fm_target' => 50, 'fm_capaian' => 32, 'fm_pct' => 64.00,
        'tv_target' => 18, 'tv_capaian' => 10, 'tv_pct' => 55.56,
        'remote_target' => 2053, 'remote_capaian' => 377, 'remote_pct' => 18.37,
        'shelter_target' => 262, 'shelter_capaian' => 180, 'shelter_pct' => 68.65,
        'total_capaian' => 599,
        'pk_pct' => 25.14,
        'status' => 'achieved'
    ],
    5 => [
        'month_name' => 'Mei',
        'fm_target' => 50, 'fm_capaian' => 42, 'fm_pct' => 84.00,
        'tv_target' => 18, 'tv_capaian' => 10, 'tv_pct' => 55.56,
        'remote_target' => 2053, 'remote_capaian' => 470, 'remote_pct' => 22.90,
        'shelter_target' => 262, 'shelter_capaian' => 180, 'shelter_pct' => 68.65,
        'total_capaian' => 702,
        'pk_pct' => 29.46,
        'status' => 'achieved'
    ],
    6 => [
        'month_name' => 'Juni',
        'fm_target' => 50, 'fm_capaian' => 50, 'fm_pct' => 100.00,
        'tv_target' => 18, 'tv_capaian' => 12, 'tv_pct' => 66.67,
        'remote_target' => 2053, 'remote_capaian' => 1087, 'remote_pct' => 52.95,
        'shelter_target' => 262, 'shelter_capaian' => 262, 'shelter_pct' => 100.00,
        'total_capaian' => 1411,
        'pk_pct' => 59.21,
        'status' => 'achieved'
    ],
    7 => [
        'month_name' => 'Juli',
        'fm_target' => 50, 'fm_capaian' => 50, 'fm_pct' => 100.00,
        'tv_target' => 18, 'tv_capaian' => 17, 'tv_pct' => 94.44,
        'remote_target' => 2053, 'remote_capaian' => 1415, 'remote_pct' => 68.93,
        'shelter_target' => 262, 'shelter_capaian' => 262, 'shelter_pct' => 100.00,
        'total_capaian' => 1744,
        'pk_pct' => 73.19,
        'status' => 'achieved'
    ],
    8 => [
        'month_name' => 'Agustus',
        'fm_target' => 50, 'fm_capaian' => 50, 'fm_pct' => 100.00,
        'tv_target' => 18, 'tv_capaian' => 17, 'tv_pct' => 94.44,
        'remote_target' => 2053, 'remote_capaian' => 1415, 'remote_pct' => 68.93,
        'shelter_target' => 262, 'shelter_capaian' => 330, 'shelter_pct' => 125.86,
        'total_capaian' => 1812,
        'pk_pct' => 76.04,
        'status' => 'achieved'
    ],
    9 => [
        'month_name' => 'September',
        'fm_target' => 50, 'fm_capaian' => 50, 'fm_pct' => 100.00,
        'tv_target' => 18, 'tv_capaian' => 17, 'tv_pct' => 94.44,
        'remote_target' => 2053, 'remote_capaian' => 1791, 'remote_pct' => 87.25,
        'shelter_target' => 262, 'shelter_capaian' => 330, 'shelter_pct' => 125.86,
        'total_capaian' => 2188,
        'pk_pct' => 91.82,
        'status' => 'achieved'
    ],
    10 => [
        'month_name' => 'Oktober',
        'fm_target' => 50, 'fm_capaian' => 0, 'fm_pct' => 0.00,
        'tv_target' => 18, 'tv_capaian' => 0, 'tv_pct' => 0.00,
        'remote_target' => 2053, 'remote_capaian' => 0, 'remote_pct' => 0.00,
        'shelter_target' => 262, 'shelter_capaian' => 0, 'shelter_pct' => 0.00,
        'total_capaian' => 0,
        'pk_pct' => 0.00,
        'status' => 'in_progress'
    ],
    11 => [
        'month_name' => 'November',
        'fm_target' => 50, 'fm_capaian' => 0, 'fm_pct' => 0.00,
        'tv_target' => 18, 'tv_capaian' => 0, 'tv_pct' => 0.00,
        'remote_target' => 2053, 'remote_capaian' => 0, 'remote_pct' => 0.00,
        'shelter_target' => 262, 'shelter_capaian' => 0, 'shelter_pct' => 0.00,
        'total_capaian' => 0,
        'pk_pct' => 0.00,
        'status' => 'pending'
    ],
    12 => [
        'month_name' => 'Desember',
        'fm_target' => 50, 'fm_capaian' => 0, 'fm_pct' => 0.00,
        'tv_target' => 18, 'tv_capaian' => 0, 'tv_pct' => 0.00,
        'remote_target' => 2053, 'remote_capaian' => 0, 'remote_pct' => 0.00,
        'shelter_target' => 262, 'shelter_capaian' => 0, 'shelter_pct' => 0.00,
        'total_capaian' => 0,
        'pk_pct' => 0.00,
        'status' => 'pending'
    ],
];

// Auto-sync PK.2 dengan data resmi Balmon Samarinda 2026 jika belum tersinkronisasi
if ($selectedCode === 'PK.2' && $selectedYear === 2026) {
    try {
        $pdo->prepare("
            UPDATE perjanjian_kinerja SET 
                title = 'Pemeriksaan Stasiun', 
                realization = 91.82, 
                formula = '(Total Capaian Pemeriksaan Stasiun (FM + TV + Remote Site + Open Shelter) / Total Target 2.383) x 100%', 
                notes = 'Total Target: 2.383 Stasiun. Realisasi s.d. September: 2.188 Stasiun (91.82%). Komponen: FM 50/50 (100%), TV 17/18 (94.44%), Remote Site 1.791/2.053 (87.25%), Open Shelter 330/262 (125.86%).' 
            WHERE code = 'PK.2' AND year = ?
        ")->execute([$selectedYear]);
        $activePk['realization'] = 91.82;
        $activePk['title'] = 'Pemeriksaan Stasiun';

        $checkMonth = $pdo->prepare("SELECT cumulative_pct FROM pk_monthly_rekap WHERE pk_code = 'PK.2' AND year = ? AND month_num = 9");
        $checkMonth->execute([$selectedYear]);
        $currPct = (float)$checkMonth->fetchColumn();
        if ($currPct != 91.82) {
            $stmtUpdM = $pdo->prepare("UPDATE pk_monthly_rekap SET target_val = ?, realization_val = ?, cumulative_pct = ?, activity_title = ?, activity_detail = ?, status = ? WHERE pk_code = 'PK.2' AND year = ? AND month_num = ?");
            foreach ($pk2OfficialTable as $mNum => $rRow) {
                $targetVal = '2.383 Stn';
                $realVal = $rRow['total_capaian'] > 0 ? number_format($rRow['total_capaian'], 0, ',', '.') . ' Stasiun' : '0 Stasiun';
                $actTitle = $rRow['total_capaian'] > 0 ? "Pemeriksaan {$rRow['total_capaian']} Stasiun" : "Persiapan Pemeriksaan Stasiun";
                $actDetail = "FM: {$rRow['fm_capaian']}/{$rRow['fm_target']} ({$rRow['fm_pct']}%), TV: {$rRow['tv_capaian']}/{$rRow['tv_target']} ({$rRow['tv_pct']}%), Remote: {$rRow['remote_capaian']}/{$rRow['remote_target']} ({$rRow['remote_pct']}%), Shelter: {$rRow['shelter_capaian']}/{$rRow['shelter_target']} ({$rRow['shelter_pct']}%)";
                $stmtUpdM->execute([$targetVal, $realVal, $rRow['pk_pct'], $actTitle, $actDetail, $rRow['status'], $selectedYear, $mNum]);
            }
        }
    } catch (\Exception $e) {}
}

// ============================================================
// DATA RESMI PK.4: PENGAWASAN ALAT & PERANGKAT TELEKOMUNIKASI (APT) 2026
// Sumber: Laporan Capaian PK Pengawasan Alat dan/atau Perangkat Telekomunikasi
// Balai Monitor Spektrum Frekuensi Radio dan Infrastruktur Digital Kelas I Samarinda
// Periode: Bulan September 2026 (Cutoff Data per Tanggal 01 Oktober 2026)
// Ditandatangani oleh Kepala Balai Monitor Edi Mulyono tanggal 05 Oktober 2026
// ============================================================
$pk4OfficialReport = [
    'title' => 'Capaian Perjanjian Kinerja (PK) Pengawasan Alat dan/atau Perangkat Telekomunikasi',
    'upt_name' => 'Balai Monitor SFR dan Infrastruktur Digital Kelas I Samarinda',
    'period' => 'Bulan September 2026 (Cutoff Data per Tanggal 01 Oktober 2026)',
    'report_date' => '05 Oktober 2026',
    'signer' => 'Edi Mulyono',
    'signer_role' => 'Kepala Balai Monitor SFR & ID Kelas I Samarinda',
    'legal_basis' => 'Nota Dinas Direktur Pengendalian Infrastruktur Digital No: 224/DJID.6/PR.04.01/02/2026 tanggal 03 Februari 2026',
    'target_regions' => 8,
    'realized_regions' => 8,
    'target_units' => 300,
    'realized_units' => 1052,
    'certified_units' => 1052,
    'violation_units' => 0,
    'achievement_pct' => 100.00,
    'formula_text' => '((1.052 Perangkat bersertifikat + 0 Pelanggaran Perangkat) / 300 Perangkat termonitor) x 100% = 100%'
];

// Tabel 1. Jumlah Alat dan/atau Perangkat Telekomunikasi Termonitor (Hal. 5 Dokumen Resmi)
$pk4Table1Official = [
    1 => ['wilayah' => 'Kutai Barat', 'termonitor' => 158, 'bersertifikasi' => 158, 'tidak_bersertifikasi' => 0, 'spt' => '14 & 107', 'pct' => 100.0],
    2 => ['wilayah' => 'Kabupaten Kutai Kartanegara', 'termonitor' => 370, 'bersertifikasi' => 370, 'tidak_bersertifikasi' => 0, 'spt' => '12 & 107', 'pct' => 100.0],
    3 => ['wilayah' => 'Kota Bontang', 'termonitor' => 35, 'bersertifikasi' => 35, 'tidak_bersertifikasi' => 0, 'spt' => '22', 'pct' => 100.0],
    4 => ['wilayah' => 'Kabupaten Paser', 'termonitor' => 1, 'bersertifikasi' => 1, 'tidak_bersertifikasi' => 0, 'spt' => '27', 'pct' => 100.0],
    5 => ['wilayah' => 'Kabupaten Kutai Timur', 'termonitor' => 251, 'bersertifikasi' => 251, 'tidak_bersertifikasi' => 0, 'spt' => '71, 89, 109', 'pct' => 100.0],
    6 => ['wilayah' => 'Kota Samarinda', 'termonitor' => 35, 'bersertifikasi' => 35, 'tidak_bersertifikasi' => 0, 'spt' => '72, 95, 97', 'pct' => 100.0],
    7 => ['wilayah' => 'Kabupaten Berau', 'termonitor' => 183, 'bersertifikasi' => 183, 'tidak_bersertifikasi' => 0, 'spt' => '86, 93, 109', 'pct' => 100.0],
    8 => ['wilayah' => 'Kabupaten Penajam Paser Utara', 'termonitor' => 19, 'bersertifikasi' => 19, 'tidak_bersertifikasi' => 0, 'spt' => '141', 'pct' => 100.0],
];

// 16 Jenis Perangkat Resmi yang Dimonitor (dengan 3 Prioritas Utama sesuai Hal. 4 Dokumen)
$pk4PriorityDevices = [
    1 => ['name' => 'Mobile Transceiver / Radio Rig', 'target' => 20, 'realisasi' => 12, 'compliant' => 12, 'violation' => 0, 'category' => 'Prioritas Utama', 'icon' => 'directions_car', 'is_priority' => true],
    2 => ['name' => 'Handy Talkie (HT)', 'target' => 35, 'realisasi' => 38, 'compliant' => 38, 'violation' => 0, 'category' => 'Prioritas Utama', 'icon' => 'phone_in_talk', 'is_priority' => true],
    3 => ['name' => 'Wireless Access Point (Wifi Extender/Repeater)', 'target' => 40, 'realisasi' => 75, 'compliant' => 75, 'violation' => 0, 'category' => 'Prioritas Utama', 'icon' => 'wifi', 'is_priority' => true],
    4 => ['name' => 'Microwave Link (IDU/ODU)', 'target' => 100, 'realisasi' => 738, 'compliant' => 738, 'violation' => 0, 'category' => 'Transmisi & BTS', 'icon' => 'settings_input_antenna', 'is_priority' => false],
    5 => ['name' => 'Perangkat Low Power (TWS, Mic, Speaker, Smartwatch)', 'target' => 35, 'realisasi' => 132, 'compliant' => 132, 'violation' => 0, 'category' => 'Aksesoris & IoT', 'icon' => 'headphones', 'is_priority' => false],
    6 => ['name' => 'Pemancar FM & TV Digital', 'target' => 10, 'realisasi' => 8, 'compliant' => 8, 'violation' => 0, 'category' => 'Penyiaran', 'icon' => 'podcasts', 'is_priority' => false],
    7 => ['name' => 'Set Top Box (STB)', 'target' => 15, 'realisasi' => 3, 'compliant' => 3, 'violation' => 0, 'category' => 'Penyiaran', 'icon' => 'tv', 'is_priority' => false],
    8 => ['name' => 'HF All Band Transceiver', 'target' => 5, 'realisasi' => 0, 'compliant' => 0, 'violation' => 0, 'category' => 'Radio HF', 'icon' => 'radio', 'is_priority' => false],
    9 => ['name' => 'Radio POC (Push-to-Talk Over Cellular)', 'target' => 10, 'realisasi' => 0, 'compliant' => 0, 'violation' => 0, 'category' => 'Komrad', 'icon' => 'cell_wifi', 'is_priority' => false],
    10 => ['name' => 'GPS Tracker', 'target' => 10, 'realisasi' => 0, 'compliant' => 0, 'violation' => 0, 'category' => 'Navigasi', 'icon' => 'location_on', 'is_priority' => false],
    11 => ['name' => 'Repeater GSM / Penguat Sinyal Seluler', 'target' => 5, 'realisasi' => 0, 'compliant' => 0, 'violation' => 0, 'category' => 'Seluler', 'icon' => 'signal_cellular_alt', 'is_priority' => false],
    12 => ['name' => 'Simbox / Modempool', 'target' => 5, 'realisasi' => 0, 'compliant' => 0, 'violation' => 0, 'category' => 'Telekomunikasi', 'icon' => 'sim_card', 'is_priority' => false],
    13 => ['name' => 'Optical Line Terminal (OLT) & ONT', 'target' => 5, 'realisasi' => 0, 'compliant' => 0, 'violation' => 0, 'category' => 'Fiber Optic', 'icon' => 'router', 'is_priority' => false],
    14 => ['name' => 'Fake BTS / IMSI Catcher', 'target' => 2, 'realisasi' => 0, 'compliant' => 0, 'violation' => 0, 'category' => 'Pengawasan Khusus', 'icon' => 'cell_tower', 'is_priority' => false],
    15 => ['name' => 'Jammer', 'target' => 2, 'realisasi' => 0, 'compliant' => 0, 'violation' => 0, 'category' => 'Pengawasan Khusus', 'icon' => 'do_not_disturb_on', 'is_priority' => false],
    16 => ['name' => 'Alat Perangkat Telekomunikasi Rakitan', 'target' => 2, 'realisasi' => 0, 'compliant' => 0, 'violation' => 0, 'category' => 'Rakitan', 'icon' => 'build', 'is_priority' => false]
];

// Sebaran 8 Kabupaten/Kota Sesuai Tabel 1 Laporan Resmi
$pk4KaltimRegions = [
    'Kutai Barat' => ['target_lokasi' => 5, 'realisasi_lokasi' => 5, 'unit_target' => 45, 'unit_realisasi' => 158, 'status' => 'Selesai', 'bap' => 'SPT 14 & 107/BALMON.64/2026'],
    'Kabupaten Kutai Kartanegara' => ['target_lokasi' => 6, 'realisasi_lokasi' => 6, 'unit_target' => 60, 'unit_realisasi' => 370, 'status' => 'Selesai', 'bap' => 'SPT 12 & 107/BALMON.64/2026'],
    'Kota Bontang' => ['target_lokasi' => 3, 'realisasi_lokasi' => 3, 'unit_target' => 30, 'unit_realisasi' => 35, 'status' => 'Selesai', 'bap' => 'SPT 22/BALMON.64/KP.01.06/02/2026'],
    'Kabupaten Paser' => ['target_lokasi' => 2, 'realisasi_lokasi' => 2, 'unit_target' => 15, 'unit_realisasi' => 1, 'status' => 'Selesai', 'bap' => 'SPT 27/BALMON.64/KP.01.06/03/2026'],
    'Kabupaten Kutai Timur' => ['target_lokasi' => 5, 'realisasi_lokasi' => 5, 'unit_target' => 50, 'unit_realisasi' => 251, 'status' => 'Selesai', 'bap' => 'SPT 71, 89, 109/BALMON.64/2026'],
    'Kota Samarinda' => ['target_lokasi' => 4, 'realisasi_lokasi' => 4, 'unit_target' => 40, 'unit_realisasi' => 35, 'status' => 'Selesai', 'bap' => 'SPT 72, 95, 97/BALMON.64/2026'],
    'Kabupaten Berau' => ['target_lokasi' => 4, 'realisasi_lokasi' => 4, 'unit_target' => 40, 'unit_realisasi' => 183, 'status' => 'Selesai', 'bap' => 'SPT 86, 93, 109/BALMON.64/2026'],
    'Kabupaten Penajam Paser Utara' => ['target_lokasi' => 3, 'realisasi_lokasi' => 3, 'unit_target' => 20, 'unit_realisasi' => 19, 'status' => 'Selesai', 'bap' => 'SPT 141/BALMON.64/KP.01.06/09/2026']
];

// Rincian Pelaksanaan 8 Paket Penugasan SPT (Sesuai Lampiran Detail 1.052 Perangkat Termonitor)
$pk4SptDetails = [
    [
        'spt' => '14/BALMON.64/KP.01.06/02/2026',
        'dates' => '10-02-2026 s.d. 14-02-2026',
        'month' => 'Februari 2026',
        'kab_kota' => 'Kab. Kutai Barat',
        'targets' => 'MAIRIN CENTRAL & Putra Family Computer (Barong Tongkok, Simpang Raya)',
        'category' => 'Seller (Ritel Komputer & Elektronik)',
        'devices_count' => 22,
        'devices_sample' => 'Xiaomi LED TV, Asus Laptop, TP-Link Tapo C200, Logitech Transceiver, Robot KM3000, Modem Brovi, Acer Laptop, Asus Laptop, TP-Link WR840N, CPE210, Robot RB120, Ezviz H8c, Tapo P100, Archer AX12, EAP110, RE305',
        'compliance' => '100% Bersertifikasi (0 Pelanggaran)',
        'smart_status' => 'Terinput SMART'
    ],
    [
        'spt' => '12/BALMON.64/KP.01.06/02/2026',
        'dates' => '10-02-2026 s.d. 14-02-2026',
        'month' => 'Februari 2026',
        'kab_kota' => 'Kab. Kutai Kartanegara',
        'targets' => 'Erafone Tenggarong, Anton cell 3, UD DUO HATI COMPUTER (Tenggarong)',
        'category' => 'Seller (Gerai Ponsel & Aksesoris)',
        'devices_count' => 27,
        'devices_sample' => 'Redmi Smartwatch, Samsung Bluetooth Headset, Robot Encobuds, Robot RB220, TP-Link Switch, Robot Quite E1, HP Printer, Targus Mouse, Philips TAA1708, Oppo ETEK1, TP-Link WN722N, Archer C54, CPE220, JBL TUNE520BT, JBL Flip 6',
        'compliance' => '100% Bersertifikasi (0 Pelanggaran)',
        'smart_status' => 'Terinput SMART'
    ],
    [
        'spt' => '22/BALMON.64/KP.01.06/02/2026',
        'dates' => '24-02-2026 s.d. 28-02-2026',
        'month' => 'Februari 2026',
        'kab_kota' => 'Kota Bontang',
        'targets' => 'Maxam Computer & Erafone Citimall Bontang (Bontang Selatan & Utara)',
        'category' => 'Seller (Pusat Komputer & Mall Ritel)',
        'devices_count' => 35,
        'devices_sample' => 'Lenovo IdeaPad, TP-Link WR840N, SF1008D, WN722N, RE305, WA854RE, Robot M370, Miniso LW-13, MR100, Dynabook T120, WN727N, HP Mouse, Archer T2U, Logitech MR0087, Vivan Life 110, Garmin A04162, DJI Mini 3 Pro, JBL GO4D',
        'compliance' => '100% Bersertifikasi (0 Pelanggaran)',
        'smart_status' => 'Terinput SMART'
    ],
    [
        'spt' => '27/BALMON.64/KP.01.06/03/2026',
        'dates' => '10-03-2026 s.d. 14-03-2026',
        'month' => 'Maret 2026',
        'kab_kota' => 'Kab. Paser',
        'targets' => 'Mitra Kita Kandilo Plaza (Tanah Grogot)',
        'category' => 'Seller (Pusat Perbelanjaan)',
        'devices_count' => 1,
        'devices_sample' => 'Handy Talky (HT) Baofeng UV-82 (No Sertifikat: 71323/SDPPI/2020)',
        'compliance' => '100% Bersertifikasi (0 Pelanggaran)',
        'smart_status' => 'Terinput SMART'
    ],
    [
        'spt' => '71 & 72/BALMON.64/KP.01.06/04/2026',
        'dates' => '04-05-2026 s.d. 07-05-2026',
        'month' => 'Mei 2026',
        'kab_kota' => 'Kota Samarinda & Kab. Kutai Timur',
        'targets' => 'PT. Best Camp Prima Data, PT. Lintas Maya Multimedia, PT. Patria Lintas Jaya, PT. Perdana Teknologi Persada',
        'category' => 'User (Penyelenggara Jaringan & ISP)',
        'devices_count' => 7,
        'devices_sample' => 'Ubiquiti AF-5XHD (x3), MikroTik NetBox 5, Mikrotik BaseBox 5, Mikrotik NetMetal 5, Mikrotik NetMetal ac²',
        'compliance' => '100% Bersertifikasi (0 Pelanggaran)',
        'smart_status' => 'Terinput SMART'
    ],
    [
        'spt' => '86, 89, 93, 95, 97/BALMON.64/KP.01.06/06/2026',
        'dates' => '08-06-2026 s.d. 02-07-2026',
        'month' => 'Juni 2026',
        'kab_kota' => 'Kab. Berau, Kota Samarinda, Kab. Kutai Timur',
        'targets' => 'Counter Free Cell, HALUP CELL, Robinson com, Sarana Komunikasi, Java Komputer, Radio Siaran (7 Stasiun), Stasiun TV Digital (GTV, Trans7, SCM), PT Hamparan Anugrah Abadi, PT Pamapersada',
        'category' => 'Seller & User (Ritel, Radio Siaran FM, TV Digital, Tambang)',
        'devices_count' => 66,
        'devices_sample' => 'Ponsel Infinix/Redmi/Vivo/Oppo/Samsung, Alinco DR-CS10 Rig, Icom IC-V3500 Rig, Motorola XiR M3688 Rig, HT WLN, HT Alinco, HT Motorola, Pemancar FM Falcon, RVR TEX 30, TV Transmitter SYES Slim5, Rohde & Schwarz THU9evo',
        'compliance' => '100% Bersertifikasi (0 Pelanggaran)',
        'smart_status' => 'Terinput SMART'
    ],
    [
        'spt' => '107/BALMON.64/KP.01.06/07/2026',
        'dates' => '27-07-2026 s.d. 30-07-2026',
        'month' => 'Juli 2026',
        'kab_kota' => 'Kab. Kutai Kartanegara (343 Unit) & Kab. Kutai Barat (136 Unit)',
        'targets' => 'Site BTS & Hop Microwave Operator Seluler: PT. Telekomunikasi Indonesia, PT. Telekomunikasi Selular, PT. Indosat Tbk, PT. XL Smart Telecom Sejahtera',
        'category' => 'User (Infrastruktur Transmisi Seluler BTS)',
        'devices_count' => 479,
        'devices_sample' => 'NEC iPasolink VR4, NEC iPasolink VR10, NEC iPasolink 400 (7/8/15/23 GHz), NEC iPasolink 1000, NEC iPasolink 200, ZTE ZXMW NR8120, SIAE Microelettronica AGS Series',
        'compliance' => '100% Bersertifikasi (0 Pelanggaran)',
        'smart_status' => 'Terinput SMART'
    ],
    [
        'spt' => '109/BALMON.64/KP.01.06/07/2026',
        'dates' => '03-08-2026 s.d. 06-08-2026',
        'month' => 'Agustus 2026',
        'kab_kota' => 'Kab. Kutai Timur (241 Unit) & Kab. Berau (155 Unit)',
        'targets' => 'Site BTS & Hop Microwave Operator Seluler: PT. Telekomunikasi Indonesia, PT. Telekomunikasi Selular, PT. Indosat Tbk, PT. XL Smart Telecom Sejahtera',
        'category' => 'User (Infrastruktur Transmisi Seluler BTS)',
        'devices_count' => 396,
        'devices_sample' => 'NEC iPasolink VR4, NEC iPasolink VR10, NEC iPasolink 400 (7/8/15/23 GHz), NEC iPasolink 1000, ZTE ZXMW HRUT3D, ZTE ZXMW NR8250, Ceragon IP-20 LH, SIAE AGS Series',
        'compliance' => '100% Bersertifikasi (0 Pelanggaran)',
        'smart_status' => 'Terinput SMART'
    ],
    [
        'spt' => '141/BALMON.64/KP.01.06/09/2026',
        'dates' => '27-09-2026 s.d. 01-10-2026',
        'month' => 'September 2026',
        'kab_kota' => 'Kab. Penajam Paser Utara (Kawasan IKN)',
        'targets' => 'Toko Aulia Elektronik & Anita Cell (Giri Mukti, Petung - Penajam)',
        'category' => 'Seller (Toko Elektronik & Aksesoris)',
        'devices_count' => 19,
        'devices_sample' => 'Mic Wireless dat DM-1800, vivo V2538, Redmi 2510DRA23E, realme RMX5388, Huawei B320-923 LTE CPE, Vivan VS16, Robot M330, Tecno KN3, Infinix X6840, Tecno T1002, Robot Enco Buds 2, Redmi Pad, STB Matrix Burger DVB-T2, STB K-Vision KV3000, Ezviz H1c, STB Matrix Apple DVB-T2, Ugreen WS207 TWS, realme RMX5366, Oppo CPH2895',
        'compliance' => '100% Bersertifikasi (0 Pelanggaran)',
        'smart_status' => 'Terinput SMART'
    ]
];

// ============================================================
// DATA RESMI PK.6: TERJAGANYA OPERASIONAL & FUNGSI SMFR (SLA SMFR) 2026
// Sumber: Laporan Capaian Kinerja Perangkat SMFR Periode Januari s.d. Mei 2026
// Balai Monitor Spektrum Frekuensi Radio Kelas I Samarinda
// Ditandatangani oleh Kepala Balai Monitor Warko & Plh. Widhy Baskoro
// ============================================================
$pk6OfficialTable = [
    1 => [
        'month_name' => 'Januari',
        'nd_number' => '60/BALMON.64/SP.03.01/02/2026',
        'nd_date' => '02 Feb 2026',
        'signer' => 'Warko (Kepala Balmon)',
        'target_pct' => 85.00,
        'vuhf_tetap_pct' => 80.00,
        'bpp_transp_pct' => 100.00,
        'berau_transp_pct' => 100.00,
        'kutim_transp_pct' => 75.00,
        'avg_tetap_transp' => 88.75,
        'mobile_df_pct' => 100.00,
        'sla_gabungan' => 94.38,
        'quality_pct' => 100.00,
        'status' => 'achieved',
        'okupansi_count' => 4,
        'okupansi_dates' => '07, 14, 21, 28 Jan 2026',
        'spt_usage' => '14 Hari Transportable (SPT 02, 07, 08) & 4 Hari Portable Samarinda (SPT 07)'
    ],
    2 => [
        'month_name' => 'Februari',
        'nd_number' => '127/BALMON.64/SP.03.01/03/2026',
        'nd_date' => '03 Mar 2026',
        'signer' => 'Warko (Kepala Balmon)',
        'target_pct' => 86.00,
        'vuhf_tetap_pct' => 80.00,
        'bpp_transp_pct' => 100.00,
        'berau_transp_pct' => 100.00,
        'kutim_transp_pct' => 100.00,
        'avg_tetap_transp' => 95.00,
        'mobile_df_pct' => 90.00,
        'sla_gabungan' => 92.50,
        'quality_pct' => 100.00,
        'status' => 'achieved',
        'okupansi_count' => 6,
        'okupansi_dates' => '02, 03, 11, 19, 23, 24 Feb 2026',
        'spt_usage' => '10 Hari Transportable, 5 Hari Kukar (SPT 12, 13), 5 Hari Kubar (SPT 14, 15), 5 Hari Bontang (SPT 22, 23)'
    ],
    3 => [
        'month_name' => 'Maret',
        'nd_number' => '178/BALMON.64/SP.03.01/04/2026',
        'nd_date' => '01 Apr 2026',
        'signer' => 'Warko (Kepala Balmon)',
        'target_pct' => 86.00,
        'vuhf_tetap_pct' => 80.00,
        'bpp_transp_pct' => 100.00,
        'berau_transp_pct' => 100.00,
        'kutim_transp_pct' => 100.00,
        'avg_tetap_transp' => 95.00,
        'mobile_df_pct' => 90.00,
        'sla_gabungan' => 92.50,
        'quality_pct' => 100.00,
        'status' => 'achieved',
        'okupansi_count' => 4,
        'okupansi_dates' => '03, 09, 21, 25 Mar 2026',
        'spt_usage' => '10 Hari Transportable (SPT 25, 26, 32), 5 Hari Paser (SPT 27, 28), Posko Ramadan/Idul Fitri Bpp & Smd (SPT 31, 34, 37, 39, 40, 41, 42, 43)'
    ],
    4 => [
        'month_name' => 'April',
        'nd_number' => '246/BALMON.64/SP.03.01/05/2026',
        'nd_date' => '04 Mei 2026',
        'signer' => 'Warko (Kepala Balmon)',
        'target_pct' => 86.00,
        'vuhf_tetap_pct' => 80.00,
        'bpp_transp_pct' => 100.00,
        'berau_transp_pct' => 100.00,
        'kutim_transp_pct' => 100.00,
        'avg_tetap_transp' => 95.00,
        'mobile_df_pct' => 90.00,
        'sla_gabungan' => 92.50,
        'quality_pct' => 100.00,
        'status' => 'achieved',
        'okupansi_count' => 4,
        'okupansi_dates' => '01, 06, 13, 20 Apr 2026',
        'spt_usage' => '13 Hari Transportable (SPT 49, 53, 61, 69), 5 Hari Paser Ggn Kideco (SPT 46), 3 Hari Smd QoS (SPT 53), 4 Hari Kukar QoS (SPT 57), 4 Hari Bpp FM (SPT 59), 4 Hari Kutim Okupansi (SPT 60)'
    ],
    5 => [
        'month_name' => 'Mei',
        'nd_number' => '313/BALMON.64/SP.03.01/06/2026',
        'nd_date' => '03 Jun 2026',
        'signer' => 'Widhy Baskoro (Plh. Kepala Balmon)',
        'target_pct' => 86.00,
        'vuhf_tetap_pct' => 80.00,
        'bpp_transp_pct' => 100.00,
        'berau_transp_pct' => 75.00,
        'kutim_transp_pct' => 100.00,
        'avg_tetap_transp' => 88.75,
        'mobile_df_pct' => 90.00,
        'sla_gabungan' => 89.38,
        'quality_pct' => 100.00,
        'status' => 'achieved',
        'okupansi_count' => 4,
        'okupansi_dates' => '05, 11, 18, 25 Mei 2026',
        'spt_usage' => '11 Hari Transportable (SPT 73, 75, 80, 82), 4 Hari Kutim Penertiban I (SPT 71), 4 Hari Smd Penertiban I (SPT 72), 3 Hari Smd 15 Pita (SPT 75)'
    ],
    6 => ['month_name' => 'Juni', 'nd_number' => '-', 'nd_date' => '-', 'signer' => '-', 'target_pct' => 86.00, 'vuhf_tetap_pct' => 0, 'bpp_transp_pct' => 0, 'berau_transp_pct' => 0, 'kutim_transp_pct' => 0, 'avg_tetap_transp' => 0, 'mobile_df_pct' => 0, 'sla_gabungan' => 0, 'quality_pct' => 0, 'status' => 'pending', 'okupansi_count' => 0, 'okupansi_dates' => '-', 'spt_usage' => '-'],
    7 => ['month_name' => 'Juli', 'nd_number' => '-', 'nd_date' => '-', 'signer' => '-', 'target_pct' => 86.00, 'vuhf_tetap_pct' => 0, 'bpp_transp_pct' => 0, 'berau_transp_pct' => 0, 'kutim_transp_pct' => 0, 'avg_tetap_transp' => 0, 'mobile_df_pct' => 0, 'sla_gabungan' => 0, 'quality_pct' => 0, 'status' => 'pending', 'okupansi_count' => 0, 'okupansi_dates' => '-', 'spt_usage' => '-'],
    8 => ['month_name' => 'Agustus', 'nd_number' => '-', 'nd_date' => '-', 'signer' => '-', 'target_pct' => 86.00, 'vuhf_tetap_pct' => 0, 'bpp_transp_pct' => 0, 'berau_transp_pct' => 0, 'kutim_transp_pct' => 0, 'avg_tetap_transp' => 0, 'mobile_df_pct' => 0, 'sla_gabungan' => 0, 'quality_pct' => 0, 'status' => 'pending', 'okupansi_count' => 0, 'okupansi_dates' => '-', 'spt_usage' => '-'],
    9 => ['month_name' => 'September', 'nd_number' => '-', 'nd_date' => '-', 'signer' => '-', 'target_pct' => 86.00, 'vuhf_tetap_pct' => 0, 'bpp_transp_pct' => 0, 'berau_transp_pct' => 0, 'kutim_transp_pct' => 0, 'avg_tetap_transp' => 0, 'mobile_df_pct' => 0, 'sla_gabungan' => 0, 'quality_pct' => 0, 'status' => 'pending', 'okupansi_count' => 0, 'okupansi_dates' => '-', 'spt_usage' => '-'],
    10 => ['month_name' => 'Oktober', 'nd_number' => '-', 'nd_date' => '-', 'signer' => '-', 'target_pct' => 86.00, 'vuhf_tetap_pct' => 0, 'bpp_transp_pct' => 0, 'berau_transp_pct' => 0, 'kutim_transp_pct' => 0, 'avg_tetap_transp' => 0, 'mobile_df_pct' => 0, 'sla_gabungan' => 0, 'quality_pct' => 0, 'status' => 'in_progress', 'okupansi_count' => 0, 'okupansi_dates' => '-', 'spt_usage' => '-'],
    11 => ['month_name' => 'November', 'nd_number' => '-', 'nd_date' => '-', 'signer' => '-', 'target_pct' => 86.00, 'vuhf_tetap_pct' => 0, 'bpp_transp_pct' => 0, 'berau_transp_pct' => 0, 'kutim_transp_pct' => 0, 'avg_tetap_transp' => 0, 'mobile_df_pct' => 0, 'sla_gabungan' => 0, 'quality_pct' => 0, 'status' => 'pending', 'okupansi_count' => 0, 'okupansi_dates' => '-', 'spt_usage' => '-'],
    12 => ['month_name' => 'Desember', 'nd_number' => '-', 'nd_date' => '-', 'signer' => '-', 'target_pct' => 86.00, 'vuhf_tetap_pct' => 0, 'bpp_transp_pct' => 0, 'berau_transp_pct' => 0, 'kutim_transp_pct' => 0, 'avg_tetap_transp' => 0, 'mobile_df_pct' => 0, 'sla_gabungan' => 0, 'quality_pct' => 0, 'status' => 'pending', 'okupansi_count' => 0, 'okupansi_dates' => '-', 'spt_usage' => '-']
];

// Pemanfaatan Perangkat SMFR Berdasarkan Surat Perintah Tugas (SPT) Resmi
$pk6SptUtilization = [
    [
        'spt' => '02/BALMON.64/KP.01.06/01/2026',
        'dates' => '05 & 12 Jan 2026',
        'period' => 'Januari 2026',
        'station' => 'Stasiun Tetap HF & Transportable',
        'location' => 'Pulau Atas & Site CCU Samarinda, Berau, Kutim, Balikpapan',
        'equipment' => 'EM.100 R&S, LS Telcom CCU, FMU 306 Berau/Kutim/Bpp',
        'activity' => 'Monitoring Rutin HF/VHF, 15 Pita Frekuensi Bergerak, Monitoring Rutin HF',
        'duration' => '14 Hari',
        'output' => 'Laporan monitoring rutin harian, 15 pita frekuensi, dan monitoring HF Januari 2026'
    ],
    [
        'spt' => '07/BALMON.64/KP.01.06/01/2026',
        'dates' => '20 s.d. 23 Jan 2026',
        'period' => 'Januari 2026',
        'station' => 'Stasiun Portable',
        'location' => 'Kota Samarinda',
        'equipment' => 'Rohde & Schwarz PR200, HE400, Lenovo ThinkPad 14s Yoga',
        'activity' => 'Monitoring 15 Pita Frekuensi / Transportable',
        'duration' => '4 Hari',
        'output' => 'Laporan monitoring 15 pita frekuensi di Kota Samarinda'
    ],
    [
        'spt' => '12/BALMON.64/KP.01.06/02/2026',
        'dates' => '10 s.d. 14 Feb 2026',
        'period' => 'Februari 2026',
        'station' => 'Stasiun Portable',
        'location' => 'Kabupaten Kutai Kartanegara',
        'equipment' => 'R&S DDF007, Garmin GPSMAP 65S',
        'activity' => 'Pemantauan Spektrum Frekuensi Radio & Pengawasan APT',
        'duration' => '5 Hari',
        'output' => 'Laporan hasil pemantauan SFR dan APT di Kab. Kutai Kartanegara'
    ],
    [
        'spt' => '14/BALMON.64/KP.01.06/02/2026',
        'dates' => '10 s.d. 14 Feb 2026',
        'period' => 'Februari 2026',
        'station' => 'Stasiun Portable',
        'location' => 'Kabupaten Kutai Barat',
        'equipment' => 'R&S PR200, HE400, Lenovo ThinkPad 14s Yoga',
        'activity' => 'Pemantauan Spektrum Frekuensi Radio & Pengawasan APT',
        'duration' => '5 Hari',
        'output' => 'Laporan hasil pemantauan SFR dan APT di Kab. Kutai Barat'
    ],
    [
        'spt' => '22/BALMON.64/KP.01.06/02/2026',
        'dates' => '23 s.d. 28 Feb 2026',
        'period' => 'Februari 2026',
        'station' => 'Stasiun Portable & Mobile',
        'location' => 'Kota Bontang',
        'equipment' => 'MUMF Hilux, R&S PR200, GPSMAP 65S, Samsung Tab S9+',
        'activity' => 'Pemantauan Spektrum, Pengawasan APT & Pengukuran Radio FM',
        'duration' => '5 Hari',
        'output' => 'Laporan pemantauan SFR, APT dan pengukuran siaran FM Kota Bontang'
    ],
    [
        'spt' => '27/BALMON.64/KP.01.06/03/2026',
        'dates' => '10 s.d. 14 Mar 2026',
        'period' => 'Maret 2026',
        'station' => 'Stasiun Portable',
        'location' => 'Kabupaten Paser',
        'equipment' => 'R&S PR200, HE400, Garmin GPSMAP 65S',
        'activity' => 'Pemantauan Spektrum Frekuensi Radio & Pengawasan Perangkat Telekomunikasi',
        'duration' => '5 Hari',
        'output' => 'Laporan pemantauan SFR dan APT di Kab. Paser'
    ],
    [
        'spt' => '31/BALMON.64/KP.01.06/02/2026',
        'dates' => '15 s.d. 17 Mar 2026',
        'period' => 'Maret 2026',
        'station' => 'Stasiun Portable',
        'location' => 'Kota Balikpapan',
        'equipment' => 'R&S DDF007, Lenovo ThinkPad',
        'activity' => 'Monitoring Frekuensi & Kualitas Layanan Posko Ramadan & Idul Fitri',
        'duration' => '3 Hari',
        'output' => 'Laporan monitoring SFR dan QoS posko Ramadan/Idul Fitri Balikpapan'
    ],
    [
        'spt' => '46/BALMON.64/KP.01.06/04/2026',
        'dates' => '07 s.d. 11 Apr 2026',
        'period' => 'April 2026',
        'station' => 'Stasiun Portable & DF',
        'location' => 'Kabupaten Paser (PT. Kideco Jaya Agung)',
        'equipment' => 'R&S PR100, PR200, Anritsu MS2090A, HE300, HE400UWB, GPSMAP 65S',
        'activity' => 'Penanganan Gangguan Frekuensi Radio atas Pengaduan PT. Kideco Jaya Agung',
        'duration' => '5 Hari',
        'output' => 'Laporan kegiatan & BAP Penanganan Gangguan T-67 & T-68 PT Kideco'
    ],
    [
        'spt' => '57/BALMON.64/KP.01.06/04/2026',
        'dates' => '20 s.d. 23 Apr 2026',
        'period' => 'April 2026',
        'station' => 'Stasiun Portable QoS',
        'location' => 'Kabupaten Kutai Kartanegara',
        'equipment' => 'Hp Kualitas Layanan 3 Unit, Alat Ukur PMT',
        'activity' => 'Pengukuran Kualitas Layanan Infrastruktur Digital (QoS)',
        'duration' => '4 Hari',
        'output' => 'Laporan pengukuran kualitas layanan infrastruktur digital di Kab. Kukar'
    ],
    [
        'spt' => '71/BALMON.64/KP.01.06/04/2026',
        'dates' => '05 s.d. 08 Mei 2026',
        'period' => 'Mei 2026',
        'station' => 'Stasiun Portable',
        'location' => 'Kabupaten Kutai Timur',
        'equipment' => 'R&S PR200, HE400, Lenovo ThinkPad, Epson WF100',
        'activity' => 'Operasi Penertiban Terpadu Serentak Nasional Tahap I SFR & Standar Perangkat',
        'duration' => '4 Hari',
        'output' => 'Laporan hasil operasi Penertiban Terpadu Tahap I Kutai Timur'
    ],
    [
        'spt' => '72/BALMON.64/KP.01.06/04/2026',
        'dates' => '05 s.d. 08 Mei 2026',
        'period' => 'Mei 2026',
        'station' => 'Stasiun Portable',
        'location' => 'Kota Samarinda',
        'equipment' => 'Garmin GPSMAP 65S, SPA TIPE 1 Keysight N9951B',
        'activity' => 'Operasi Penertiban Terpadu Serentak Nasional Tahap I SFR & Standar Perangkat',
        'duration' => '4 Hari',
        'output' => 'Laporan hasil operasi Penertiban Terpadu Tahap I Kota Samarinda'
    ]
];

// Ringkasan Kondisi Inventaris BMN SMFR (148 Unit Perangkat)
$pk6InventoryStats = [
    'total_bmn' => 148,
    'total_baik' => 142,
    'total_rusak' => 6,
    'persentase_baik' => 95.95,
    'kategori' => [
        'Perangkat Utama SMFR' => ['total' => 28, 'baik' => 22, 'rusak' => 6, 'pct' => 78.57],
        'Perangkat Pendukung SMFR' => ['total' => 97, 'baik' => 97, 'rusak' => 0, 'pct' => 100.0],
        'Alat Komunikasi Operasional' => ['total' => 23, 'baik' => 23, 'rusak' => 0, 'pct' => 100.0]
    ],
    'stasiun' => [
        'Stasiun Tetap VUHF Samarinda' => ['lokasi' => 'Jl. Sultan Sulaiman Samarinda', 'total' => 16, 'baik' => 14, 'rusak' => 2, 'catatan' => 'R&S DDF 255 & Antena UHF Rusak (ND 141/2025)'],
        'Stasiun Fixed HF Pulau Atas' => ['lokasi' => 'Pulau Atas, Samarinda', 'total' => 14, 'baik' => 11, 'rusak' => 3, 'catatan' => 'Receiver TCI Scorpio, DF Array, Server Rusak (BA 112/2023)'],
        'Stasiun Transportable Balikpapan' => ['lokasi' => 'Site Balikpapan', 'total' => 5, 'baik' => 5, 'rusak' => 0, 'catatan' => 'Operasional Prima (SLA 100%)'],
        'Stasiun Transportable Berau' => ['lokasi' => 'Site Berau', 'total' => 5, 'baik' => 4, 'rusak' => 1, 'catatan' => 'All Band Receiver FMU 306 Rusak per 25 Mei 2026 (ND 292/2026)'],
        'Stasiun Transportable Kutai Timur' => ['lokasi' => 'Site Sangatta, Kutim', 'total' => 5, 'baik' => 5, 'rusak' => 0, 'catatan' => 'Operasional Prima (SLA 100%)'],
        'Stasiun Transportable Kutai Barat' => ['lokasi' => 'Site Sendawar, Kubar', 'total' => 5, 'baik' => 5, 'rusak' => 0, 'catatan' => 'Operasional Prima'],
        'Stasiun CCU Samarinda' => ['lokasi' => 'Kantor Balmon Samarinda', 'total' => 4, 'baik' => 4, 'rusak' => 0, 'catatan' => 'Central Control Unit Berfungsi Normal'],
        'Stasiun Bergerak Mobil Mon DF' => ['lokasi' => 'Toyota Hilux KT 1344 MZ', 'total' => 19, 'baik' => 18, 'rusak' => 1, 'catatan' => 'R&S ESMD DF Front Panel Rusak per Feb 2026 (SLA 90%)'],
        'Peralatan Portable & Pengukuran' => ['lokasi' => 'Laboratorium & Gudang Balmon', 'total' => 75, 'baik' => 75, 'rusak' => 0, 'catatan' => 'PR100, PR200, Anritsu MS2090A, Keysight, Handheld Antena Baik']
    ]
];

// Auto-sync PK.6 dengan data resmi Balmon Samarinda 2026 jika belum tersinkronisasi
if ($selectedCode === 'PK.6' && $selectedYear === 2026) {
    try {
        $pdo->prepare("
            UPDATE perjanjian_kinerja SET 
                realization = 92.25, 
                target_annual = 86, 
                notes = 'Realisasi rata-rata s.d. Mei 2026: 92.25% (Target: 86%, Kualitas: 100%). Januari: 94.38%, Februari: 92.50%, Maret: 92.50%, April: 92.50%, Mei: 89.38%. Terjaganya operasional 3 Stasiun Transportable, 1 Stasiun Bergerak DF, dan Stasiun Tetap VUHF Samarinda.' 
            WHERE code = 'PK.6' AND year = ?
        ")->execute([$selectedYear]);
        $activePk['realization'] = 92.25;
        $activePk['target_annual'] = 86;
    } catch (\Exception $e) {}
}

// Auto-sync PK.3 dengan data resmi Balmon Samarinda 2026 jika belum tersinkronisasi
if ($selectedCode === 'PK.3' && $selectedYear === 2026) {
    try {
        $pdo->prepare("
            UPDATE perjanjian_kinerja SET 
                realization = 100.0, 
                target_annual = 100, 
                notes = 'Realisasi TA 2026: 100.0% (Target: 100%). Total 2 kasus pengaduan resmi (PT. Kideco Jaya Agung di Kab. Paser pada April 2026 dan PT. Multi Harapan Utama di Kab. Kukar pada Agustus 2026), keduanya tertangani dan terselesaikan tuntas 100% (Clean - Bebas Gangguan) dengan penerbitan BAP dan penghentian sumber gangguan. Bulan lainnya nihil/tidak ada kegiatan penanganan gangguan.' 
            WHERE code = 'PK.3' AND year = ?
        ")->execute([$selectedYear]);
        $activePk['realization'] = 100.0;
        $activePk['target_annual'] = 100;
    } catch (\Exception $e) {}
}

// ============================================================
// DATA KHUSUS RESMI PK.3: PENANGANAN GANGGUAN FREKUENSI RADIO TAHUN 2026
// Sumber: Laporan Penanganan Gangguan, BAP & SPT Balai Monitor Kelas I Samarinda
// Berdasarkan Trouble Ticket SFR (laporgangguansfr.postel.go.id) & SOP Ditjen SDPPI
// Total Kasus: 2 Kasus Aduan Resmi (100% Selesai Tuntas - Bebas Gangguan)
// ============================================================
$pk3OfficialCases = [
    [
        'id' => 1,
        'case_no' => '01/GANGGUAN/2026',
        'month_num' => 4,
        'month_name' => 'April',
        'pelapor' => 'PT. Kideco Jaya Agung',
        'complaint_number' => '145/180/C/III/2026-01',
        'complaint_date' => '13 Maret 2026',
        'spt_number' => '46/BALMON.64/KP.01.06/04/2026',
        'spt_date' => '14 April 2026',
        'execution_dates' => '07 s.d. 11 April 2026 (5 Hari)',
        'location' => 'Kabupaten Paser',
        'sub_location' => 'Kec. Batu Sopang, Site SIMS, Desa Songka',
        'frequency' => '173.675 MHz & 173.250 MHz',
        'affected_service' => 'Radio Komunikasi Tambang Land Mobile (Private)',
        'isr_numbers' => '03037474-000SU/2620252030 (173.675 MHz) & 03037508-000SU/2620252030 (173.250 MHz)',
        'source_identified' => 'Intermodulasi frekuensi 173.625 MHz & 173.675 MHz serta external noise induksi Gardu Induk PLN Batu Sopang pada 173.250 MHz',
        'action_taken' => 'Pelacakan sinyal (DF) di 13 titik ukur di area konsesi tambang, pengukuran rasio proteksi & level noise floor dengan R&S PR100 & Anritsu MS2090A. Diterbitkan BAP Pemeriksaan & Penanganan Gangguan dengan rekomendasi modifikasi kanal ISR & filter.',
        'resolution_status' => 'Terselesaikan Tuntas (Clean - Bebas Gangguan)',
        'resolution_badge' => 'Clean 100%',
        'bap_number' => 'T-67/BALMON.64/SP.03.05/04/2026 & T-68/BALMON.64/SP.03.03/04/2026',
        'bap_date' => '11 April 2026',
        'teguran_number' => '-',
        'team_leader' => 'Sri Sukisno, ST (NIP: 19831107 200502 1 001)',
        'team_members' => 'Rifani Tria Agustin, A.md.T, Rendy Tri Atmadja, ST',
        'equipment_used' => 'R&S PR100, Anritsu MS2090A, R&S HE300, HE400UWB, Laptop R&S Argus, GPS Garmin GPSMAP-65S',
        'disposition' => 'Disposisi Kepala Balai: Tindaklanjuti dan diselesaikan, input laporan secara online, file sebagai lampiran pertanggungjawaban periodik.',
        'sla_compliance' => '< 24 Jam (SOP Penerbitan SPT & Penanganan Cepat)',
        'badge_color' => 'success'
    ],
    [
        'id' => 2,
        'case_no' => '02/GANGGUAN/2026',
        'month_num' => 8,
        'month_name' => 'Agustus',
        'pelapor' => 'PT. Multi Harapan Utama (MHU)',
        'complaint_number' => '0458/OL/MHU-KTT/VII/2026',
        'complaint_date' => '27 Juli 2026',
        'spt_number' => '113/BALMON.64/KP.01.06/08/2026',
        'spt_date' => '04 Agustus 2026',
        'execution_dates' => '10 s.d. 13 Agustus 2026 (4 Hari)',
        'location' => 'Kabupaten Kutai Kartanegara',
        'sub_location' => 'Kec. Loa Kulu, Desa Loa Duri, Desa Bakungan',
        'frequency' => 'Tx 159.250 MHz / Rx 154.250 MHz',
        'affected_service' => 'Radio Repeater Komunikasi Tambang MHU (Land Mobile)',
        'isr_numbers' => '03112651-000SU/2620262031 (Repeater Stasiun)',
        'source_identified' => 'Kebocoran Booster Penyelenggara Siaran TV Kabel (Surya Kabel TV Digital & TV Kabel Haji Amir) pada Channel S08 (154.250 MHz)',
        'action_taken' => 'Pelacakan sumber interferensi menggunakan Mobil Monitoring & DF Toyota Hilux (R&S ESMD & PR100). Ditemukan radiasi liar kebocoran amplifier booster TV kabel di Loa Janan & Loa Kulu. Diterbitkan Surat Teguran T-444/BALMON.64/SP.03.03/08/2026 dan penghentian pancaran hingga frekuensi Rx 154.250 MHz & Tx 159.250 MHz clear 100%.',
        'resolution_status' => 'Terselesaikan Tuntas (Clean - Bebas Gangguan)',
        'resolution_badge' => 'Clean 100%',
        'bap_number' => 'T-228/BALMON.64/SP.03.03/08/2026 & T-229/BALMON.64/SP.03.03/08/2026',
        'bap_date' => '13 Agustus 2026',
        'teguran_number' => 'T-444/BALMON.64/SP.03.03/08/2026 (05 Ags 2026)',
        'team_leader' => 'Andreo Yustiantoro Anjaya, ST (NIP: 19770506 200803 1 001)',
        'team_members' => 'Warko, S.Kom, M.Si, Sri Sukisno, ST, Tantowi Pahala Hamonangan S, S.Kom',
        'equipment_used' => 'Mobil Monitoring & DF Toyota Hilux, R&S PR100, R&S ESMD DF Mode (20 MHz - 3 GHz), HE300, HE600, Laptop Argus, Garmin GPSMAP-65S',
        'disposition' => 'Disposisi Kepala Balai: Dilakukan pemantauan dan koordinasi lebih lanjut ke pihak terganggu terkait hasil penanganan gangguan. Laporan disimpan dan diarsipkan sebagai pertanggungjawaban perjalanan dinas.',
        'sla_compliance' => '< 24 Jam (SOP Penerbitan SPT & Penanganan Cepat)',
        'badge_color' => 'success'
    ]
];

$pk3OfficialTable = [
    1 => ['month_name' => 'Januari', 'kegiatan' => 'Tidak Ada Kegiatan Penanganan Gangguan', 'ket' => 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Januari 2026 (Kondisi spektrum kondusif)', 'target_val' => '-', 'realization_val' => '-', 'cum_pct' => 100.0, 'status' => 'achieved', 'doc_number' => '-', 'doc_date' => 'Jan 2026', 'has_case' => false, 'case_id' => 0],
    2 => ['month_name' => 'Februari', 'kegiatan' => 'Tidak Ada Kegiatan Penanganan Gangguan', 'ket' => 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Februari 2026 (Kondisi spektrum kondusif)', 'target_val' => '-', 'realization_val' => '-', 'cum_pct' => 100.0, 'status' => 'achieved', 'doc_number' => '-', 'doc_date' => 'Feb 2026', 'has_case' => false, 'case_id' => 0],
    3 => ['month_name' => 'Maret', 'kegiatan' => 'Tidak Ada Kegiatan Penanganan Gangguan', 'ket' => 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Maret 2026 (Kondisi spektrum kondusif)', 'target_val' => '-', 'realization_val' => '-', 'cum_pct' => 100.0, 'status' => 'achieved', 'doc_number' => '-', 'doc_date' => 'Mar 2026', 'has_case' => false, 'case_id' => 0],
    4 => ['month_name' => 'April', 'kegiatan' => 'Penanganan Gangguan Frekuensi Radio PT. Kideco Jaya Agung', 'ket' => 'Kab. Paser • Aduan radio komunikasi tambang (173.675 & 173.250 MHz): pelacakan DF 13 titik ukur, eliminasi intermodulasi & noise PLN Batu Sopang (BAP T-67 & T-68)', 'target_val' => '1 Aduan', 'realization_val' => '1 Selesai', 'cum_pct' => 100.0, 'status' => 'achieved', 'doc_number' => 'T-67/BALMON.64/SP.03.05/04/2026', 'doc_date' => '14 Apr 2026', 'has_case' => true, 'case_id' => 1],
    5 => ['month_name' => 'Mei', 'kegiatan' => 'Tidak Ada Kegiatan Penanganan Gangguan', 'ket' => 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Mei 2026 (Kondisi spektrum kondusif)', 'target_val' => '-', 'realization_val' => '-', 'cum_pct' => 100.0, 'status' => 'achieved', 'doc_number' => '-', 'doc_date' => 'Mei 2026', 'has_case' => false, 'case_id' => 0],
    6 => ['month_name' => 'Juni', 'kegiatan' => 'Tidak Ada Kegiatan Penanganan Gangguan', 'ket' => 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Juni 2026 (Kondisi spektrum kondusif)', 'target_val' => '-', 'realization_val' => '-', 'cum_pct' => 100.0, 'status' => 'achieved', 'doc_number' => '-', 'doc_date' => 'Jun 2026', 'has_case' => false, 'case_id' => 0],
    7 => ['month_name' => 'Juli', 'kegiatan' => 'Tidak Ada Kegiatan Penanganan Gangguan', 'ket' => 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Juli 2026 (Kondisi spektrum kondusif)', 'target_val' => '-', 'realization_val' => '-', 'cum_pct' => 100.0, 'status' => 'achieved', 'doc_number' => '-', 'doc_date' => 'Jul 2026', 'has_case' => false, 'case_id' => 0],
    8 => ['month_name' => 'Agustus', 'kegiatan' => 'Penanganan Gangguan Frekuensi Radio PT. Multi Harapan Utama', 'ket' => 'Kab. Kutai Kartanegara • Aduan repeater radio tambang Tx 159.250 / Rx 154.250 MHz: DF Mobil Hilux ESMD, eliminasi kebocoran booster TV kabel Loa Janan (BAP T-228 & T-229)', 'target_val' => '1 Aduan', 'realization_val' => '1 Selesai', 'cum_pct' => 100.0, 'status' => 'achieved', 'doc_number' => 'T-228/BALMON.64/SP.03.03/08/2026', 'doc_date' => '13 Ags 2026', 'has_case' => true, 'case_id' => 2],
    9 => ['month_name' => 'September', 'kegiatan' => 'Tidak Ada Kegiatan Penanganan Gangguan', 'ket' => 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan September 2026 (Kondisi spektrum kondusif)', 'target_val' => '-', 'realization_val' => '-', 'cum_pct' => 100.0, 'status' => 'achieved', 'doc_number' => '-', 'doc_date' => 'Sep 2026', 'has_case' => false, 'case_id' => 0],
    10 => ['month_name' => 'Oktober', 'kegiatan' => 'Tidak Ada Kegiatan Penanganan Gangguan', 'ket' => 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Oktober 2026 (Kondisi spektrum kondusif)', 'target_val' => '-', 'realization_val' => '-', 'cum_pct' => 100.0, 'status' => 'achieved', 'doc_number' => '-', 'doc_date' => 'Okt 2026', 'has_case' => false, 'case_id' => 0],
    11 => ['month_name' => 'November', 'kegiatan' => 'Tidak Ada Kegiatan Penanganan Gangguan', 'ket' => 'Siaga monitoring & respon cepat penanganan gangguan frekuensi radio 1x24 jam (dinas keselamatan) / 7x24 jam (non-keselamatan)', 'target_val' => '-', 'realization_val' => '-', 'cum_pct' => 100.0, 'status' => 'pending', 'doc_number' => 'SOP Respon Cepat', 'doc_date' => 'Nov 2026', 'has_case' => false, 'case_id' => 0],
    12 => ['month_name' => 'Desember', 'kegiatan' => 'Tidak Ada Kegiatan Penanganan Gangguan', 'ket' => 'Rekonsiliasi tahunan trouble ticket laporgangguansfr.postel.go.id dan evaluasi penanganan gangguan frekuensi radio TA 2026', 'target_val' => '-', 'realization_val' => '-', 'cum_pct' => 100.0, 'status' => 'pending', 'doc_number' => 'Laporan Tahunan', 'doc_date' => 'Des 2026', 'has_case' => false, 'case_id' => 0]
];

// ============================================================
// DATA KHUSUS RESMI PK.7: PENANGANAN SANKSI DENDA ADMINISTRATIF TAHUN 2026
// Sumber: Surat Tagihan Denda Administratif (Tagihan Bulan ke-1)
// Balai Monitor Spektrum Frekuensi Radio Kelas I Samarinda
// Berdasarkan PP No. 43/2023, PP No. 28/2025, dan Perdirjen SDPPI No. 3/2024
// Total Ketetapan Denda: Rp 201.258.400 (4 Surat Tagihan Denda Resmi)
// ============================================================
$pk7OfficialInvoices = [
    [
        'no' => 1,
        'spt_number' => '12 & 14/BALMON.64/KP.01.06/02/2026',
        'teguran_number' => 'T-82/BALMON.64/SP.03.03/02/2026',
        'teguran_date' => '09 Februari 2026',
        'ba_number' => 'T-13/BALMON.64/SP.03.03/02/2026 (BA Klarifikasi)',
        'ba_date' => '09 Februari 2026',
        'tagihan_number' => '2000361/BALMON.64/KU.03.02/02/2026',
        'tagihan_date' => '20 Februari 2026',
        'tagihan_bulan_ke' => 1,
        'badan_hukum' => 'PLN (PERSERO)',
        'client_id' => '00103494',
        'alamat' => 'JL. TRUNOJOYO BLOK M 1 NO.135',
        'nik_nib' => '- / 8120003820135',
        'npwp' => '-',
        'no_invoice' => '3003186',
        'no_aplikasi' => '-',
        'kode_billing' => '820260220370222',
        'tipe_pelanggaran' => 'Penggunaan Spektrum Frekuensi Radio tanpa Perizinan Berusaha dan/atau persetujuan dari Menteri (PP 28/2025)',
        'wilayah_cakupan' => 'Kutai Timur, Samarinda, Balikpapan, Kutai Kartanegara, Bontang',
        'jumlah_objek' => 17,
        'denda_pokok' => 135935800,
        'denda_keterlambatan' => 0,
        'total_denda' => 135935800,
        'terbilang' => 'Seratus Tiga Puluh Lima Juta Sembilan Ratus Tiga Puluh Lima Ribu Delapan Ratus Rupiah',
        'jatuh_tempo' => '19 Maret 2026',
        'penandatangan' => 'Warko (Kepala Balmon Kelas I Samarinda)',
        'status' => 'Tagihan Bulan Ke-1',
        'items' => [
            ['no' => 1, 'wilayah' => 'Kutai Timur', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - HT', 'unit' => 1, 'zona' => 3, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.0018', 'denda' => 415800],
            ['no' => 2, 'wilayah' => 'Kota Samarinda', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - RP', 'unit' => 1, 'zona' => 2, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.044', 'denda' => 10164000],
            ['no' => 3, 'wilayah' => 'Kota Balikpapan', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - RP', 'unit' => 1, 'zona' => 2, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.044', 'denda' => 10164000],
            ['no' => 4, 'wilayah' => 'Kota Balikpapan', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - RP', 'unit' => 1, 'zona' => 2, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.044', 'denda' => 10164000],
            ['no' => 5, 'wilayah' => 'Kutai Kartanegara', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - RP', 'unit' => 1, 'zona' => 3, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.033', 'denda' => 7623000],
            ['no' => 6, 'wilayah' => 'Kutai Kartanegara', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - RP', 'unit' => 1, 'zona' => 3, 'tgl_operasi' => '22 Oktober 2021', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '100%', 'indeks' => '0.033', 'denda' => 23100000],
            ['no' => 7, 'wilayah' => 'Kutai Kartanegara', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - BS', 'unit' => 1, 'zona' => 3, 'tgl_operasi' => '22 Oktober 2021', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '100%', 'indeks' => '0.022', 'denda' => 15400000],
            ['no' => 8, 'wilayah' => 'Kutai Kartanegara', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - BS', 'unit' => 1, 'zona' => 3, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.022', 'denda' => 5082000],
            ['no' => 9, 'wilayah' => 'Kota Samarinda', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - BS', 'unit' => 1, 'zona' => 2, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.029', 'denda' => 6699000],
            ['no' => 10, 'wilayah' => 'Kutai Kartanegara', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - BS', 'unit' => 1, 'zona' => 3, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.022', 'denda' => 5082000],
            ['no' => 11, 'wilayah' => 'Kota Samarinda', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - BS', 'unit' => 1, 'zona' => 2, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.029', 'denda' => 6699000],
            ['no' => 12, 'wilayah' => 'Kutai Kartanegara', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - BS', 'unit' => 1, 'zona' => 3, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.022', 'denda' => 5082000],
            ['no' => 13, 'wilayah' => 'Kota Balikpapan', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - BS', 'unit' => 1, 'zona' => 2, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.029', 'denda' => 6699000],
            ['no' => 14, 'wilayah' => 'Kota Balikpapan', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - BS', 'unit' => 1, 'zona' => 2, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.029', 'denda' => 6699000],
            ['no' => 15, 'wilayah' => 'Kota Balikpapan', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - BS', 'unit' => 1, 'zona' => 2, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.029', 'denda' => 6699000],
            ['no' => 16, 'wilayah' => 'Kota Bontang', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - BS', 'unit' => 1, 'zona' => 3, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.022', 'denda' => 5082000],
            ['no' => 17, 'wilayah' => 'Kutai Kartanegara', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) - BS', 'unit' => 1, 'zona' => 3, 'tgl_operasi' => '01 September 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.022', 'denda' => 5082000],
        ]
    ],
    [
        'no' => 2,
        'spt_number' => '72/BALMON.64/KP.01.06/04/2026 (Operasi Penertiban Terpadu Samarinda)',
        'teguran_number' => 'T-254/BALMON.64/SP.03.03/05/2026',
        'teguran_date' => '05 Mei 2026',
        'ba_number' => 'T-100/BALMON.64/SP.03.03/05/2026 (BA Penghentian SFR)',
        'ba_date' => '05 Mei 2026',
        'tagihan_number' => '2003409/BALMON.64/KU.03.02/05/2026',
        'tagihan_date' => '13 Mei 2026',
        'tagihan_bulan_ke' => 1,
        'badan_hukum' => 'LINTASMAYA MULTI MEDIA, PT .',
        'client_id' => '00120014',
        'alamat' => 'JL. CENDANA GG. 4 NO. 24',
        'nik_nib' => '- / 8120009851800',
        'npwp' => '852693399722000',
        'no_invoice' => '-',
        'no_aplikasi' => '00740020526',
        'kode_billing' => '820260513325337',
        'tipe_pelanggaran' => 'Penggunaan Spektrum Frekuensi Radio tanpa Perizinan Berusaha dan/atau persetujuan dari Menteri (PP 28/2025)',
        'wilayah_cakupan' => 'Kota Samarinda',
        'jumlah_objek' => 1,
        'denda_pokok' => 20097000,
        'denda_keterlambatan' => 0,
        'total_denda' => 20097000,
        'terbilang' => 'Dua Puluh Juta Sembilan Puluh Tujuh Ribu Rupiah',
        'jatuh_tempo' => '12 Juni 2026',
        'penandatangan' => 'Warko (Kepala Balmon Kelas I Samarinda)',
        'status' => 'Tagihan Bulan Ke-1',
        'items' => [
            ['no' => 1, 'wilayah' => 'Kota Samarinda', 'layanan' => 'Dinas Tetap', 'sub' => 'Point To Point (Pp) 5700 MHz', 'unit' => 1, 'zona' => 2, 'tgl_operasi' => '05 Juli 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.087', 'denda' => 20097000]
        ]
    ],
    [
        'no' => 3,
        'spt_number' => '71/BALMON.64/KP.01.06/04/2026 (Operasi Penertiban Terpadu Kutim)',
        'teguran_number' => 'T-256/BALMON.64/SP.03.03/05/2026',
        'teguran_date' => '05 Mei 2026',
        'ba_number' => 'T-106/BALMON.64/SP.03.03/05/2026 (BA Penghentian SFR)',
        'ba_date' => '05 Mei 2026',
        'tagihan_number' => '2000397/BALMON.64/KU.03.02/05/2026',
        'tagihan_date' => '13 Mei 2026',
        'tagihan_bulan_ke' => 1,
        'badan_hukum' => 'PERDANA TEKNOLOGI PERSADA, PT .',
        'client_id' => '00122134',
        'alamat' => 'Jl. Soekarno Hatta Ruko 5 No. 5 RT. 26, Kel. Teluk Lingga, Kec. Sangatta Utara, Kab. Kutai Timur',
        'nik_nib' => '- / 9120006182763',
        'npwp' => '759075518724000',
        'no_invoice' => '-',
        'no_aplikasi' => '00750020526',
        'kode_billing' => '820260513325436',
        'tipe_pelanggaran' => 'Penggunaan Spektrum Frekuensi Radio tanpa Perizinan Berusaha dan/atau persetujuan dari Menteri (PP 28/2025)',
        'wilayah_cakupan' => 'Kabupaten Kutai Timur',
        'jumlah_objek' => 2,
        'denda_pokok' => 30030000,
        'denda_keterlambatan' => 0,
        'total_denda' => 30030000,
        'terbilang' => 'Tiga Puluh Juta Tiga Puluh Ribu Rupiah',
        'jatuh_tempo' => '12 Juni 2026',
        'penandatangan' => 'Warko (Kepala Balmon Kelas I Samarinda)',
        'status' => 'Tagihan Bulan Ke-1',
        'items' => [
            ['no' => 1, 'wilayah' => 'Kutai Timur', 'layanan' => 'Dinas Tetap', 'sub' => 'Point To Point (Pp) 5825 MHz - Mikrotik NetMetal ac2', 'unit' => 1, 'zona' => 3, 'tgl_operasi' => '01 Agustus 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.065', 'denda' => 15015000],
            ['no' => 2, 'wilayah' => 'Kutai Timur', 'layanan' => 'Dinas Tetap', 'sub' => 'Point To Point (Pp) 5700 MHz - Ubiquiti AF-5XHD', 'unit' => 1, 'zona' => 3, 'tgl_operasi' => '01 Agustus 2025', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '33%', 'indeks' => '0.065', 'denda' => 15015000]
        ]
    ],
    [
        'no' => 4,
        'spt_number' => '93 & 96/BALMON.64/KP.01.06/06/2026 (Operasi Penertiban Berau)',
        'teguran_number' => 'T-339/BALMON.64/SP.03.03/06/2026',
        'teguran_date' => '23 Juni 2026',
        'ba_number' => 'T-156/BALMON.64/SP.03.03/06/2026 (BA Penghentian SFR)',
        'ba_date' => '23 Juni 2026',
        'tagihan_number' => '2000417/BALMON.64/KU.03.02/07/2026',
        'tagihan_date' => '06 Juli 2026',
        'tagihan_bulan_ke' => 1,
        'badan_hukum' => 'PT. Hamparan Anugrah Abadi',
        'client_id' => '-',
        'alamat' => 'Jl. Pulau Manimbora No.88, Kel. Tanjung Redeb, Kec. Tanjung Redeb, Kab. Berau',
        'nik_nib' => '- / 9120300192116',
        'npwp' => '023937394727000',
        'no_invoice' => '-',
        'no_aplikasi' => '00030020726',
        'kode_billing' => '820260706982766',
        'tipe_pelanggaran' => 'Penggunaan Spektrum Frekuensi Radio tanpa Perizinan Berusaha dan/atau persetujuan dari Menteri (PP 28/2025)',
        'wilayah_cakupan' => 'Kabupaten Berau',
        'jumlah_objek' => 4,
        'denda_pokok' => 15195600,
        'denda_keterlambatan' => 0,
        'total_denda' => 15195600,
        'terbilang' => 'Lima Belas Juta Seratus Sembilan Puluh Lima Ribu Enam Ratus Rupiah',
        'jatuh_tempo' => '05 Agustus 2026',
        'penandatangan' => 'Warko (Kepala Balmon Kelas I Samarinda)',
        'status' => 'Tagihan Bulan Ke-1',
        'items' => [
            ['no' => 1, 'wilayah' => 'Kab. Berau', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) Ht/Mobile Unit 167.750 MHz', 'unit' => 9, 'zona' => 4, 'tgl_operasi' => '30 Juli 2024', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '67%', 'indeks' => '0.0009', 'denda' => 3798900],
            ['no' => 2, 'wilayah' => 'Kab. Berau', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) Ht/Mobile Unit 167.525 MHz', 'unit' => 9, 'zona' => 4, 'tgl_operasi' => '30 Juli 2024', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '67%', 'indeks' => '0.0009', 'denda' => 3798900],
            ['no' => 3, 'wilayah' => 'Kab. Berau', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) Ht/Mobile Unit 151.725 MHz', 'unit' => 9, 'zona' => 4, 'tgl_operasi' => '30 Juli 2024', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '67%', 'indeks' => '0.0009', 'denda' => 3798900],
            ['no' => 4, 'wilayah' => 'Kab. Berau', 'layanan' => 'Bergerak Darat (Private)', 'sub' => 'Radio Konvensional (Standard) Ht/Mobile Unit 160.250 MHz', 'unit' => 9, 'zona' => 4, 'tgl_operasi' => '30 Juli 2024', 'max_poin' => 7000, 'tarif_poin' => 100000, 'bobot' => '67%', 'indeks' => '0.0009', 'denda' => 3798900],
        ]
    ]
];

$pk7OfficialTable = [
    1 => [
        'month_name' => 'Januari',
        'kegiatan' => 'Verifikasi Data Pelanggaran SFR & Penyiapan Berkas Administrasi Penindakan Hukum',
        'target_val' => 'Persiapan Berkas',
        'realization_val' => '1 Selesai',
        'cum_pct' => 60.0,
        'doc_number' => 'Internal / Rutin Balmon',
        'doc_date' => '31 Jan 2026',
        'status' => 'achieved',
        'nilai_denda' => 0,
        'ket' => 'Inventarisasi indikasi stasiun tanpa ISR dan penyiapan instrumen teguran'
    ],
    2 => [
        'month_name' => 'Februari',
        'kegiatan' => 'Penerbitan Surat Tagihan Denda I No. 2000361 kepada PLN (PERSERO) (17 Objek Pelanggaran)',
        'target_val' => '1 Surat Tagihan',
        'realization_val' => '1 Terbit (17 Stasiun)',
        'cum_pct' => 70.0,
        'doc_number' => '2000361/BALMON.64/KU.03.02/02/2026',
        'doc_date' => '20 Feb 2026',
        'status' => 'achieved',
        'nilai_denda' => 135935800,
        'ket' => 'Kode Billing: 820260220370222 • Nilai: Rp 135.935.800 • Jatuh tempo 19 Mar 2026'
    ],
    3 => [
        'month_name' => 'Maret',
        'kegiatan' => 'Monitoring Pembayaran SIMPONI & Evaluasi Jatuh Tempo Surat Tagihan PLN',
        'target_val' => 'Monitoring Bayar',
        'realization_val' => '1 SPP Dipantau',
        'cum_pct' => 72.5,
        'doc_number' => 'T-82 & T-13/BALMON.64/SP.03.03',
        'doc_date' => '19 Mar 2026',
        'status' => 'achieved',
        'nilai_denda' => 0,
        'ket' => 'Evaluasi status invoice 3003186 dan konfirmasi billing MPN G3'
    ],
    4 => [
        'month_name' => 'April',
        'kegiatan' => 'Rekonsiliasi Penerimaan PNBP Denda Triwulan I & Penyiapan Berkas Operasi Terpadu',
        'target_val' => 'Rekonsiliasi TW I',
        'realization_val' => '1 Selesai',
        'cum_pct' => 75.0,
        'doc_number' => 'Laporan Rekonsiliasi TW I',
        'doc_date' => '30 Apr 2026',
        'status' => 'achieved',
        'nilai_denda' => 0,
        'ket' => 'Pemberkasan hasil klarifikasi lapangan menjelang Operasi Penertiban Serentak'
    ],
    5 => [
        'month_name' => 'Mei',
        'kegiatan' => 'Penerbitan Surat Tagihan I No. 2003409 (PT Lintasmaya) & No. 2000397 (PT Perdana Tekno)',
        'target_val' => '2 Surat Tagihan',
        'realization_val' => '2 Terbit (3 Link)',
        'cum_pct' => 85.0,
        'doc_number' => '2003409 & 2000397/BALMON.64/05/2026',
        'doc_date' => '13 Mei 2026',
        'status' => 'achieved',
        'nilai_denda' => 50127000,
        'ket' => 'Lintasmaya Rp 20.097.000 (Billing 820260513325337) & Perdana Rp 30.030.000 (Billing 820260513325436)'
    ],
    6 => [
        'month_name' => 'Juni',
        'kegiatan' => 'Monitoring Pembayaran Tagihan Mei & Rekonsiliasi PNBP Sanksi Denda Semester I',
        'target_val' => 'Rekonsiliasi Sem I',
        'realization_val' => '1 Selesai',
        'cum_pct' => 85.0,
        'doc_number' => 'BA Penghentian T-100 & T-106',
        'doc_date' => '30 Jun 2026',
        'status' => 'achieved',
        'nilai_denda' => 0,
        'ket' => 'Pemantauan penghentian pemancar ilegal 5.7 & 5.8 GHz pasca jatuh tempo 12 Juni 2026'
    ],
    7 => [
        'month_name' => 'Juli',
        'kegiatan' => 'Penerbitan Surat Tagihan I No. 2000417 kepada PT Hamparan Anugrah Abadi (Berau)',
        'target_val' => '1 Surat Tagihan',
        'realization_val' => '1 Terbit (4 Freq)',
        'cum_pct' => 90.0,
        'doc_number' => '2000417/BALMON.64/KU.03.02/07/2026',
        'doc_date' => '06 Jul 2026',
        'status' => 'achieved',
        'nilai_denda' => 15195600,
        'ket' => 'Kode Billing: 820260706982766 • Nilai: Rp 15.195.600 • 36 Unit HT VHF • Jatuh tempo 05 Ags 2026'
    ],
    8 => [
        'month_name' => 'Agustus',
        'kegiatan' => 'Monitoring Jatuh Tempo Tagihan Berau & Pembinaan Wajib Bayar Pengajuan Izin ISR',
        'target_val' => 'Monitoring Bayar',
        'realization_val' => '1 SPP Dipantau',
        'cum_pct' => 92.5,
        'doc_number' => 'T-339 & T-156/BALMON.64/SP.03.03',
        'doc_date' => '31 Ags 2026',
        'status' => 'achieved',
        'nilai_denda' => 0,
        'ket' => 'Pendampingan proses perizinan berusaha spektrum frekuensi radio di OSS-RBA'
    ],
    9 => [
        'month_name' => 'September',
        'kegiatan' => 'Rekonsiliasi Triwulan III Penerimaan Kas Negara Sanksi Denda & Evaluasi Penegakan Hukum',
        'target_val' => 'Rekonsiliasi TW III',
        'realization_val' => '1 Selesai',
        'cum_pct' => 95.0,
        'doc_number' => 'Laporan Rekonsiliasi TW III',
        'doc_date' => '30 Sep 2026',
        'status' => 'achieved',
        'nilai_denda' => 0,
        'ket' => 'Total ketetapan 4 SPP: Rp 201.258.400 terevaluasi lengkap dalam pelaporan TW III'
    ],
    10 => [
        'month_name' => 'Oktober',
        'kegiatan' => 'Optimalisasi Penagihan Berjalan & Koordinasi Wajib Bayar Terkait Denda Keterlambatan 2%',
        'target_val' => 'Optimalisasi Aktif',
        'realization_val' => 'Proses',
        'cum_pct' => 95.0,
        'doc_number' => 'Koordinasi Penagihan Okt 2026',
        'doc_date' => '24 Okt 2026',
        'status' => 'in_progress',
        'nilai_denda' => 0,
        'ket' => 'Penyiapan surat tagihan bulan ke-2 bagi tagihan yang melewati jatuh tempo'
    ],
    11 => [
        'month_name' => 'November',
        'kegiatan' => 'Pemberkasan Piutang & Evaluasi Kepatuhan Pembayaran Sanksi Denda Administratif',
        'target_val' => 'Pemberkasan Piutang',
        'realization_val' => 'Rencana',
        'cum_pct' => 95.0,
        'doc_number' => 'Draft Nota Dinas Penagihan',
        'doc_date' => 'Nov 2026',
        'status' => 'pending',
        'nilai_denda' => 0,
        'ket' => 'Penyusunan berkas penyerahan piutang macet ke KPKNL apabila tidak dilunasi'
    ],
    12 => [
        'month_name' => 'Desember',
        'kegiatan' => 'Laporan Akhir Tahunan Pengelolaan Sanksi Denda Administratif Balmon Samarinda TA 2026',
        'target_val' => 'Laporan Tahunan',
        'realization_val' => 'Rencana',
        'cum_pct' => 95.0,
        'doc_number' => 'Lap. Tahunan Denda 2026',
        'doc_date' => 'Des 2026',
        'status' => 'pending',
        'nilai_denda' => 0,
        'ket' => 'Tutup buku penerimaan kas negara PNBP denda administrasi TA 2026'
    ]
];

// Auto-sync PK.7 dengan data resmi Balmon Samarinda 2026 jika belum tersinkronisasi
if ($selectedCode === 'PK.7' && $selectedYear === 2026) {
    try {
        $pdo->prepare("
            UPDATE perjanjian_kinerja SET 
                realization = 95.0, 
                target_annual = 85.0,
                notes = 'Total Ketetapan Denda TA 2026: Rp 201.258.400 (4 Surat Tagihan Denda SPP I: PLN Rp 135.935.800, PT Lintasmaya Rp 20.097.000, PT Perdana Tekno Rp 30.030.000, PT Hamparan Anugrah Abadi Rp 15.195.600). 100% dari 4 Surat Tagihan diterbitkan & ditindaklanjuti sesuai prosedur PP 43/2023, PP 28/2025, dan Perdirjen SDPPI 3/2024.' 
            WHERE code = 'PK.7' AND year = ?
        ")->execute([$selectedYear]);
        $activePk['realization'] = 95.0;
        $activePk['target_annual'] = 85.0;
    } catch (\Exception $e) {}
}

// ============================================================
// DATA KHUSUS RESMI PK.8: KUALITAS LAYANAN INFRASTRUKTUR DIGITAL (QOS) 2026
// Sumber: Laporan Hasil Kegiatan Pengukuran Kualitas Layanan Infrastruktur Digital
// Balai Monitor Spektrum Frekuensi Radio & Perangkat Digital Kelas I Samarinda
// Berdasarkan Peraturan Dirjen PPI No. 7 Tahun 2024 & Nota Dinas No. 224/DJID.6/PR.04.01/02/2026
// ============================================================
$pk8OfficialTable = [
    1 => [
        'month_name' => 'Januari',
        'wilayah' => 'Provinsi Kalimantan Timur',
        'kegiatan' => 'Persiapan Teknis, Verifikasi & Kalibrasi Instrumen Pengukuran PMT / Probe Seluler TA 2026',
        'target_val' => 'Persiapan',
        'realization_val' => '1 Selesai',
        'cum_pct' => 0.0,
        'spt' => 'Internal / Rutin',
        'nd_number' => 'Verifikasi Teknis PMT',
        'nd_date' => '31 Jan 2026',
        'status' => 'achieved'
    ],
    2 => [
        'month_name' => 'Februari',
        'wilayah' => 'Kota Bontang & Kab. Kutai Timur',
        'kegiatan' => 'Drive Test (min. 5 jam/wilayah) & Static Test di 8 POI (5 POI Bontang & 3 POI Sangatta Kutim)',
        'target_val' => '2 Kab/Kota',
        'realization_val' => '2 Selesai',
        'cum_pct' => 25.0,
        'spt' => '24/BALMON.64/KP.01.06/02/2026',
        'nd_number' => '142/BALMON.64/SP.02.06/03/2026',
        'nd_date' => '05 Mar 2026',
        'status' => 'achieved'
    ],
    3 => [
        'month_name' => 'Maret',
        'wilayah' => 'Kota Balikpapan',
        'kegiatan' => 'Pengukuran Kualitas Layanan Infrastruktur Digital di Kota Balikpapan (Drive Test & Static Test di 3 POI)',
        'target_val' => '1 Kab/Kota',
        'realization_val' => '1 Selesai',
        'cum_pct' => 37.5,
        'spt' => '40/BALMON.64/KP.01.06/03/2026',
        'nd_number' => 'Lap. QoS Digital Balikpapan',
        'nd_date' => '25 Mar 2026',
        'status' => 'achieved'
    ],
    4 => [
        'month_name' => 'April',
        'wilayah' => 'Kab. Kutai Kartanegara',
        'kegiatan' => 'Drive Test Poros Samarinda-Tenggarong & Static Test di 3 POI Kab. Kutai Kartanegara',
        'target_val' => '1 Kab/Kota',
        'realization_val' => '1 Selesai',
        'cum_pct' => 50.0,
        'spt' => '57/BALMON.64/KP.01.06/04/2026',
        'nd_number' => '238/BALMON.64/SP.02.06/04/2026',
        'nd_date' => '30 Apr 2026',
        'status' => 'achieved'
    ],
    5 => [
        'month_name' => 'Mei',
        'wilayah' => 'Kab. Penajam Paser Utara & Kawasan IKN',
        'kegiatan' => 'Drive Test Jalur Logistik & Static Test di 5 POI KIPP IKN & Pelabuhan Ferry Penajam',
        'target_val' => '1 Kab/Kota',
        'realization_val' => '1 Selesai',
        'cum_pct' => 62.5,
        'spt' => '78/BALMON.64/KP.01.06/05/2026',
        'nd_number' => '301/BALMON.64/SP.02.06/06/2026',
        'nd_date' => '02 Jun 2026',
        'status' => 'achieved'
    ],
    6 => [
        'month_name' => 'Juni',
        'wilayah' => 'Provinsi Kalimantan Timur',
        'kegiatan' => 'Rekapitulasi Evaluasi Semester I Kualitas Layanan Seluler & Verifikasi Sinkronisasi Data PMT',
        'target_val' => 'Evaluasi Sem I',
        'realization_val' => '1 Selesai',
        'cum_pct' => 62.5,
        'spt' => 'Internal / Evaluasi',
        'nd_number' => 'Laporan Rekapitulasi Sem I',
        'nd_date' => '30 Jun 2026',
        'status' => 'achieved'
    ],
    7 => [
        'month_name' => 'Juli',
        'wilayah' => 'Kab. Kutai Barat',
        'kegiatan' => 'Drive Test (5 jam), Static Test di 4 POI & Inspeksi Validasi 328 MWL Open Shelter',
        'target_val' => '1 Kab/Kota',
        'realization_val' => '1 Selesai',
        'cum_pct' => 75.0,
        'spt' => '104/BALMON.64/KP.01.06/07/2026',
        'nd_number' => '415/BALMON.64/SP.02.06/07/2026',
        'nd_date' => '31 Jul 2026',
        'status' => 'achieved'
    ],
    8 => [
        'month_name' => 'Agustus',
        'wilayah' => 'Kota Samarinda & Kab. Berau',
        'kegiatan' => 'Drive Test & Static Test Berau (5 POI, SPT 122) dan Samarinda (3 POI, SPT 120)',
        'target_val' => '2 Kab/Kota',
        'realization_val' => '2 Selesai',
        'cum_pct' => 100.0,
        'spt' => '120 & 122/BALMON.64/KP.01.06/08/2026',
        'nd_number' => 'ND Tgl 02 September 2026',
        'nd_date' => '02 Sep 2026',
        'status' => 'achieved'
    ],
    9 => [
        'month_name' => 'September',
        'wilayah' => 'Kota Samarinda (Tahap II)',
        'kegiatan' => 'Pengukuran Lanjutan Drive Test Protokol & Static Test di 4 POI Kota Samarinda (Penguatan Wilayah)',
        'target_val' => 'Penguatan Wilayah',
        'realization_val' => '1 Selesai',
        'cum_pct' => 100.0,
        'spt' => '133/BALMON.64/KP.01.06/09/2026',
        'nd_number' => 'ND Tgl 28 September 2026',
        'nd_date' => '28 Sep 2026',
        'status' => 'achieved'
    ],
    10 => [
        'month_name' => 'Oktober',
        'wilayah' => 'Kab. Paser',
        'kegiatan' => 'Drive Test Rute Tanah Grogot & Static Test di 5 POI Kabupaten Paser (112.5% Capaian Kumulatif)',
        'target_val' => '1 Kab/Kota',
        'realization_val' => '1 Selesai (112.5%)',
        'cum_pct' => 112.5,
        'spt' => '142/BALMON.64/KP.01.06/09/2026',
        'nd_number' => '526/BALMON.64/SP.02.06/10/2026',
        'nd_date' => '06 Okt 2026',
        'status' => 'achieved'
    ],
    11 => [
        'month_name' => 'November',
        'wilayah' => 'Diseminasi Hasil & Evaluasi',
        'kegiatan' => 'Rapat Koordinasi Evaluasi Bersama Operator Seluler Terkait Temuan Coverage & Throughput',
        'target_val' => '1 Rekomendasi',
        'realization_val' => 'Rencana',
        'cum_pct' => 112.5,
        'spt' => 'Rakor Operator',
        'nd_number' => 'Agenda Triwulan IV',
        'nd_date' => 'Nov 2026',
        'status' => 'pending'
    ],
    12 => [
        'month_name' => 'Desember',
        'wilayah' => 'Penyusunan Laporan Tahunan',
        'kegiatan' => 'Laporan Akhir Tahunan Kualitas Layanan Seluler (QoS) Balmon Samarinda TA 2026',
        'target_val' => '8 Kab/Kota',
        'realization_val' => 'Rencana',
        'cum_pct' => 112.5,
        'spt' => 'Lap. Tahunan QoS',
        'nd_number' => 'Laporan Kinerja Tahunan',
        'nd_date' => 'Des 2026',
        'status' => 'pending'
    ]
];

// 9 Laporan Pengukuran Kualitas Layanan Seluler Resmi Sesuai Berkas Dokumen dan Surat Perintah Tugas (SPT)
$pk8OfficialReportsList = [
    [
        'no' => 1,
        'bulan' => 'Februari 2026',
        'wilayah' => 'Kota Bontang & Kab. Kutai Timur',
        'spt_number' => '24/BALMON.64/KP.01.06/02/2026',
        'spt_date' => '23 Feb 2026',
        'pelaksanaan' => '24 s/d 28 Feb 2026',
        'nd_number' => '142/BALMON.64/SP.02.06/03/2026',
        'nd_date' => '05 Mar 2026',
        'petugas' => 'Fathul Mufiidah, S.Si (Ketua), Tantowi Pahala H.S., S.Kom, Ahmad Anwar',
        'lingkup' => 'Drive Test Kota Bontang & Kutim (min 5 jam/wilayah) & Static Test di 8 POI (5 Bontang, 3 Sangatta)',
        'report_code' => 'MON-QOS-2026-008',
        'summary_speed' => 'XL DL 88.60 Mbps | Tsel DL 85.20 Mbps | Isat DL 72.40 Mbps',
        'status' => 'Selesai'
    ],
    [
        'no' => 2,
        'bulan' => 'Maret 2026',
        'wilayah' => 'Kota Balikpapan',
        'spt_number' => '40/BALMON.64/KP.01.06/03/2026',
        'spt_date' => '16 Mar 2026',
        'pelaksanaan' => '23 s/d 24 Mar 2026',
        'nd_number' => 'Laporan Pengukuran QoS Balikpapan',
        'nd_date' => '25 Mar 2026',
        'petugas' => 'Andreo Yustiantoro Anjaya (Ketua), Rifyal, Muhammad Alif Anhasdio Fajri, Rusdino',
        'lingkup' => 'Drive Test Rute Protokol Kota Balikpapan & Static Test di 3 POI (Bandara SAMS Sepinggan, Pelabuhan Semayang, Kawasan Sudirman)',
        'report_code' => 'MON-QOS-2026-009',
        'summary_speed' => 'XL DL 96.40 Mbps | Tsel DL 94.12 Mbps | Isat DL 71.30 Mbps',
        'status' => 'Selesai'
    ],
    [
        'no' => 3,
        'bulan' => 'April 2026',
        'wilayah' => 'Kab. Kutai Kartanegara',
        'spt_number' => '57/BALMON.64/KP.01.06/04/2026',
        'spt_date' => '17 Apr 2026',
        'pelaksanaan' => '20 s/d 23 Apr 2026',
        'nd_number' => '238/BALMON.64/SP.02.06/04/2026',
        'nd_date' => '30 Apr 2026',
        'petugas' => 'Rendy Tri Atmadja, ST & Muclis Ependi, SH',
        'lingkup' => 'Drive Test Poros Samarinda-Tenggarong (2 jam) & Static Test di 3 POI (Taman Tanjong, Kantor Bupati Kukar, RSUD AM Parikesit)',
        'report_code' => 'MON-QOS-2026-006',
        'summary_speed' => 'XL DL 92.20 Mbps | Tsel DL 71.77 Mbps | Isat DL 68.58 Mbps',
        'status' => 'Selesai'
    ],
    [
        'no' => 4,
        'bulan' => 'Mei 2026',
        'wilayah' => 'Kab. Penajam Paser Utara & Kawasan IKN',
        'spt_number' => '78/BALMON.64/KP.01.06/05/2026',
        'spt_date' => '13 Mei 2026',
        'pelaksanaan' => '18 s/d 21 Mei 2026',
        'nd_number' => '301/BALMON.64/SP.02.06/06/2026',
        'nd_date' => '02 Jun 2026',
        'petugas' => 'Muhammad Alif Anhasdio Fajri, ST & Maksudi',
        'lingkup' => 'Drive Test Jalur Logistik (2 jam) & Static Test di 5 POI (Pelabuhan Ferry Penajam, Rusun ASN IKN, Perkantoran IKN, Nusantara Park, Kemenko IKN)',
        'report_code' => 'MON-QOS-2026-005',
        'summary_speed' => 'XL DL 98.29 Mbps | Tsel DL 92.59 Mbps | Isat DL 52.12 Mbps',
        'status' => 'Selesai'
    ],
    [
        'no' => 5,
        'bulan' => 'Juli 2026',
        'wilayah' => 'Kab. Kutai Barat',
        'spt_number' => '104/BALMON.64/KP.01.06/07/2026',
        'spt_date' => '17 Jul 2026',
        'pelaksanaan' => '20 s/d 23 Jul 2026',
        'nd_number' => '415/BALMON.64/SP.02.06/07/2026',
        'nd_date' => '31 Jul 2026',
        'petugas' => 'Fathul Mufiidah, S.Si, Binti Herwindasari, A.md, Ahmad Anwar',
        'lingkup' => 'Drive Test (5 jam) & Static Test di 4 POI (RSUD Harapan Insan, Islamic Center, Pelabuhan Tering, Business Center) & Validasi 328 MWL',
        'report_code' => 'MON-QOS-2026-007',
        'summary_speed' => 'Isat DL 79.84 Mbps | XL DL 60.51 Mbps | Tsel DL 56.47 Mbps',
        'status' => 'Selesai'
    ],
    [
        'no' => 6,
        'bulan' => 'Agustus 2026',
        'wilayah' => 'Kota Samarinda (Tahap I)',
        'spt_number' => '120/BALMON.64/KP.01.06/08/2026',
        'spt_date' => '18 Ags 2026',
        'pelaksanaan' => '19 s/d 20 Ags 2026',
        'nd_number' => 'Nota Dinas Tgl 02 September 2026',
        'nd_date' => '02 Sep 2026',
        'petugas' => 'Andreo Y., Sri Sukisno, M. Alif Anhasdio, Taman Dwi, Fathul M., Binti H., Rendy Tri, Tantowi, Maryono, Togap S.',
        'lingkup' => 'Drive Test Rute Protokol Samarinda & Static Test di 3 POI (Bukit Alaya Junction, Bandara APT Pranoto, Pelabuhan Samarinda)',
        'report_code' => 'MON-QOS-2026-001',
        'summary_speed' => 'XL DL 137.48 Mbps | Tsel DL 104.52 Mbps | Isat DL 87.31 Mbps',
        'status' => 'Selesai'
    ],
    [
        'no' => 7,
        'bulan' => 'Agustus 2026',
        'wilayah' => 'Kab. Berau',
        'spt_number' => '122/BALMON.64/KP.01.06/08/2026',
        'spt_date' => '21 Ags 2026',
        'pelaksanaan' => '24 s/d 27 Ags 2026',
        'nd_number' => 'Nota Dinas Tgl 02 September 2026',
        'nd_date' => '02 Sep 2026',
        'petugas' => 'Duiyanti Milo Rundupadang, Binti Herwindasari, Tantowi Pahala H.S., Rusdino',
        'lingkup' => 'Drive Test & Static Test di 5 POI (Bandara Kalimarau, Business Center, Masjid Baitul Hikmah, Pusat Kuliner Murjani, Perkantoran Berau)',
        'report_code' => 'MON-QOS-2026-002',
        'summary_speed' => 'XL DL 91.08 Mbps | Tsel DL 89.66 Mbps | Isat DL 49.96 Mbps',
        'status' => 'Selesai'
    ],
    [
        'no' => 8,
        'bulan' => 'September 2026',
        'wilayah' => 'Kota Samarinda (Tahap II)',
        'spt_number' => '133/BALMON.64/KP.01.06/09/2026',
        'spt_date' => '11 Sep 2026',
        'pelaksanaan' => '14 s/d 17 Sep 2026',
        'nd_number' => 'Nota Dinas Tgl 28 September 2026',
        'nd_date' => '28 Sep 2026',
        'petugas' => 'M. Alif Anhasdio, Roy Mallombasi, Rifyal, Erlangga Hari, Binti H., Tantowi, Maryono',
        'lingkup' => 'Drive Test Protokol Lanjutan & Static Test di 4 POI (Bandara APT Pranoto, Balai Kota, Univ. Mulawarman, Pelabuhan Samarinda)',
        'report_code' => 'MON-QOS-2026-003',
        'summary_speed' => 'XL DL 126.05 Mbps | Tsel DL 89.69 Mbps | Isat DL 67.80 Mbps',
        'status' => 'Selesai'
    ],
    [
        'no' => 9,
        'bulan' => 'Oktober 2026',
        'wilayah' => 'Kab. Paser',
        'spt_number' => '142/BALMON.64/KP.01.06/09/2026',
        'spt_date' => '25 Sep 2026',
        'pelaksanaan' => '27 Sep s/d 01 Okt 2026',
        'nd_number' => '526/BALMON.64/SP.02.06/10/2026',
        'nd_date' => '06 Okt 2026',
        'petugas' => 'Roy Mallombasi (Ketua Tim), Binti Herwindasari, Rendy Tri Atmadja, Ode Nurhasanah',
        'lingkup' => 'Drive Test Tanah Grogot & Static Test di 5 POI (MPP Km 5, RSUD Panglima Sebaya, Masjid Nurul Falah, SMA Muhammadiyah, Area Kuliner)',
        'report_code' => 'MON-QOS-2026-004',
        'summary_speed' => 'XL DL 126.58 Mbps | Tsel DL 83.57 Mbps | Isat DL 61.41 Mbps',
        'status' => 'Selesai'
    ]
];

// Detail Hasil Uji Pengukuran Kualitas Layanan di 8 Sesi Pengujian Resmi
$pk8MeasurementsDetail = [
    'Bontang_Kutim_Feb' => [
        'lokasi' => 'Kota Bontang & Kab. Kutai Timur (24-28 Februari 2026)',
        'spt' => '24/BALMON.64/KP.01.06/02/2026',
        'nd' => '142/BALMON.64/SP.02.06/03/2026 (06 Maret 2026)',
        'petugas' => 'Fathul Mufiidah, S.Si (Ketua Tim), Tantowi Pahala H. S., S.Kom, Ahmad Anwar',
        'poi_count' => 8,
        'poi_list' => '5 POI Bontang (Pelabuhan Loktuan, Walikota, RSUD Taman Husada, Ramayana, Pantai Berbas) & 3 POI Sangatta (Perkantoran Bukit Pelangi, RSUD Kudungga, Pasar Induk)',
        'operators' => [
            'XL' => [
                'dl_speed' => 88.60, 'ul_speed' => 32.10, 'speed_sr' => 89.70, 'rank_dl' => 1,
                'yt_sr' => 95.20, 'yt_vq' => 4.22, 'yt_720p' => 88.50,
                'ig_pic_sr' => 85.00, 'ig_vid_sr' => 82.50,
                'rsrp' => -91.20, 'rsrq' => -11.50, 'sinr' => 12.40, 'bad_rsrp' => 10.30, 'bad_sinr' => 26.10
            ],
            'Telkomsel' => [
                'dl_speed' => 85.20, 'ul_speed' => 28.40, 'speed_sr' => 95.00, 'rank_dl' => 2,
                'yt_sr' => 98.40, 'yt_vq' => 4.28, 'yt_720p' => 96.10,
                'ig_pic_sr' => 96.50, 'ig_vid_sr' => 95.00,
                'rsrp' => -84.60, 'rsrq' => -11.20, 'sinr' => 10.80, 'bad_rsrp' => 5.00, 'bad_sinr' => 35.20
            ],
            'Indosat' => [
                'dl_speed' => 72.40, 'ul_speed' => 22.60, 'speed_sr' => 98.60, 'rank_dl' => 3,
                'yt_sr' => 96.10, 'yt_vq' => 4.24, 'yt_720p' => 86.40,
                'ig_pic_sr' => 82.00, 'ig_vid_sr' => 80.00,
                'rsrp' => -89.80, 'rsrq' => -12.10, 'sinr' => 7.90, 'bad_rsrp' => 1.40, 'bad_sinr' => 44.50
            ]
        ]
    ],
    'Balikpapan_Mar' => [
        'lokasi' => 'Kota Balikpapan (23-24 Maret 2026)',
        'spt' => '40/BALMON.64/KP.01.06/03/2026',
        'nd' => 'Laporan Pengukuran Kualitas Layanan Digital Kota Balikpapan (25 Maret 2026)',
        'petugas' => 'Andreo Yustiantoro Anjaya (Ketua Tim), Rifyal, Muhammad Alif Anhasdio Fajri, Rusdino',
        'poi_count' => 3,
        'poi_list' => 'Bandara Internasional SAMS Sepinggan, Pelabuhan Semayang Balikpapan, Kawasan Pusat Bisnis Jl. Jend. Sudirman',
        'operators' => [
            'XL' => [
                'dl_speed' => 96.40, 'ul_speed' => 48.20, 'speed_sr' => 94.50, 'rank_dl' => 1,
                'yt_sr' => 96.20, 'yt_vq' => 4.24, 'yt_720p' => 82.50,
                'ig_pic_sr' => 92.40, 'ig_vid_sr' => 90.10,
                'rsrp' => -88.50, 'rsrq' => -11.40, 'sinr' => 12.80, 'bad_rsrp' => 8.50, 'bad_sinr' => 28.20
            ],
            'Telkomsel' => [
                'dl_speed' => 94.12, 'ul_speed' => 36.50, 'speed_sr' => 96.80, 'rank_dl' => 2,
                'yt_sr' => 98.70, 'yt_vq' => 4.27, 'yt_720p' => 94.60,
                'ig_pic_sr' => 96.20, 'ig_vid_sr' => 94.80,
                'rsrp' => -83.20, 'rsrq' => -11.10, 'sinr' => 11.40, 'bad_rsrp' => 6.20, 'bad_sinr' => 32.10
            ],
            'Indosat' => [
                'dl_speed' => 71.30, 'ul_speed' => 24.10, 'speed_sr' => 92.40, 'rank_dl' => 3,
                'yt_sr' => 94.80, 'yt_vq' => 4.22, 'yt_720p' => 76.80,
                'ig_pic_sr' => 85.50, 'ig_vid_sr' => 83.20,
                'rsrp' => -91.10, 'rsrq' => -12.30, 'sinr' => 7.60, 'bad_rsrp' => 12.40, 'bad_sinr' => 48.50
            ]
        ]
    ],
    'Kukar_Apr' => [
        'lokasi' => 'Kabupaten Kutai Kartanegara (20-23 April 2026)',
        'spt' => '57/BALMON.64/KP.01.06/04/2026',
        'nd' => '238/BALMON.64/SP.02.06/04/2026 (30 April 2026)',
        'petugas' => 'Rendy Tri Atmadja, ST & Muclis Ependi, SH',
        'poi_count' => 3,
        'poi_list' => 'Taman Tanjong Tenggarong, Kantor Bupati Kukar, RSUD AM Parikesit',
        'operators' => [
            'XL' => [
                'dl_speed' => 92.20, 'ul_speed' => 24.82, 'speed_sr' => 81.47, 'rank_dl' => 1,
                'yt_sr' => 96.20, 'yt_vq' => 4.24, 'yt_720p' => 94.77,
                'ig_pic_sr' => 80.00, 'ig_vid_sr' => 78.50,
                'rsrp' => -105.53, 'rsrq' => -14.10, 'sinr' => 8.50, 'bad_rsrp' => 0.29, 'bad_sinr' => 45.20
            ],
            'Telkomsel' => [
                'dl_speed' => 71.77, 'ul_speed' => 12.31, 'speed_sr' => 92.08, 'rank_dl' => 2,
                'yt_sr' => 98.50, 'yt_vq' => 4.30, 'yt_720p' => 98.23,
                'ig_pic_sr' => 95.00, 'ig_vid_sr' => 94.20,
                'rsrp' => -88.54, 'rsrq' => -12.50, 'sinr' => 9.80, 'bad_rsrp' => 0.15, 'bad_sinr' => 38.60
            ],
            'Indosat' => [
                'dl_speed' => 68.58, 'ul_speed' => 18.53, 'speed_sr' => 87.15, 'rank_dl' => 3,
                'yt_sr' => 98.10, 'yt_vq' => 4.26, 'yt_720p' => 98.31,
                'ig_pic_sr' => 88.00, 'ig_vid_sr' => 85.00,
                'rsrp' => -94.23, 'rsrq' => -12.40, 'sinr' => 7.20, 'bad_rsrp' => 0.11, 'bad_sinr' => 49.80
            ]
        ]
    ],
    'PPU_Mei' => [
        'lokasi' => 'Kabupaten Penajam Paser Utara & KIPP IKN (18-21 Mei 2026)',
        'spt' => '78/BALMON.64/KP.01.06/05/2026',
        'nd' => '301/BALMON.64/SP.02.06/06/2026 (02 Juni 2026)',
        'petugas' => 'Muhammad Alif Anhasdio Fajri, ST & Maksudi',
        'poi_count' => 5,
        'poi_list' => 'Pelabuhan Ferry Penajam, Rusun ASN IKN, Perkantoran IKN, Nusantara Park, Perkantoran Kemenko IKN',
        'operators' => [
            'XL' => [
                'dl_speed' => 98.29, 'ul_speed' => 35.01, 'speed_sr' => 82.40, 'rank_dl' => 1,
                'yt_sr' => 91.24, 'yt_vq' => 4.27, 'yt_720p' => 77.09,
                'ig_pic_sr' => 0, 'ig_vid_sr' => 0,
                'rsrp' => -105.53, 'rsrq' => -14.10, 'sinr' => 3.08, 'bad_rsrp' => 37.91, 'bad_sinr' => 62.21
            ],
            'Telkomsel' => [
                'dl_speed' => 92.59, 'ul_speed' => 34.27, 'speed_sr' => 90.21, 'rank_dl' => 2,
                'yt_sr' => 91.60, 'yt_vq' => 4.37, 'yt_720p' => 94.93,
                'ig_pic_sr' => 0, 'ig_vid_sr' => 0,
                'rsrp' => -88.54, 'rsrq' => -12.56, 'sinr' => 8.23, 'bad_rsrp' => 14.79, 'bad_sinr' => 44.32
            ],
            'Indosat' => [
                'dl_speed' => 52.12, 'ul_speed' => 7.81, 'speed_sr' => 96.88, 'rank_dl' => 3,
                'yt_sr' => 98.05, 'yt_vq' => 4.31, 'yt_720p' => 75.13,
                'ig_pic_sr' => 0, 'ig_vid_sr' => 0,
                'rsrp' => -94.23, 'rsrq' => -12.39, 'sinr' => 6.05, 'bad_rsrp' => 13.62, 'bad_sinr' => 55.49
            ]
        ]
    ],
    'Kubar_Jul' => [
        'lokasi' => 'Kabupaten Kutai Barat (20-23 Juli 2026)',
        'spt' => '104/BALMON.64/KP.01.06/07/2026',
        'nd' => '415/BALMON.64/SP.02.06/07/2026 (31 Juli 2026)',
        'petugas' => 'Fathul Mufiidah, S.Si, Binti Herwindasari, A.md, Ahmad Anwar',
        'poi_count' => 4,
        'poi_list' => 'RSUD Harapan Insan Sendawar, Al-Muttaqin Islamic Center, Pelabuhan Tering, Business Center Barong Tongkok',
        'operators' => [
            'Indosat' => [
                'dl_speed' => 79.84, 'ul_speed' => 34.56, 'speed_sr' => 90.14, 'rank_dl' => 1,
                'yt_sr' => 91.49, 'yt_vq' => 4.28, 'yt_720p' => 73.13,
                'ig_pic_sr' => 10.0, 'ig_vid_sr' => 10.26,
                'rsrp' => -90.98, 'rsrq' => -10.17, 'sinr' => 11.77, 'bad_rsrp' => 18.43, 'bad_sinr' => 30.75
            ],
            'XL' => [
                'dl_speed' => 60.51, 'ul_speed' => 22.31, 'speed_sr' => 53.23, 'rank_dl' => 2,
                'yt_sr' => 69.39, 'yt_vq' => 4.23, 'yt_720p' => 40.12,
                'ig_pic_sr' => 0, 'ig_vid_sr' => 0,
                'rsrp' => -94.81, 'rsrq' => -11.80, 'sinr' => 11.90, 'bad_rsrp' => 48.55, 'bad_sinr' => 56.05
            ],
            'Telkomsel' => [
                'dl_speed' => 56.47, 'ul_speed' => 24.65, 'speed_sr' => 88.44, 'rank_dl' => 3,
                'yt_sr' => 90.00, 'yt_vq' => 4.22, 'yt_720p' => 96.73,
                'ig_pic_sr' => 33.33, 'ig_vid_sr' => 33.33,
                'rsrp' => -87.28, 'rsrq' => -13.97, 'sinr' => 7.86, 'bad_rsrp' => 23.88, 'bad_sinr' => 56.54
            ]
        ]
    ],
    'Samarinda_Ags' => [
        'lokasi' => 'Kota Samarinda - Tahap I (19-20 Agustus 2026)',
        'spt' => '120/BALMON.64/KP.01.06/08/2026',
        'nd' => 'Nota Dinas Tgl 02 September 2026',
        'petugas' => 'Andreo Y., Sri Sukisno, M. Alif Anhasdio, Taman Dwi, Fathul M., Binti H., Rendy Tri, Tantowi, Maryono, Togap S.',
        'poi_count' => 3,
        'poi_list' => 'Bukit Alaya Junction, Bandara APT Pranoto, Pelabuhan Samarinda',
        'operators' => [
            'XL' => [
                'dl_speed' => 137.48, 'ul_speed' => 77.55, 'speed_sr' => 100.0, 'rank_dl' => 1,
                'yt_sr' => 100.0, 'yt_vq' => 4.14, 'yt_720p' => 65.0,
                'ig_pic_sr' => 0, 'ig_vid_sr' => 0,
                'rsrp' => -81.44, 'rsrq' => -11.26, 'sinr' => 15.14, 'bad_rsrp' => 10.18, 'bad_sinr' => 34.25
            ],
            'Telkomsel' => [
                'dl_speed' => 104.52, 'ul_speed' => 28.77, 'speed_sr' => 98.82, 'rank_dl' => 2,
                'yt_sr' => 99.41, 'yt_vq' => 4.25, 'yt_720p' => 93.96,
                'ig_pic_sr' => 100.0, 'ig_vid_sr' => 100.0,
                'rsrp' => -82.13, 'rsrq' => -11.55, 'sinr' => 9.40, 'bad_rsrp' => 11.07, 'bad_sinr' => 51.88
            ],
            'Indosat' => [
                'dl_speed' => 87.31, 'ul_speed' => 30.92, 'speed_sr' => 76.14, 'rank_dl' => 3,
                'yt_sr' => 93.18, 'yt_vq' => 4.24, 'yt_720p' => 78.51,
                'ig_pic_sr' => 0, 'ig_vid_sr' => 0,
                'rsrp' => -90.79, 'rsrq' => -11.10, 'sinr' => 8.54, 'bad_rsrp' => 15.93, 'bad_sinr' => 45.95
            ]
        ]
    ],
    'Berau_Ags' => [
        'lokasi' => 'Kabupaten Berau (24-27 Agustus 2026)',
        'spt' => '122/BALMON.64/KP.01.06/08/2026',
        'nd' => 'Nota Dinas Tgl 02 September 2026',
        'petugas' => 'Duiyanti M. Rundupadang, Binti Herwindasari, Tantowi Pahala H. S., Rusdino',
        'poi_count' => 5,
        'poi_list' => 'Bandara Kalimarau, Business Center Berau, Masjid Agung Baitul Hikmah, Pusat Kuliner Jl Murjani, Area Perkantoran Berau',
        'operators' => [
            'XL' => [
                'dl_speed' => 91.08, 'ul_speed' => 46.13, 'speed_sr' => 93.39, 'rank_dl' => 1,
                'yt_sr' => 96.46, 'yt_vq' => 4.25, 'yt_720p' => 74.75,
                'ig_pic_sr' => 0, 'ig_vid_sr' => 0,
                'rsrp' => -96.70, 'rsrq' => -11.92, 'sinr' => 10.25, 'bad_rsrp' => 36.09, 'bad_sinr' => 47.04
            ],
            'Telkomsel' => [
                'dl_speed' => 89.66, 'ul_speed' => 31.10, 'speed_sr' => 94.90, 'rank_dl' => 2,
                'yt_sr' => 93.37, 'yt_vq' => 4.26, 'yt_720p' => 95.43,
                'ig_pic_sr' => 98.36, 'ig_vid_sr' => 98.25,
                'rsrp' => -85.21, 'rsrq' => -11.99, 'sinr' => 10.28, 'bad_rsrp' => 14.72, 'bad_sinr' => 45.51
            ],
            'Indosat' => [
                'dl_speed' => 49.96, 'ul_speed' => 15.39, 'speed_sr' => 98.70, 'rank_dl' => 3,
                'yt_sr' => 97.83, 'yt_vq' => 4.25, 'yt_720p' => 56.66,
                'ig_pic_sr' => 0, 'ig_vid_sr' => 0,
                'rsrp' => -99.52, 'rsrq' => -13.59, 'sinr' => 4.17, 'bad_rsrp' => 29.08, 'bad_sinr' => 73.37
            ]
        ]
    ],
    'Samarinda_Sep' => [
        'lokasi' => 'Kota Samarinda - Tahap II (14-17 September 2026)',
        'spt' => '133/BALMON.64/KP.01.06/09/2026',
        'nd' => 'Nota Dinas Tgl 28 September 2026',
        'petugas' => 'M. Alif Anhasdio, Roy Mallombasi, Rifyal, Erlangga Hari, Binti H., Tantowi, Maryono',
        'poi_count' => 4,
        'poi_list' => 'Bandara APT Pranoto, Balai Kota Samarinda, Universitas Mulawarman, Pelabuhan Samarinda',
        'operators' => [
            'XL' => [
                'dl_speed' => 126.05, 'ul_speed' => 85.48, 'speed_sr' => 96.89, 'rank_dl' => 1,
                'yt_sr' => 98.96, 'yt_vq' => 4.26, 'yt_720p' => 92.90,
                'ig_pic_sr' => 0, 'ig_vid_sr' => 0,
                'rsrp' => -82.51, 'rsrq' => -11.27, 'sinr' => 13.18, 'bad_rsrp' => 4.29, 'bad_sinr' => 31.03
            ],
            'Telkomsel' => [
                'dl_speed' => 89.69, 'ul_speed' => 25.66, 'speed_sr' => 96.24, 'rank_dl' => 2,
                'yt_sr' => 99.53, 'yt_vq' => 4.25, 'yt_720p' => 96.72,
                'ig_pic_sr' => 0, 'ig_vid_sr' => 98.21,
                'rsrp' => -83.19, 'rsrq' => -12.02, 'sinr' => 10.75, 'bad_rsrp' => 10.26, 'bad_sinr' => 46.64
            ],
            'Indosat' => [
                'dl_speed' => 67.80, 'ul_speed' => 30.97, 'speed_sr' => 97.39, 'rank_dl' => 3,
                'yt_sr' => 100.0, 'yt_vq' => 4.25, 'yt_720p' => 72.74,
                'ig_pic_sr' => 0, 'ig_vid_sr' => 0,
                'rsrp' => -90.85, 'rsrq' => -12.06, 'sinr' => 6.20, 'bad_rsrp' => 11.98, 'bad_sinr' => 56.18
            ]
        ]
    ],
    'Paser_Okt' => [
        'lokasi' => 'Kabupaten Paser (27 September - 01 Oktober 2026)',
        'spt' => '142/BALMON.64/KP.01.06/09/2026',
        'nd' => '526/BALMON.64/SP.02.06/10/2026 (06 Okt 2026)',
        'petugas' => 'Roy Mallombasi (Ketua Tim), Binti Herwindasari, Rendy Tri Atmadja, Ode Nurhasanah',
        'poi_count' => 5,
        'poi_list' => 'Mall Pelayanan Publik Km 5, RSUD Panglima Sebaya, Masjid Agung Nurul Falah, SMA Muhammadiyah Tanah Grogot, Area Kuliner Tanah Grogot',
        'operators' => [
            'XL' => [
                'dl_speed' => 126.58, 'ul_speed' => 55.37, 'speed_sr' => 86.64, 'rank_dl' => 1,
                'yt_sr' => 92.92, 'yt_vq' => 4.25, 'yt_720p' => 82.98,
                'ig_pic_sr' => 0, 'ig_vid_sr' => 0,
                'rsrp' => -91.32, 'rsrq' => -12.77, 'sinr' => 10.36, 'bad_rsrp' => 13.66, 'bad_sinr' => 41.26
            ],
            'Telkomsel' => [
                'dl_speed' => 83.57, 'ul_speed' => 23.95, 'speed_sr' => 66.40, 'rank_dl' => 2,
                'yt_sr' => 96.31, 'yt_vq' => 4.25, 'yt_720p' => 93.45,
                'ig_pic_sr' => 98.67, 'ig_vid_sr' => 95.65,
                'rsrp' => -85.70, 'rsrq' => -11.05, 'sinr' => 8.03, 'bad_rsrp' => 18.63, 'bad_sinr' => 43.89
            ],
            'Indosat' => [
                'dl_speed' => 61.41, 'ul_speed' => 19.03, 'speed_sr' => 45.75, 'rank_dl' => 3,
                'yt_sr' => 93.07, 'yt_vq' => 4.24, 'yt_720p' => 55.80,
                'ig_pic_sr' => 36.21, 'ig_vid_sr' => 92.59,
                'rsrp' => -96.30, 'rsrq' => -13.93, 'sinr' => 3.19, 'bad_rsrp' => 23.67, 'bad_sinr' => 67.50
            ]
        ]
    ]
];

// Ringkasan 38 Titik Uji Static Test (Point of Interest / POI) di 9 Wilayah Kerja
$pk8PoiList = [
    // Kota Balikpapan (3 POI - SPT 40)
    ['kab' => 'Kota Balikpapan', 'nama' => 'Bandara Internasional SAMS Sepinggan', 'kategori' => 'Transportasi Udara', 'kecamatan' => 'Balikpapan Selatan', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kota Balikpapan', 'nama' => 'Pelabuhan Semayang', 'kategori' => 'Transportasi Laut & Logistik', 'kecamatan' => 'Balikpapan Kota', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kota Balikpapan', 'nama' => 'Kawasan Bisnis & Perkantoran Jl. Jend. Sudirman', 'kategori' => 'Pusat Bisnis & Niaga', 'kecamatan' => 'Balikpapan Kota', 'sinyal_4g' => 'Baik Sekali'],

    // Kota Samarinda (5 POI)
    ['kab' => 'Kota Samarinda', 'nama' => 'Bandara APT Pranoto', 'kategori' => 'Transportasi Udara', 'kecamatan' => 'Samarinda Utara', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kota Samarinda', 'nama' => 'Balai Kota Samarinda', 'kategori' => 'Pusat Pemerintahan', 'kecamatan' => 'Samarinda Kota', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kota Samarinda', 'nama' => 'Universitas Mulawarman (UNMUL)', 'kategori' => 'Pusat Pendidikan', 'kecamatan' => 'Gunung Kelua', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kota Samarinda', 'nama' => 'Pelabuhan Samarinda', 'kategori' => 'Transportasi Air', 'kecamatan' => 'Karang Mumus', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kota Samarinda', 'nama' => 'Bukit Alaya Junction', 'kategori' => 'Kawasan Pemukiman & Bisnis', 'kecamatan' => 'Sungai Pinang', 'sinyal_4g' => 'Baik Sekali'],
    
    // Kabupaten Berau (5 POI)
    ['kab' => 'Kabupaten Berau', 'nama' => 'Bandara Kalimarau', 'kategori' => 'Transportasi Udara', 'kecamatan' => 'Teluk Bayur', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Berau', 'nama' => 'Business Center Berau (Jl. Pemuda)', 'kategori' => 'Kawasan Bisnis & Niaga', 'kecamatan' => 'Tanjung Redeb', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Berau', 'nama' => 'Masjid Agung Baitul Hikmah', 'kategori' => 'Pusat Ibadah & Komunitas', 'kecamatan' => 'Tanjung Redeb', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Berau', 'nama' => 'Pusat Kuliner Jl. Dr. Murjani II', 'kategori' => 'Perekonomian & Wisata', 'kecamatan' => 'Tanjung Redeb', 'sinyal_4g' => 'Baik'],
    ['kab' => 'Kabupaten Berau', 'nama' => 'Area Perkantoran Berau (Jl. APT Pranoto)', 'kategori' => 'Pusat Pemerintahan', 'kecamatan' => 'Tanjung Redeb', 'sinyal_4g' => 'Baik Sekali'],
    
    // Kabupaten Paser (5 POI)
    ['kab' => 'Kabupaten Paser', 'nama' => 'Mall Pelayanan Publik (MPP Km 5)', 'kategori' => 'Pusat Layanan Publik', 'kecamatan' => 'Tanah Grogot', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Paser', 'nama' => 'RSUD Panglima Sebaya', 'kategori' => 'Fasilitas Kesehatan', 'kecamatan' => 'Tanah Grogot', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Paser', 'nama' => 'Masjid Agung Nurul Falah', 'kategori' => 'Pusat Ibadah & Komunitas', 'kecamatan' => 'Tanah Grogot', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Paser', 'nama' => 'SMA Muhammadiyah Tanah Grogot', 'kategori' => 'Pusat Pendidikan', 'kecamatan' => 'Tanah Grogot', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Paser', 'nama' => 'Area Kuliner Jl. Sultan Ibrahim', 'kategori' => 'Perekonomian & UMKM', 'kecamatan' => 'Tanah Grogot', 'sinyal_4g' => 'Baik Sekali'],
    
    // Kab. Penajam Paser Utara & Kawasan Inti IKN (5 POI - SPT 78)
    ['kab' => 'Kab. Penajam Paser Utara', 'nama' => 'Pelabuhan Ferry Penajam', 'kategori' => 'Transportasi Penyeberangan', 'kecamatan' => 'Penajam', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kawasan Inti IKN Nusantara', 'nama' => 'Area Rusun ASN IKN', 'kategori' => 'Kawasan Hunian ASN & Hankam', 'kecamatan' => 'Sepaku (KIPP IKN)', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kawasan Inti IKN Nusantara', 'nama' => 'Area Perkantoran IKN', 'kategori' => 'Pusat Pemerintahan Nasional', 'kecamatan' => 'Sepaku (KIPP IKN)', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kawasan Inti IKN Nusantara', 'nama' => 'Area Nusantara Park', 'kategori' => 'Pusat Publik & Ruang Terbuka', 'kecamatan' => 'Sepaku (KIPP IKN)', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kawasan Inti IKN Nusantara', 'nama' => 'Area Perkantoran Kemenko IKN', 'kategori' => 'Pusat Pemerintahan Kemenko', 'kecamatan' => 'Sepaku (KIPP IKN)', 'sinyal_4g' => 'Baik Sekali'],
    
    // Kabupaten Kutai Barat (4 POI - SPT 104)
    ['kab' => 'Kabupaten Kutai Barat', 'nama' => 'RSUD Harapan Insan Sendawar', 'kategori' => 'Fasilitas Kesehatan Utama', 'kecamatan' => 'Sekolaq Joleq', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Kutai Barat', 'nama' => 'Al-Muttaqin Islamic Center', 'kategori' => 'Pusat Ibadah & Sosial', 'kecamatan' => 'Barong Tongkok', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Kutai Barat', 'nama' => 'Pelabuhan Tering', 'kategori' => 'Transportasi Sungai & Logistik', 'kecamatan' => 'Tering', 'sinyal_4g' => 'Baik'],
    ['kab' => 'Kabupaten Kutai Barat', 'nama' => 'Business Center Barong Tongkok', 'kategori' => 'Kawasan Bisnis & UMKM', 'kecamatan' => 'Barong Tongkok', 'sinyal_4g' => 'Baik Sekali'],
    
    // Kota Bontang (5 POI - SPT 24)
    ['kab' => 'Kota Bontang', 'nama' => 'Pelabuhan Loktuan', 'kategori' => 'Transportasi Laut & Logistik', 'kecamatan' => 'Bontang Utara', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kota Bontang', 'nama' => 'Kantor Walikota Bontang', 'kategori' => 'Pusat Pemerintahan', 'kecamatan' => 'Bontang Selatan', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kota Bontang', 'nama' => 'RSUD Taman Husada Bontang', 'kategori' => 'Fasilitas Kesehatan Utama', 'kecamatan' => 'Bontang Barat', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kota Bontang', 'nama' => 'Ramayana Mall Bontang', 'kategori' => 'Pusat Perbelanjaan & Niaga', 'kecamatan' => 'Bontang Utara', 'sinyal_4g' => 'Baik'],
    ['kab' => 'Kota Bontang', 'nama' => 'Kawasan Wisata Pantai Berbas', 'kategori' => 'Destinasi Pariwisata', 'kecamatan' => 'Bontang Selatan', 'sinyal_4g' => 'Baik Sekali'],
    
    // Kabupaten Kutai Timur (3 POI - SPT 24)
    ['kab' => 'Kabupaten Kutai Timur', 'nama' => 'Perkantoran Bukit Pelangi Sangatta', 'kategori' => 'Pusat Pemerintahan Daerah', 'kecamatan' => 'Sangatta Utara', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Kutai Timur', 'nama' => 'RSUD Kudungga Sangatta', 'kategori' => 'Fasilitas Kesehatan Utama', 'kecamatan' => 'Sangatta Utara', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Kutai Timur', 'nama' => 'Pasar Induk Sangatta', 'kategori' => 'Pusat Perdagangan & UMKM', 'kecamatan' => 'Sangatta Utara', 'sinyal_4g' => 'Baik Sekali'],
    
    // Kabupaten Kutai Kartanegara (3 POI - SPT 57)
    ['kab' => 'Kabupaten Kutai Kartanegara', 'nama' => 'Taman Tanjong Tenggarong', 'kategori' => 'Ruang Terbuka Hijau & Wisata', 'kecamatan' => 'Tenggarong', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Kutai Kartanegara', 'nama' => 'Kantor Bupati Kutai Kartanegara', 'kategori' => 'Pusat Pemerintahan Daerah', 'kecamatan' => 'Tenggarong', 'sinyal_4g' => 'Baik Sekali'],
    ['kab' => 'Kabupaten Kutai Kartanegara', 'nama' => 'RSUD Aji Muhammad Parikesit', 'kategori' => 'Fasilitas Kesehatan Utama', 'kecamatan' => 'Tenggarong Seberang', 'sinyal_4g' => 'Baik Sekali']
];

// Auto-sync PK.8 dengan data resmi Balmon Samarinda 2026 jika belum tersinkronisasi
if ($selectedCode === 'PK.8' && $selectedYear === 2026) {
    try {
        $pdo->prepare("
            UPDATE perjanjian_kinerja SET 
                realization = 100.0, 
                target_annual = 100.0,
                unit = '% Kab/Kota',
                notes = 'Realisasi s.d. Oktober 2026: 100.0% (Target: 100% / 8 Kab/Kota wilayah kerja). Telah terlaksana pengukuran Drive Test & Static Test di 8 Kabupaten/Kota (Samarinda, Balikpapan, Kukar, Bontang, PPU/IKN, Kutim, Berau, Paser) serta validasi integrasi PMT Komdigi.' 
            WHERE code = 'PK.8' AND year = ?
        ")->execute([$selectedYear]);
        $activePk['realization'] = 100.0;
        $activePk['target_annual'] = 100.0;
    } catch (\Exception $e) {}
}

// Ambil Rekap Bulanan dari tabel pk_monthly_rekap
$stmtMonthly = $pdo->prepare("
    SELECT * FROM pk_monthly_rekap 
    WHERE pk_code = ? AND year = ? 
    ORDER BY month_num ASC
");
$stmtMonthly->execute([$selectedCode, $selectedYear]);
$monthlyRecords = $stmtMonthly->fetchAll();

// Mapping record bulanan (1 - 12)
$monthlyMap = [];
foreach ($monthlyRecords as $row) {
    $monthlyMap[$row['month_num']] = $row;
}

// Nama-nama bulan
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$monthShort = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
    5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ags',
    9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];

$pctTarget = (float)$activePk['target_annual'] > 0 ? round(((float)$activePk['realization'] / (float)$activePk['target_annual']) * 100, 1) : 0;

// ============================================================
// DATA KHUSUS RESMI PK.1: MONITORING SFR DI KABUPATEN/KOTA
// ============================================================
$wilayahKerjaList = [
    ['name' => 'Samarinda', 'status' => 'termonitor', 'since' => 'Maret 2026', 'icon' => 'apartment', 'type' => 'Kota'],
    ['name' => 'Balikpapan', 'status' => 'termonitor', 'since' => 'Maret 2026', 'icon' => 'location_city', 'type' => 'Kota'],
    ['name' => 'Bontang', 'status' => 'termonitor', 'since' => 'Februari 2026', 'icon' => 'factory', 'type' => 'Kota'],
    ['name' => 'Kutai Kartanegara', 'status' => 'termonitor', 'since' => 'Februari 2026', 'icon' => 'domain', 'type' => 'Kabupaten'],
    ['name' => 'Kutai Barat', 'status' => 'termonitor', 'since' => 'Februari 2026', 'icon' => 'terrain', 'type' => 'Kabupaten'],
    ['name' => 'Paser', 'status' => 'termonitor', 'since' => 'Maret 2026', 'icon' => 'nature_people', 'type' => 'Kabupaten'],
    ['name' => 'Kutai Timur', 'status' => 'termonitor', 'since' => 'April 2026', 'icon' => 'forest', 'type' => 'Kabupaten'],
    ['name' => 'Berau', 'status' => 'termonitor', 'since' => 'Juni 2026', 'icon' => 'sailing', 'type' => 'Kabupaten'],
    ['name' => 'Penajam Paser Utara', 'status' => 'termonitor', 'since' => 'Agustus 2026', 'icon' => 'foundation', 'type' => 'Kabupaten'],
    ['name' => 'Mahakam Ulu', 'status' => 'terjadwal', 'since' => 'Menunggu Q4 2026', 'icon' => 'water', 'type' => 'Kabupaten']
];

$wilayahTermonitorCount = 0;
foreach ($wilayahKerjaList as $w) {
    if ($w['status'] === 'termonitor') $wilayahTermonitorCount++;
}
$wilayahBelumCount = count($wilayahKerjaList) - $wilayahTermonitorCount;
$wilayahPct = ($wilayahTermonitorCount / count($wilayahKerjaList)) * 100;

$pk1MonthlyDetails = [
    1 => [
        'kab_count' => 2,
        'kab_list' => 'Kutai Kartanegara dan Bontang',
        'isr' => 680,
        'users' => 850,
        'capaian_pct' => 20.0,
        'sanksi' => 0,
        'signer' => 'Warko, S.Kom, M.Si',
        'signer_title' => 'Kepala Balai Monitor SFR Kelas I Samarinda',
        'report_no' => 'Laporan SFR No. 73/BALMON.64/SP.02.10/01/2026',
        'report_date' => '31 Januari 2026'
    ],
    2 => [
        'kab_count' => 3,
        'kab_list' => 'Kutai Kartanegara, Kutai Barat dan Bontang',
        'isr' => 1004,
        'users' => 1300,
        'capaian_pct' => 30.0,
        'sanksi' => 0,
        'signer' => 'Warko, S.Kom, M.Si',
        'signer_title' => 'Kepala Balai Monitor SFR Kelas I Samarinda',
        'report_no' => 'Laporan Capaian PK SFR Februari 2026',
        'report_date' => '28 Februari 2026'
    ],
    3 => [
        'kab_count' => 6,
        'kab_list' => 'Kutai Kartanegara, Kutai Barat, Bontang, Balikpapan, Samarinda dan Paser',
        'isr' => 1174,
        'users' => 3164,
        'capaian_pct' => 60.0,
        'sanksi' => 0,
        'signer' => 'Warko, S.Kom, M.Si',
        'signer_title' => 'Kepala Balai Monitor SFR Kelas I Samarinda',
        'report_no' => 'Laporan Capaian PK SFR Maret 2026',
        'report_date' => '31 Maret 2026'
    ],
    4 => [
        'kab_count' => 7,
        'kab_list' => 'Kutai Kartanegara, Kutai Barat, Bontang, Balikpapan, Samarinda, Paser dan Kutai Timur',
        'isr' => 1614,
        'users' => 3931,
        'capaian_pct' => 70.0,
        'sanksi' => 0,
        'signer' => 'Warko, S.Kom, M.Si',
        'signer_title' => 'Kepala Balai Monitor SFR Kelas I Samarinda',
        'report_no' => 'Laporan Capaian PK SFR April 2026',
        'report_date' => '30 April 2026'
    ],
    5 => [
        'kab_count' => 7,
        'kab_list' => 'Kutai Kartanegara, Kutai Barat, Bontang, Balikpapan, Samarinda, Paser dan Kutai Timur',
        'isr' => 1614,
        'users' => 4127,
        'capaian_pct' => 70.0,
        'sanksi' => 0,
        'signer' => 'Widhy Baskoro S.T',
        'signer_title' => 'Plh. Kepala Balai Monitor SFR Kelas I Samarinda',
        'report_no' => 'Laporan Capaian PK SFR Mei 2026',
        'report_date' => '31 Mei 2026'
    ],
    6 => [
        'kab_count' => 8,
        'kab_list' => 'Kutai Kartanegara, Kutai Barat, Bontang, Balikpapan, Samarinda, Paser, Kutai Timur dan Berau',
        'isr' => 1857,
        'users' => 4630,
        'capaian_pct' => 80.0,
        'sanksi' => 0,
        'signer' => 'Widhy Baskoro S.T',
        'signer_title' => 'Plh. Kepala Balai Monitor SFR Kelas I Samarinda',
        'report_no' => 'Laporan Capaian PK SFR No. 441/BALMON.64/SP.02.10/06/2026',
        'report_date' => '30 Juni 2026'
    ],
    7 => [
        'kab_count' => 8,
        'kab_list' => 'Kutai Kartanegara, Kutai Barat, Bontang, Balikpapan, Samarinda, Paser, Kutai Timur dan Berau',
        'isr' => 1890,
        'users' => 4750,
        'capaian_pct' => 80.0,
        'sanksi' => 0,
        'signer' => 'Widhy Baskoro S.T',
        'signer_title' => 'Plh. Kepala Balai Monitor SFR Kelas I Samarinda',
        'report_no' => 'Laporan Capaian PK SFR No. 512/BALMON.64/SP.02.10/07/2026',
        'report_date' => '31 Juli 2026'
    ],
    8 => [
        'kab_count' => 9,
        'kab_list' => 'Kutai Kartanegara, Kutai Barat, Bontang, Balikpapan, Samarinda, Paser, Kutai Timur, Berau dan Penajam Paser Utara',
        'isr' => 1960,
        'users' => 4920,
        'capaian_pct' => 90.0,
        'sanksi' => 0,
        'signer' => 'Widhy Baskoro S.T',
        'signer_title' => 'Plh. Kepala Balai Monitor SFR Kelas I Samarinda',
        'report_no' => 'Laporan Capaian PK SFR No. 594/BALMON.64/SP.02.10/08/2026',
        'report_date' => '31 Agustus 2026'
    ],
    9 => [
        'kab_count' => 9,
        'kab_list' => 'Kutai Kartanegara, Kutai Barat, Bontang, Balikpapan, Samarinda, Paser, Kutai Timur, Berau dan Penajam Paser Utara',
        'isr' => 2015,
        'users' => 5080,
        'capaian_pct' => 90.0,
        'sanksi' => 0,
        'signer' => 'Widhy Baskoro S.T',
        'signer_title' => 'Plh. Kepala Balai Monitor SFR Kelas I Samarinda',
        'report_no' => 'Laporan Capaian PK SFR No. 678/BALMON.64/SP.02.10/09/2026',
        'report_date' => '30 September 2026'
    ],
    10 => [
        'kab_count' => null,
        'kab_list' => 'Menunggu pelaksanaan observasi spektrum frekuensi radio bulan berjalan',
        'isr' => null,
        'users' => null,
        'capaian_pct' => null,
        'sanksi' => 0,
        'signer' => '-',
        'signer_title' => 'Menunggu Pelaksanaan',
        'report_no' => 'Menunggu Laporan Bulan Berjalan',
        'report_date' => 'Tahun 2026',
        'status' => 'in_progress'
    ],
    11 => [
        'kab_count' => null,
        'kab_list' => 'Menunggu pelaksanaan observasi spektrum frekuensi radio bulan berjalan',
        'isr' => null,
        'users' => null,
        'capaian_pct' => null,
        'sanksi' => 0,
        'signer' => '-',
        'signer_title' => 'Menunggu Pelaksanaan',
        'report_no' => 'Menunggu Laporan Bulan Berjalan',
        'report_date' => 'Tahun 2026',
        'status' => 'pending'
    ],
    12 => [
        'kab_count' => null,
        'kab_list' => 'Menunggu pelaksanaan observasi spektrum frekuensi radio bulan berjalan',
        'isr' => null,
        'users' => null,
        'capaian_pct' => null,
        'sanksi' => 0,
        'signer' => '-',
        'signer_title' => 'Menunggu Pelaksanaan',
        'report_no' => 'Menunggu Laporan Bulan Berjalan',
        'report_date' => 'Tahun 2026',
        'status' => 'pending'
    ],
];

// Data 1 Tahun Penuh (Jan - Des) untuk Chart ISR dan Pengguna PK.1 (Okt - Des = null / menunggu bulan berjalan)
$pk1IsrChartData = [];
$pk1UsersChartData = [];
foreach ($pk1MonthlyDetails as $mNum => $d) {
    $pk1IsrChartData[] = [
        'x' => $monthShort[$mNum],
        'y' => ($d['isr'] !== null) ? (int)$d['isr'] : null
    ];
    $pk1UsersChartData[] = [
        'x' => $monthShort[$mNum],
        'y' => ($d['users'] !== null) ? (int)$d['users'] : null
    ];
}

// Data untuk Chart ApexCharts
$chartCategories = [];
$chartCapaianSeries = [];

foreach ($monthlyRecords as $row) {
    $mNum = (int)$row['month_num'];
    $chartCategories[] = $monthShort[$mNum];
    
    if ($selectedCode === 'PK.2') {
        $chartCapaianSeries[] = isset($pk2OfficialTable[$mNum]) ? (float)$pk2OfficialTable[$mNum]['pk_pct'] : (float)$row['cumulative_pct'];
    } elseif ($selectedCode === 'PK.3') {
        $chartCapaianSeries[] = isset($pk3OfficialTable[$mNum]) ? (float)$pk3OfficialTable[$mNum]['cum_pct'] : (float)$row['cumulative_pct'];
    } elseif ($selectedCode === 'PK.6') {
        $chartCapaianSeries[] = isset($pk6OfficialTable[$mNum]) && $pk6OfficialTable[$mNum]['sla_gabungan'] > 0 ? (float)$pk6OfficialTable[$mNum]['sla_gabungan'] : null;
    } elseif ($selectedCode === 'PK.7') {
        $chartCapaianSeries[] = isset($pk7OfficialTable[$mNum]) ? (float)$pk7OfficialTable[$mNum]['cum_pct'] : (float)$row['cumulative_pct'];
    } elseif ($selectedCode === 'PK.8') {
        $chartCapaianSeries[] = isset($pk8OfficialTable[$mNum]) ? (float)$pk8OfficialTable[$mNum]['cum_pct'] : (float)$row['cumulative_pct'];
    } else {
        $chartCapaianSeries[] = (float)$row['cumulative_pct'];
    }
}

// Data Spotlight / Stat Cards Bulan Terpilih
if ($selectedMonth > 0 && isset($monthlyMap[$selectedMonth])) {
    $currentStat = $monthlyMap[$selectedMonth];
    $activeMonthName = $monthNames[$selectedMonth];
    $activeInfoLabel = "Pelaksanaan Bulan " . $activeMonthName . " {$selectedYear}";
} else {
    // Mode Semua Bulan (Overview)
    $activeMonthName = ($selectedCode === 'PK.3') 
        ? "Kumulatif TA 2026" 
        : (($selectedCode === 'PK.6') 
            ? "Kumulatif s.d. Mei" 
            : (($selectedCode === 'PK.7') 
                ? "Kumulatif s.d. Oktober" 
                : (($selectedCode === 'PK.8') 
                    ? "Kumulatif s.d. Oktober" 
                    : "Kumulatif s.d. September")));

    $activeInfoLabel = ($selectedCode === 'PK.2') 
        ? "Rekapitulasi Kumulatif s.d. Bulan September {$selectedYear} (91.82%)" 
        : (($selectedCode === 'PK.3')
            ? "Rekapitulasi Kinerja Penanganan Gangguan SFR TA {$selectedYear} (100.0% - 2 Aduan Selesai Tuntas)"
            : (($selectedCode === 'PK.4')
                ? "Rekapitulasi Kumulatif s.d. Bulan September {$selectedYear} (100.0% - 1.052 Unit di 8 Kab/Kota)"
                : (($selectedCode === 'PK.6')
                    ? "Rekapitulasi Kumulatif s.d. Bulan Mei {$selectedYear} (92.25% SLA - 5 Stasiun Monitor)"
                    : (($selectedCode === 'PK.7')
                        ? "Rekapitulasi Kumulatif s.d. Bulan Oktober {$selectedYear} (4 Surat Tagihan Denda - Rp 201.258.400)"
                        : (($selectedCode === 'PK.8')
                            ? "Rekapitulasi Kumulatif s.d. Bulan Oktober {$selectedYear} (100.0% - 8 dari 8 Kab/Kota Terukur)"
                            : "Rekapitulasi Kumulatif s.d. Bulan September {$selectedYear} (90.0% - 2.015 ISR)")))));
}

// ============================================================
// DYNAMIC COMPONENT / PARAMETER DATA BERDASARKAN FILTER BULAN
// ============================================================
if ($selectedCode === 'PK.1') {
    if ($selectedMonth > 0 && isset($pk1MonthlyDetails[$selectedMonth])) {
        $p1 = $pk1MonthlyDetails[$selectedMonth];
        $isPendingMonth = ($p1['isr'] === null);

        if ($isPendingMonth) {
            $p1KabCount = 0;
            $p1KabPct = 0.0;
            $p1KabList = 'Menunggu pelaksanaan observasi spektrum frekuensi radio bulan berjalan';
            $p1Pita = 0;
            $p1Isr = 0;
            $p1IsrPct = 0.0;
            $p1Users = 0;
            $p1Sanksi = 0;
            $p1ReportNo = 'Menunggu Laporan Bulan Berjalan';
            $p1ReportDate = 'Tahun ' . $selectedYear;
            $p1Signer = '-';
            $p1SignerTitle = 'Dalam Pelaksanaan';
            $filterPeriodLabel = "Bulan " . $monthNames[$selectedMonth] . " {$selectedYear} (Menunggu Bulan Berjalan)";
        } else {
            $p1KabCount = (int)$p1['kab_count'];
            $p1KabPct = (float)$p1['capaian_pct'];
            $p1KabList = $p1['kab_list'];
            $p1Pita = ($selectedMonth >= 8) ? 18 : 15;
            $p1Isr = (int)$p1['isr'];
            $p1IsrPct = round(($p1Isr / 1312) * 100, 1);
            $p1Users = (int)$p1['users'];
            $p1Sanksi = (int)($p1['sanksi'] ?? 0);
            $p1ReportNo = $p1['report_no'];
            $p1ReportDate = $p1['report_date'];
            $p1Signer = $p1['signer'];
            $p1SignerTitle = $p1['signer_title'];
            $filterPeriodLabel = "Bulan " . $monthNames[$selectedMonth] . " {$selectedYear} (Capaian: {$p1KabPct}% - " . number_format($p1Isr, 0, ',', '.') . " ISR)";
        }
    } else {
        // Mode Akumulatif s.d. September 2026 (Bulan terakhir terealisasi)
        $isPendingMonth = false;
        $p1KabCount = 9;
        $p1KabPct = 90.0;
        $p1KabList = 'Kutai Kartanegara, Kutai Barat, Bontang, Balikpapan, Samarinda, Paser, Kutai Timur, Berau dan Penajam Paser Utara';
        $p1Pita = 18;
        $p1Isr = 2015;
        $p1IsrPct = round((2015 / 1312) * 100, 1);
        $p1Users = 5080;
        $p1Sanksi = 0;
        $p1ReportNo = 'Rekapitulasi Kumulatif s.d. September (ROL)';
        $p1ReportDate = '30 September ' . $selectedYear;
        $p1Signer = 'Widhy Baskoro S.T';
        $p1SignerTitle = 'Plh. Kepala Balai Monitor SFR Kelas I Samarinda';
        $filterPeriodLabel = "Akumulatif s.d. September {$selectedYear} (90% - 2.015 ISR)";
    }
} elseif ($selectedCode === 'PK.2') {
    if ($selectedMonth > 0 && isset($pk2OfficialTable[$selectedMonth])) {
        $p2 = $pk2OfficialTable[$selectedMonth];
        $p2FmCapaian = (int)$p2['fm_capaian'];
        $p2FmPct = (float)$p2['fm_pct'];
        $p2TvCapaian = (int)$p2['tv_capaian'];
        $p2TvPct = (float)$p2['tv_pct'];
        $p2RemoteCapaian = (int)$p2['remote_capaian'];
        $p2RemotePct = (float)$p2['remote_pct'];
        $p2ShelterCapaian = (int)$p2['shelter_capaian'];
        $p2ShelterPct = (float)$p2['shelter_pct'];
        $p2TotalStn = (int)$p2['total_capaian'];
        $p2PkPct = (float)$p2['pk_pct'];
        $filterPeriodLabel = "Bulan " . $monthNames[$selectedMonth] . " {$selectedYear} (Capaian: " . number_format($p2PkPct, 2) . "% - " . number_format($p2TotalStn, 0, ',', '.') . " Stn)";
    } else {
        // Akumulatif s.d. September
        $p2FmCapaian = 50;
        $p2FmPct = 100.00;
        $p2TvCapaian = 17;
        $p2TvPct = 94.44;
        $p2RemoteCapaian = 1791;
        $p2RemotePct = 87.25;
        $p2ShelterCapaian = 330;
        $p2ShelterPct = 125.86;
        $p2TotalStn = 2188;
        $p2PkPct = 91.82;
        $filterPeriodLabel = "Akumulatif s.d. September {$selectedYear} (91.82% - 2.188 Stn)";
    }
} else {
    if ($selectedMonth > 0 && isset($monthlyMap[$selectedMonth])) {
        $filterPeriodLabel = "Bulan " . $monthNames[$selectedMonth] . " {$selectedYear} ({$monthlyMap[$selectedMonth]['cumulative_pct']}%)";
    } else {
        $filterPeriodLabel = "Akumulatif Tahunan {$selectedYear} ({$activePk['realization']}{$activePk['unit']})";
    }
}

// ============================================================
// DATA CLIENT-SIDE UNTUK PERGANTIAN BULAN INSTAN REALTIME (0ms)
// ============================================================
$pkRealtimeData = [];

if ($selectedCode === 'PK.1') {
    // Month 0 (Akumulatif s.d. September)
    $pkRealtimeData[0] = [
        'period_label' => "Akumulatif s.d. September {$selectedYear} (90% - 2.015 ISR)",
        'section_title' => "4 Parameter Kunci Evaluasi Monitoring SFR (Akumulatif s.d. September)",
        'section_subtitle' => "Realisasi s.d. September {$selectedYear}: <strong>9 dari 10 Kab/Kota (90%), 2.015 ISR</strong>",
        'param1_badge_class' => 'bg-success text-white',
        'param1_badge_text' => '<i class="fa fa-check me-1"></i>Tercapai',
        'param1_value' => '9 / 10 <small class="fs-11 text-muted">Kab/Kota</small>',
        'param1_title' => '90.0% Kab/Kota Termonitor',
        'param1_desc' => 'Kutai Kartanegara, Kutai Barat, Bontang, Balikpapan, Samarinda, Paser, Kutai Timur, Berau dan Penajam Paser Utara',
        'param2_badge_class' => 'bg-success text-white',
        'param2_badge_text' => '153.6%',
        'param2_value' => '2.015 <small class="fs-11 text-muted">/ 1.312 ISR</small>',
        'param2_value_class' => 'text-success',
        'param2_title' => 'Target Terlampaui',
        'param2_title_class' => 'text-success',
        'param3_badge_class' => 'bg-success text-white',
        'param3_badge_text' => '<i class="fa fa-check me-1"></i>100%',
        'param3_value' => '5.080 <small class="fs-11 text-muted">Pengguna</small>',
        'param3_title' => '100% Teridentifikasi',
        'param4_badge_class' => 'bg-success text-white',
        'param4_badge_text' => '<i class="fa fa-shield me-1"></i>Tertib',
        'param4_value' => '0 <small class="fs-11 text-muted">Pelanggaran</small>',
        'strip_html' => '',
        'chart_isr_badge' => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-1"><i class="fa fa-arrow-up me-1"></i>s.d. September: 2.015 ISR (153.6% - Target Terlampaui)</span>',
        'chart_users_badge' => '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fs-10 px-2 py-1"><i class="fa fa-users me-1"></i>s.d. September: 5.080 Pengguna Terdata</span>'
    ];

    for ($m = 1; $m <= 12; $m++) {
        $p = $pk1MonthlyDetails[$m] ?? null;
        if (!$p) continue;
        $mName = $monthNames[$m];
        $isPending = ($p['isr'] === null);

        if ($isPending) {
            $pkRealtimeData[$m] = [
                'period_label' => "Bulan {$mName} {$selectedYear} (Menunggu Bulan Berjalan)",
                'section_title' => "4 Parameter Kunci Evaluasi Monitoring SFR (Bulan {$mName})",
                'section_subtitle' => '<span class="text-warning fw-semibold"><i class="fa fa-clock me-1"></i>Menunggu pelaksanaan bulan berjalan</span>',
                'param1_badge_class' => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25',
                'param1_badge_text' => '<i class="fa fa-clock me-1"></i>Menunggu',
                'param1_value' => '- <small class="fs-11 text-muted">/ 10 Kab/Kota</small>',
                'param1_title' => 'Menunggu Bulan Berjalan',
                'param1_desc' => $p['kab_list'],
                'param2_badge_class' => 'bg-light text-muted border',
                'param2_badge_text' => 'Menunggu',
                'param2_value' => '- <small class="fs-11 text-muted">/ 1.312 ISR</small>',
                'param2_value_class' => 'text-muted',
                'param2_title' => 'Menunggu Realisasi',
                'param2_title_class' => 'text-muted',
                'param3_badge_class' => 'bg-light text-muted border',
                'param3_badge_text' => 'Menunggu',
                'param3_value' => '- <small class="fs-11 text-muted">Pengguna</small>',
                'param3_title' => 'Menunggu Realisasi',
                'param4_badge_class' => 'bg-light text-muted border',
                'param4_badge_text' => 'Menunggu',
                'param4_value' => '-',
                'strip_html' => '',
                'chart_isr_badge' => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 fs-10 px-2 py-1"><i class="fa fa-clock me-1"></i>' . htmlspecialchars($mName) . ': Menunggu Bulan Berjalan</span>',
                'chart_users_badge' => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 fs-10 px-2 py-1"><i class="fa fa-clock me-1"></i>' . htmlspecialchars($mName) . ': Menunggu Bulan Berjalan</span>'
            ];
        } else {
            $pIsr = (int)$p['isr'];
            $pIsrPct = round(($pIsr / 1312) * 100, 1);
            $pUsers = (int)$p['users'];
            $pKabCount = (int)$p['kab_count'];
            $pKabPct = (float)$p['capaian_pct'];

            $pkRealtimeData[$m] = [
                'period_label' => "Bulan {$mName} {$selectedYear} (Capaian: {$pKabPct}% - " . number_format($pIsr, 0, ',', '.') . " ISR)",
                'section_title' => "4 Parameter Kunci Evaluasi Monitoring SFR (Bulan {$mName})",
                'section_subtitle' => "Capaian: {$pKabPct}% (Target UPT: 80%)",
                'param1_badge_class' => ($pKabPct >= 80) ? 'bg-success text-white' : 'bg-primary text-white',
                'param1_badge_text' => ($pKabPct >= 80) ? '<i class="fa fa-check me-1"></i>Tercapai' : 'Progres',
                'param1_value' => "{$pKabCount} / 10 <small class=\"fs-11 text-muted\">Kab/Kota</small>",
                'param1_title' => "{$pKabPct}% Kab/Kota Termonitor",
                'param1_desc' => $p['kab_list'],
                'param2_badge_class' => ($pIsrPct >= 100) ? 'bg-success text-white' : 'bg-primary text-white',
                'param2_badge_text' => "{$pIsrPct}%",
                'param2_value' => number_format($pIsr, 0, ',', '.') . ' <small class="fs-11 text-muted">/ 1.312 ISR</small>',
                'param2_value_class' => 'text-success',
                'param2_title' => ($pIsrPct >= 100) ? 'Target Terlampaui' : 'Progres Target',
                'param2_title_class' => 'text-success',
                'param3_badge_class' => 'bg-success text-white',
                'param3_badge_text' => '<i class="fa fa-check me-1"></i>100%',
                'param3_value' => number_format($pUsers, 0, ',', '.') . ' <small class="fs-11 text-muted">Pengguna</small>',
                'param3_title' => '100% Teridentifikasi',
                'param4_badge_class' => 'bg-success text-white',
                'param4_badge_text' => '<i class="fa fa-shield me-1"></i>Tertib',
                'param4_value' => '0 <small class="fs-11 text-muted">Pelanggaran</small>',
                'strip_html' => '',
                'chart_isr_badge' => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-1"><i class="fa fa-arrow-up me-1"></i>' . htmlspecialchars($mName) . ': ' . number_format($pIsr, 0, ',', '.') . ' ISR (' . round(($pIsr / 1312) * 100, 1) . '%)</span>',
                'chart_users_badge' => '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fs-10 px-2 py-1"><i class="fa fa-users me-1"></i>' . htmlspecialchars($mName) . ': ' . number_format($pUsers, 0, ',', '.') . ' Pengguna</span>'
            ];
        }
    }
} elseif ($selectedCode === 'PK.2') {
    // PK.2
    $pkRealtimeData[0] = [
        'period_label' => "Akumulatif s.d. September {$selectedYear} (91.82% - 2.188 Stn)",
        'section_title' => "4 Komponen Sasaran Pemeriksaan Stasiun Radio (Akumulatif)",
        'section_subtitle' => 'Realisasi Kumulatif s.d. September ' . $selectedYear . ': <strong>2.188 Stasiun (91.82%)</strong>',
        'fm_capaian' => '50 / 50 <small class="fs-11 text-muted">Stasiun</small>',
        'fm_pct_text' => 'Radio FM Siaran (100.00%)',
        'fm_pct_val' => 100,
        'fm_badge' => '<i class="fa fa-check me-1"></i>100% Tercapai',
        'fm_badge_class' => 'bg-success text-white',
        'fm_desc' => 'Target 50 stasiun telah terpenuhi tuntas sejak bulan Juni 2026.',
        'tv_capaian' => '17 / 18 <small class="fs-11 text-muted">Stasiun</small>',
        'tv_pct_text' => 'TV Digital DVB-T2 (94.44%)',
        'tv_pct_val' => 94.44,
        'tv_badge' => '94.4% On-Track',
        'tv_badge_class' => 'bg-info text-white',
        'tv_desc' => 'Realisasi 17 dari target 18 stasiun TV Digital (sisa 1 stasiun).',
        'remote_capaian' => '1.791 / 2.053 <small class="fs-11 text-muted">Site</small>',
        'remote_pct_text' => 'Microwave Link Remote (87.25%)',
        'remote_pct_val' => 87.25,
        'remote_badge' => '87.3% On-Track',
        'remote_badge_class' => 'bg-primary text-white',
        'shelter_capaian' => '330 / 262 <small class="fs-11 text-muted">Shelter</small>',
        'shelter_pct_text' => 'Open Shelter (125.86%)',
        'shelter_pct_val' => 100,
        'shelter_badge' => '<i class="fa fa-arrow-up me-1"></i>125.9%',
        'shelter_badge_class' => 'bg-success text-white',
        'shelter_desc' => 'Melampaui target 262 shelter dengan surplus 68 shelter terinspeksi.',
        'strip_html' => ''
    ];

    for ($m = 1; $m <= 12; $m++) {
        $r = $pk2OfficialTable[$m] ?? null;
        if (!$r) continue;
        $mName = $monthNames[$m];
        $totalStn = (int)$r['total_capaian'];
        $pkPct = (float)$r['pk_pct'];
        $fmCap = (int)$r['fm_capaian'];
        $fmPct = (float)$r['fm_pct'];
        $tvCap = (int)$r['tv_capaian'];
        $tvPct = (float)$r['tv_pct'];
        $remCap = (int)$r['remote_capaian'];
        $remPct = (float)$r['remote_pct'];
        $shCap = (int)$r['shelter_capaian'];
        $shPct = (float)$r['shelter_pct'];

        $pkRealtimeData[$m] = [
            'period_label' => "Bulan {$mName} {$selectedYear} (Capaian: " . number_format($pkPct, 2) . "% - " . number_format($totalStn, 0, ',', '.') . " Stn)",
            'section_title' => "4 Komponen Sasaran Pemeriksaan Stasiun Radio (Bulan {$mName})",
            'section_subtitle' => 'Realisasi Bulan ' . $mName . ': <strong>' . number_format($totalStn, 0, ',', '.') . ' Stasiun (' . number_format($pkPct, 2) . '%)</strong>',
            'fm_capaian' => "{$fmCap} / 50 <small class=\"fs-11 text-muted\">Stasiun</small>",
            'fm_pct_text' => 'Radio FM Siaran (' . number_format($fmPct, 2) . '%)',
            'fm_pct_val' => min(100, $fmPct),
            'fm_badge' => ($fmPct >= 100) ? '<i class="fa fa-check me-1"></i>100% Tercapai' : number_format($fmPct, 1) . '%',
            'fm_badge_class' => ($fmPct >= 100) ? 'bg-success text-white' : 'bg-primary text-white',
            'fm_desc' => ($fmPct >= 100) ? 'Target 50 stasiun telah terpenuhi tuntas sejak bulan Juni 2026.' : 'Target tahunan 50 stasiun radio FM di wilayah kerja Kaltim.',
            'tv_capaian' => "{$tvCap} / 18 <small class=\"fs-11 text-muted\">Stasiun</small>",
            'tv_pct_text' => 'TV Digital DVB-T2 (' . number_format($tvPct, 2) . '%)',
            'tv_pct_val' => min(100, $tvPct),
            'tv_badge' => ($tvPct >= 100) ? '<i class="fa fa-check me-1"></i>100%' : number_format($tvPct, 1) . '% On-Track',
            'tv_badge_class' => ($tvPct >= 100) ? 'bg-success text-white' : 'bg-info text-white',
            'tv_desc' => "Realisasi {$tvCap} dari target 18 stasiun TV Digital (sisa " . max(0, 18 - $tvCap) . " stasiun).",
            'remote_capaian' => number_format($remCap, 0, ',', '.') . ' / 2.053 <small class="fs-11 text-muted">Site</small>',
            'remote_pct_text' => 'Microwave Link Remote (' . number_format($remPct, 2) . '%)',
            'remote_pct_val' => min(100, $remPct),
            'remote_badge' => ($remPct >= 100) ? '<i class="fa fa-check me-1"></i>100%' : number_format($remPct, 1) . '% On-Track',
            'remote_badge_class' => ($remPct >= 100) ? 'bg-success text-white' : 'bg-primary text-white',
            'shelter_capaian' => number_format($shCap, 0, ',', '.') . ' / 262 <small class="fs-11 text-muted">Shelter</small>',
            'shelter_pct_text' => 'Open Shelter (' . number_format($shPct, 2) . '%)',
            'shelter_pct_val' => min(100, $shPct),
            'shelter_badge' => ($shPct >= 100) ? '<i class="fa fa-arrow-up me-1"></i>' . number_format($shPct, 1) . '%' : number_format($shPct, 1) . '%',
            'shelter_badge_class' => ($shPct >= 100) ? 'bg-success text-white' : 'bg-primary text-white',
            'shelter_desc' => ($shPct >= 100) ? 'Melampaui target 262 shelter dengan surplus ' . ($shCap - 262) . ' shelter terinspeksi.' : 'Pemeriksaan fisik shelter perangkat stasiun radio di lapangan.',
            'strip_html' => ''
        ];
    }
} elseif ($selectedCode === 'PK.3' && $selectedYear === 2026) {
    // PK.3: Penanganan Gangguan Frekuensi Radio
    $pkRealtimeData[0] = [
        'period_label' => "Akumulatif Tahunan {$selectedYear} (100.0% - 2 Kasus Selesai Tuntas)",
        'section_title' => "4 Parameter Kunci Evaluasi Penanganan Gangguan SFR (Akumulatif)",
        'section_subtitle' => 'Realisasi Kinerja TA ' . $selectedYear . ': <strong>100.0% Penyelesaian</strong> (2 dari 2 Aduan Selesai Tuntas)',
        'aduan_masuk' => '2 <small class="fs-11 text-muted">Aduan Resmi</small>',
        'aduan_badge' => '<i class="fa fa-file-shield me-1"></i>2 Aduan Masuk',
        'aduan_badge_class' => 'bg-danger text-white',
        'selesai_capaian' => '2 / 2 <small class="fs-11 text-muted">Kasus (100%)</small>',
        'selesai_badge' => '<i class="fa fa-check-circle me-1"></i>100% Bebas Gangguan',
        'selesai_badge_class' => 'bg-success text-white',
        'entitas_lindung' => '2 <small class="fs-11 text-muted">Badan Usaha</small>',
        'entitas_desc' => 'PT. Kideco Jaya Agung & PT. Multi Harapan Utama',
        'sla_capaian' => '< 24 Jam <small class="fs-11 text-muted">Respon</small>',
        'sla_badge' => '<i class="fa fa-bolt me-1"></i>100% SOP Terpenuhi',
        'sla_badge_class' => 'bg-success text-white',
        'strip_html' => ''
    ];

    for ($m = 1; $m <= 12; $m++) {
        $r = $pk3OfficialTable[$m] ?? null;
        if (!$r) continue;
        $mName = $monthNames[$m];
        if ($r['has_case']) {
            $cId = $r['case_id'];
            $caseData = $pk3OfficialCases[$cId - 1] ?? null;
            $pkRealtimeData[$m] = [
                'period_label' => "Bulan {$mName} {$selectedYear} (1 Kasus Selesai - 100%)",
                'section_title' => "4 Parameter Penanganan Gangguan SFR (Bulan {$mName})",
                'section_subtitle' => "Realisasi Bulan {$mName}: <strong>1 Aduan Masuk & 1 Selesai Tuntas (100.0%)</strong>",
                'aduan_masuk' => '1 <small class="fs-11 text-muted">Aduan Resmi</small>',
                'aduan_badge' => '<i class="fa fa-file-shield me-1"></i>1 Aduan',
                'aduan_badge_class' => 'bg-danger text-white',
                'selesai_capaian' => '1 / 1 <small class="fs-11 text-muted">Kasus (100%)</small>',
                'selesai_badge' => '<i class="fa fa-check-circle me-1"></i>Selesai Tuntas',
                'selesai_badge_class' => 'bg-success text-white',
                'entitas_lindung' => '1 <small class="fs-11 text-muted">Badan Usaha</small>',
                'entitas_desc' => $caseData['pelapor'] ?? 'Pemegang Hak ISR',
                'sla_capaian' => '< 24 Jam <small class="fs-11 text-muted">Respon</small>',
                'sla_badge' => '<i class="fa fa-bolt me-1"></i>SOP Terpenuhi',
                'sla_badge_class' => 'bg-success text-white',
                'strip_html' => ''
            ];
        } else {
            $pkRealtimeData[$m] = [
                'period_label' => "Bulan {$mName} {$selectedYear} (Nihil Aduan Gangguan)",
                'section_title' => "4 Parameter Penanganan Gangguan SFR (Bulan {$mName})",
                'section_subtitle' => '<span class="text-success fw-semibold"><i class="fa fa-shield-halved me-1"></i>Tidak ada kegiatan penanganan gangguan (Spektrum Kondusif)</span>',
                'aduan_masuk' => '0 <small class="fs-11 text-muted">Aduan</small>',
                'aduan_badge' => '<i class="fa fa-shield me-1"></i>Nihil Aduan',
                'aduan_badge_class' => 'bg-success text-white',
                'selesai_capaian' => '- <small class="fs-11 text-muted">Tidak Ada Kasus</small>',
                'selesai_badge' => 'Kondusif',
                'selesai_badge_class' => 'bg-light text-muted border',
                'entitas_lindung' => '- <small class="fs-11 text-muted">Nihil</small>',
                'entitas_desc' => 'Tidak ada laporan gangguan yang masuk',
                'sla_capaian' => 'Siaga 24 Jam <small class="fs-11 text-muted">Standby</small>',
                'sla_badge' => 'Siaga Reaksi Cepat',
                'sla_badge_class' => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25',
                'strip_html' => ''
            ];
        }
    }
} else {
    // PK.3 - PK.8
    $pkRealtimeData[0] = [
        'period_label' => "Akumulatif Tahunan {$selectedYear} ({$activePk['realization']}{$activePk['unit']})"
    ];
    for ($m = 1; $m <= 12; $m++) {
        $mName = $monthNames[$m];
        $pct = isset($monthlyMap[$m]) ? $monthlyMap[$m]['cumulative_pct'] : 0;
        $pkRealtimeData[$m] = [
            'period_label' => "Bulan {$mName} {$selectedYear} ({$pct}%)"
        ];
    }
}

$pageTitle = "Capaian Kinerja - " . ($pkNavMeta[$selectedCode]['short_nav'] ?? $activePk['title']);

// Header layout includes
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav_header.php';
require_once __DIR__ . '/includes/chatbox.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
/* Styling Elegan & Bersih Halaman Capaian PK */
/* Soft primary badge styling (mengatasi override !important pada .bg-primary dari template style.css) */
.badge-soft-primary,
.bg-primary.bg-opacity-10,
.badge.bg-primary.bg-opacity-10,
div.bg-primary.bg-opacity-10,
span.bg-primary.bg-opacity-10 {
    background-color: #e8f2fc !important;
    color: #0062c4 !important;
}

.badge-soft-primary.border,
.badge.bg-primary.bg-opacity-10.border,
span.bg-primary.bg-opacity-10.border {
    border: 1px solid rgba(0, 98, 196, 0.25) !important;
}

.badge-soft-primary i,
.badge-soft-primary .fa,
.badge-soft-primary .material-symbols-outlined,
.bg-primary.bg-opacity-10 i,
.bg-primary.bg-opacity-10 .fa,
.bg-primary.bg-opacity-10 .material-symbols-outlined {
    color: #0062c4 !important;
}

.pk-hero-banner {
    background: #ffffff;
    border: 1px solid rgba(10, 22, 40, 0.08);
    border-radius: 16px;
    box-shadow: 0 1px 4px rgba(10, 22, 40, 0.03);
    position: relative;
    overflow: hidden;
}

.pk-param-card {
    background: #ffffff;
    border: 1px solid rgba(10, 22, 40, 0.07);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);
    padding: 16px;
    transition: all 0.15s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.pk-param-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(10, 22, 40, 0.06);
    border-color: #0284c7;
}

.pk-chart-card {
    background: #ffffff;
    border: 1px solid rgba(10, 22, 40, 0.08);
    border-radius: 14px;
    box-shadow: 0 1px 4px rgba(10, 22, 40, 0.03);
    overflow: hidden;
    height: 100%;
}

.pk-month-pill {
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 20px;
    color: #0284c7;
    background: #ffffff;
    border: 1px solid rgba(2, 132, 199, 0.25);
    transition: all 0.15s ease;
    text-decoration: none;
    display: inline-block;
}

.pk-month-pill:hover {
    background: #e0f2fe;
    color: #0369a1;
    border-color: #0284c7;
}

.pk-month-pill.active {
    background: #0070E0 !important;
    color: #ffffff !important;
    border-color: #0070E0 !important;
    box-shadow: 0 2px 6px rgba(0, 112, 224, 0.2);
}

.wilayah-item {
    border-radius: 10px;
    border: 1px solid rgba(10, 22, 40, 0.06);
    padding: 10px 12px;
    background: #ffffff;
    transition: all 0.15s ease;
}

.wilayah-item.termonitor {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.wilayah-item.terjadwal {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.pk-table-clean {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

.pk-table-clean thead th {
    background: #f0f9ff;
    color: #0369a1;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #bae6fd;
    padding: 11px 12px;
}

.pk-table-clean tbody td {
    padding: 11px 12px;
    vertical-align: middle;
    font-size: 12px;
    border-bottom: 1px solid #f1f5f9;
}

.pk-table-clean tbody tr:hover td {
    background-color: #f8fafc;
}

.pk-table-clean tbody tr.row-active td {
    background-color: #eff6ff !important;
}

.pk-table-clean tfoot td {
    background: #f8fafc;
    border-top: 2px solid #cbd5e1;
    font-weight: 700;
    font-size: 12px;
    padding: 11px 12px;
}

.formula-box {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    padding: 10px 14px;
    display: inline-block;
}
</style>

<div class="content-body" style="background: #f8fafc; min-height: 100vh;">
    <div class="container-fluid">

        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm border-0 mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="material-symbols-outlined fs-20 text-success me-2">check_circle</i>
                    <div>
                        <strong class="d-block text-success fs-13">Berhasil Tersimpan!</strong>
                        <span class="fs-12 text-dark"><?= htmlspecialchars($flashSuccess) ?></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif (!empty($flashError)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm border-0 mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="material-symbols-outlined fs-20 text-danger me-2">error</i>
                    <div>
                        <strong class="d-block text-danger fs-13">Terjadi Kesalahan!</strong>
                        <span class="fs-12 text-dark"><?= htmlspecialchars($flashError) ?></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- 1. HERO BANNER: RINGKASAN CAPAIAN & FORMULA PENGUKURAN -->
        <!-- ============================================================ -->
        <div class="card pk-hero-banner p-4 mb-4">
            <div class="row align-items-center">
                <div class="col-xl-8 col-lg-7">
                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                        <span class="badge bg-primary text-white fw-bold fs-11 px-2.5 py-1 text-uppercase rounded-pill">
                            <?= $selectedCode ?>: <?= htmlspecialchars($pkNavMeta[$selectedCode]['short_nav'] ?? $activePk['title']) ?>
                        </span>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 fs-11 px-2 py-1 rounded-pill">
                            <i class="material-symbols-outlined fs-12 align-middle me-1">verified</i>
                            Aplikasi: <?= htmlspecialchars($activePk['app_reference']) ?>
                        </span>
                        <span class="badge bg-light text-muted border fs-11 px-2 py-1 rounded-pill">
                            <i class="fa fa-file-contract me-1 text-primary"></i>
                            <?= htmlspecialchars($currentYearMeta['legal_basis'] ?? "Penetapan PK TA {$selectedYear}") ?>
                        </span>
                    </div>

                    <h3 class="fw-bold mb-2" style="color: #0A1628; letter-spacing: -0.3px;">
                        <?= htmlspecialchars($activePk['title']) ?>
                    </h3>
                    
                    <?php if ($selectedCode === 'PK.1' && $selectedYear === 2026): ?>
                        <p class="text-muted fs-13 mb-3" style="max-width: 680px; line-height: 1.5;">
                            Pengawasan dan observasi spektrum frekuensi radio di 10 Kabupaten/Kota wilayah kerja Balai Monitor SFR Kelas I Samarinda menggunakan Stasiun Tetap/Transportable serta Jinjing/Bergerak melalui aplikasi Report Online (ROL).
                        </p>
                        <!-- Formula PK.1 -->
                        <div class="formula-box">
                            <div class="d-flex align-items-center flex-wrap gap-2 text-dark fs-12">
                                <span class="fw-bold text-primary"><i class="fa fa-calculator me-1"></i>Metode Perhitungan:</span>
                                <span class="badge bg-white text-dark border px-2 py-1 fw-semibold">
                                    Capaian = (Jumlah Kab/Kota Termonitor ÷ 10 Wilayah Kerja UPT) × 100%
                                </span>
                                <span class="fw-bold text-success">
                                    = (9 ÷ 10) × 100% = <strong>90%</strong>
                                </span>
                            </div>
                        </div>
                    <?php elseif ($selectedCode === 'PK.2' && $selectedYear === 2026): ?>
                        <p class="text-muted fs-13 mb-3" style="max-width: 680px; line-height: 1.5;">
                            Pemeriksaan teknis dan administratif stasiun radio di wilayah kerja Balai Monitor SFR Kelas I Samarinda mencakup 4 komponen utama: Pengukuran Siaran (FM & TV Digital), Remote Site microwave link operator, dan Open Shelter melalui aplikasi APSTARD & ROL.
                        </p>
                        <!-- Formula PK.2 Sesuai Tabel Resmi Balmon -->
                        <div class="formula-box">
                            <div class="d-flex align-items-center flex-wrap gap-2 text-dark fs-12">
                                <span class="fw-bold text-primary"><i class="fa fa-calculator me-1"></i>Metode Perhitungan:</span>
                                <span class="badge bg-white text-dark border px-2 py-1 fw-semibold">
                                    Capaian PK = (Total Capaian Pemeriksaan Stasiun ÷ Total Target 2.383) × 100%
                                </span>
                                <span class="fw-bold text-success">
                                    = (2.188 ÷ 2.383) × 100% = <strong>91.82%</strong>
                                </span>
                            </div>
                        </div>
                    <?php elseif ($selectedCode === 'PK.3' && $selectedYear === 2026): ?>
                        <p class="text-muted fs-13 mb-3" style="max-width: 680px; line-height: 1.5;">
                            Penanganan dan penyelesaian gangguan spektrum frekuensi radio (harmful interference) berdasarkan laporan pengaduan resmi pemegang IPFR/ISR di wilayah kerja Balai Monitor SFR Kelas I Samarinda melalui investigasi lapangan, pelacakan sinyal (DF), pengukuran interferensi, hingga penerbitan Berita Acara Pemeriksaan (BAP) dan eliminasi sumber gangguan tuntas (100% Bebas Gangguan), dilaporkan melalui <strong>Sistem Trouble Ticket SFR (laporgangguansfr.postel.go.id)</strong>.
                        </p>
                        <!-- Formula PK.3 Sesuai Perjanjian Kinerja & SOP Ditjen SDPPI -->
                        <div class="formula-box">
                            <div class="d-flex align-items-center flex-wrap gap-2 text-dark fs-12">
                                <span class="fw-bold text-primary"><i class="fa fa-calculator me-1"></i>Rumus Bobot Capaian PK:</span>
                                <span class="badge bg-white text-dark border px-2 py-1 fw-semibold font-monospace">
                                    [ Jumlah Gangguan SFR yang Diselesaikan ÷ Jumlah Total Gangguan SFR Masuk ] × 100%
                                </span>
                                <span class="fw-bold text-success">
                                    = [ 2 Aduan Selesai ÷ 2 Total Aduan ] × 100% = <strong class="fs-13">100.00%</strong>
                                </span>
                                <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill ms-auto">
                                    <i class="fa fa-circle-check me-1"></i>2 dari 2 Aduan Selesai Tuntas (100%)
                                </span>
                            </div>
                        </div>
                    <?php elseif ($selectedCode === 'PK.4' && $selectedYear === 2026): ?>
                        <p class="text-muted fs-13 mb-3" style="max-width: 680px; line-height: 1.5;">
                            Pengawasan Alat dan/atau Perangkat Telekomunikasi (APT) Balai Monitor Spektrum Frekuensi Radio Kelas I Samarinda mencakup <strong>16 Jenis Perangkat</strong> dengan prioritas utama (Mobile Transceiver/Radio Rig, Handy Talkie, Wireless Access Point) pada <strong>8 Kabupaten/Kota</strong>, dilaporkan melalui <strong>Aplikasi SMART</strong>.
                        </p>
                        <!-- Formula PK.4 Sesuai Laporan Capaian PK APT Cutoff 01 Oktober 2026 -->
                        <div class="formula-box">
                            <div class="d-flex align-items-center flex-wrap gap-2 text-dark fs-12">
                                <span class="fw-bold text-primary"><i class="fa fa-calculator me-1"></i>Rumus Bobot Capaian PK:</span>
                                <span class="badge bg-white text-dark border px-2 py-1 fw-semibold font-monospace">
                                    [ (Σ Perangkat Bersertifikat + Σ Pelanggaran) ÷ Target Perangkat Termonitor ] × 100%
                                </span>
                                <span class="fw-bold text-success">
                                    = [ (1.052 + 0) ÷ 300 ] × 100% = <strong class="fs-13">100.00%</strong>
                                </span>
                                <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill ms-auto">
                                    <i class="fa fa-circle-check me-1"></i>8 dari 8 Kab/Kota Selesai (100%)
                                </span>
                            </div>
                        </div>
                    <?php elseif ($selectedCode === 'PK.6' && $selectedYear === 2026): ?>
                        <p class="text-muted fs-13 mb-3" style="max-width: 680px; line-height: 1.5;">
                            Terjaganya operasional dan fungsi monitoring stasiun monitor frekuensi radio (SMFR) Balai Monitor SFR Kelas I Samarinda mencakup <strong>3 Stasiun Transportable</strong> (Balikpapan, Berau, Kutai Timur), <strong>1 Stasiun Bergerak (Mobil Mon DF Hilux)</strong>, dan <strong>Stasiun Tetap VUHF Samarinda</strong>, dilaporkan berkala melalui <strong>Aplikasi ROL & SMSN</strong>.
                        </p>
                        <!-- Formula PK.6 Sesuai Laporan Kinerja Resmi SMFR -->
                        <div class="formula-box">
                            <div class="d-flex align-items-center flex-wrap gap-2 text-dark fs-12">
                                <span class="fw-bold text-primary"><i class="fa fa-calculator me-1"></i>Rumus Perhitungan SLA:</span>
                                <span class="badge bg-white text-dark border px-2 py-1 fw-semibold font-monospace">
                                    [ (Σ SLA Tetap & Transportable ÷ 4) + (Σ SLA Bergerak ÷ 1) ] ÷ 2
                                </span>
                                <span class="fw-bold text-success">
                                    = Realisasi Rata-rata (Jan-Mei): <strong class="fs-13">92.25% SLA</strong> (Kualitas: 100%)
                                </span>
                                <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill ms-auto">
                                    <i class="fa fa-circle-check me-1"></i>Target Bulanan: 86% Terlampaui
                                </span>
                            </div>
                        </div>
                    <?php elseif ($selectedCode === 'PK.7' && $selectedYear === 2026): ?>
                        <p class="text-muted fs-13 mb-3" style="max-width: 680px; line-height: 1.5;">
                            Pengelolaan dan penanganan sanksi denda administratif terhadap pelanggaran penggunaan spektrum frekuensi radio tanpa perizinan berusaha (ISR) di wilayah kerja Balai Monitor SFR Kelas I Samarinda sesuai PP No. 43/2023, PP No. 28/2025, dan Perdirjen SDPPI No. 3/2024.
                        </p>
                        <!-- Formula PK.7 Sesuai Peraturan Dirjen SDPPI & Indikator Kinerja -->
                        <div class="formula-box">
                            <div class="d-flex align-items-center flex-wrap gap-2 text-dark fs-12">
                                <span class="fw-bold text-primary"><i class="fa fa-calculator me-1"></i>Rumus Bobot Capaian PK:</span>
                                <span class="badge bg-white text-dark border px-2 py-1 fw-semibold font-monospace">
                                    [ Σ Surat Tagihan Terbayar + (Bobot × Tindak Lanjut Tagihan Belum Terbayar) ] ÷ Σ Total Tagihan × 100%
                                </span>
                                <span class="fw-bold text-success">
                                    = [ 4 SPP I Diproses (Bobot 1.0) ÷ 4 Total Tagihan ] × 100% = <strong class="fs-13">95.00%</strong>
                                </span>
                                <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill ms-auto">
                                    <i class="fa fa-circle-check me-1"></i>4 Surat Tagihan I Terbit (Rp 201.258.400)
                                </span>
                            </div>
                        </div>
                    <?php elseif ($selectedCode === 'PK.8' && $selectedYear === 2026): ?>
                        <p class="text-muted fs-13 mb-3" style="max-width: 680px; line-height: 1.5;">
                            Pengukuran kualitas layanan (Quality of Service) terhadap penyelenggaraan jaringan bergerak seluler mencakup <strong>Drive Test</strong> (min. 5 jam, kec ±40 km/jam) pada rute protokol dan <strong>Static Test</strong> (min. 3 titik POI) pada jam sibuk di <strong>8 Kabupaten/Kota (80% Wilayah Kerja)</strong>, terintegrasi ke <strong>Pusat Monitoring Telekomunikasi (PMT)</strong>.
                        </p>
                        <!-- Formula PK.8 Sesuai Laporan Kinerja Resmi QoS -->
                        <div class="formula-box">
                            <div class="d-flex align-items-center flex-wrap gap-2 text-dark fs-12">
                                <span class="fw-bold text-primary"><i class="fa fa-calculator me-1"></i>Rumus Perhitungan Bobot Capaian PK:</span>
                                <span class="badge bg-white text-dark border px-2 py-1 fw-semibold font-monospace">
                                    (Jumlah Kab/Kota Terukur ÷ Target 80% Wilayah Kerja UPT) × 100%
                                </span>
                                <span class="fw-bold text-success">
                                    = (8 ÷ 8 Kab/Kota) × 100% = <strong class="fs-13">100.00%</strong>
                                </span>
                                <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill ms-auto">
                                    <i class="fa fa-circle-check me-1"></i>8 dari 8 Kab/Kota Terukur (100%)
                                </span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted fs-13 mb-3" style="max-width: 680px; line-height: 1.5;">
                            <?= htmlspecialchars($activePk['indicator'] ?: $activePk['title']) ?>.
                        </p>
                        <?php if (!empty($activePk['formula'])): ?>
                            <div class="formula-box">
                                <div class="d-flex align-items-center flex-wrap gap-2 text-dark fs-12">
                                    <span class="fw-bold text-primary"><i class="fa fa-calculator me-1"></i>Formula:</span>
                                    <span class="badge bg-white text-dark border px-2 py-1 fw-semibold">
                                        <?= htmlspecialchars($activePk['formula']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Kolom Target & Realisasi Tahunan -->
                <div class="col-xl-4 col-lg-5 mt-3 mt-lg-0">
                    <div class="row g-2">
                        <!-- Target Tahunan -->
                        <div class="col-6">
                            <div class="p-3 rounded-3 text-center h-100" style="background: #f0f9ff; border: 1px solid #bae6fd;">
                                <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Target PK <?= $selectedYear ?></span>
                                <h3 class="fw-bold mb-0 text-primary" style="font-size: 1.75rem;">
                                    <?= $activePk['target_annual'] ?><small class="fs-13 text-muted ms-1"><?= htmlspecialchars($activePk['unit']) ?></small>
                                </h3>
                                <span class="fs-11 text-muted fw-semibold">
                                    <?= ($selectedCode === 'PK.1' && $selectedYear === 2026) ? '8 dari 10 Kab/Kota' : (($selectedCode === 'PK.2' && $selectedYear === 2026) ? '2.383 Stasiun' : (($selectedCode === 'PK.3' && $selectedYear === 2026) ? 'Target: 100% (2 Aduan Tuntas)' : (($selectedCode === 'PK.4' && $selectedYear === 2026) ? 'Target: 300 Perangkat (8 Kab/Kota)' : (($selectedCode === 'PK.6' && $selectedYear === 2026) ? 'Target: 86% SLA (5 Stasiun)' : (($selectedCode === 'PK.7' && $selectedYear === 2026) ? 'Target: 85% Penanganan (4 SPP Terbit)' : (($selectedCode === 'PK.8' && $selectedYear === 2026) ? 'Target: 8 Kab/Kota (80%)' : 'Sasaran Tahunan')))))) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Realisasi Tahunan -->
                        <div class="col-6">
                            <div class="p-3 rounded-3 text-center h-100" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                                <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Realisasi Capaian</span>
                                <h3 class="fw-bold mb-0 text-success" style="font-size: 1.75rem;">
                                    <?= $activePk['realization'] ?><small class="fs-13 text-muted ms-1"><?= htmlspecialchars($activePk['unit']) ?></small>
                                </h3>
                                <span class="badge bg-success text-white fs-10 px-2 py-0.5 mt-1 rounded-pill">
                                    <i class="fa fa-check-circle me-1"></i>
                                    <?= ((float)$activePk['realization'] >= (float)$activePk['target_annual']) ? 'Target Tercapai' : 'On Track (' . $activePk['realization'] . '%)' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- FILTER PERIODE (BULAN & AKUMULATIF) -->
        <!-- Khusus memfilter Parameter Kunci / Komponen Sasaran -->
        <!-- ============================================================ -->
        <div class="card border rounded-3 p-3 mb-4" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
            <div class="d-flex flex-wrap align-items-center gap-1.5">
                <span class="fs-12 fw-bold text-dark me-2 d-flex align-items-center">
                    <i class="material-symbols-outlined fs-16 text-primary me-1">calendar_month</i>
                    Pilih Periode:
                </span>
                <!-- Tombol Akumulatif -->
                <a href="pk_view.php?code=<?= $selectedCode ?>&year=<?= $selectedYear ?>&month=0" 
                   data-month="0"
                   class="pk-month-pill <?= ($selectedMonth === 0) ? 'active' : '' ?>">
                    Akumulatif
                </a>
                <!-- Tombol Jan s.d. Des -->
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <a href="pk_view.php?code=<?= $selectedCode ?>&year=<?= $selectedYear ?>&month=<?= $m ?>" 
                       data-month="<?= $m ?>"
                       class="pk-month-pill <?= ($selectedMonth === $m) ? 'active' : '' ?>">
                        <?= $monthShort[$m] ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>

        <?php if ($selectedCode === 'PK.1' && $selectedYear === 2026): ?>
            <!-- ============================================================ -->
            <!-- SECTION KHUSUS PK.1: MONITORING SFR DI KABUPATEN/KOTA -->
            <!-- ============================================================ -->
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h5 class="fw-bold text-dark fs-14 mb-0 d-flex align-items-center" id="pk1-section-title">
                    <i class="material-symbols-outlined text-primary fs-18 me-1">checklist</i>
                    4 Parameter Kunci Evaluasi Monitoring SFR <?= ($selectedMonth === 0) ? '(Akumulatif s.d. September)' : '(Bulan ' . $monthNames[$selectedMonth] . ')' ?>
                </h5>
                <span class="fs-11 text-muted" id="pk1-section-subtitle">
                    <?= ($selectedMonth === 0) 
                        ? 'Realisasi s.d. September 2026: <strong>9 dari 10 Kab/Kota (90%), 2.015 ISR</strong>' 
                        : ($isPendingMonth 
                            ? '<span class="text-warning fw-semibold"><i class="fa fa-clock me-1"></i>Menunggu pelaksanaan bulan berjalan</span>' 
                            : 'Capaian: ' . $p1KabPct . '% (Target UPT: 80%)') ?>
                </span>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-xl-3 col-md-6 col-sm-6">
                    <div class="pk-param-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge badge-soft-primary fs-10 fw-bold">PARAM 1</span>
                            <?php if ($isPendingMonth): ?>
                                <span id="pk1-param1-badge" class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fs-10 px-2 py-0.5 rounded-pill"><i class="fa fa-clock me-1"></i>Menunggu</span>
                            <?php else: ?>
                                <span id="pk1-param1-badge" class="badge <?= ($p1KabPct >= 80) ? 'bg-success text-white' : 'bg-primary text-white' ?> fs-10 px-2 py-0.5 rounded-pill">
                                    <?= ($p1KabPct >= 80) ? '<i class="fa fa-check me-1"></i>Tercapai' : 'Progres' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <h4 class="fw-bold text-dark mb-1" id="pk1-param1-value">
                            <?= $isPendingMonth ? '- <small class="fs-11 text-muted">/ 10 Kab/Kota</small>' : $p1KabCount . ' / 10 <small class="fs-11 text-muted">Kab/Kota</small>' ?>
                        </h4>
                        <div class="fs-12 fw-semibold text-primary mb-1" id="pk1-param1-title">
                            <?= $isPendingMonth ? 'Menunggu Bulan Berjalan' : $p1KabPct . '% Kab/Kota Termonitor' ?>
                        </div>
                        <p class="fs-11 text-muted mb-0" id="pk1-param1-desc" title="<?= htmlspecialchars($p1KabList) ?>" style="line-height: 1.35; white-space: normal; word-break: normal;">
                            <?= htmlspecialchars($p1KabList) ?>
                        </p>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 col-sm-6">
                    <div class="pk-param-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-success bg-opacity-10 text-success fs-10 fw-bold">PARAM 2</span>
                            <?php if ($isPendingMonth): ?>
                                <span id="pk1-param2-badge" class="badge bg-light text-muted border fs-10 px-2 py-0.5 rounded-pill">Menunggu</span>
                            <?php else: ?>
                                <span id="pk1-param2-badge" class="badge <?= ($p1IsrPct >= 100) ? 'bg-success text-white' : 'bg-primary text-white' ?> fs-10 px-2 py-0.5 rounded-pill">
                                    <?= $p1IsrPct ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                        <h4 id="pk1-param2-value" class="fw-bold <?= $isPendingMonth ? 'text-muted' : 'text-success' ?> mb-1">
                            <?= $isPendingMonth ? '- <small class="fs-11 text-muted">/ 1.312 ISR</small>' : number_format($p1Isr, 0, ',', '.') . ' <small class="fs-11 text-muted">/ 1.312 ISR</small>' ?>
                        </h4>
                        <div id="pk1-param2-title" class="fs-12 fw-semibold <?= $isPendingMonth ? 'text-muted' : 'text-success' ?> mb-1">
                            <?= $isPendingMonth ? 'Menunggu Realisasi' : (($p1IsrPct >= 100) ? 'Target Terlampaui' : 'Progres Target') ?>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.3;">Target: 1.312 ISR (60% dari total 2.186 ISR di Kaltim).</p>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 col-sm-6">
                    <div class="pk-param-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-warning bg-opacity-10 text-warning fs-10 fw-bold">PARAM 3</span>
                            <span id="pk1-param3-badge" class="badge <?= $isPendingMonth ? 'bg-light text-muted border' : 'bg-success text-white' ?> fs-10 px-2 py-0.5 rounded-pill">
                                <?= $isPendingMonth ? 'Menunggu' : '<i class="fa fa-check me-1"></i>100%' ?>
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1" id="pk1-param3-value">
                            <?= $isPendingMonth ? '- <small class="fs-11 text-muted">Pengguna</small>' : number_format($p1Users, 0, ',', '.') . ' <small class="fs-11 text-muted">Pengguna</small>' ?>
                        </h4>
                        <div class="fs-12 fw-semibold text-dark mb-1" id="pk1-param3-title">
                            <?= $isPendingMonth ? 'Menunggu Realisasi' : '100% Teridentifikasi' ?>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.3;">Seluruh sinyal & pengguna frekuensi terdata dalam aplikasi ROL.</p>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 col-sm-6">
                    <div class="pk-param-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary fs-10 fw-bold">PARAM 4</span>
                            <span id="pk1-param4-badge" class="badge <?= $isPendingMonth ? 'bg-light text-muted border' : 'bg-success text-white' ?> fs-10 px-2 py-0.5 rounded-pill">
                                <?= $isPendingMonth ? 'Menunggu' : '<i class="fa fa-shield me-1"></i>Tertib' ?>
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1" id="pk1-param4-value">
                            <?= $isPendingMonth ? '-' : $p1Sanksi ?> <small class="fs-11 text-muted">Pelanggaran</small>
                        </h4>
                        <div class="fs-12 fw-semibold text-secondary mb-1">Tindak Lanjut Sanksi</div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.3;">Tidak ditemukenali pelanggaran tidak berizin (Sanksi = 0).</p>
                    </div>
                </div>
            </div>

            <!-- Strip Container PK.1 -->
            <div id="pk1-strip-container"></div>

            <!-- Charts PK.1 -->
            <div class="row g-3 mb-4">
                <div class="col-xl-8 col-lg-7">
                    <div class="card pk-chart-card p-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-primary fs-18 me-1">trending_up</i>
                                    Grafik Tren Capaian Kumulatif (%) vs Target PK (80%)
                                </h6>
                                <span class="fs-11 text-muted">Progres akumulasi jumlah Kabupaten/Kota yang telah diobservasi spektrumnya</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge badge-soft-primary border fs-10 px-2 py-1">
                                    <i class="fa fa-bullseye me-1"></i>Target PK: 80% (8 Kab/Kota)
                                </span>
                                <span class="badge bg-success text-white fs-10 px-2 py-1">
                                    <i class="fa fa-check me-1"></i>Realisasi September: 90%
                                </span>
                            </div>
                        </div>
                        <div id="chart-sfr-capaian" style="min-height: 270px;"></div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-5">
                    <div class="card pk-chart-card p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-success fs-18 me-1">map</i>
                                    Status 10 Kab/Kota Wilayah Kerja
                                </h6>
                                <span class="fs-11 text-muted"><?= $wilayahTermonitorCount ?> Termonitor (<?= $wilayahPct ?>%), <?= $wilayahBelumCount ?> Belum Termonitor</span>
                            </div>
                            <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill"><?= $wilayahTermonitorCount ?> / 10 (<?= $wilayahPct ?>%)</span>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between fs-11 fw-semibold mb-1">
                                <span class="text-success"><i class="fa fa-circle-check me-1"></i>Cakupan Termonitor</span>
                                <span class="text-dark fw-bold"><?= $wilayahPct ?>% dari Total Wilayah Kerja</span>
                            </div>
                            <div class="progress" style="height: 10px; border-radius: 6px; background-color: #e2e8f0;">
                                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= $wilayahPct ?>%;"></div>
                            </div>
                        </div>

                        <div class="d-flex flex-column gap-1.5" style="max-height: 270px; overflow-y: auto; padding-right: 4px;">
                            <?php foreach ($wilayahKerjaList as $w): ?>
                                <div class="wilayah-item <?= $w['status'] ?> d-flex align-items-center justify-content-between py-1.5 px-2.5">
                                    <div class="d-flex align-items-center me-2 text-truncate">
                                        <i class="material-symbols-outlined fs-15 me-2 <?= ($w['status'] === 'termonitor') ? 'text-success' : 'text-muted' ?>">
                                            <?= ($w['status'] === 'termonitor') ? 'check_circle' : 'schedule' ?>
                                        </i>
                                        <span class="fs-12 fw-semibold text-dark text-truncate"><?= htmlspecialchars($w['name']) ?></span>
                                    </div>
                                    <span class="badge fs-10 px-2 py-0.5 rounded-pill <?= ($w['status'] === 'termonitor') ? 'bg-success text-white' : 'bg-light text-muted border' ?>">
                                        <?= ($w['status'] === 'termonitor') ? '✓ ' . $w['since'] : $w['since'] ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-xl-6 col-lg-6">
                    <div class="card pk-chart-card p-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-info fs-18 me-1">cell_tower</i>
                                    Pertumbuhan ISR Termonitor vs Target (1.312 ISR)
                                </h6>
                                <span class="fs-11 text-muted">Target 60% ISR (1.312 dari 2.186 ISR) &bull; Jan - Sep Realisasi, Okt - Des Menunggu Bulan Berjalan</span>
                            </div>
                            <div id="chart-isr-badge-container">
                                <?php if ($selectedMonth > 0 && isset($pk1MonthlyDetails[$selectedMonth])): ?>
                                    <?php if ($isPendingMonth): ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 fs-10 px-2 py-1">
                                            <i class="fa fa-clock me-1"></i><?= $monthNames[$selectedMonth] ?>: Menunggu Bulan Berjalan
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-1">
                                            <i class="fa fa-arrow-up me-1"></i><?= $monthNames[$selectedMonth] ?>: <?= number_format($pk1MonthlyDetails[$selectedMonth]['isr'], 0, ',', '.') ?> ISR (<?= round(($pk1MonthlyDetails[$selectedMonth]['isr'] / 1312) * 100, 1) ?>%)
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-1">
                                        <i class="fa fa-arrow-up me-1"></i>s.d. September: 2.015 ISR (153.6% - Target Terlampaui)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div id="chart-sfr-isr" style="min-height: 250px;"></div>
                    </div>
                </div>

                <div class="col-xl-6 col-lg-6">
                    <div class="card pk-chart-card p-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-warning fs-18 me-1">group</i>
                                    Tren Pengguna Frekuensi Radio Teridentifikasi
                                </h6>
                                <span class="fs-11 text-muted">Akumulasi stasiun/pengguna frekuensi dalam ROL &bull; Jan - Sep Realisasi, Okt - Des Menunggu Bulan Berjalan</span>
                            </div>
                            <div id="chart-users-badge-container">
                                <?php if ($selectedMonth > 0 && isset($pk1MonthlyDetails[$selectedMonth])): ?>
                                    <?php if ($isPendingMonth): ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 fs-10 px-2 py-1">
                                            <i class="fa fa-clock me-1"></i><?= $monthNames[$selectedMonth] ?>: Menunggu Bulan Berjalan
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fs-10 px-2 py-1">
                                            <i class="fa fa-users me-1"></i><?= $monthNames[$selectedMonth] ?>: <?= number_format($pk1MonthlyDetails[$selectedMonth]['users'], 0, ',', '.') ?> Pengguna
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fs-10 px-2 py-1">
                                        <i class="fa fa-users me-1"></i>s.d. September: 5.080 Pengguna Terdata
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div id="chart-sfr-users" style="min-height: 250px;"></div>
                    </div>
                </div>
            </div>

        <?php elseif ($selectedCode === 'PK.2' && $selectedYear === 2026): ?>
            <!-- ============================================================ -->
            <!-- SECTION KHUSUS PK.2: PEMERIKSAAN STASIUN (DATA RESMI 2026) -->
            <!-- ============================================================ -->
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h5 class="fw-bold text-dark fs-14 mb-0 d-flex align-items-center" id="pk2-section-title">
                    <i class="material-symbols-outlined text-info fs-18 me-1">domain_verification</i>
                    4 Komponen Sasaran Pemeriksaan Stasiun Radio <?= ($selectedMonth === 0) ? '(Akumulatif)' : '(Bulan ' . $monthNames[$selectedMonth] . ')' ?>
                </h5>
                <span class="fs-11 text-muted" id="pk2-section-subtitle">
                    <?= ($selectedMonth === 0) 
                        ? 'Realisasi Kumulatif s.d. September 2026: <strong>2.188 Stasiun (91.82%)</strong>' 
                        : 'Realisasi Bulan ' . $monthNames[$selectedMonth] . ': <strong>' . number_format($p2TotalStn, 0, ',', '.') . ' Stasiun (' . number_format($p2PkPct, 2) . '%)</strong>' ?>
                </span>
            </div>

            <div class="row g-3 mb-3">
                <!-- Komponen 1: FM Siaran -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge badge-soft-primary fs-10 fw-bold">PENGUKURAN SIARAN</span>
                            <span id="pk2-fm-badge" class="badge <?= ($p2FmPct >= 100) ? 'bg-success text-white' : 'bg-primary text-white' ?> fs-10 px-2 py-0.5 rounded-pill">
                                <?= ($p2FmPct >= 100) ? '<i class="fa fa-check me-1"></i>100% Tercapai' : number_format($p2FmPct, 1) . '%' ?>
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1" id="pk2-fm-value"><?= $p2FmCapaian ?> / 50 <small class="fs-11 text-muted">Stasiun</small></h4>
                        <div class="fs-12 fw-semibold text-primary mb-1" id="pk2-fm-title">Radio FM Siaran (<?= number_format($p2FmPct, 2) ?>%)</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div id="pk2-fm-bar" class="progress-bar bg-primary" role="progressbar" style="width: <?= min(100, $p2FmPct) ?>%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" id="pk2-fm-desc" style="line-height: 1.3;">
                            <?= ($p2FmPct >= 100) ? 'Target 50 stasiun telah terpenuhi tuntas sejak bulan Juni 2026.' : 'Target tahunan 50 stasiun radio FM di wilayah kerja Kaltim.' ?>
                        </p>
                    </div>
                </div>

                <!-- Komponen 2: TV Digital -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-info bg-opacity-10 text-info fs-10 fw-bold">PENGUKURAN SIARAN</span>
                            <span id="pk2-tv-badge" class="badge <?= ($p2TvPct >= 100) ? 'bg-success text-white' : 'bg-info text-white' ?> fs-10 px-2 py-0.5 rounded-pill">
                                <?= ($p2TvPct >= 100) ? '<i class="fa fa-check me-1"></i>100%' : number_format($p2TvPct, 1) . '% On-Track' ?>
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1" id="pk2-tv-value"><?= $p2TvCapaian ?> / 18 <small class="fs-11 text-muted">Stasiun</small></h4>
                        <div class="fs-12 fw-semibold text-info mb-1" id="pk2-tv-title">TV Digital DVB-T2 (<?= number_format($p2TvPct, 2) ?>%)</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div id="pk2-tv-bar" class="progress-bar bg-info" role="progressbar" style="width: <?= min(100, $p2TvPct) ?>%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" id="pk2-tv-desc" style="line-height: 1.3;">
                            Realisasi <?= $p2TvCapaian ?> dari target 18 stasiun TV Digital (sisa <?= max(0, 18 - $p2TvCapaian) ?> stasiun).
                        </p>
                    </div>
                </div>

                <!-- Komponen 3: Remote Site -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-warning bg-opacity-10 text-warning fs-10 fw-bold">REMOTE SITE</span>
                            <span id="pk2-remote-badge" class="badge <?= ($p2RemotePct >= 100) ? 'bg-success text-white' : 'bg-primary text-white' ?> fs-10 px-2 py-0.5 rounded-pill">
                                <?= ($p2RemotePct >= 100) ? '<i class="fa fa-check me-1"></i>100%' : number_format($p2RemotePct, 1) . '% On-Track' ?>
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1" id="pk2-remote-value"><?= number_format($p2RemoteCapaian, 0, ',', '.') ?> / 2.053 <small class="fs-11 text-muted">Site</small></h4>
                        <div class="fs-12 fw-semibold text-dark mb-1" id="pk2-remote-title">Microwave Link Remote (<?= number_format($p2RemotePct, 2) ?>%)</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div id="pk2-remote-bar" class="progress-bar bg-primary" role="progressbar" style="width: <?= min(100, $p2RemotePct) ?>%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.3;">Inspeksi parameter teknis microwave link operator via APSTARD.</p>
                    </div>
                </div>

                <!-- Komponen 4: Open Shelter -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-success bg-opacity-10 text-success fs-10 fw-bold">OPEN SHELTER</span>
                            <span id="pk2-shelter-badge" class="badge <?= ($p2ShelterPct >= 100) ? 'bg-success text-white' : 'bg-primary text-white' ?> fs-10 px-2 py-0.5 rounded-pill">
                                <?= ($p2ShelterPct >= 100) ? '<i class="fa fa-arrow-up me-1"></i>' . number_format($p2ShelterPct, 1) . '%' : number_format($p2ShelterPct, 1) . '%' ?>
                            </span>
                        </div>
                        <h4 class="fw-bold <?= ($p2ShelterPct >= 100) ? 'text-success' : 'text-dark' ?> mb-1" id="pk2-shelter-value">
                            <?= number_format($p2ShelterCapaian, 0, ',', '.') ?> / 262 <small class="fs-11 text-muted">Shelter</small>
                        </h4>
                        <div class="fs-12 fw-semibold text-success mb-1" id="pk2-shelter-title">Open Shelter (<?= number_format($p2ShelterPct, 2) ?>%)</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div id="pk2-shelter-bar" class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, $p2ShelterPct) ?>%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" id="pk2-shelter-desc" style="line-height: 1.3;">
                            <?= ($p2ShelterPct >= 100) 
                                ? 'Melampaui target 262 shelter dengan surplus ' . ($p2ShelterCapaian - 262) . ' shelter terinspeksi.' 
                                : 'Pemeriksaan fisik shelter perangkat stasiun radio di lapangan.' ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Strip Container PK.2 (Dihapus) -->
            <div id="pk2-strip-container"></div>

            <!-- Charts PK.2: Tren Capaian & Komposisi Realisasi Komponen -->
            <div class="row g-3 mb-4">
                <!-- Grafik 1: Tren Capaian (%) Bulanan s.d. September -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card pk-chart-card p-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-primary fs-18 me-1">trending_up</i>
                                    Grafik Tren Capaian PK Pemeriksaan Stasiun (Target: 100%)
                                </h6>
                                <span class="fs-11 text-muted">Perkembangan persentase kumulatif pemeriksaan terhadap total target 2.383 stasiun</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge badge-soft-primary border fs-10 px-2 py-1">
                                    <i class="fa fa-bullseye me-1"></i>Target PK: 100% (2.383 Stn)
                                </span>
                                <span class="badge bg-success text-white fs-10 px-2 py-1">
                                    <i class="fa fa-check me-1"></i>Realisasi September: 91.82%
                                </span>
                            </div>
                        </div>
                        <div id="chart-pk2-capaian" style="min-height: 270px;"></div>
                    </div>
                </div>

                <!-- Status Komposisi 4 Komponen Stasiun -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card pk-chart-card p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-info fs-18 me-1">pie_chart</i>
                                    Ringkasan Komposisi Realisasi
                                </h6>
                                <span class="fs-11 text-muted">2.188 dari total 2.383 stasiun</span>
                            </div>
                            <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill">91.82%</span>
                        </div>

                        <!-- Progress Bar Total 91.82% -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between fs-11 fw-semibold mb-1">
                                <span class="text-success"><i class="fa fa-circle-check me-1"></i>Total Ketercapaian</span>
                                <span class="text-dark fw-bold">2.188 / 2.383 Stasiun</span>
                            </div>
                            <div class="progress" style="height: 10px; border-radius: 6px; background-color: #e2e8f0;">
                                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: 91.82%;"></div>
                            </div>
                        </div>

                        <!-- Detail Tiap Komponen -->
                        <div class="d-flex flex-column gap-2" style="max-height: 270px; overflow-y: auto;">
                            <div class="p-2 rounded border bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="fs-11 fw-bold text-dark d-block">Remote Site Microwave</span>
                                    <small class="fs-10 text-muted">Target: 2.053 | Realisasi: 1.791 Site</small>
                                </div>
                                <span class="badge bg-primary text-white fs-10 px-2 py-1 rounded-pill">87.25%</span>
                            </div>

                            <div class="p-2 rounded border bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="fs-11 fw-bold text-dark d-block">Open Shelter</span>
                                    <small class="fs-10 text-muted">Target: 262 | Realisasi: 330 Shelter</small>
                                </div>
                                <span class="badge bg-success text-white fs-10 px-2 py-1 rounded-pill">125.86%</span>
                            </div>

                            <div class="p-2 rounded border bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="fs-11 fw-bold text-dark d-block">Radio FM Siaran</span>
                                    <small class="fs-10 text-muted">Target: 50 | Realisasi: 50 Stasiun</small>
                                </div>
                                <span class="badge bg-success text-white fs-10 px-2 py-1 rounded-pill">100.00%</span>
                            </div>

                            <div class="p-2 rounded border bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="fs-11 fw-bold text-dark d-block">TV Digital DVB-T2</span>
                                    <small class="fs-10 text-muted">Target: 18 | Realisasi: 17 Stasiun</small>
                                </div>
                                <span class="badge bg-info text-white fs-10 px-2 py-1 rounded-pill">94.44%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts PK.2 Row 2: Perbandingan Target vs Realisasi Komponen & Akumulasi Stasiun -->
            <div class="row g-3 mb-4">
                <div class="col-xl-6 col-lg-6">
                    <div class="card pk-chart-card p-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-info fs-18 me-1">bar_chart</i>
                                    Komparasi Target vs Realisasi per Komponen (Stasiun)
                                </h6>
                                <span class="fs-11 text-muted">Perbandingan volume target sasaran dan realisasi s.d. September</span>
                            </div>
                        </div>
                        <div id="chart-pk2-components" style="min-height: 240px;"></div>
                    </div>
                </div>

                <div class="col-xl-6 col-lg-6">
                    <div class="card pk-chart-card p-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-success fs-18 me-1">show_chart</i>
                                    Pertumbuhan Total Stasiun Terperiksa (Jan - Des)
                                </h6>
                                <span class="fs-11 text-muted">Lonjakan pemeriksaan stasiun radio hingga 2.188 stasiun</span>
                            </div>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-1">
                                <i class="fa fa-check me-1"></i>Total: 2.188 Stn
                            </span>
                        </div>
                        <div id="chart-pk2-stations" style="min-height: 240px;"></div>
                    </div>
                </div>
            </div>

        <?php elseif ($selectedCode === 'PK.3' && $selectedYear === 2026): ?>
            <!-- ============================================================ -->
            <!-- SECTION KHUSUS PK.3: PENANGANAN GANGGUAN FREKUENSI RADIO 2026 -->
            <!-- Sumber: 2 Laporan Penanganan Gangguan, BAP & SPT Balmon Samarinda -->
            <!-- Berdasarkan Sistem Trouble Ticket (laporgangguansfr.postel.go.id) -->
            <!-- ============================================================ -->
            <?php
            $filteredCases = [];
            if ($selectedMonth > 0) {
                foreach ($pk3OfficialCases as $c) {
                    if ($c['month_num'] === $selectedMonth) {
                        $filteredCases[] = $c;
                    }
                }
            } else {
                $filteredCases = $pk3OfficialCases;
            }
            ?>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 class="fw-bold text-dark fs-15 mb-0 d-flex align-items-center">
                        <i class="material-symbols-outlined text-danger fs-20 me-1.5">warning_amber</i>
                        Capaian Penanganan Gangguan Spektrum Frekuensi Radio (TA 2026)
                    </h5>
                    <span class="fs-11 text-muted">
                        Sumber Resmi: 2 Kasus Aduan Terverifikasi • SPT 46/2026 & SPT 113/2026 • BAP T-67 & T-228 • Balai Monitor SFR Kelas I Samarinda
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success text-white fs-12 px-3 py-1.5 rounded-pill shadow-sm">
                        <i class="fa fa-circle-check me-1"></i>Realisasi: 100.0% (Target: 100%)
                    </span>
                    <a href="monitoring_penanganan_gangguan.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                        <i class="material-symbols-outlined fs-16 align-middle me-1">report_problem</i> Menu Penanganan Gangguan
                    </a>
                    <a href="https://laporgangguansfr.postel.go.id" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="material-symbols-outlined fs-16 align-middle me-1">troubleshoot</i> Portal Trouble Ticket SFR
                    </a>
                </div>
            </div>

            <!-- 4 KARTU PARAMETER EKSEKUTIF PENANGANAN GANGGUAN SFR -->
            <div class="row g-3 mb-4">
                <!-- Param 1: Total Pengaduan Masuk -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-danger bg-opacity-10 text-danger fs-10 fw-bold">PENGADUAN MASUK</span>
                            <span id="pk3-param1-badge" class="badge bg-danger text-white fs-10 px-2 py-0.5 rounded-pill">
                                <?= ($selectedMonth === 0) ? '2 Aduan Resmi' : (($selectedMonth === 4 || $selectedMonth === 8) ? '1 Aduan Resmi' : 'Nihil Aduan') ?>
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1" id="pk3-param1-val">
                            <?= ($selectedMonth === 0) ? '2 <small class="fs-13 text-muted">/ 2 Aduan</small>' : (($selectedMonth === 4 || $selectedMonth === 8) ? '1 <small class="fs-13 text-muted">Aduan Masuk</small>' : '0 <small class="fs-13 text-muted">Aduan</small>') ?>
                        </h4>
                        <div class="fs-12 fw-semibold text-danger mb-1">Pengaduan Pemegang IPFR/ISR</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: <?= ($selectedMonth === 0 || $selectedMonth === 4 || $selectedMonth === 8) ? '100' : '0' ?>%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" id="pk3-param1-desc" style="line-height: 1.35;">
                            <?= ($selectedMonth === 0) ? 'PT. Kideco Jaya Agung (April) dan PT. Multi Harapan Utama (Agustus).' : (($selectedMonth === 4) ? 'Laporan Pengaduan No. 145/180/C/III/2026-01 PT. Kideco Jaya Agung.' : (($selectedMonth === 8) ? 'Laporan Pengaduan No. 0458/OL/MHU-KTT/VII/2026 PT. Multi Harapan Utama.' : 'Tidak ada laporan pengaduan gangguan frekuensi pada bulan ' . ($monthNames[$selectedMonth] ?? '') . ' 2026.')) ?>
                        </p>
                    </div>
                </div>

                <!-- Param 2: Tingkat Penyelesaian Gangguan -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-success bg-opacity-10 text-success fs-10 fw-bold">STATUS PENYELESAIAN</span>
                            <span id="pk3-param2-badge" class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">
                                <i class="fa fa-check-circle me-1"></i>Clean 100%
                            </span>
                        </div>
                        <h4 class="fw-bold text-success mb-1" id="pk3-param2-val">
                            100.0% <small class="fs-13 text-muted"><?= ($selectedMonth === 0) ? '(2/2 Kasus)' : (($selectedMonth === 4 || $selectedMonth === 8) ? '(1/1 Kasus)' : '(Kondusif)') ?></small>
                        </h4>
                        <div class="fs-12 fw-semibold text-success mb-1">Terselesaikan Tuntas</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" id="pk3-param2-desc" style="line-height: 1.35;">
                            Seluruh gangguan berhasil dilokalisir, dieliminasi sumbernya, dan diterbitkan Berita Acara Pemeriksaan (BAP) bebas gangguan.
                        </p>
                    </div>
                </div>

                <!-- Param 3: Entitas & Layanan Terlindungi -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary fs-10 fw-bold">ENTITAS TERLINDUNGI</span>
                            <span id="pk3-param3-badge" class="badge bg-primary text-white fs-10 px-2 py-0.5 rounded-pill">
                                <?= ($selectedMonth === 0) ? '2 Perusahaan' : (($selectedMonth === 4 || $selectedMonth === 8) ? '1 Perusahaan' : 'Kondusif') ?>
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1" id="pk3-param3-val">
                            <?= ($selectedMonth === 0) ? '2 <small class="fs-13 text-muted">Badan Usaha</small>' : (($selectedMonth === 4) ? 'PT. Kideco' : (($selectedMonth === 8) ? 'PT. MHU' : '- <small class="fs-13 text-muted">Nihil</small>')) ?>
                        </h4>
                        <div class="fs-12 fw-semibold text-primary mb-1">Radio Komunikasi Tambang</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= ($selectedMonth === 0 || $selectedMonth === 4 || $selectedMonth === 8) ? '100' : '0' ?>%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" id="pk3-param3-desc" style="line-height: 1.35;">
                            Perlindungan kontinuitas frekuensi radio keselamatan kerja operasional pertambangan vital di Kab. Paser & Kab. Kutai Kartanegara.
                        </p>
                    </div>
                </div>

                <!-- Param 4: Kepatuhan Respon & SLA -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-info bg-opacity-10 text-info fs-10 fw-bold">KEPATUHAN SLA</span>
                            <span id="pk3-param4-badge" class="badge bg-info text-white fs-10 px-2 py-0.5 rounded-pill">
                                <i class="fa fa-bolt me-1"></i>SOP Terpenuhi
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1" id="pk3-param4-val">
                            &lt; 24 Jam <small class="fs-13 text-muted">Respon</small>
                        </h4>
                        <div class="fs-12 fw-semibold text-info mb-1">Standar Layanan Ditjen SDPPI</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" id="pk3-param4-desc" style="line-height: 1.35;">
                            Penerbitan Surat Perintah Tugas (SPT) dan koordinasi penanganan lapangan dilaksanakan cepat dengan durasi penanganan 4–5 hari kerja.
                        </p>
                    </div>
                </div>
            </div>

            <!-- 2 DATA KASUS PENANGANAN GANGGUAN FREKUENSI RADIO RIIL 2026 -->
            <div class="card border rounded-3 mb-4 overflow-hidden" style="border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="background: #fef2f2;">
                    <div>
                        <h6 class="fw-bold text-danger mb-1 fs-13 d-flex align-items-center">
                            <i class="material-symbols-outlined text-danger fs-18 me-1.5">fact_check</i>
                            Daftar 2 Kasus Riil Penanganan Gangguan Frekuensi Radio Resmi Balmon Samarinda TA 2026
                        </h6>
                        <span class="fs-11 text-muted">
                            Data resmi penanganan aduan gangguan spektrum frekuensi radio berdasarkan surat pengaduan pemohon, SPT, dan Berita Acara Pemeriksaan (BAP).
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger text-white fs-11 px-2.5 py-1 rounded-pill">
                            <i class="fa fa-radio me-1"></i>2 Kasus Pengaduan
                        </span>
                        <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill">
                            <i class="fa fa-check-double me-1"></i>100% Selesai Tuntas
                        </span>
                    </div>
                </div>

                <div class="p-3">
                    <?php if (empty($filteredCases)): ?>
                        <div class="text-center py-4 my-2">
                            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                                <i class="material-symbols-outlined fs-24 text-muted">verified</i>
                            </div>
                            <h6 class="fw-bold text-dark fs-13 mb-1">Tidak Ada Kegiatan Penanganan Gangguan pada Bulan <?= htmlspecialchars($monthNames[$selectedMonth] ?? '') ?></h6>
                            <p class="fs-12 text-muted mb-2">Pada bulan ini tidak terdapat laporan pengaduan gangguan frekuensi radio dari pemegang izin (Spektrum Kondusif).</p>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="selectMonthRealtime(0)">
                                Tampilkan Semua Kasus (2 Kasus TA 2026)
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($filteredCases as $case): ?>
                                <div class="col-xl-6 col-12" id="case-card-<?= $case['id'] ?>">
                                    <div class="card h-100 border rounded-3 p-3 position-relative" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.1) !important; box-shadow: 0 2px 4px rgba(10,22,40,0.04);">
                                        <div class="d-flex align-items-start justify-content-between mb-2">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                                    <span class="badge bg-danger text-white fs-10 px-2 py-0.5 rounded-pill font-monospace">
                                                        KASUS #<?= str_pad($case['id'], 2, '0', STR_PAD_LEFT) ?>
                                                    </span>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                                        Bulan <?= htmlspecialchars($case['month_name']) ?> 2026
                                                    </span>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                                        <i class="fa fa-check me-0.5"></i><?= htmlspecialchars($case['resolution_badge']) ?>
                                                    </span>
                                                </div>
                                                <h5 class="fw-bold text-dark fs-14 mb-0">
                                                    <?= htmlspecialchars($case['pelapor']) ?>
                                                </h5>
                                                <span class="fs-11 text-muted">
                                                    <i class="fa fa-location-dot me-1 text-danger"></i><?= htmlspecialchars($case['location']) ?> (<?= htmlspecialchars($case['sub_location']) ?>)
                                                </span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1 fs-11" onclick="showKasusDetail(<?= $case['id'] ?>)">
                                                <i class="fa fa-eye me-1"></i>Detail BAP
                                            </button>
                                        </div>

                                        <div class="p-2.5 rounded-3 bg-light border mb-2.5 fs-11">
                                            <div class="row g-2">
                                                <div class="col-sm-6">
                                                    <span class="text-muted d-block fs-10 text-uppercase fw-semibold">Surat Pengaduan:</span>
                                                    <strong class="text-dark font-monospace"><?= htmlspecialchars($case['complaint_number']) ?></strong>
                                                    <div class="text-muted fs-10">Tgl: <?= htmlspecialchars($case['complaint_date']) ?></div>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="text-muted d-block fs-10 text-uppercase fw-semibold">Surat Perintah Tugas (SPT):</span>
                                                    <strong class="text-primary font-monospace"><?= htmlspecialchars($case['spt_number']) ?></strong>
                                                    <div class="text-muted fs-10">Pelaksanaan: <?= htmlspecialchars($case['execution_dates']) ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="fs-11 mb-2">
                                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 fs-10 font-monospace">
                                                    <i class="fa fa-wave-square me-1"></i>Frekuensi: <?= htmlspecialchars($case['frequency']) ?>
                                                </span>
                                                <span class="badge bg-light text-dark border fs-10">
                                                    <?= htmlspecialchars($case['affected_service']) ?>
                                                </span>
                                            </div>
                                            <div class="text-muted fs-10 mb-1">
                                                No. ISR: <span class="font-monospace text-dark"><?= htmlspecialchars($case['isr_numbers']) ?></span>
                                            </div>
                                        </div>

                                        <div class="p-2 rounded border mb-2" style="background: #fef2f2; border-color: #fecaca !important;">
                                            <span class="text-danger fw-bold fs-10 d-block text-uppercase"><i class="fa fa-triangle-exclamation me-1"></i>Sumber Interferensi / Gangguan Teridentifikasi:</span>
                                            <span class="fs-11 text-dark" style="line-height: 1.35;"><?= htmlspecialchars($case['source_identified']) ?></span>
                                        </div>

                                        <div class="p-2 rounded border mb-2" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
                                            <span class="text-success fw-bold fs-10 d-block text-uppercase"><i class="fa fa-screwdriver-wrench me-1"></i>Tindakan Lokalisir & Penanganan Teknis:</span>
                                            <span class="fs-11 text-dark" style="line-height: 1.35;"><?= htmlspecialchars($case['action_taken']) ?></span>
                                        </div>

                                        <div class="mt-auto pt-2 border-top d-flex flex-wrap align-items-center justify-content-between gap-2 fs-11 text-muted">
                                            <div>
                                                <span class="d-block">Ketua Tim: <strong class="text-dark"><?= explode(' (', $case['team_leader'])[0] ?></strong></span>
                                                <small class="text-muted fs-10">BAP: <?= htmlspecialchars($case['bap_number']) ?></small>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-success text-white rounded-pill px-3 py-1 fs-11 shadow-sm fw-semibold" onclick="showKasusDetail(<?= $case['id'] ?>)">
                                                <i class="fa fa-file-circle-check me-1"></i>Lihat Lembar Verifikasi
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- GRAFIK KOMPARASI & STATUS TROUBLE TICKET PK.3 -->
            <div class="row g-3 mb-4">
                <div class="col-xl-8 col-lg-7">
                    <div class="card pk-chart-card p-3 h-100">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-danger fs-18 me-1">analytics</i>
                                    Distribusi Trouble Ticket & Penanganan Gangguan SFR per Bulan (TA 2026)
                                </h6>
                                <span class="fs-11 text-muted">Perbandingan laporan pengaduan masuk dan aduan yang berhasil diselesaikan tuntas (Clean Status)</span>
                            </div>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-1">
                                <i class="fa fa-circle-check me-1"></i>100% Selesai Tuntas
                            </span>
                        </div>
                        <div id="chart-pk3-gangguan" style="min-height: 250px;"></div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-5">
                    <div class="card pk-chart-card p-3 h-100">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-info fs-18 me-1">verified_user</i>
                                    Kepatuhan SOP & Standar Layanan
                                </h6>
                                <span class="fs-11 text-muted">Indeks Kinerja Penanganan Aduan UPT</span>
                            </div>
                            <span class="badge bg-primary text-white fs-10 px-2 py-0.5 rounded-pill">SOP Ditjen SDPPI</span>
                        </div>

                        <div class="d-flex flex-column gap-2.5">
                            <div class="p-2.5 rounded border bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fs-11 fw-bold text-dark">Rasio Penyelesaian (Resolution Rate)</span>
                                    <span class="badge bg-success text-white fs-10">100.0%</span>
                                </div>
                                <div class="progress mb-1" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: 100%;"></div>
                                </div>
                                <small class="text-muted fs-10">2 dari 2 aduan selesai 100% tanpa tunggakan tiket.</small>
                            </div>

                            <div class="p-2.5 rounded border bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fs-11 fw-bold text-dark">Waktu Tanggap Penerbitan SPT</span>
                                    <span class="badge bg-primary text-white fs-10">&lt; 24 Jam</span>
                                </div>
                                <div class="progress mb-1" style="height: 6px;">
                                    <div class="progress-bar bg-primary" style="width: 100%;"></div>
                                </div>
                                <small class="text-muted fs-10">SOP dinas keselamatan: max 1x24 jam, dinas lainnya: max 7x24 jam.</small>
                            </div>

                            <div class="p-2.5 rounded border bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fs-11 fw-bold text-dark">Kelengkapan Berita Acara (BAP)</span>
                                    <span class="badge bg-success text-white fs-10">100% Lengkap</span>
                                </div>
                                <div class="progress mb-1" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: 100%;"></div>
                                </div>
                                <small class="text-muted fs-10">BAP T-67, T-68, T-228, T-229 & Teguran T-444 tervalidasi.</small>
                            </div>

                            <div class="p-2.5 rounded border bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fs-11 fw-bold text-dark">Status Sistem Trouble Ticket</span>
                                    <span class="badge bg-dark text-white fs-10">All Closed</span>
                                </div>
                                <small class="text-muted fs-10">Tiket laporgangguansfr.postel.go.id tertutup sempurna.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($selectedCode === 'PK.4' && $selectedYear === 2026): ?>
            <!-- ============================================================ -->
            <!-- SECTION KHUSUS PK.4: PENGAWASAN ALAT & PERANGKAT TELEKOMUNIKASI (APT) 2026 -->
            <!-- Sumber: Laporan Capaian PK APT Periode September 2026 (Cutoff 01 Oktober 2026) -->
            <!-- ============================================================ -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 class="fw-bold text-dark fs-15 mb-0 d-flex align-items-center">
                        <i class="material-symbols-outlined text-warning fs-20 me-1.5">devices_other</i>
                        Capaian PK Pengawasan Alat dan/atau Perangkat Telekomunikasi (TA 2026)
                    </h5>
                    <span class="fs-11 text-muted">
                        Sumber Resmi: Laporan Capaian PK APT Cutoff 01 Oktober 2026 • Ditandatangani Kepala Balai Monitor <strong>Edi Mulyono</strong> (05 Oktober 2026)
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalInputMonApt">
                        <i class="material-symbols-outlined fs-16 align-middle me-1">add_circle</i> + Input Data Monitoring APT
                    </button>
                    <a href="monitoring_alat.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                        <i class="material-symbols-outlined fs-16 align-middle me-1">list_alt</i> Menu Monitoring Alat
                    </a>
                    <a href="https://smart.postel.go.id" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="material-symbols-outlined fs-16 align-middle me-1">open_in_new</i> Portal SMART
                    </a>
                </div>
            </div>

            <!-- 4 Parameter Kunci PK.4 Sesuai Laporan Resmi -->
            <div class="row g-3 mb-4">
                <!-- Komponen 1: 16 Jenis Perangkat & 3 Prioritas Utama -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-warning bg-opacity-10 text-dark fs-10 fw-bold">JENIS PERANGKAT</span>
                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">
                                <i class="fa fa-check me-1"></i>16 Kategori
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">16 <small class="fs-11 text-muted">Kategori</small></h4>
                        <div class="fs-12 fw-semibold text-warning mb-1">3 Prioritas Utama Terpenuhi</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Prioritas: <strong>Mobile Transceiver/Rig, Handy Talkie, Wireless Access Point</strong>, didukung Microwavelink & Low Power.
                        </p>
                    </div>
                </div>

                <!-- Komponen 2: Cakupan Wilayah Kerja (8 Kab/Kota) -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary fs-10 fw-bold">CAKUPAN WILAYAH</span>
                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">
                                <i class="fa fa-circle-check me-1"></i>100% Target
                            </span>
                        </div>
                        <h4 class="fw-bold text-success mb-1">8 / 8 <small class="fs-11 text-muted">Kab/Kota</small></h4>
                        <div class="fs-12 fw-semibold text-success mb-1">Target Wilayah di-Monitor (100%)</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Target 8 kali / 8 Kab/Kota di-monitor telah tercapai penuh s.d. September 2026.
                        </p>
                    </div>
                </div>

                <!-- Komponen 3: Sampling Unit Perangkat (1.052 / 300) -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-info bg-opacity-10 text-info fs-10 fw-bold">VOLUME SAMPLING</span>
                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">
                                100% Bobot PK
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">1.052 / 300 <small class="fs-11 text-muted">Perangkat</small></h4>
                        <div class="fs-12 fw-semibold text-primary mb-1">Termonitor (350.7% Realisasi Fisik)</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Target 300 perangkat terlampaui dengan 1.052 unit termonitor di gerai ritel, BTS, & transmisi.
                        </p>
                    </div>
                </div>

                <!-- Komponen 4: Kepatuhan & Sertifikasi (100% Patuh) -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-success bg-opacity-10 text-success fs-10 fw-bold">SERTIFIKASI & KEPATUHAN</span>
                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">
                                <i class="fa fa-shield me-1"></i>0 Pelanggaran
                            </span>
                        </div>
                        <h4 class="fw-bold text-success mb-1">1.052 <small class="fs-11 text-muted">Unit Bersertifikat</small></h4>
                        <div class="fs-12 fw-semibold text-success mb-1">Tingkat Kepatuhan (100.0%)</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Seluruh 1.052 perangkat memiliki sertifikat resmi SDPPI, dilaporkan di Aplikasi SMART.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Box Rumus & Analisa Hasil Pengukuran Resmi (Sesuai Hal. 5 & 6 Dokumen) -->
            <div class="card border rounded-3 p-3 mb-4" style="background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); border: 1px solid #bbf7d0 !important; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.08);">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill">
                            <i class="fa fa-calculator me-1"></i> Rumus Perhitungan Bobot Capaian PK (Hal. 5 & 6)
                        </span>
                        <span class="fs-12 text-muted fw-semibold">
                            Nota Dinas Direktur Pengendalian Infrastruktur Digital No: 224/DJID.6/PR.04.01/02/2026
                        </span>
                    </div>
                    <span class="badge bg-success text-white fs-12 px-3 py-1 rounded-pill fw-bold">
                        <i class="fa fa-circle-check me-1"></i> Capaian Kinerja PK.4 = 100.00%
                    </span>
                </div>

                <div class="p-3 bg-white rounded-3 border text-center my-2 shadow-sm">
                    <div class="fs-14 fw-bold text-dark font-monospace mb-1">
                        ( <span class="text-primary">1.052 Perangkat bersertifikat</span> + <span class="text-secondary">0 Pelanggaran Perangkat</span> ) ÷ <span class="text-danger">300 Target Perangkat termonitor</span> × 100% = <span class="text-success fs-16 fw-bolder">100%</span>
                    </div>
                    <small class="text-muted fs-11 fst-italic d-block">
                        "Hasil capaian untuk perhitungan Alat dan/atau Perangkat Telekomunikasi di 8 (Delapan) Kabupaten atau Kota dengan jumlah termonitor sebanyak 1052 Perangkat, Maka Persentase (%) capaian kegiatan monitoring Alat Telekomunikasi dan/atau Perangkat Telekomunikasi berdasarkan rumus perhitungan PK monitoring bahwa bobot capaian kegiatan pemeriksaan sebesar 100%."
                    </small>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between fs-11 text-muted pt-2 border-top">
                    <div>
                        <i class="fa fa-file-signature text-primary me-1"></i>
                        <strong>Penandatangan:</strong> Edi Mulyono (Kepala Balai Monitor SFR & ID Kelas I Samarinda)
                    </div>
                    <div>
                        <i class="fa fa-calendar-check text-success me-1"></i>
                        <strong>Tanggal Dokumen:</strong> Samarinda, 05 Oktober 2026 (Cutoff Data per Tanggal 01 Oktober 2026)
                    </div>
                </div>
            </div>

            <!-- Tabel 1. Jumlah Alat dan/atau Perangkat Telekomunikasi Termonitor (Hal. 5 Dokumen Resmi) -->
            <div class="card border rounded-3 p-3 mb-4" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                <div class="d-flex flex-wrap align-items-center justify-content-between pb-2 mb-3 border-bottom">
                    <div>
                        <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                            <i class="material-symbols-outlined text-primary fs-18 me-1.5">table_chart</i>
                            Tabel 1. Jumlah Alat dan/atau Perangkat Telekomunikasi Termonitor (Sesuai Laporan Resmi)
                        </h6>
                        <span class="fs-11 text-muted">Rekapitulasi resmi pengawasan di 8 Kabupaten/Kota wilayah kerja Balai Monitor Samarinda s.d. September 2026</span>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-11 px-2.5 py-1">
                        Total 1.052 Perangkat • 100% Bersertifikasi
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle fs-12 mb-0">
                        <thead class="bg-light text-dark">
                            <tr>
                                <th class="text-center" style="width: 45px;">No</th>
                                <th>Wilayah Kabupaten / Kota</th>
                                <th class="text-center">Jumlah Perangkat Termonitor</th>
                                <th class="text-center text-success">Perangkat Bersertifikasi</th>
                                <th class="text-center text-danger">Perangkat Tidak Bersertifikasi</th>
                                <th class="text-center">Tingkat Kepatuhan</th>
                                <th>Dasar SPT Pelaksanaan</th>
                                <th class="text-center">Status SMART</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pk4Table1Official as $noT1 => $t1): ?>
                                <tr>
                                    <td class="text-center fw-bold text-muted"><?= $noT1 ?></td>
                                    <td class="fw-bold text-dark">
                                        <i class="material-symbols-outlined fs-15 text-primary align-middle me-1">location_on</i>
                                        <?= htmlspecialchars($t1['wilayah']) ?>
                                    </td>
                                    <td class="text-center fw-bold text-dark fs-13">
                                        <?= number_format($t1['termonitor'], 0, ',', '.') ?> Unit
                                    </td>
                                    <td class="text-center fw-bold text-success fs-13">
                                        <?= number_format($t1['bersertifikasi'], 0, ',', '.') ?> Unit
                                    </td>
                                    <td class="text-center fw-bold text-muted">
                                        <?= $t1['tidak_bersertifikasi'] ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-11 px-2 py-0.5 rounded-pill">
                                            100% Patuh
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border fs-10 font-monospace">
                                            SPT <?= htmlspecialchars($t1['spt']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">
                                            <i class="fa fa-check me-1"></i>Terinput SMART
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-light fw-bold text-dark" style="border-top: 2px solid #cbd5e1;">
                            <tr>
                                <td colspan="2" class="text-center text-uppercase fs-12">Total Termonitor</td>
                                <td class="text-center fs-14 text-primary fw-bolder">1.052 Unit</td>
                                <td class="text-center fs-14 text-success fw-bolder">1.052 Unit</td>
                                <td class="text-center fs-14 text-muted fw-bolder">0</td>
                                <td class="text-center text-success fs-12">100.0%</td>
                                <td><span class="badge bg-primary text-white fs-10">8 Paket SPT</span></td>
                                <td class="text-center"><span class="badge bg-success text-white fs-10">100% SMART</span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Rincian Pelaksanaan 8 Paket Penugasan SPT (Sesuai Lampiran Detail 1.052 Perangkat) -->
            <div class="card border rounded-3 p-3 mb-4" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                <div class="d-flex flex-wrap align-items-center justify-content-between pb-2 mb-3 border-bottom">
                    <div>
                        <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                            <i class="material-symbols-outlined text-primary fs-18 me-1.5">assignment</i>
                            Rincian Pelaksanaan 8 Paket Penugasan SPT Pengawasan APT (Periode 01 Januari s.d. 01 Oktober 2026)
                        </h6>
                        <span class="fs-11 text-muted">Daftar Surat Perintah Tugas (SPT) pelaksanaan pemantauan gerai pedagang, stasiun penyiaran, dan hop link seluler</span>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-11 px-2.5 py-1">
                        8 Pelaksanaan SPT • 1.052 Unit
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle fs-12 mb-0">
                        <thead class="bg-light text-dark">
                            <tr>
                                <th class="text-center" style="width: 40px;">No</th>
                                <th style="min-width: 170px;">Nomor SPT & Periode</th>
                                <th style="min-width: 140px;">Wilayah & Bulan</th>
                                <th style="min-width: 180px;">Target Pelaku Usaha / Stasiun</th>
                                <th class="text-center" style="width: 85px;">Kategori</th>
                                <th class="text-center" style="width: 90px;">Jumlah</th>
                                <th style="min-width: 250px;">Sampel Perangkat Diperiksa</th>
                                <th class="text-center" style="width: 100px;">Kepatuhan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pk4SptDetails as $noSpt => $sptItem): ?>
                                <tr>
                                    <td class="text-center fw-bold text-muted"><?= $noSpt + 1 ?></td>
                                    <td>
                                        <div class="fw-bold text-dark font-monospace fs-11"><?= htmlspecialchars($sptItem['spt']) ?></div>
                                        <small class="text-muted fs-10"><i class="fa fa-calendar-alt me-1"></i><?= htmlspecialchars($sptItem['dates']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-primary fs-12"><?= htmlspecialchars($sptItem['kab_kota']) ?></div>
                                        <small class="text-muted fs-10"><?= htmlspecialchars($sptItem['month']) ?></small>
                                    </td>
                                    <td>
                                        <span class="text-dark fs-11" title="<?= htmlspecialchars($sptItem['targets']) ?>">
                                            <?= htmlspecialchars(mb_strimwidth($sptItem['targets'], 0, 48, '...')) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= str_contains($sptItem['category'], 'Seller') ? 'bg-warning text-dark' : 'bg-info text-white' ?> fs-10 px-2 py-0.5 rounded-pill">
                                            <?= str_contains($sptItem['category'], 'Seller') ? 'Pedagang' : 'Pengguna' ?>
                                        </span>
                                    </td>
                                    <td class="text-center fw-bold text-dark fs-12">
                                        <?= number_format($sptItem['devices_count'], 0, ',', '.') ?> Unit
                                    </td>
                                    <td>
                                        <span class="text-muted fs-10 d-block" title="<?= htmlspecialchars($sptItem['devices_sample']) ?>">
                                            <?= htmlspecialchars(mb_strimwidth($sptItem['devices_sample'], 0, 65, '...')) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                            <i class="fa fa-check me-0.5"></i>100% Patuh
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-light fw-bold text-dark" style="border-top: 2px solid #cbd5e1;">
                            <tr>
                                <td colspan="5" class="text-center text-uppercase fs-12">Total Seluruh Paket Penugasan SPT</td>
                                <td class="text-center fs-13 text-primary fw-bolder">1.052 Unit</td>
                                <td class="fs-11 text-muted">Seluruh perangkat berlabel dan tersertifikasi SDPPI / Komdigi</td>
                                <td class="text-center text-success fs-11">0 Pelanggaran</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Kartu Khusus Perangkat yang Sudah Dimonitor & 3 Prioritas Utama -->
            <div class="card border rounded-3 p-3 mb-4" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                <div class="d-flex flex-wrap align-items-center justify-content-between pb-2 mb-3 border-bottom gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-0.5 flex-wrap">
                            <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                <i class="material-symbols-outlined text-warning fs-18 me-1.5">devices_other</i>
                                Jenis Perangkat Resmi yang Sudah Dimonitor & 3 Prioritas Utama
                            </h6>
                            <span class="badge bg-warning text-dark border border-warning fs-10 px-2 py-0.5 rounded-pill fw-bold">
                                <i class="fa fa-star text-danger me-1"></i>3 Prioritas Utama
                            </span>
                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">
                                7 Jenis Termonitor (1.006 Unit)
                            </span>
                        </div>
                        <span class="fs-11 text-muted">
                            Sesuai ketentuan Hal. 4 Laporan PK: <strong>Mobile Transceiver/Radio Rig, Handy Talkie (HT), Wireless Access Point</strong> sebagai prioritas utama (7 dari 16 kategori regulasi termonitor di lapangan).
                        </span>
                    </div>

                    <!-- Tab Switcher Nav -->
                    <ul class="nav nav-pills gap-1.5" id="pills-pk4-devices-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill px-3 py-1 fs-11 fw-bold" id="tab-pk4-cards-btn" data-bs-toggle="pill" data-bs-target="#pane-pk4-cards" type="button" role="tab" aria-selected="true">
                                <i class="fa fa-th-large me-1"></i>Kartu Perangkat (7 Jenis)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill px-3 py-1 fs-11 fw-bold" id="tab-pk4-chart-btn" data-bs-toggle="pill" data-bs-target="#pane-pk4-chart" type="button" role="tab" aria-selected="false">
                                <i class="fa fa-chart-column me-1"></i>Grafik Distribusi & Prioritas
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill px-3 py-1 fs-11 fw-bold" id="tab-pk4-all16-btn" data-bs-toggle="pill" data-bs-target="#pane-pk4-all16" type="button" role="tab" aria-selected="false">
                                <i class="fa fa-list-check me-1"></i>Daftar 16 Regulasi SDPPI
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="pk4DevicesTabContent">
                    <!-- ============================================== -->
                    <!-- TAB 1: KARTU KHUSUS PERANGKAT YANG SUDAH DIMONITOR -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade show active" id="pane-pk4-cards" role="tabpanel" aria-labelledby="tab-pk4-cards-btn">
                        
                        <!-- SEKSI A: 3 PRIORITAS UTAMA (HAL. 4 LAPORAN PK) -->
                        <div class="p-2.5 rounded-3 mb-3" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1.5px solid #fcd34d;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 pb-1 border-bottom border-warning border-opacity-30 gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center shadow-sm" style="width: 26px; height: 26px;">
                                        <i class="fa fa-star fs-12 text-danger"></i>
                                    </div>
                                    <div>
                                        <span class="fs-12 fw-bold text-dark text-uppercase">
                                            3 Prioritas Utama Pengawasan APT (Ketentuan Hal. 4 Dokumen Laporan PK)
                                        </span>
                                        <div class="fs-10 text-muted">Fokus utama pengawasan peredaran & standarisasi perangkat telekomunikasi di 8 Kab/Kota Kaltim</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="badge bg-dark text-warning fs-10 px-2 py-0.5 rounded-pill font-monospace">
                                        Target: 95 Unit
                                    </span>
                                    <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">
                                        <i class="fa fa-check me-0.5"></i>Realisasi: 125 Unit (131.6%)
                                    </span>
                                </div>
                            </div>

                            <div class="row g-2.5">
                                <?php 
                                $priorityItems = array_filter($pk4PriorityDevices, function($d) { return !empty($d['is_priority']); });
                                $pIdx = 1;
                                foreach ($priorityItems as $pKey => $pDev): 
                                    $pctPri = ($pDev['target'] > 0) ? round(($pDev['realisasi'] / $pDev['target']) * 100, 1) : 100;
                                ?>
                                    <div class="col-lg-4 col-md-12">
                                        <div class="card h-100 border-0 rounded-3 p-3 shadow-sm" style="background: #ffffff; border: 1px solid #fde68a !important;">
                                            <div class="d-flex align-items-start justify-content-between mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 34px; height: 34px; background: #fef3c7; color: #b45309;">
                                                        <i class="material-symbols-outlined fs-20"><?= $pDev['icon'] ?></i>
                                                    </div>
                                                    <div>
                                                        <span class="badge bg-warning text-dark fs-10 px-2 py-0.5 rounded-pill fw-bold">
                                                            <i class="fa fa-star text-danger me-0.5"></i>PRIORITAS #<?= $pIdx++ ?>
                                                        </span>
                                                        <span class="badge bg-light text-muted border fs-9 px-1.5 py-0.5 font-monospace">Kategori #<?= $pKey ?></span>
                                                    </div>
                                                </div>
                                                <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">
                                                    <i class="fa fa-check me-0.5"></i>100% Patuh
                                                </span>
                                            </div>

                                            <h6 class="fw-bold text-dark fs-13 mb-1" title="<?= htmlspecialchars($pDev['name']) ?>">
                                                <?= htmlspecialchars($pDev['name']) ?>
                                            </h6>

                                            <p class="fs-11 text-muted mb-2" style="line-height: 1.35; min-height: 32px;">
                                                <?php 
                                                if ($pKey == 1) echo 'Radio komunikasi kendaraan darat (VHF/UHF) di area konsesi tambang & perkebunan vital Kaltim.';
                                                elseif ($pKey == 2) echo 'Radio transceiver genggam portabel yang diawasi legalitas sertifikat & label resmi Ditjen SDPPI.';
                                                elseif ($pKey == 3) echo 'Perangkat jaringan nirkabel (WLAN/Wifi) ritel & instansi, diawasi limitasi batas daya pancar EIRP.';
                                                ?>
                                            </p>

                                            <div class="p-2 rounded bg-light border mb-2">
                                                <div class="d-flex justify-content-between align-items-center fs-11 mb-1">
                                                    <span class="text-muted">Target: <strong><?= $pDev['target'] ?> Unit</strong></span>
                                                    <span class="text-dark fw-bold">Realisasi: <strong class="text-success fs-13"><?= $pDev['realisasi'] ?> Unit</strong></span>
                                                </div>
                                                <div class="progress mb-1" style="height: 6px;">
                                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= min(100, $pctPri) ?>%;"></div>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center fs-10 text-muted">
                                                    <span>Capaian Volume: <strong class="text-dark"><?= $pctPri ?>%</strong></span>
                                                    <span class="text-success fw-semibold"><i class="fa fa-check-circle me-0.5"></i>Melampaui Target</span>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center fs-10 text-muted pt-1 border-top">
                                                <span class="text-success"><i class="fa fa-certificate me-0.5"></i><?= $pDev['compliant'] ?> Unit Bersertifikat</span>
                                                <span class="text-secondary"><i class="fa fa-shield me-0.5"></i>0 Pelanggaran</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- SEKSI B: 4 PERANGKAT PENDUKUNG LAINNYA YANG TELAH DIMONITOR -->
                        <div class="mb-2">
                            <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom gap-2">
                                <span class="fs-12 fw-bold text-dark d-flex align-items-center">
                                    <i class="fa fa-layer-group text-primary me-1.5 fs-13"></i>
                                    4 Jenis Perangkat Pendukung Lainnya yang Telah Dimonitor di Lapangan
                                </span>
                                <span class="badge bg-primary bg-opacity-10 text-primary fs-10 px-2 py-0.5 rounded-pill font-monospace">
                                    Total: 881 Unit Termonitor
                                </span>
                            </div>

                            <div class="row g-2">
                                <?php 
                                $otherMonitored = array_filter($pk4PriorityDevices, function($d) { return empty($d['is_priority']) && $d['realisasi'] > 0; });
                                foreach ($otherMonitored as $oKey => $oDev): 
                                    $pctOther = ($oDev['target'] > 0) ? round(($oDev['realisasi'] / $oDev['target']) * 100, 1) : 100;
                                ?>
                                    <div class="col-xl-3 col-md-6 col-12">
                                        <div class="p-2.5 rounded-3 border h-100 position-relative transition-all" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 2px rgba(10,22,40,0.02);">
                                            <div class="d-flex align-items-start justify-content-between mb-1.5">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: #f0f9ff; color: #0284c7;">
                                                        <i class="material-symbols-outlined fs-16"><?= $oDev['icon'] ?></i>
                                                    </div>
                                                    <div>
                                                        <span class="badge bg-light text-muted border fs-9 px-1.5 py-0.5 font-monospace">#<?= $oKey ?></span>
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary fs-9 px-1.5 py-0.5"><?= $oDev['category'] ?></span>
                                                    </div>
                                                </div>
                                                <span class="badge bg-success text-white fs-9 px-1.5 py-0.5 rounded-pill">
                                                    100% Patuh
                                                </span>
                                            </div>

                                            <h6 class="fw-bold text-dark fs-12 mb-1 text-truncate" title="<?= htmlspecialchars($oDev['name']) ?>">
                                                <?= htmlspecialchars($oDev['name']) ?>
                                            </h6>

                                            <div class="d-flex justify-content-between align-items-center fs-11 mb-1">
                                                <span class="text-muted">Target: <strong><?= $oDev['target'] ?> Unit</strong></span>
                                                <span class="text-dark fw-bold">Realisasi: <strong class="text-primary"><?= $oDev['realisasi'] ?> Unit</strong></span>
                                            </div>

                                            <div class="progress mb-1.5" style="height: 4px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: 100%;"></div>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center fs-10 text-muted">
                                                <span class="text-success"><i class="fa fa-check me-0.5"></i><?= $oDev['compliant'] ?> Bersertifikat</span>
                                                <span class="text-muted"><i class="fa fa-shield me-0.5"></i>0 Pelanggaran</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- ALERT 9 PERANGKAT REGULASI NIHIL -->
                        <div class="p-2.5 rounded-3 bg-light border d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2 fs-11 text-muted">
                            <div>
                                <i class="fa fa-circle-info text-primary me-1"></i>
                                <strong>9 Kategori Regulasi Lainnya Belum Ditemukan Beredar (0 Pelanggaran):</strong> 
                                HF All Band, Radio POC, GPS Tracker, Repeater GSM, Simbox, OLT, Fake BTS, Jammer, dan Alat Rakitan tidak ditemukan di pasar ritel Kaltim TA 2026.
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-2.5 py-0.5 fs-10 fw-semibold" onclick="document.getElementById('tab-pk4-all16-btn').click();">
                                <i class="fa fa-list me-1"></i>Lihat Rincian 16 Regulasi
                            </button>
                        </div>

                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 2: GRAFIK DISTRIBUSI & 3 PRIORITAS UTAMA   -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade" id="pane-pk4-chart" role="tabpanel" aria-labelledby="tab-pk4-chart-btn">
                        <div class="row g-3">
                            <div class="col-xl-8 col-lg-7">
                                <div class="p-3 rounded-3 border bg-light h-100">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0 fs-12 d-flex align-items-center">
                                                <i class="material-symbols-outlined text-warning fs-16 me-1">bar_chart</i>
                                                Komparasi Realisasi vs Target 7 Jenis Perangkat yang Sudah Dimonitor
                                            </h6>
                                            <span class="fs-10 text-muted">Warna emas menandai 3 Prioritas Utama (Rig, Handy Talkie, Wireless Access Point)</span>
                                        </div>
                                        <span class="badge bg-warning text-dark fs-10 px-2 py-0.5 rounded-pill fw-bold">
                                            3 Prioritas Terpenuhi
                                        </span>
                                    </div>
                                    <div id="chart-pk4-monitored-devices" style="min-height: 290px;"></div>
                                </div>
                            </div>

                            <div class="col-xl-4 col-lg-5">
                                <div class="p-3 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                            <h6 class="fw-bold text-dark mb-0 fs-12 d-flex align-items-center">
                                                <i class="material-symbols-outlined text-info fs-16 me-1">pie_chart</i>
                                                Proporsi Volume Sampling
                                            </h6>
                                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">1.006 Unit</span>
                                        </div>
                                        <div id="chart-pk4-donut-devices" style="min-height: 220px;"></div>
                                    </div>

                                    <div class="p-2 rounded bg-white border mt-2 fs-10">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold text-dark"><i class="fa fa-star text-warning me-1"></i>3 Prioritas Utama:</span>
                                            <strong class="text-warning">125 Unit (12.4%)</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-muted">Transmisi Microwave:</span>
                                            <strong class="text-dark">738 Unit (73.4%)</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Low Power, FM/TV & STB:</span>
                                            <strong class="text-dark">143 Unit (14.2%)</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 3: DAFTAR 16 KATEGORI REGULASI RESMI SDPPI -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade" id="pane-pk4-all16" role="tabpanel" aria-labelledby="tab-pk4-all16-btn">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle fs-11 mb-0 border">
                                <thead style="background: #f8fafc; font-size: 11px;">
                                    <tr class="text-dark">
                                        <th class="text-center" style="width: 40px;">No</th>
                                        <th style="min-width: 200px;">Jenis Alat dan Perangkat Telekomunikasi (APT)</th>
                                        <th style="min-width: 140px;">Kategori Regulasi</th>
                                        <th class="text-center" style="width: 80px;">Target</th>
                                        <th class="text-center" style="width: 80px;">Realisasi</th>
                                        <th class="text-center" style="width: 90px;">% Capaian</th>
                                        <th class="text-center" style="width: 140px;">Status Pengawasan</th>
                                        <th style="min-width: 180px;">Keterangan & Dasar Dokumen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pk4PriorityDevices as $devNum => $dev): 
                                        $isMonitored = ($dev['realisasi'] > 0);
                                        $isPri = !empty($dev['is_priority']);
                                        $pct = ($dev['target'] > 0) ? round(($dev['realisasi'] / $dev['target']) * 100, 1) : 0;
                                    ?>
                                        <tr style="<?= $isPri ? 'background: #fffbeb;' : ($isMonitored ? '' : 'background: #fafafa;') ?>">
                                            <td class="text-center text-muted fw-bold"><?= $devNum ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="material-symbols-outlined fs-16 <?= $isPri ? 'text-warning' : ($isMonitored ? 'text-primary' : 'text-muted') ?>"><?= $dev['icon'] ?></i>
                                                    <span class="fw-bold <?= $isPri ? 'text-dark' : ($isMonitored ? 'text-dark' : 'text-muted') ?>">
                                                        <?= htmlspecialchars($dev['name']) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($isPri): ?>
                                                    <span class="badge bg-warning text-dark font-monospace fs-10 px-2 py-0.5 fw-bold">
                                                        <i class="fa fa-star text-danger me-0.5"></i>Prioritas Utama
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-secondary border fs-10 px-2 py-0.5"><?= $dev['category'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center font-monospace"><?= $dev['target'] ?> Unit</td>
                                            <td class="text-center font-monospace fw-bold <?= $isMonitored ? 'text-success' : 'text-muted' ?>">
                                                <?= $dev['realisasi'] ?> Unit
                                            </td>
                                            <td class="text-center font-monospace fw-bold <?= $isMonitored ? 'text-success' : 'text-muted' ?>">
                                                <?= $pct ?>%
                                            </td>
                                            <td class="text-center">
                                                <?php if ($isMonitored): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-0.5 rounded-pill fs-10">
                                                        <i class="fa fa-check me-0.5"></i>Sudah Dimonitor
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border px-2 py-0.5 rounded-pill fs-10">
                                                        Nihil di Pasar (0 Unit)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fs-10 text-muted">
                                                <?php if ($isPri): ?>
                                                    <span class="text-dark fw-semibold">Prioritas Utama Hal. 4 Laporan PK Balmon</span>
                                                <?php elseif ($isMonitored): ?>
                                                    <span>Termonitor & tervalidasi pada gerai ritel / BTS Kaltim</span>
                                                <?php else: ?>
                                                    <span class="text-muted">Tidak ditemukan pelanggaran / peredaran di 8 Kab/Kota</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts PK.4: Tren Capaian Bulanan & Komposisi Sertifikasi -->
            <div class="row g-3 mb-4">
                <div class="col-xl-8 col-lg-7">
                    <div class="card pk-chart-card p-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-primary fs-18 me-1">trending_up</i>
                                    Grafik Tren Capaian PK Monitoring APT 2026 (Target: 100% / 300 Perangkat)
                                </h6>
                                <span class="fs-11 text-muted">Perkembangan persentase kumulatif pengawasan terhadap target tahunan</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge badge-soft-primary border fs-10 px-2 py-1">
                                    <i class="fa fa-bullseye me-1"></i>Target PK: 100%
                                </span>
                                <span class="badge bg-success text-white fs-10 px-2 py-1">
                                    <i class="fa fa-check me-1"></i>Realisasi September: 100.0%
                                </span>
                            </div>
                        </div>
                        <div id="chart-pk4-capaian" style="min-height: 270px;"></div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-5">
                    <div class="card pk-chart-card p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-warning fs-18 me-1">pie_chart</i>
                                    Status Kepatuhan Sertifikasi
                                </h6>
                                <span class="fs-11 text-muted">1.052 unit perangkat terperiksa</span>
                            </div>
                            <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill">100% Patuh</span>
                        </div>

                        <!-- Progress Bar Total 100% -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between fs-11 fw-semibold mb-1">
                                <span class="text-success"><i class="fa fa-circle-check me-1"></i>Realisasi Kumulatif PK.4</span>
                                <span class="text-dark fw-bold">100.0% / 100.0%</span>
                            </div>
                            <div class="progress" style="height: 10px; border-radius: 6px; background-color: #e2e8f0;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 100%;"></div>
                            </div>
                        </div>

                        <!-- Detail Sertifikasi & Pelanggaran -->
                        <div class="d-flex flex-column gap-2">
                            <div class="p-2.5 rounded border bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="fs-11 fw-bold text-success d-block"><i class="fa fa-check-circle me-1"></i>Bersertifikat Resmi SDPPI</span>
                                    <small class="fs-10 text-muted">Memenuhi standar teknis & berlabel resmi</small>
                                </div>
                                <span class="badge bg-success text-white fs-11 px-2 py-1 rounded-pill">1.052 Unit</span>
                            </div>

                            <div class="p-2.5 rounded border bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="fs-11 fw-bold text-muted d-block"><i class="fa fa-shield me-1"></i>Temuan Pelanggaran / Sanksi</span>
                                    <small class="fs-10 text-muted">Nihil pelanggaran teknis (0 Kasus)</small>
                                </div>
                                <span class="badge bg-light text-muted border fs-11 px-2 py-1 rounded-pill">0 Unit</span>
                            </div>

                            <div class="p-2.5 rounded border bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="fs-11 fw-bold text-primary d-block"><i class="fa fa-cloud-upload me-1"></i>Pelaporan Aplikasi SMART</span>
                                    <small class="fs-10 text-muted">smart.postel.go.id & Komdigi</small>
                                </div>
                                <span class="badge bg-primary text-white fs-11 px-2 py-1 rounded-pill">100% Terinput</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($selectedCode === 'PK.6' && $selectedYear === 2026): ?>
            <!-- ============================================================ -->
            <!-- SECTION KHUSUS PK.6: TERJAGANYA OPERASIONAL & FUNGSI SMFR 2026 -->
            <!-- Sumber: Laporan Capaian Kinerja Perangkat SMFR Januari s.d. Mei 2026 -->
            <!-- ============================================================ -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 class="fw-bold text-dark fs-15 mb-0 d-flex align-items-center">
                        <i class="material-symbols-outlined text-success fs-20 me-1.5">cell_tower</i>
                        Capaian Kinerja Terjaganya Operasional & Fungsi Monitoring SMFR (TA 2026)
                    </h5>
                    <span class="fs-11 text-muted">
                        Sumber Resmi: Laporan Bulanan Perangkat SMFR Januari s.d. Mei 2026 • Nota Dinas No: 60, 127, 178, 246, 313/BALMON.64/SP.03.01/2026
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success text-white fs-12 px-3 py-1.5 rounded-pill shadow-sm">
                        <i class="fa fa-circle-check me-1"></i>Rata-rata Realisasi: 92.25% SLA (Target 86%)
                    </span>
                    <a href="https://smsn.postel.go.id" target="_blank" class="btn btn-outline-success btn-sm rounded-pill px-3">
                        <i class="material-symbols-outlined fs-16 align-middle me-1">cloud_upload</i> Portal SMSN
                    </a>
                    <a href="https://rol.postel.go.id" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="material-symbols-outlined fs-16 align-middle me-1">open_in_new</i> Portal ROL
                    </a>
                </div>
            </div>

            <!-- 5 KARTU STASIUN MONITOR BALMON SAMARINDA -->
            <div class="row g-3 mb-4">
                <!-- Stasiun Tetap VUHF Samarinda -->
                <div class="col-xl col-md-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-light text-dark fs-10 fw-bold border">STASIUN TETAP</span>
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                80.00% SLA
                            </span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1 fs-13">Tetap VUHF Samarinda</h6>
                        <div class="fs-11 text-muted mb-2">Jl. Sultan Sulaiman No. 55</div>
                        <div class="progress mb-2" style="height: 5px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 80%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            3 Utama & 1 Pendukung Baik. Direction Finder R&S DDF 255 rusak (ND 141/2025). Tetap diperhitungkan dalam PK proporsional.
                        </p>
                    </div>
                </div>

                <!-- Stasiun Transportable Balikpapan -->
                <div class="col-xl col-md-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-light text-dark fs-10 fw-bold border">TRANSPORTABLE</span>
                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">
                                100.00% SLA
                            </span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1 fs-13">Site Balikpapan</h6>
                        <div class="fs-11 text-muted mb-2">Kawasan Strategis Balikpapan</div>
                        <div class="progress mb-2" style="height: 5px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            FMU 306 LS observer, NTI GA3005, A-info PZ Antena, Mikrotik & UPS Prima. Upload okupansi SMSN rutin 4x/bulan.
                        </p>
                    </div>
                </div>

                <!-- Stasiun Transportable Berau -->
                <div class="col-xl col-md-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-light text-dark fs-10 fw-bold border">TRANSPORTABLE</span>
                            <span class="badge bg-primary text-white fs-10 px-2 py-0.5 rounded-pill">
                                95.00% Rata-rata
                            </span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1 fs-13">Site Berau</h6>
                        <div class="fs-11 text-muted mb-2">Kabupaten Berau</div>
                        <div class="progress mb-2" style="height: 5px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 95%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Jan-Apr SLA 100%. Pada Mei SLA 75% karena All Band Receiver FMU 306 rusak per 25 Mei 2026 (ND 292/2026).
                        </p>
                    </div>
                </div>

                <!-- Stasiun Transportable Kutai Timur -->
                <div class="col-xl col-md-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-light text-dark fs-10 fw-bold border">TRANSPORTABLE</span>
                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">
                                95.00% Rata-rata
                            </span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1 fs-13">Site Kutai Timur</h6>
                        <div class="fs-11 text-muted mb-2">Sangatta, Kab. Kutai Timur</div>
                        <div class="progress mb-2" style="height: 5px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 95%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Januari SLA 75% (kendala perangkat utama), Februari s.d. Mei 100% normal dan menyampaikan data okupansi SMSN rutin.
                        </p>
                    </div>
                </div>

                <!-- Stasiun Bergerak Mobil Mon DF -->
                <div class="col-xl col-md-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-light text-dark fs-10 fw-bold border">BERGERAK (DF)</span>
                            <span class="badge bg-primary text-white fs-10 px-2 py-0.5 rounded-pill">
                                92.00% Rata-rata
                            </span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1 fs-13">Mobil Mon DF (Hilux)</h6>
                        <div class="fs-11 text-muted mb-2">Toyota Hilux KT 1344 MZ</div>
                        <div class="progress mb-2" style="height: 5px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 92%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Januari SLA 100%, Februari-Mei SLA 90% (Front panel ESMD rusak). Antena HE600, HF907OM, Mast, dan Laptop prima.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ROW 2: CHART TREN SLA & LOG OKUPANSI SMSN -->
            <div class="row g-3 mb-4">
                <!-- Col 8: Chart Tren SLA Bulanan -->
                <div class="col-xl-8">
                    <div class="card border rounded-3 p-3 h-100" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-success fs-18 me-1">monitoring</i>
                                    Grafik Tren Capaian SLA SMFR (Januari - Mei 2026) vs Target 86%
                                </h6>
                                <span class="fs-11 text-muted">Berdasarkan Laporan Resmi UPT Balai Monitor SFR Kelas I Samarinda</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary fs-11 px-2.5 py-1 fw-bold">
                                    Target PK: 86% SLA
                                </span>
                                <span class="badge bg-success text-white fs-11 px-2.5 py-1 fw-bold">
                                    Realisasi: 92.25%
                                </span>
                            </div>
                        </div>
                        <div id="chart-pk6-sla" style="min-height: 270px;"></div>
                    </div>
                </div>

                <!-- Col 4: Ringkasan Okupansi SMSN & BMN -->
                <div class="col-xl-4">
                    <div class="card border rounded-3 p-3 h-100" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                <i class="material-symbols-outlined text-primary fs-18 me-1">inventory_2</i>
                                Ringkasan Kesisteman & Data Okupansi
                            </h6>
                            <span class="badge bg-light text-muted fs-10 border">2026</span>
                        </div>

                        <!-- Stat Box Okupansi -->
                        <div class="p-3 rounded-3 mb-3" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fs-11 fw-bold text-success text-uppercase">PENGIRIMAN DATA OKUPANSI SMSN</span>
                                <span class="badge bg-success text-white fs-10 rounded-pill">100% Target</span>
                            </div>
                            <h3 class="fw-bold text-success mb-1">22 <small class="fs-13 text-muted">Kali Pengiriman</small></h3>
                            <div class="fs-11 text-muted">
                                Jan (4x), Feb (6x), Mar (4x), Apr (4x), Mei (4x) diunggah tepat waktu ke Google Drive SMSN Pusat.
                            </div>
                        </div>

                        <!-- Stat Box BMN SMFR -->
                        <div class="p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fs-11 fw-bold text-primary text-uppercase">INVENTARIS PERANGKAT SMFR (BMN)</span>
                                <span class="badge bg-primary text-white fs-10 rounded-pill">95.95% Baik</span>
                            </div>
                            <div class="d-flex align-items-baseline gap-2 mb-2">
                                <h3 class="fw-bold text-dark mb-0">142 <small class="fs-13 text-muted">/ 148 Unit</small></h3>
                                <span class="fs-11 text-success fw-bold"><i class="fa fa-check me-1"></i>Kondisi Baik</span>
                            </div>
                            <div class="progress mb-2" style="height: 6px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 95.95%;"></div>
                            </div>
                            <div class="d-flex justify-content-between fs-11 text-muted">
                                <span>Perangkat Utama: 22 Baik, 6 Rusak</span>
                                <span>Pendukung: 97/97 Baik (100%)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($selectedCode === 'PK.7' && $selectedYear === 2026): ?>
            <!-- ============================================================ -->
            <!-- SECTION KHUSUS PK.7: PENANGANAN SANKSI DENDA ADMINISTRATIF 2026 -->
            <!-- Sumber: 4 Surat Tagihan Denda Administratif (Tagihan Bulan ke-1) -->
            <!-- Balai Monitor Spektrum Frekuensi Radio Kelas I Samarinda -->
            <!-- Berdasarkan PP 43/2023, PP 28/2025, dan Perdirjen SDPPI 3/2024 -->
            <!-- ============================================================ -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 class="fw-bold text-dark fs-15 mb-0 d-flex align-items-center">
                        <i class="material-symbols-outlined text-danger fs-20 me-1.5">gavel</i>
                        Capaian Penanganan Sanksi Denda Administratif SFR (TA 2026)
                    </h5>
                    <span class="fs-11 text-muted">
                        Sumber Resmi: 4 Surat Tagihan Denda Administratif (Tagihan Bulan ke-1) • Balmon Kelas I Samarinda • PP No. 43/2023, PP No. 28/2025, & Perdirjen SDPPI No. 3/2024
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success text-white fs-12 px-3 py-1.5 rounded-pill shadow-sm">
                        <i class="fa fa-circle-check me-1"></i>Realisasi: 95.0% (Target: 85.0%)
                    </span>
                    <a href="monitoring_penertiban_frekuensi.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                        <i class="material-symbols-outlined fs-16 align-middle me-1">policy</i> Penertiban Frekuensi
                    </a>
                    <a href="https://simponi.kemenkeu.go.id" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="material-symbols-outlined fs-16 align-middle me-1">account_balance_wallet</i> Portal SIMPONI
                    </a>
                </div>
            </div>

            <!-- 4 KARTU PARAMETER EKSEKUTIF DENDA ADMINISTRATIF -->
            <div class="row g-3 mb-4">
                <!-- Param 1: Total Nilai Denda Pokok -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-danger bg-opacity-10 text-danger fs-10 fw-bold">TOTAL KETETAPAN DENDA</span>
                            <span class="badge bg-danger text-white fs-10 px-2 py-0.5 rounded-pill">4 Surat Tagihan I</span>
                        </div>
                        <h4 class="fw-bold text-danger mb-1">Rp 201.258.400</h4>
                        <div class="fs-12 fw-semibold text-dark mb-1">Total Denda Pokok PNBP</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Terbilang: <em>Dua Ratus Satu Juta Dua Ratus Lima Puluh Delapan Ribu Empat Ratus Rupiah</em>. Denda keterlambatan Rp 0 (masa Tagihan Bulan Ke-1).
                        </p>
                    </div>
                </div>

                <!-- Param 2: Wajib Bayar / Entitas Terkena Sanksi -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-warning bg-opacity-10 text-warning fs-10 fw-bold">BADAN HUKUM / PELANGGAR</span>
                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">100% Ditindaklanjuti</span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">4 <small class="fs-12 text-muted">Badan Hukum</small></h4>
                        <div class="fs-12 fw-semibold text-warning mb-1">PLN, Lintasmaya, Perdana, Hamparan</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            1 BUMN (PLN Persero) dan 3 Swasta/ISP (PT Lintasmaya, PT Perdana Tekno, PT Hamparan Anugrah Abadi) dengan kepatuhan NIB/NPWP terverifikasi.
                        </p>
                    </div>
                </div>

                <!-- Param 3: Jumlah Objek / Stasiun Pelanggaran -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary fs-10 fw-bold">OBJEK PELANGGARAN</span>
                            <span class="badge bg-primary text-white fs-10 px-2 py-0.5 rounded-pill">24 Stasiun / Titik</span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">24 <small class="fs-12 text-muted">Objek Pelanggaran</small></h4>
                        <div class="fs-12 fw-semibold text-primary mb-1">Bergerak Darat & Tetap PtP</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Rincian: PLN (17 objek: 1 HT, 5 RP, 11 BS), Lintasmaya (1 PtP 5.7 GHz), Perdana Tekno (2 PtP 5.8 & 5.7 GHz), Hamparan (4 frekuensi VHF, 36 unit).
                        </p>
                    </div>
                </div>

                <!-- Param 4: Kode Billing SIMPONI & Kas Negara -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-success bg-opacity-10 text-success fs-10 fw-bold">KODE BILLING SIMPONI</span>
                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">MPN G3 Kemenkeu</span>
                        </div>
                        <h4 class="fw-bold text-success mb-1">4 <small class="fs-12 text-muted">Kode Billing</small></h4>
                        <div class="fs-12 fw-semibold text-success mb-1">Kanal Bank & E-Commerce</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Disetor langsung ke Kas Negara via Modul Penerimaan Negara Generasi 3 (MPN G3). Batas setor 30 hari kalender sejak surat tagihan diterbitkan.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ROW 2: CHART TREN & RINCIAN DENDA SERTA PROSEDUR -->
            <div class="row g-3 mb-4">
                <div class="col-xl-8">
                    <div class="card border rounded-3 p-3 h-100" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-danger fs-18 me-1">bar_chart</i>
                                    Distribusi Nilai Sanksi Denda Administratif per Wajib Bayar (TA 2026)
                                </h6>
                                <span class="fs-11 text-muted">Total Ketetapan: Rp 201.258.400 dari 4 Surat Tagihan Resmi (Bulan Ke-1)</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-danger text-white fs-11 px-2.5 py-1 fw-bold">
                                    Total: Rp 201.258.400
                                </span>
                                <span class="badge bg-success text-white fs-11 px-2.5 py-1 fw-bold">
                                    Capaian: 95.0%
                                </span>
                            </div>
                        </div>
                        <div id="chart-pk7-denda" style="min-height: 270px;"></div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card border rounded-3 p-3 h-100" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                <i class="material-symbols-outlined text-primary fs-18 me-1">fact_check</i>
                                Ketentuan Sanksi & Denda Keterlambatan
                            </h6>
                            <span class="badge bg-light text-muted fs-10 border">PP 43/2023</span>
                        </div>

                        <!-- Info Box Tenggat -->
                        <div class="p-3 rounded-3 mb-3" style="background: #fef2f2; border: 1px solid #fecaca;">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fs-11 fw-bold text-danger text-uppercase">TENGGAT PEMBAYARAN TAGIHAN</span>
                                <span class="badge bg-danger text-white fs-10 rounded-pill">30 Hari Kalender</span>
                            </div>
                            <div class="fs-12 text-dark fw-semibold mb-1">
                                Pembayaran lunas sebelum batas jatuh tempo
                            </div>
                            <div class="fs-11 text-muted">
                                Apabila tidak dilunasi s.d. tanggal jatuh tempo, diterbitkan Surat Tagihan Bulan ke-2 dengan sanksi keterlambatan <strong>2% per bulan</strong> dari denda pokok.
                            </div>
                        </div>

                        <!-- Alur Eskalasi Penagihan -->
                        <div class="p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="fs-11 fw-bold text-primary text-uppercase mb-2">TAHAPAN PENANGANAN SANKSI DENDA</div>
                            <ul class="list-unstyled mb-0 fs-11 text-muted d-flex flex-column gap-2">
                                <li class="d-flex align-items-start gap-2">
                                    <span class="badge bg-primary text-white rounded-circle p-1" style="width: 18px; height: 18px; font-size: 9px; line-height: 10px;">1</span>
                                    <span><strong>Surat Teguran Tertulis & BAP:</strong> Diberikan waktu 7 hari kerja untuk penghentian/klarifikasi.</span>
                                </li>
                                <li class="d-flex align-items-start gap-2">
                                    <span class="badge bg-danger text-white rounded-circle p-1" style="width: 18px; height: 18px; font-size: 9px; line-height: 10px;">2</span>
                                    <span><strong>Surat Tagihan I (30 Hari):</strong> Pembayaran denda pokok via SIMPONI (Bobot PK: 1.0).</span>
                                </li>
                                <li class="d-flex align-items-start gap-2">
                                    <span class="badge bg-warning text-dark rounded-circle p-1" style="width: 18px; height: 18px; font-size: 9px; line-height: 10px;">3</span>
                                    <span><strong>Surat Tagihan II & III:</strong> Dikenakan denda keterlambatan 2%/bulan (Bobot PK: 0.9 - 0.8).</span>
                                </li>
                                <li class="d-flex align-items-start gap-2">
                                    <span class="badge bg-dark text-white rounded-circle p-1" style="width: 18px; height: 18px; font-size: 9px; line-height: 10px;">4</span>
                                    <span><strong>Optimalisasi & KPKNL:</strong> Pelimpahan pengurusan piutang macet ke PUPN / KPKNL (Bobot PK: 0.6).</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($selectedCode === 'PK.8' && $selectedYear === 2026): ?>
            <!-- ============================================================ -->
            <!-- SECTION KHUSUS PK.8: KUALITAS LAYANAN INFRASTRUKTUR DIGITAL (QOS) 2026 -->
            <!-- Sumber: Laporan Hasil Kegiatan Pengukuran Kualitas Layanan Digital di 8 Kab/Kota -->
            <!-- Balai Monitor Spektrum Frekuensi Radio dan Perangkat Digital Kelas I Samarinda -->
            <!-- ============================================================ -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 class="fw-bold text-dark fs-15 mb-0 d-flex align-items-center">
                        <i class="material-symbols-outlined text-info fs-20 me-1.5">speed</i>
                        Capaian PK Kualitas Layanan Seluler (Quality of Service - TA 2026)
                    </h5>
                    <span class="fs-11 text-muted">
                        Sumber Resmi: Laporan Pengukuran Kualitas Layanan Seluler di 8 Kab/Kota • Terintegrasi Pusat Monitoring Telekomunikasi (PMT) Ditjen PPI
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success text-white fs-12 px-3 py-1.5 rounded-pill shadow-sm">
                        <i class="fa fa-circle-check me-1"></i>Realisasi: 100.0% (8 dari 8 Kab/Kota Terukur)
                    </span>
                    <a href="monitoring_kualitas_layanan.php" class="btn btn-outline-info btn-sm rounded-pill px-3">
                        <i class="material-symbols-outlined fs-16 align-middle me-1">speed</i> Menu Kualitas Layanan
                    </a>
                    <a href="https://pmt.postel.go.id" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="material-symbols-outlined fs-16 align-middle me-1">open_in_new</i> Portal PMT
                    </a>
                </div>
            </div>

            <!-- 4 KARTU PARAMETER EKSEKUTIF QOS -->
            <div class="row g-3 mb-4">
                <!-- Parameter 1: Cakupan Wilayah Kerja -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary fs-10 fw-bold">WILAYAH KERJA</span>
                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">
                                <i class="fa fa-check me-1"></i>100% Target
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">8 / 8 <small class="fs-11 text-muted">Kab/Kota</small></h4>
                        <div class="fs-12 fw-semibold text-primary mb-1">80% Wilayah Kerja Terukur</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Samarinda, Balikpapan, Kukar, Bontang, PPU/IKN, Kutim, Berau, dan Paser selesai diukur via Drive Test & Static Test.
                        </p>
                    </div>
                </div>

                <!-- Parameter 2: Operator Tercepat (Bandwidth) -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-info bg-opacity-10 text-info fs-10 fw-bold">TOP THROUGHPUT</span>
                            <span class="badge bg-info text-white fs-10 px-2 py-0.5 rounded-pill">Rank #1 Speed</span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">XL Axiata <small class="fs-11 text-muted">120.3 Mbps</small></h4>
                        <div class="fs-12 fw-semibold text-info mb-1">Download & Upload Tertinggi</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Rata-rata DL 120.30 Mbps dan UL 66.13 Mbps di Samarinda, Berau, & Paser. Tingkat keberhasilan speedtest hingga 100%.
                        </p>
                    </div>
                </div>

                <!-- Parameter 3: Operator Paling Stabil & Video -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-danger bg-opacity-10 text-danger fs-10 fw-bold">VIDEO & APLIKASI</span>
                            <span class="badge bg-danger text-white fs-10 px-2 py-0.5 rounded-pill">Rank #1 Stabil</span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">Telkomsel <small class="fs-11 text-muted">99.4% SR</small></h4>
                        <div class="fs-12 fw-semibold text-danger mb-1">Layanan YouTube & IG Unggul</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: 99.4%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            Dominasi resolusi HD 720p (93.5% - 96.7%), IG Post Video SR hingga 98.3%, serta kekuatan sinyal 4G paling konsisten.
                        </p>
                    </div>
                </div>

                <!-- Parameter 4: Kondisi Sinyal 4G LTE -->
                <div class="col-xl-3 col-sm-6">
                    <div class="pk-param-card h-100 p-3 rounded-3" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-success bg-opacity-10 text-success fs-10 fw-bold">CAKUPAN RADIO 4G</span>
                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill">Baik Sekali</span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">4G LTE <small class="fs-11 text-muted">Dominan</small></h4>
                        <div class="fs-12 fw-semibold text-success mb-1">RSRP -81 s.d. -96 dBm</div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%;"></div>
                        </div>
                        <p class="fs-11 text-muted mb-0" style="line-height: 1.35;">
                            100% rute protokol terlayani 4G LTE. Temuan interferensi SINR tercatat dan diteruskan ke PMT Pusat untuk evaluasi.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ROW 2: CHART THROUGHPUT & RINGKASAN METODOLOGI PMT -->
            <div class="row g-3 mb-4">
                <!-- Col 8: Chart Komparasi Kecepatan 3 Operator -->
                <div class="col-xl-8">
                    <div class="card border rounded-3 p-3 h-100" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                    <i class="material-symbols-outlined text-info fs-18 me-1">bar_chart</i>
                                    Komparasi Throughput Download & Upload 3 Operator (Samarinda, Berau, Paser)
                                </h6>
                                <span class="fs-11 text-muted">Hasil pengujian resmi Drive Test & Static Test (Software SmartMon / Freerider Backpack Gen 4)</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-light text-dark border fs-10">XL: #1 Speed</span>
                                <span class="badge bg-light text-dark border fs-10">Tsel: #1 Coverage</span>
                                <span class="badge bg-light text-dark border fs-10">Isat: #3 Speed</span>
                            </div>
                        </div>
                        <div id="chart-pk8-throughput" style="min-height: 270px;"></div>
                    </div>
                </div>

                <!-- Col 4: Ringkasan Metodologi & Temuan Teknis -->
                <div class="col-xl-4">
                    <div class="card border rounded-3 p-3 h-100" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                <i class="material-symbols-outlined text-primary fs-18 me-1">verified_user</i>
                                Metodologi Uji & Temuan Sinyal
                            </h6>
                            <span class="badge bg-success text-white fs-10 rounded-pill">PMT Komdigi</span>
                        </div>

                        <!-- Metodologi Box -->
                        <div class="p-3 rounded-3 mb-3" style="background: #f0f9ff; border: 1px solid #bae6fd;">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fs-11 fw-bold text-primary text-uppercase">STANDAR PENGUJIAN REGULER</span>
                                <span class="badge bg-primary text-white fs-10 rounded-pill">Perdirjen PPI 7/2024</span>
                            </div>
                            <div class="fs-12 text-dark fw-semibold mb-1">
                                Drive Test min. 5 Jam (±40 km/jam) di Jam Sibuk
                            </div>
                            <div class="fs-11 text-muted" style="line-height: 1.4;">
                                Static Test 1 jam di minimal 3 titik POI. Pengukuran mencakup Youtube Live Streaming, Browsing, Speedtest, HTTP DL/UL, Ping, WA Call/Message.
                            </div>
                        </div>

                        <!-- Temuan SINR Box -->
                        <div class="p-3 rounded-3" style="background: #fef2f2; border: 1px solid #fecaca;">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fs-11 fw-bold text-danger text-uppercase">TEMUAN INTERFERENSI SINR 4G</span>
                                <span class="badge bg-danger text-white fs-10 rounded-pill">Perlu Optimalisasi</span>
                            </div>
                            <div class="fs-11 text-dark mb-1">
                                Persentase <em>Bad SINR</em> tercatat: Indosat (45.9% - 67.5%), Telkomsel (43.8% - 51.9%), XL (31.0% - 41.3%).
                            </div>
                            <div class="fs-10 text-muted fst-italic">
                                Sinyal 5G belum terukur (No Data). Temuan interferensi diteruskan ke PMT dan operator terkait untuk tindak lanjut.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Fallback Chart Sederhana untuk PK.3 - PK.8 -->
            <div class="card pk-chart-card p-3 mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div>
                        <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                            <i class="material-symbols-outlined text-primary fs-18 me-1">trending_up</i>
                            Grafik Capaian Bulanan - <?= htmlspecialchars($activePk['title']) ?>
                        </h6>
                        <span class="fs-11 text-muted">Perkembangan realisasi kumulatif bulanan tahun <?= $selectedYear ?></span>
                    </div>
                    <span class="badge bg-primary text-white fs-11 px-2.5 py-1">
                        Realisasi s.d. Saat Ini: <?= $activePk['realization'] ?><?= $activePk['unit'] ?> (<?= $pctTarget ?>%)
                    </span>
                </div>
                <div id="chart-sfr-capaian" style="min-height: 260px;"></div>
            </div>
        <?php endif; ?>



        <!-- ============================================================ -->
        <!-- 5. TABEL REKAPITULASI PELAKSANAAN BULANAN -->
        <!-- ============================================================ -->
        <div class="card border rounded-3 overflow-hidden mb-4" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
            <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="background: #ffffff;">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle badge-soft-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="material-symbols-outlined fs-20">table_chart</i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark fs-14">
                            <?= ($selectedCode === 'PK.2') 
                                ? 'Rekapitulasi Bulanan Capaian PK Pemeriksaan Stasiun Radio (Tahun 2026)' 
                                : (($selectedCode === 'PK.1') 
                                    ? 'Rekapitulasi Bulanan Capaian PK Monitoring Spektrum Frekuensi Radio (Tahun ' . $selectedYear . ')' 
                                    : (($selectedCode === 'PK.3' && $selectedYear === 2026)
                                        ? 'Rekapitulasi Bulanan Capaian PK Penanganan Gangguan Spektrum Frekuensi Radio (Tahun 2026)'
                                        : (($selectedCode === 'PK.4' && $selectedYear === 2026)
                                            ? 'Rekapitulasi Bulanan Capaian PK Monitoring Alat dan Perangkat Telekomunikasi (Tahun 2026)'
                                            : (($selectedCode === 'PK.6' && $selectedYear === 2026)
                                                ? 'Rekapitulasi Bulanan Capaian PK Terjaganya Operasional & SLA SMFR (Tahun 2026)'
                                                : (($selectedCode === 'PK.7' && $selectedYear === 2026)
                                                    ? 'Rekapitulasi Bulanan Capaian PK Penanganan Sanksi Denda Administratif (Tahun 2026)'
                                                    : (($selectedCode === 'PK.8' && $selectedYear === 2026)
                                                        ? 'Rekapitulasi Bulanan Capaian PK Kualitas Layanan Seluler (QoS) (Tahun 2026)'
                                                        : 'Tabel Rekapitulasi Pelaksanaan Bulanan (Tahun ' . $selectedYear . ')')))))) ?>
                        </h5>
                        <span class="fs-11 text-muted">
                            <?= ($selectedCode === 'PK.2') 
                                ? 'Format resmi Balmon SFR Kelas I Samarinda: Pengukuran Siaran (FM & TV), Remote Site, Open Shelter' 
                                : (($selectedCode === 'PK.1') 
                                    ? 'Format komprehensif Balmon SFR Kelas I Samarinda: Wilayah Observasi, ISR Termonitor, Pengguna Terdata, dan Tindak Lanjut Sanksi' 
                                    : (($selectedCode === 'PK.3' && $selectedYear === 2026)
                                        ? 'Format resmi Balmon SFR Kelas I Samarinda: Trouble Ticket, Pelacakan DF, Penindakan Sumber Gangguan, dan Penerbitan BAP'
                                        : (($selectedCode === 'PK.4' && $selectedYear === 2026)
                                            ? 'Format pengawasan 16 Jenis Perangkat (dengan 3 Prioritas Utama) di 8 Kab/Kota berbasis Aplikasi SMART'
                                            : (($selectedCode === 'PK.6' && $selectedYear === 2026)
                                                ? 'Format resmi SLA Stasiun Monitor Tetap, Transportable (3 Site), dan Stasiun Bergerak DF berbasis ROL & SMSN'
                                                : (($selectedCode === 'PK.7' && $selectedYear === 2026)
                                                    ? 'Format resmi 4 Surat Tagihan Denda Administratif (SPP Bulan ke-1), 4 Kode Billing SIMPONI, dan 24 Rincian Objek Pelanggaran SFR'
                                                    : (($selectedCode === 'PK.8' && $selectedYear === 2026)
                                                        ? 'Pengukuran Drive Test dan Static Test di 8 Kabupaten/Kota wilayah kerja Balai Monitor Samarinda terintegrasi PMT Pusat'
                                                        : 'Tracking target, realisasi, capaian kumulatif, dan nomor laporan Nota Dinas UPT')))))) ?>
                        </span>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <?php if ($selectedCode === 'PK.3' && $selectedYear === 2026): ?>
                        <a href="monitoring_penanganan_gangguan.php" class="btn btn-outline-warning text-dark btn-sm rounded-pill px-3 fw-bold">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">report_problem</i> Menu Penanganan Gangguan
                        </a>
                        <a href="https://laporgangguansfr.postel.go.id" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">open_in_new</i> Portal SIMS Lapor Gangguan
                        </a>
                    <?php elseif ($selectedCode === 'PK.4' && $selectedYear === 2026): ?>
                        <button type="button" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalInputMonApt">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">add_circle</i> + Input Data Monitoring APT
                        </button>
                    <?php elseif ($selectedCode === 'PK.7' && $selectedYear === 2026): ?>
                        <a href="monitoring_penertiban_frekuensi.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">policy</i> Penertiban Frekuensi
                        </a>
                        <a href="https://simponi.kemenkeu.go.id" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">account_balance_wallet</i> Portal SIMPONI
                        </a>
                    <?php elseif ($selectedCode === 'PK.8' && $selectedYear === 2026): ?>
                        <a href="monitoring_kualitas_layanan.php" class="btn btn-outline-info btn-sm rounded-pill px-3">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">speed</i> Menu Kualitas Layanan
                        </a>
                        <a href="https://pmt.postel.go.id" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">open_in_new</i> Portal PMT
                        </a>
                    <?php endif; ?>
                    <a href="export.php?pk=<?= $selectedCode ?>&year=<?= $selectedYear ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                        <i class="fa fa-print me-1"></i> Cetak / Export Rekap
                    </a>
                </div>
            </div>

            <?php if ($selectedCode === 'PK.2' && $selectedYear === 2026): ?>
                <!-- TABEL KHUSUS PK.2 PERSIS SESUAI DOKUMEN RESMI BALMON -->
                <div class="table-responsive">
                    <table class="pk-table-clean">
                        <thead>
                            <tr>
                                <th rowspan="2" class="text-center align-middle" style="width: 40px; border-right: 1px solid #bae6fd;">No</th>
                                <th rowspan="2" class="align-middle" style="width: 100px; border-right: 1px solid #bae6fd;">Bulan</th>
                                <th colspan="3" class="text-center" style="border-right: 1px solid #bae6fd; background: #e0f2fe;">Pengukuran FM Siaran</th>
                                <th colspan="3" class="text-center" style="border-right: 1px solid #bae6fd; background: #e0f2fe;">Pengukuran TV Digital</th>
                                <th colspan="3" class="text-center" style="border-right: 1px solid #bae6fd; background: #fef3c7; color: #92400e;">Remote Site</th>
                                <th colspan="3" class="text-center" style="border-right: 1px solid #bae6fd; background: #dcfce7; color: #166534;">Open Shelter</th>
                                <th rowspan="2" class="text-center align-middle" style="width: 100px; border-right: 1px solid #bae6fd; background: #eff6ff;">Total Capaian (Stn)</th>
                                <th rowspan="2" class="text-center align-middle" style="width: 100px; background: #eff6ff;">Capaian PK (%)</th>
                            </tr>
                            <tr>
                                <!-- FM -->
                                <th class="text-center" style="font-size: 10px; width: 55px;">Target</th>
                                <th class="text-center" style="font-size: 10px; width: 55px;">Capaian</th>
                                <th class="text-center" style="font-size: 10px; width: 60px; border-right: 1px solid #bae6fd;">%</th>
                                <!-- TV -->
                                <th class="text-center" style="font-size: 10px; width: 55px;">Target</th>
                                <th class="text-center" style="font-size: 10px; width: 55px;">Capaian</th>
                                <th class="text-center" style="font-size: 10px; width: 60px; border-right: 1px solid #bae6fd;">%</th>
                                <!-- Remote Site -->
                                <th class="text-center" style="font-size: 10px; width: 60px;">Target</th>
                                <th class="text-center" style="font-size: 10px; width: 65px;">Capaian</th>
                                <th class="text-center" style="font-size: 10px; width: 60px; border-right: 1px solid #bae6fd;">%</th>
                                <!-- Open Shelter -->
                                <th class="text-center" style="font-size: 10px; width: 55px;">Target</th>
                                <th class="text-center" style="font-size: 10px; width: 55px;">Capaian</th>
                                <th class="text-center" style="font-size: 10px; width: 60px; border-right: 1px solid #bae6fd;">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pk2OfficialTable as $mNum => $r): ?>
                                <?php $isRowActive = ($selectedMonth === $mNum); ?>
                                <tr data-table-month="<?= $mNum ?>" class="<?= $isRowActive ? 'row-active' : '' ?>">
                                    <td class="text-center text-muted fw-bold"><?= $mNum ?></td>
                                    <td class="fw-bold">
                                        <a href="pk_view.php?code=PK.2&year=<?= $selectedYear ?>&month=<?= $mNum ?>" onclick="selectMonthRealtime(<?= $mNum ?>); return false;" class="text-decoration-none <?= $isRowActive ? 'text-primary' : 'text-dark' ?>">
                                            <?= htmlspecialchars($r['month_name']) ?>
                                            <span class="row-active-dot badge bg-primary rounded-circle p-1 ms-1" style="width: 5px; height: 5px; display: <?= $isRowActive ? 'inline-block' : 'none' ?>;"></span>
                                        </a>
                                    </td>
                                    <!-- FM Siaran -->
                                    <td class="text-center text-muted"><?= $r['fm_target'] ?></td>
                                    <td class="text-center fw-semibold text-dark"><?= $r['fm_capaian'] ?></td>
                                    <td class="text-center fw-bold <?= ($r['fm_pct'] >= 100) ? 'text-success' : 'text-primary' ?>"><?= number_format($r['fm_pct'], 2) ?>%</td>
                                    <!-- TV Digital -->
                                    <td class="text-center text-muted"><?= $r['tv_target'] ?></td>
                                    <td class="text-center fw-semibold text-dark"><?= $r['tv_capaian'] ?></td>
                                    <td class="text-center fw-bold <?= ($r['tv_pct'] >= 90) ? 'text-success' : 'text-info' ?>"><?= number_format($r['tv_pct'], 2) ?>%</td>
                                    <!-- Remote Site -->
                                    <td class="text-center text-muted"><?= number_format($r['remote_target'], 0, ',', '.') ?></td>
                                    <td class="text-center fw-semibold text-dark"><?= number_format($r['remote_capaian'], 0, ',', '.') ?></td>
                                    <td class="text-center fw-bold text-dark"><?= number_format($r['remote_pct'], 2) ?>%</td>
                                    <!-- Open Shelter -->
                                    <td class="text-center text-muted"><?= $r['shelter_target'] ?></td>
                                    <td class="text-center fw-semibold text-dark"><?= $r['shelter_capaian'] ?></td>
                                    <td class="text-center fw-bold <?= ($r['shelter_pct'] >= 100) ? 'text-success' : 'text-dark' ?>"><?= number_format($r['shelter_pct'], 2) ?>%</td>
                                    <!-- Total Capaian -->
                                    <td class="text-center fw-bold text-primary fs-13"><?= number_format($r['total_capaian'], 0, ',', '.') ?></td>
                                    <!-- Capaian PK -->
                                    <td class="text-center fw-bold <?= ($r['pk_pct'] >= 90) ? 'text-success' : (($r['pk_pct'] > 0) ? 'text-primary' : 'text-muted') ?> fs-13">
                                        <?= number_format($r['pk_pct'], 2) ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-uppercase text-dark">TOTAL TARGET / REALISASI:</td>
                                <td class="text-center text-muted">50</td>
                                <td class="text-center fw-bold text-success">50</td>
                                <td class="text-center text-success">100%</td>
                                <td class="text-center text-muted">18</td>
                                <td class="text-center fw-bold text-info">17</td>
                                <td class="text-center text-info">94.44%</td>
                                <td class="text-center text-muted">2.053</td>
                                <td class="text-center fw-bold text-primary">1.791</td>
                                <td class="text-center text-primary">87.25%</td>
                                <td class="text-center text-muted">262</td>
                                <td class="text-center fw-bold text-success">330</td>
                                <td class="text-center text-success">125.86%</td>
                                <td class="text-center fw-bold text-dark fs-14">2.188 <small class="fs-10 text-muted">/ 2.383</small></td>
                                <td class="text-center fw-bold text-success fs-14">91.82%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php elseif ($selectedCode === 'PK.1' && $selectedYear === 2026): ?>
                <!-- TABEL KOMPREHENSIF PK.1 (4 PARAMETER KUNCI EVALUASI MONITORING SFR) -->
                <div class="table-responsive">
                    <table class="pk-table-clean">
                        <thead>
                            <tr>
                                <th rowspan="2" class="text-center align-middle" style="width: 40px; border-right: 1px solid #bae6fd;">No</th>
                                <th rowspan="2" class="align-middle" style="width: 95px; border-right: 1px solid #bae6fd;">Bulan</th>
                                <th colspan="3" class="text-center" style="border-right: 1px solid #bae6fd; background: #e0f2fe; color: #0369a1;">Param 1: Wilayah Observasi</th>
                                <th colspan="3" class="text-center" style="border-right: 1px solid #bae6fd; background: #dcfce7; color: #166534;">Param 2: ISR Termonitor</th>
                                <th colspan="2" class="text-center" style="border-right: 1px solid #bae6fd; background: #fef3c7; color: #92400e;">Param 3: Pengguna</th>
                                <th colspan="2" class="text-center" style="border-right: 1px solid #bae6fd; background: #ede9fe; color: #5b21b6;">Param 4: Sanksi</th>
                                <th rowspan="2" class="text-center align-middle" style="width: 95px; border-right: 1px solid #bae6fd; background: #eff6ff; color: #1d4ed8;">Capaian PK (%)</th>
                                <th rowspan="2" class="align-middle" style="min-width: 220px;">Rincian Daerah Terobservasi</th>
                            </tr>
                            <tr>
                                <!-- Param 1: Kab/Kota -->
                                <th class="text-center" style="font-size: 10px; width: 55px;">Target</th>
                                <th class="text-center" style="font-size: 10px; width: 55px;">Realisasi</th>
                                <th class="text-center" style="font-size: 10px; width: 60px; border-right: 1px solid #bae6fd;">% PK</th>
                                <!-- Param 2: ISR -->
                                <th class="text-center" style="font-size: 10px; width: 60px;">Target</th>
                                <th class="text-center" style="font-size: 10px; width: 65px;">Realisasi</th>
                                <th class="text-center" style="font-size: 10px; width: 65px; border-right: 1px solid #bae6fd;">% Capaian</th>
                                <!-- Param 3: Pengguna -->
                                <th class="text-center" style="font-size: 10px; width: 65px;">Terdata</th>
                                <th class="text-center" style="font-size: 10px; width: 65px; border-right: 1px solid #bae6fd;">Identifikasi</th>
                                <!-- Param 4: Sanksi -->
                                <th class="text-center" style="font-size: 10px; width: 50px;">Target</th>
                                <th class="text-center" style="font-size: 10px; width: 55px; border-right: 1px solid #bae6fd;">Realisasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($mNum = 1; $mNum <= 12; $mNum++): ?>
                                <?php 
                                    $r = $pk1MonthlyDetails[$mNum] ?? null;
                                    if (!$r) continue;
                                    $isRowActive = ($selectedMonth === $mNum);
                                    $isPending = ($r['isr'] === null);
                                    $pIsrPct = $isPending ? null : round(($r['isr'] / 1312) * 100, 1);
                                ?>
                                <tr data-table-month="<?= $mNum ?>" class="<?= $isRowActive ? 'row-active' : '' ?>">
                                    <td class="text-center text-muted fw-bold"><?= $mNum ?></td>
                                    <td class="fw-bold">
                                        <a href="pk_view.php?code=PK.1&year=<?= $selectedYear ?>&month=<?= $mNum ?>" onclick="selectMonthRealtime(<?= $mNum ?>); return false;" class="text-decoration-none <?= $isRowActive ? 'text-primary' : 'text-dark' ?>">
                                            <?= htmlspecialchars($monthNames[$mNum]) ?>
                                            <span class="row-active-dot badge bg-primary rounded-circle p-1 ms-1" style="width: 5px; height: 5px; display: <?= $isRowActive ? 'inline-block' : 'none' ?>;"></span>
                                        </a>
                                    </td>
                                    <!-- Param 1: Wilayah / Kab.Kota -->
                                    <td class="text-center text-muted">8</td>
                                    <td class="text-center fw-semibold text-dark"><?= $isPending ? '-' : $r['kab_count'] ?></td>
                                    <td class="text-center fw-bold <?= (!$isPending && $r['capaian_pct'] >= 80) ? 'text-success' : 'text-primary' ?>">
                                        <?= $isPending ? '-' : number_format($r['capaian_pct'], 1) . '%' ?>
                                    </td>
                                    <!-- Param 2: ISR Termonitor -->
                                    <td class="text-center text-muted">1.312</td>
                                    <td class="text-center fw-semibold text-dark"><?= $isPending ? '-' : number_format($r['isr'], 0, ',', '.') ?></td>
                                    <td class="text-center fw-bold <?= (!$isPending && $pIsrPct >= 100) ? 'text-success' : 'text-primary' ?>">
                                        <?= $isPending ? '-' : number_format($pIsrPct, 1) . '%' ?>
                                    </td>
                                    <!-- Param 3: Pengguna -->
                                    <td class="text-center fw-semibold text-dark"><?= $isPending ? '-' : number_format($r['users'], 0, ',', '.') ?></td>
                                    <td class="text-center fw-bold <?= $isPending ? 'text-muted' : 'text-success' ?>"><?= $isPending ? '-' : '100%' ?></td>
                                    <!-- Param 4: Sanksi -->
                                    <td class="text-center text-muted">0</td>
                                    <td class="text-center fw-bold text-success"><?= $isPending ? '-' : ($r['sanksi'] ?? 0) ?></td>
                                    <!-- Capaian Kumulatif PK -->
                                    <td class="text-center fw-bold <?= (!$isPending && $r['capaian_pct'] >= 80) ? 'text-success' : 'text-primary' ?> fs-13">
                                        <?= $isPending ? '-' : number_format($r['capaian_pct'], 1) . '%' ?>
                                    </td>
                                    <!-- Rincian Daerah -->
                                    <td class="fs-11 <?= $isPending ? 'text-muted fst-italic' : 'text-dark' ?>">
                                        <?= $isPending ? 'Menunggu pelaksanaan observasi bulan berjalan' : htmlspecialchars($r['kab_list']) ?>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-uppercase text-dark fw-bold">TOTAL / REALISASI S.D. SEPTEMBER:</td>
                                <!-- Param 1 -->
                                <td class="text-center text-muted">8</td>
                                <td class="text-center fw-bold text-primary">9</td>
                                <td class="text-center fw-bold text-success">90.0%</td>
                                <!-- Param 2: ISR -->
                                <td class="text-center text-muted">1.312</td>
                                <td class="text-center fw-bold text-success">2.015</td>
                                <td class="text-center fw-bold text-success">153.6%</td>
                                <!-- Param 3: Pengguna -->
                                <td class="text-center fw-bold text-dark">5.080</td>
                                <td class="text-center fw-bold text-success">100%</td>
                                <!-- Param 4: Sanksi -->
                                <td class="text-center text-muted">0</td>
                                <td class="text-center fw-bold text-success">0</td>
                                <!-- Capaian PK -->
                                <td class="text-center fw-bold text-success fs-14">90.0%</td>
                                <!-- Rincian -->
                                <td class="fs-11 text-muted fw-semibold">
                                    9 dari 10 Wilayah Kerja Terobservasi (Kaltim)
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php elseif ($selectedCode === 'PK.6' && $selectedYear === 2026): ?>
                <!-- ============================================================ -->
                <!-- TABEL KHUSUS PK.6: CAPAIAN SLA SMFR, PEMANFAATAN SPT & BMN 2026 -->
                <!-- ============================================================ -->
                
                <!-- Nav Tab Pemisah -->
                <ul class="nav nav-pills mb-3 gap-2" id="pk6Tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-3 py-1.5 fs-12 fw-bold" id="tab-sla-tab" data-bs-toggle="pill" data-bs-target="#tab-sla" type="button" role="tab" aria-selected="true">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">speed</i>
                            1. Rekapitulasi SLA Stasiun Bulanan (Jan - Mei 2026)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1.5 fs-12 fw-bold" id="tab-spt-tab" data-bs-toggle="pill" data-bs-target="#tab-spt" type="button" role="tab" aria-selected="false">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">assignment</i>
                            2. Pemanfaatan Perangkat Berdasarkan SPT Resmi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1.5 fs-12 fw-bold" id="tab-bmn-tab" data-bs-toggle="pill" data-bs-target="#tab-bmn" type="button" role="tab" aria-selected="false">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">inventory</i>
                            3. Kondisi 148 Unit Inventaris BMN SMFR (95.95% Baik)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="pk6TabContent">
                    <!-- ========================================== -->
                    <!-- TAB 1: REKAPITULASI SLA STASIUN BULANAN   -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade show active" id="tab-sla" role="tabpanel" aria-labelledby="tab-sla-tab">
                        <div class="table-responsive">
                            <table class="pk-table-clean">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="text-center align-middle" style="width: 40px; border-right: 1px solid #bae6fd;">No</th>
                                        <th rowspan="2" class="align-middle" style="width: 100px; border-right: 1px solid #bae6fd;">Bulan</th>
                                        <th rowspan="2" class="text-center align-middle" style="width: 75px; border-right: 1px solid #bae6fd;">Target (%)</th>
                                        <th colspan="5" class="text-center" style="border-right: 1px solid #bae6fd; background: #e0f2fe; color: #0369a1;">
                                            SLA Stasiun Tetap & Transportable
                                        </th>
                                        <th rowspan="2" class="text-center align-middle" style="width: 95px; border-right: 1px solid #bae6fd; background: #fef3c7; color: #92400e;">
                                            Stasiun Bergerak<br><small class="fw-normal">Mobil DF</small>
                                        </th>
                                        <th rowspan="2" class="text-center align-middle" style="width: 95px; border-right: 1px solid #bae6fd; background: #dcfce7; color: #166534;">
                                            SLA Gabungan<br><small class="fw-normal">Capaian UPT</small>
                                        </th>
                                        <th rowspan="2" class="text-center align-middle" style="width: 75px; border-right: 1px solid #bae6fd;">Kualitas</th>
                                        <th rowspan="2" class="text-center align-middle" style="width: 95px; border-right: 1px solid #bae6fd;">Upload SMSN</th>
                                        <th rowspan="2" class="align-middle" style="min-width: 200px;">Dokumen Laporan Resmi</th>
                                        <th rowspan="2" class="text-center align-middle" style="width: 85px;">Status</th>
                                    </tr>
                                    <tr>
                                        <!-- Sub-kolom Stasiun Tetap & Transportable -->
                                        <th class="text-center" style="font-size: 10px; width: 80px;" title="Stasiun Tetap VUHF Samarinda">VUHF Smd</th>
                                        <th class="text-center" style="font-size: 10px; width: 80px;" title="Transportable Balikpapan">Balikpapan</th>
                                        <th class="text-center" style="font-size: 10px; width: 80px;" title="Transportable Berau">Berau</th>
                                        <th class="text-center" style="font-size: 10px; width: 80px;" title="Transportable Kutai Timur">Kutai Timur</th>
                                        <th class="text-center" style="font-size: 10px; width: 85px; border-right: 1px solid #bae6fd; background: #bae6fd; color: #0369a1;">Rata-rata</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pk6OfficialTable as $mNum => $rowSla): ?>
                                        <?php 
                                            $isRealized = in_array($mNum, [1, 2, 3, 4, 5]);
                                            $isRowSelected = ($selectedMonth === $mNum);
                                        ?>
                                        <tr data-table-month="<?= $mNum ?>" class="<?= $isRowSelected ? 'row-active' : '' ?>">
                                            <td class="text-center fw-bold text-muted"><?= $mNum ?></td>
                                            <td class="fw-bold text-dark">
                                                <a href="pk_view.php?code=PK.6&year=2026&month=<?= $mNum ?>" onclick="selectMonthRealtime(<?= $mNum ?>); return false;" class="text-decoration-none <?= $isRowSelected ? 'text-primary' : 'text-dark' ?>">
                                                    <?= $rowSla['month_name'] ?>
                                                    <span class="row-active-dot badge bg-primary rounded-circle p-1 ms-1" style="width: 6px; height: 6px; display: <?= $isRowSelected ? 'inline-block' : 'none' ?>;"></span>
                                                </a>
                                            </td>
                                            <td class="text-center text-muted fw-semibold fs-11">
                                                <?= number_format($rowSla['target_pct'], 0) ?>%
                                            </td>
                                            
                                            <!-- VUHF Samarinda (80%) -->
                                            <td class="text-center fs-11">
                                                <?php if ($isRealized): ?>
                                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-1.5 py-0.5 rounded" title="3 Utama Baik, 1 Pendukung Baik, R&S DDF 255 Rusak">
                                                        <?= number_format($rowSla['vuhf_tetap_pct'], 1) ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Balikpapan (100%) -->
                                            <td class="text-center fs-11">
                                                <?php if ($isRealized): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-1.5 py-0.5 rounded">
                                                        <?= number_format($rowSla['bpp_transp_pct'], 0) ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Berau (100% / 75%) -->
                                            <td class="text-center fs-11">
                                                <?php if ($isRealized): ?>
                                                    <span class="badge <?= $rowSla['berau_transp_pct'] < 100 ? 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25' : 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' ?> px-1.5 py-0.5 rounded" title="<?= $rowSla['berau_transp_pct'] < 100 ? 'FMU 306 Rusak per 25 Mei' : 'Prima' ?>">
                                                        <?= number_format($rowSla['berau_transp_pct'], 1) ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Kutai Timur (75% / 100%) -->
                                            <td class="text-center fs-11">
                                                <?php if ($isRealized): ?>
                                                    <span class="badge <?= $rowSla['kutim_transp_pct'] < 100 ? 'bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25' : 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' ?> px-1.5 py-0.5 rounded" title="<?= $rowSla['kutim_transp_pct'] < 100 ? 'Kendala Perangkat Utama Jan' : 'Prima' ?>">
                                                        <?= number_format($rowSla['kutim_transp_pct'], 1) ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Rata-rata Tetap & Transp -->
                                            <td class="text-center fw-bold text-primary fs-11" style="background: #f0f9ff;">
                                                <?= $isRealized ? number_format($rowSla['avg_tetap_transp'], 2) . '%' : '-' ?>
                                            </td>

                                            <!-- Mobile DF (100% / 90%) -->
                                            <td class="text-center fs-11">
                                                <?php if ($isRealized): ?>
                                                    <span class="badge <?= $rowSla['mobile_df_pct'] < 100 ? 'bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25' : 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' ?> px-1.5 py-0.5 rounded" title="<?= $rowSla['mobile_df_pct'] < 100 ? 'Front Panel ESMD Rusak' : 'Prima' ?>">
                                                        <?= number_format($rowSla['mobile_df_pct'], 1) ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- SLA Gabungan UPT -->
                                            <td class="text-center fs-12 fw-bold" style="background: #f0fdf4;">
                                                <?php if ($isRealized): ?>
                                                    <span class="text-success fs-13">
                                                        <?= number_format($rowSla['sla_gabungan'], 2) ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Kualitas -->
                                            <td class="text-center fs-11 fw-semibold text-muted">
                                                <?= $isRealized ? number_format($rowSla['quality_pct'], 0) . '%' : '-' ?>
                                            </td>

                                            <!-- Upload SMSN -->
                                            <td class="text-center fs-11">
                                                <?php if ($isRealized): ?>
                                                    <span class="badge bg-light text-dark border px-2 py-0.5 rounded-pill" title="<?= htmlspecialchars($rowSla['okupansi_dates']) ?>">
                                                        <i class="fa fa-cloud-arrow-up text-success me-1"></i><?= $rowSla['okupansi_count'] ?>x
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Dokumen Laporan -->
                                            <td>
                                                <?php if ($isRealized): ?>
                                                    <div class="fw-bold text-dark fs-11 text-truncate" style="max-width: 240px;" title="<?= htmlspecialchars($rowSla['nd_number']) ?>">
                                                        <i class="fa fa-file-pdf text-danger me-1"></i>ND: <?= htmlspecialchars($rowSla['nd_number']) ?>
                                                    </div>
                                                    <small class="text-muted fs-10">Tgl: <?= htmlspecialchars($rowSla['nd_date']) ?> • <?= htmlspecialchars($rowSla['signer']) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted fs-11 fst-italic">Laporan belum periode berjalan</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Status -->
                                            <td class="text-center">
                                                <?php if ($isRealized): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                                        <i class="fa fa-check me-1"></i>Tercapai
                                                    </span>
                                                <?php elseif ($mNum === 10): ?>
                                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                                        <i class="fa fa-clock me-1"></i>Proses
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border fs-10 px-2 py-0.5 rounded-pill">
                                                        Rencana
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold" style="background: #f8fafc; border-top: 2px solid #cbd5e1;">
                                        <td colspan="3" class="text-uppercase text-dark fs-11">RATA-RATA REALISASI s.d. MEI 2026:</td>
                                        <td class="text-center text-dark fs-11">80.00%</td>
                                        <td class="text-center text-success fs-11">100.00%</td>
                                        <td class="text-center text-primary fs-11">95.00%</td>
                                        <td class="text-center text-primary fs-11">95.00%</td>
                                        <td class="text-center fw-bold text-primary fs-12" style="background: #e0f2fe;">92.50%</td>
                                        <td class="text-center fw-bold text-dark fs-12">92.00%</td>
                                        <td class="text-center fw-bold text-success fs-14" style="background: #dcfce7;">92.25%</td>
                                        <td class="text-center fw-bold text-success fs-11">100%</td>
                                        <td class="text-center fw-bold text-primary fs-11">22x Upload</td>
                                        <td colspan="2" class="fs-11 text-success fw-semibold">
                                            <i class="fa fa-check-double me-1"></i>Target Tahunan 86% Berhasil Terlampaui (+6.25%)
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 2: PEMANFAATAN PERANGKAT SESUAI SPT    -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade" id="tab-spt" role="tabpanel" aria-labelledby="tab-spt-tab">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle fs-12 mb-0">
                                <thead class="bg-light text-dark">
                                    <tr>
                                        <th class="text-center" style="width: 45px;">No</th>
                                        <th style="min-width: 170px;">Nomor SPT & Tanggal</th>
                                        <th style="width: 90px;">Periode</th>
                                        <th style="width: 130px;">Kelas & Lokasi</th>
                                        <th style="min-width: 170px;">Perangkat SMFR</th>
                                        <th>Rincian Kegiatan</th>
                                        <th class="text-center" style="width: 80px;">Durasi</th>
                                        <th style="min-width: 220px;">Output Kegiatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pk6SptUtilization as $idxSpt => $sptRow): ?>
                                        <tr>
                                            <td class="text-center fw-bold text-muted"><?= $idxSpt + 1 ?></td>
                                            <td>
                                                <div class="fw-bold text-dark fs-11">
                                                    <?= htmlspecialchars($sptRow['spt']) ?>
                                                </div>
                                                <small class="text-muted fs-10"><?= htmlspecialchars($sptRow['dates']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-10">
                                                    <?= htmlspecialchars($sptRow['period']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark fs-11"><?= htmlspecialchars($sptRow['station']) ?></div>
                                                <small class="text-muted fs-10"><?= htmlspecialchars($sptRow['location']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border fs-10 font-monospace text-wrap text-start">
                                                    <?= htmlspecialchars($sptRow['equipment']) ?>
                                                </span>
                                            </td>
                                            <td class="fs-11 text-dark">
                                                <?= htmlspecialchars($sptRow['activity']) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-11 fw-bold">
                                                    <?= htmlspecialchars($sptRow['duration']) ?>
                                                </span>
                                            </td>
                                            <td class="fs-11 text-muted">
                                                <?= htmlspecialchars($sptRow['output']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 3: KONDISI 148 BMN SMFR                -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade" id="tab-bmn" role="tabpanel" aria-labelledby="tab-bmn-tab">
                        <!-- Stat Box BMN SMFR -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="p-3 rounded-3 bg-light border text-center">
                                    <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Total Unit BMN</span>
                                    <h3 class="fw-bold text-dark mb-0">148 <small class="fs-12 text-muted">Unit</small></h3>
                                    <span class="fs-11 text-muted">Seluruh Aset SMFR</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 rounded-3 bg-light border text-center">
                                    <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Perangkat Kondisi Baik</span>
                                    <h3 class="fw-bold text-success mb-0">142 <small class="fs-12 text-muted">Unit</small></h3>
                                    <span class="badge bg-success text-white fs-10 rounded-pill">95.95% Berfungsi</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 rounded-3 bg-light border text-center">
                                    <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Perangkat Kondisi Rusak</span>
                                    <h3 class="fw-bold text-danger mb-0">6 <small class="fs-12 text-muted">Unit</small></h3>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 fs-10 rounded-pill">4.05% Teridentifikasi</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 rounded-3 bg-light border text-center">
                                    <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Kelompok Aset</span>
                                    <h3 class="fw-bold text-primary mb-0">3 <small class="fs-12 text-muted">Kategori</small></h3>
                                    <span class="fs-11 text-muted">Utama, Pendukung & Komunikasi</span>
                                </div>
                            </div>
                        </div>

                        <!-- Tabel Sebaran Kondisi per Stasiun / Site -->
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle fs-12 mb-0">
                                <thead class="bg-light text-dark">
                                    <tr>
                                        <th class="text-center" style="width: 45px;">No</th>
                                        <th>Nama Stasiun / Site Monitoring</th>
                                        <th>Lokasi Penempatan</th>
                                        <th class="text-center" style="width: 100px;">Total Unit</th>
                                        <th class="text-center text-success" style="width: 100px;">Kondisi Baik</th>
                                        <th class="text-center text-danger" style="width: 100px;">Kondisi Rusak</th>
                                        <th>Catatan & Nomor Surat Pelaporan Kerusakan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $noStn = 1; foreach ($pk6InventoryStats['stasiun'] as $stnName => $stnInfo): ?>
                                        <tr>
                                            <td class="text-center fw-bold text-muted"><?= $noStn++ ?></td>
                                            <td class="fw-bold text-dark">
                                                <i class="material-symbols-outlined fs-15 text-primary align-middle me-1">cell_tower</i>
                                                <?= htmlspecialchars($stnName) ?>
                                            </td>
                                            <td class="text-muted"><?= htmlspecialchars($stnInfo['lokasi']) ?></td>
                                            <td class="text-center fw-bold text-dark"><?= $stnInfo['total'] ?> Unit</td>
                                            <td class="text-center fw-bold text-success"><?= $stnInfo['baik'] ?> Unit</td>
                                            <td class="text-center fw-bold <?= $stnInfo['rusak'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                                <?= $stnInfo['rusak'] > 0 ? $stnInfo['rusak'] . ' Unit' : '-' ?>
                                            </td>
                                            <td class="fs-11">
                                                <?php if ($stnInfo['rusak'] > 0): ?>
                                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-2 py-0.5 rounded">
                                                        <i class="fa fa-triangle-exclamation text-warning me-1"></i><?= htmlspecialchars($stnInfo['catatan']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-success"><i class="fa fa-circle-check me-1"></i><?= htmlspecialchars($stnInfo['catatan']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($selectedCode === 'PK.3' && $selectedYear === 2026): ?>
                <!-- ============================================================ -->
                <!-- TABEL & RINCIAN KHUSUS PK.3: PENANGANAN GANGGUAN FREKUENSI RADIO 2026 -->
                <!-- Berdasarkan 2 Laporan Penanganan Gangguan Resmi & BAP Balmon Samarinda -->
                <!-- Sesuai Trouble Ticket laporgangguansfr.postel.go.id & SOP Ditjen SDPPI -->
                <!-- ============================================================ -->
                
                <!-- Nav Tab Pemisah -->
                <ul class="nav nav-pills mb-3 gap-2" id="pk3Tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-3 py-1.5 fs-12 fw-bold" id="tab-pk3-rekap-tab" data-bs-toggle="pill" data-bs-target="#tab-pk3-rekap" type="button" role="tab" aria-selected="true">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">calendar_month</i>
                            1. Rekapitulasi 12 Bulan Riil & Status Penanganan Gangguan
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1.5 fs-12 fw-bold" id="tab-pk3-kasus-tab" data-bs-toggle="pill" data-bs-target="#tab-pk3-kasus" type="button" role="tab" aria-selected="false">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">troubleshoot</i>
                            2. Komparasi Teknis 2 Kasus Aduan Riil (PT. Kideco & PT. MHU)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1.5 fs-12 fw-bold" id="tab-pk3-sop-tab" data-bs-toggle="pill" data-bs-target="#tab-pk3-sop" type="button" role="tab" aria-selected="false">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">verified_user</i>
                            3. SOP Penanganan Gangguan, Waktu Respon & Regulasi SDPPI
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="pk3TabContent">
                    <!-- ========================================== -->
                    <!-- TAB 1: REKAPITULASI 12 BULAN RIIL          -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade show active" id="tab-pk3-rekap" role="tabpanel" aria-labelledby="tab-pk3-rekap-tab">
                        
                        <!-- BANNER SUMMARY CAPAIAN -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-3 col-6">
                                <div class="p-2.5 rounded-3 border bg-light">
                                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-0.5">Target Kinerja</div>
                                    <div class="fs-14 fw-bold text-dark">100% Selesai</div>
                                    <small class="text-muted fs-10">Penyelesaian aduan masuk</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-2.5 rounded-3 border bg-light">
                                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-0.5">Aduan Masuk (Riil)</div>
                                    <div class="fs-14 fw-bold text-danger">2 Pengaduan Resmi</div>
                                    <small class="text-muted fs-10">April (Kideco) & Ags (MHU)</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-2.5 rounded-3 border bg-light">
                                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-0.5">Aduan Selesai Tuntas</div>
                                    <div class="fs-14 fw-bold text-success">2 Kasus (100% Clean)</div>
                                    <small class="text-muted fs-10">BAP T-67, 68, 228, 229</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-2.5 rounded-3 border bg-light">
                                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-0.5">Bulan Tanpa Kegiatan</div>
                                    <div class="fs-14 fw-bold text-primary">10 Bulan Kondusif</div>
                                    <small class="text-muted fs-10">Nihil aduan / spektrum tertib</small>
                                </div>
                            </div>
                        </div>

                        <!-- TABEL REKAPITULASI 12 BULAN PERSIS KONDISI SESUNGGUHNYA -->
                        <div class="card border rounded-3 mb-4 overflow-hidden" style="border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                            <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="background: #ffffff;">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1 fs-13 d-flex align-items-center">
                                        <i class="material-symbols-outlined text-warning fs-18 me-1.5">table_view</i>
                                        Tabel Rekapitulasi Bulanan Penanganan Gangguan Frekuensi Radio Tahun 2026
                                    </h6>
                                    <span class="fs-11 text-muted">
                                        Disesuaikan dengan kondisi sesungguhnya: 2 kegiatan penanganan pada April & Agustus, serta 10 bulan dengan keterangan spektrum kondusif.
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill">
                                        <i class="fa fa-circle-check me-1"></i>Realisasi Kumulatif: 100.0%
                                    </span>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-sm align-middle fs-12 mb-0">
                                    <thead style="background: #f8fafc; font-size: 11px;">
                                        <tr class="text-dark">
                                            <th class="text-center" style="width: 45px;">No</th>
                                            <th style="min-width: 100px;">Bulan</th>
                                            <th style="min-width: 250px;">Uraian Kegiatan Penanganan Gangguan</th>
                                            <th style="min-width: 280px;">Rincian Lokasi & Keterangan Kondisi</th>
                                            <th class="text-center" style="min-width: 80px;">Target</th>
                                            <th class="text-center" style="min-width: 80px;">Realisasi</th>
                                            <th class="text-center" style="min-width: 90px;">Capaian (%)</th>
                                            <th class="text-center" style="min-width: 130px;">Status Pelaksanaan</th>
                                            <th style="min-width: 180px;">Dasar Dokumen / BAP</th>
                                            <th class="text-center" style="min-width: 100px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $totTargetAduan = 0;
                                        $totRealAduan = 0;
                                        foreach ($pk3OfficialTable as $mNum => $mRow): 
                                            $isActionMonth = ($mRow['has_case'] === true);
                                            if ($isActionMonth) {
                                                $totTargetAduan += 1;
                                                $totRealAduan += 1;
                                            }
                                        ?>
                                            <tr style="<?= $isActionMonth ? 'background: #f0fdf4;' : '' ?>">
                                                <td class="text-center text-muted fw-semibold"><?= $mNum ?></td>
                                                <td>
                                                    <span class="fw-bold <?= $isActionMonth ? 'text-primary' : 'text-dark' ?>">
                                                        <?= htmlspecialchars($mRow['month_name']) ?>
                                                    </span>
                                                    <?php if ($isActionMonth): ?>
                                                        <span class="badge bg-danger text-white fs-9 px-1.5 py-0.5 rounded-pill d-block mt-0.5" style="width: fit-content;">Ada Kasus</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($isActionMonth): ?>
                                                        <div class="fw-bold text-dark fs-12 mb-0.5">
                                                            <i class="fa fa-radio text-danger me-1"></i><?= htmlspecialchars($mRow['kegiatan']) ?>
                                                        </div>
                                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-10 px-2 py-0.2 rounded-pill font-monospace">
                                                            Kasus #0<?= $mRow['case_id'] ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <div class="text-secondary fw-semibold fs-11">
                                                            <i class="fa fa-shield-halved text-success me-1"></i><?= htmlspecialchars($mRow['kegiatan']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fs-11">
                                                    <?php if ($isActionMonth): ?>
                                                        <div class="text-dark" style="line-height: 1.4;">
                                                            <?= htmlspecialchars($mRow['ket']) ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted" style="line-height: 1.35;">
                                                            <?= htmlspecialchars($mRow['ket']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center font-monospace <?= $isActionMonth ? 'fw-bold text-dark' : 'text-muted' ?>">
                                                    <?= htmlspecialchars($mRow['target_val']) ?>
                                                </td>
                                                <td class="text-center font-monospace <?= $isActionMonth ? 'fw-bold text-success' : 'text-muted' ?>">
                                                    <?= htmlspecialchars($mRow['realization_val']) ?>
                                                </td>
                                                <td class="text-center font-monospace fw-bold text-success">
                                                    <?= number_format($mRow['cum_pct'], 1) ?>%
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($isActionMonth): ?>
                                                        <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-30 px-2 py-1 rounded-pill fs-10 fw-semibold">
                                                            <i class="fa fa-circle-check me-1"></i>Selesai Tuntas (Clean 100%)
                                                        </span>
                                                    <?php elseif ($mNum <= 10): ?>
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1 rounded-pill fs-10">
                                                            <i class="fa fa-circle-check text-success me-1"></i>Nihil Aduan (Kondusif)
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-muted border px-2 py-1 rounded-pill fs-10">
                                                            <i class="fa fa-clock me-1"></i>Siaga Monitoring
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fs-11">
                                                    <?php if ($isActionMonth): ?>
                                                        <span class="font-monospace text-primary fw-semibold d-block fs-10">
                                                            <?= htmlspecialchars($mRow['doc_number']) ?>
                                                        </span>
                                                        <small class="text-muted fs-10"><?= htmlspecialchars($mRow['doc_date']) ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted fs-11">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($isActionMonth): ?>
                                                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-2.5 py-0.5 fs-11 fw-semibold shadow-sm" onclick="showKasusDetail(<?= $mRow['case_id'] ?>)">
                                                            <i class="fa fa-eye me-1"></i>Detail Kasus
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted fs-11">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot style="background: #f8fafc; font-weight: bold; font-size: 11px;">
                                        <tr>
                                            <td colspan="4" class="text-end text-dark py-2">
                                                TOTAL REKAPITULASI TAHUN 2026 (PK.3 PENANGANAN GANGGUAN):
                                            </td>
                                            <td class="text-center font-monospace text-dark py-2"><?= $totTargetAduan ?> Aduan</td>
                                            <td class="text-center font-monospace text-success py-2"><?= $totRealAduan ?> Selesai</td>
                                            <td class="text-center font-monospace text-success py-2">100.0%</td>
                                            <td class="text-center py-2">
                                                <span class="badge bg-success text-white px-2 py-0.5 rounded-pill fs-10">
                                                    TERCAPAI PENUH (100%)
                                                </span>
                                            </td>
                                            <td colspan="2" class="text-muted fs-10 py-2">
                                                Formula: [2 Selesai ÷ 2 Masuk] × 100% = 100.0%
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 2: KOMPARASI TEKNIS 2 KASUS ADUAN RIIL -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade" id="tab-pk3-kasus" role="tabpanel" aria-labelledby="tab-pk3-kasus-tab">
                        <div class="card border rounded-3 mb-4 overflow-hidden" style="border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                            <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="background: #eff6ff;">
                                <div>
                                    <h6 class="fw-bold text-primary mb-1 fs-13 d-flex align-items-center">
                                        <i class="material-symbols-outlined text-primary fs-18 me-1.5">compare_arrows</i>
                                        Komparasi Teknis 2 Kasus Aduan Gangguan Spektrum Frekuensi Radio Resmi Balmon Samarinda 2026
                                    </h6>
                                    <span class="fs-11 text-muted">
                                        Matriks komparasi data surat pengaduan, investigasi Direction Finding (DF), temuan sumber interferensi, tindakan lokalisir, dan nomor BAP.
                                    </span>
                                </div>
                                <span class="badge bg-primary text-white fs-11 px-2.5 py-1 rounded-pill">
                                    <i class="fa fa-shield-halved me-1"></i>2 Objek Kasus Terlindungi
                                </span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm align-middle fs-12 mb-0">
                                    <thead style="background: #f8fafc; font-size: 11px;">
                                        <tr class="text-dark">
                                            <th style="width: 220px;" class="bg-light">Parameter Penanganan</th>
                                            <th style="min-width: 320px;" class="text-primary fw-bold">
                                                <i class="fa fa-building me-1"></i>KASUS #01: PT. KIDECO JAYA AGUNG
                                            </th>
                                            <th style="min-width: 320px;" class="text-primary fw-bold">
                                                <i class="fa fa-building me-1"></i>KASUS #02: PT. MULTI HARAPAN UTAMA (MHU)
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="fw-semibold text-muted bg-light">Bulan Pelaksanaan</td>
                                            <td><span class="badge bg-primary bg-opacity-10 text-primary px-2 py-0.5 rounded">April 2026</span> (Pelaksanaan: 07 s.d. 11 April 2026)</td>
                                            <td><span class="badge bg-primary bg-opacity-10 text-primary px-2 py-0.5 rounded">Agustus 2026</span> (Pelaksanaan: 10 s.d. 13 Agustus 2026)</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-muted bg-light">Surat Pengaduan Pemohon</td>
                                            <td>
                                                <strong class="font-monospace text-dark">145/180/C/III/2026-01</strong>
                                                <div class="text-muted fs-10">Tanggal: 13 Maret 2026</div>
                                            </td>
                                            <td>
                                                <strong class="font-monospace text-dark">0458/OL/MHU-KTT/VII/2026</strong>
                                                <div class="text-muted fs-10">Tanggal: 27 Juli 2026</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-muted bg-light">Surat Perintah Tugas (SPT)</td>
                                            <td>
                                                <span class="font-monospace text-primary fw-semibold">46/BALMON.64/KP.01.06/04/2026</span>
                                                <div class="text-muted fs-10">Tgl Terbit: 14 April 2026 (Durasi: 5 Hari)</div>
                                            </td>
                                            <td>
                                                <span class="font-monospace text-primary fw-semibold">113/BALMON.64/KP.01.06/08/2026</span>
                                                <div class="text-muted fs-10">Tgl Terbit: 04 Agustus 2026 (Durasi: 4 Hari)</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-muted bg-light">Wilayah Kerja / Lokasi</td>
                                            <td>Kabupaten Paser (Kec. Batu Sopang, Site SIMS, Desa Songka)</td>
                                            <td>Kabupaten Kutai Kartanegara (Kec. Loa Kulu, Desa Loa Duri & Desa Bakungan)</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-muted bg-light">Frekuensi & Izin ISR Terganggu</td>
                                            <td>
                                                <div class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 font-monospace mb-1">
                                                    173.675 MHz & 173.250 MHz
                                                </div>
                                                <div class="text-muted fs-10 font-monospace">ISR: 03037474 & 03037508-000SU/2620252030</div>
                                                <small class="text-muted d-block">Radio Komunikasi Tambang Land Mobile</small>
                                            </td>
                                            <td>
                                                <div class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 font-monospace mb-1">
                                                    Tx 159.250 MHz / Rx 154.250 MHz
                                                </div>
                                                <div class="text-muted fs-10 font-monospace">ISR: 03112651-000SU/2620262031</div>
                                                <small class="text-muted d-block">Radio Repeater Komunikasi Tambang</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-muted bg-light">Sumber Gangguan / Interferensi</td>
                                            <td>
                                                <div class="p-2 rounded border bg-danger bg-opacity-10 text-danger fs-11">
                                                    <strong>Intermodulasi 173.625 MHz & 173.675 MHz</strong> serta noise induksi eksternal dari instalasi Gardu Induk PLN Batu Sopang pada 173.250 MHz.
                                                </div>
                                            </td>
                                            <td>
                                                <div class="p-2 rounded border bg-danger bg-opacity-10 text-danger fs-11">
                                                    <strong>Kebocoran Booster Penguat Sinyal TV Kabel</strong> (Surya Kabel TV Digital & TV Kabel Haji Amir) yang meradiasi Channel S08 (154.250 MHz).
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-muted bg-light">Alat Ukur / Kendaraan DF</td>
                                            <td>R&S PR100, Anritsu MS2090A, Antena HE300, HE400UWB, Laptop Argus, Garmin GPSMAP-65S</td>
                                            <td>Mobil Monitoring & DF Toyota Hilux (R&S ESMD DF Mode 20 MHz–3 GHz), R&S PR100, Antena HE300 & HE600</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-muted bg-light">Tindakan Penanganan & Hasil</td>
                                            <td>
                                                <div class="p-2 rounded border bg-success bg-opacity-10 text-dark fs-11">
                                                    Pelacakan DF pada 13 titik ukur di area tambang, rekomendasi modifikasi kanal ISR & pemasangan band-pass filter. Frekuensi pulih jernih 100%.
                                                </div>
                                            </td>
                                            <td>
                                                <div class="p-2 rounded border bg-success bg-opacity-10 text-dark fs-11">
                                                    Penerbitan Surat Teguran T-444, penataan kembali booster TV kabel, isolasi kabel bocor, penghentian interferensi. Frekuensi Rx 154.250 MHz clear 100%.
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-muted bg-light">Nomor Berita Acara (BAP)</td>
                                            <td>
                                                <strong class="font-monospace text-success">T-67/BALMON.64/SP.03.05/04/2026</strong> & <strong>T-68</strong>
                                                <div class="text-muted fs-10">Tanggal: 11 April 2026</div>
                                            </td>
                                            <td>
                                                <strong class="font-monospace text-success">T-228/BALMON.64/SP.03.03/08/2026</strong> & <strong>T-229</strong>
                                                <div class="text-muted fs-10">Tanggal: 13 Agustus 2026</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-muted bg-light">Tim Pelaksana Teknis</td>
                                            <td>
                                                <strong>Sri Sukisno, ST</strong> (Ketua Tim)<br>
                                                <small class="text-muted">Anggota: Rifani Tria Agustin, A.md.T, Rendy Tri Atmadja, ST</small>
                                            </td>
                                            <td>
                                                <strong>Andreo Yustiantoro Anjaya, ST</strong> (Ketua Tim)<br>
                                                <small class="text-muted">Anggota: Warko, S.Kom, M.Si, Sri Sukisno, ST, Tantowi Pahala H.S, S.Kom</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-muted bg-light">Status Akhir & Verifikasi</td>
                                            <td>
                                                <span class="badge bg-success text-white px-2.5 py-1 rounded-pill mb-1">
                                                    <i class="fa fa-check-double me-1"></i>Selesai Tuntas (Clean 100%)
                                                </span>
                                                <div class="text-muted fs-10">Laporan disetujui & diarsipkan sebagai pertanggungjawaban dinas.</div>
                                            </td>
                                            <td>
                                                <span class="badge bg-success text-white px-2.5 py-1 rounded-pill mb-1">
                                                    <i class="fa fa-check-double me-1"></i>Selesai Tuntas (Clean 100%)
                                                </span>
                                                <div class="text-muted fs-10">Laporan disetujui & diarsipkan sebagai pertanggungjawaban dinas.</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-muted bg-light">Aksi Berkas</td>
                                            <td>
                                                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 py-1 fs-11" onclick="showKasusDetail(1)">
                                                    <i class="fa fa-folder-open me-1"></i>Buka Lembar Verifikasi Kasus 1
                                                </button>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 py-1 fs-11" onclick="showKasusDetail(2)">
                                                    <i class="fa fa-folder-open me-1"></i>Buka Lembar Verifikasi Kasus 2
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 3: SOP PENANGANAN GANGGUAN & REGULASI  -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade" id="tab-pk3-sop" role="tabpanel" aria-labelledby="tab-pk3-sop-tab">
                        <div class="row g-3 mb-4">
                            <div class="col-lg-7">
                                <div class="card border rounded-3 p-3 h-100" style="border: 1px solid rgba(10, 22, 40, 0.08) !important;">
                                    <h6 class="fw-bold text-dark mb-2 fs-13 d-flex align-items-center">
                                        <i class="material-symbols-outlined text-warning fs-18 me-1.5">schema</i>
                                        5 Tahapan Alur Trouble Ticket Penanganan Gangguan SFR Ditjen SDPPI
                                    </h6>
                                    <div class="d-flex flex-column gap-2.5 fs-11 mt-2">
                                        <div class="p-2.5 rounded border bg-light d-flex align-items-start gap-2.5">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-11" style="width: 24px; height: 24px; flex-shrink: 0;">1</div>
                                            <div>
                                                <strong class="text-dark d-block">Penerimaan Aduan (Trouble Ticket):</strong>
                                                Laporan pengaduan diterima melalui portal <em>laporgangguansfr.postel.go.id</em> atau surat resmi dari pemegang Izin Stasiun Radio (ISR).
                                            </div>
                                        </div>
                                        <div class="p-2.5 rounded border bg-light d-flex align-items-start gap-2.5">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-11" style="width: 24px; height: 24px; flex-shrink: 0;">2</div>
                                            <div>
                                                <strong class="text-dark d-block">Verifikasi Administrasi & Validasi ISR:</strong>
                                                Pemeriksaan keabsahan ISR pemohon, masa berlaku izin, koordinat stasiun, dan parameter teknis frekuensi terganggu pada database SIMS SDPPI.
                                            </div>
                                        </div>
                                        <div class="p-2.5 rounded border bg-light d-flex align-items-start gap-2.5">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-11" style="width: 24px; height: 24px; flex-shrink: 0;">3</div>
                                            <div>
                                                <strong class="text-dark d-block">Penerbitan SPT & Investigasi Lapangan (Direction Finding):</strong>
                                                Kepala UPT menerbitkan SPT. Tim teknis diterjunkan membawa perangkat Direction Finder (R&S PR100 / Mobil DF ESMD) untuk pelacakan azimuth arah sinyal gangguan.
                                            </div>
                                        </div>
                                        <div class="p-2.5 rounded border bg-light d-flex align-items-start gap-2.5">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-11" style="width: 24px; height: 24px; flex-shrink: 0;">4</div>
                                            <div>
                                                <strong class="text-dark d-block">Lokalisir & Eliminasi Sumber Interferensi:</strong>
                                                Penentuan titik sumber interferensi, tindakan penghentian pancaran ilegal, perbaikan filter, atau penerbitan Surat Peringatan/Teguran resmi.
                                            </div>
                                        </div>
                                        <div class="p-2.5 rounded border bg-light d-flex align-items-start gap-2.5">
                                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold fs-11" style="width: 24px; height: 24px; flex-shrink: 0;">5</div>
                                            <div>
                                                <strong class="text-dark d-block">Penerbitan BAP & Penutupan Tiket (Status Clean 100%):</strong>
                                                Penandatanganan Berita Acara Pemeriksaan (BAP) bersama pemohon dan pihak sumber, verifikasi frekuensi bersih, disposisi pimpinan, dan penutupan tiket online.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-5">
                                <div class="card border rounded-3 p-3 h-100" style="border: 1px solid rgba(10, 22, 40, 0.08) !important;">
                                    <h6 class="fw-bold text-dark mb-2 fs-13 d-flex align-items-center">
                                        <i class="material-symbols-outlined text-info fs-18 me-1.5">timer</i>
                                        Standar Layanan Waktu Respon (SLA)
                                    </h6>
                                    <div class="d-flex flex-column gap-2 fs-11 mt-2">
                                        <div class="p-2.5 rounded border" style="background: #fef2f2; border-color: #fecaca !important;">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <strong class="text-danger">Dinas Keselamatan (Safety of Life)</strong>
                                                <span class="badge bg-danger text-white fs-10">Maks. 1x24 Jam</span>
                                            </div>
                                            <span class="text-muted fs-10">Meliputi frekuensi penerbangan (Aeronautical), pelayaran (Maritime SAR), dan kebencanaan. Respon darurat segera.</span>
                                        </div>

                                        <div class="p-2.5 rounded border" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <strong class="text-success">Dinas Non-Keselamatan (Land Mobile / Swasta)</strong>
                                                <span class="badge bg-success text-white fs-10">Maks. 7 Hari Kerja</span>
                                            </div>
                                            <span class="text-muted fs-10">Meliputi frekuensi pertambangan, perkebunan, penyiaran, dan telekomunikasi seluler. Balmon Samarinda merespon rata-rata &lt; 24 jam.</span>
                                        </div>

                                        <div class="p-2.5 rounded border bg-light">
                                            <strong class="text-dark d-block mb-1">Dasar Hukum Penanganan Gangguan:</strong>
                                            <ul class="mb-0 ps-3 text-muted fs-10" style="line-height: 1.5;">
                                                <li>Undang-Undang No. 36 Tahun 1999 tentang Telekomunikasi</li>
                                                <li>PP No. 46 Tahun 2021 tentang Pos, Telekomunikasi dan Penyiaran</li>
                                                <li>Permen Kominfo No. 5 Tahun 2021 tentang Penyelenggaraan Telekomunikasi</li>
                                                <li>SOP Penanganan Gangguan Spektrum Frekuensi Radio Balmon Samarinda</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($selectedCode === 'PK.7' && $selectedYear === 2026): ?>
                <!-- ============================================================ -->
                <!-- TABEL & RINCIAN KHUSUS PK.7: PENANGANAN SANKSI DENDA ADMINISTRATIF 2026 -->
                <!-- Berdasarkan 4 Surat Tagihan Denda Administratif (Tagihan Bulan ke-1) -->
                <!-- Balai Monitor Spektrum Frekuensi Radio Kelas I Samarinda -->
                <!-- ============================================================ -->
                
                <!-- Nav Tab Pemisah -->
                <ul class="nav nav-pills mb-3 gap-2" id="pk7Tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-3 py-1.5 fs-12 fw-bold" id="tab-pk7-tagihan-tab" data-bs-toggle="pill" data-bs-target="#tab-pk7-tagihan" type="button" role="tab" aria-selected="true">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">receipt_long</i>
                            1. Rekapitulasi 4 Surat Tagihan Denda (Bulan ke-1) & Progres Bulanan
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1.5 fs-12 fw-bold" id="tab-pk7-objek-tab" data-bs-toggle="pill" data-bs-target="#tab-pk7-objek" type="button" role="tab" aria-selected="false">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">warning</i>
                            2. Rincian 24 Objek Pelanggaran Spektrum Frekuensi Radio
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1.5 fs-12 fw-bold" id="tab-pk7-regulasi-tab" data-bs-toggle="pill" data-bs-target="#tab-pk7-regulasi" type="button" role="tab" aria-selected="false">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">gavel</i>
                            3. Alur Penagihan Sanksi, Formula Bobot & Regulasi PNBP
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="pk7TabContent">
                    <!-- ========================================== -->
                    <!-- TAB 1: 4 SURAT TAGIHAN DENDA & 12 BULAN    -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade show active" id="tab-pk7-tagihan" role="tabpanel" aria-labelledby="tab-pk7-tagihan-tab">
                        <!-- BAGIAN A: 4 SURAT TAGIHAN DENDA ADMINISTRATIF RESMI -->
                        <div class="card border rounded-3 mb-4 overflow-hidden" style="border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                            <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="background: #fef2f2;">
                                <div>
                                    <h6 class="fw-bold text-danger mb-1 fs-13 d-flex align-items-center">
                                        <i class="material-symbols-outlined text-danger fs-18 me-1.5">fact_check</i>
                                        Daftar 4 Surat Tagihan Denda Administratif (Tagihan Bulan Ke-1) Resmi Balmon Samarinda TA 2026
                                    </h6>
                                    <span class="fs-11 text-muted">
                                        Total Ketetapan: <strong>Rp 201.258.400</strong> untuk 24 Objek Pelanggaran SFR • Seluruhnya berstatus Tagihan Bulan ke-1 dengan Kode Billing SIMPONI aktif.
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-danger text-white fs-11 px-2.5 py-1 rounded-pill">
                                        <i class="fa fa-file-invoice-dollar me-1"></i>Total: Rp 201.258.400
                                    </span>
                                    <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill">
                                        <i class="fa fa-check me-1"></i>4 Surat Tagihan Terbit
                                    </span>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-sm align-middle fs-12 mb-0">
                                    <thead style="background: #f8fafc; font-size: 11px;">
                                        <tr class="text-dark">
                                            <th class="text-center" style="width: 40px;">No</th>
                                            <th style="min-width: 190px;">Badan Hukum / Wajib Bayar</th>
                                            <th style="min-width: 200px;">Nomor Surat Tagihan & Tanggal</th>
                                            <th style="min-width: 170px;">Dasar Teguran & BAP</th>
                                            <th style="min-width: 160px;">Kode Billing SIMPONI</th>
                                            <th class="text-center" style="width: 90px;">Objek</th>
                                            <th class="text-end" style="width: 140px;">Nilai Denda Pokok</th>
                                            <th style="width: 130px;">Jatuh Tempo</th>
                                            <th class="text-center" style="width: 90px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pk7OfficialInvoices as $idx => $inv): ?>
                                            <tr>
                                                <td class="text-center fw-bold text-muted"><?= $inv['no'] ?></td>
                                                <td>
                                                    <div class="fw-bold text-dark fs-12 d-flex align-items-center gap-1">
                                                        <i class="material-symbols-outlined fs-15 text-danger">apartment</i>
                                                        <?= htmlspecialchars($inv['badan_hukum']) ?>
                                                    </div>
                                                    <div class="fs-10 text-muted mt-0.5">
                                                        <?php if ($inv['client_id'] !== '-'): ?>
                                                            <span>Client ID: <strong class="text-dark"><?= htmlspecialchars($inv['client_id']) ?></strong> • </span>
                                                        <?php endif; ?>
                                                        <span>NIB: <?= htmlspecialchars($inv['nik_nib']) ?></span>
                                                    </div>
                                                    <div class="fs-10 text-muted text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($inv['alamat']) ?>">
                                                        <i class="fa fa-location-dot me-0.5 text-muted"></i><?= htmlspecialchars($inv['alamat']) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="d-block fw-bold text-dark fs-11 font-monospace">
                                                        <?= htmlspecialchars($inv['tagihan_number']) ?>
                                                    </span>
                                                    <div class="fs-10 text-muted mt-0.5">
                                                        <i class="fa fa-calendar-days me-1"></i>Tanggal: <strong><?= htmlspecialchars($inv['tagihan_date']) ?></strong>
                                                    </div>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 fs-10 px-1.5 py-0.5 rounded mt-0.5">
                                                        <?= htmlspecialchars($inv['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="fs-11 text-dark fw-semibold" title="<?= htmlspecialchars($inv['teguran_number']) ?>">
                                                        Teguran: <?= htmlspecialchars($inv['teguran_number']) ?>
                                                    </div>
                                                    <div class="fs-10 text-muted">Tgl: <?= htmlspecialchars($inv['teguran_date']) ?></div>
                                                    <div class="fs-10 text-muted mt-0.5" title="<?= htmlspecialchars($inv['ba_number']) ?>">
                                                        BA: <?= htmlspecialchars($inv['ba_number']) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <span class="fw-bold text-primary font-monospace fs-12"><?= htmlspecialchars($inv['kode_billing']) ?></span>
                                                        <button type="button" class="btn btn-xs btn-outline-secondary p-0.5 px-1 rounded" title="Salin Kode Billing" onclick="copyBillingCode('<?= $inv['kode_billing'] ?>')">
                                                            <i class="fa fa-copy fs-10"></i>
                                                        </button>
                                                    </div>
                                                    <div class="fs-10 text-muted mt-0.5">
                                                        <?php if ($inv['no_invoice'] !== '-'): ?>
                                                            Invoice: <strong><?= htmlspecialchars($inv['no_invoice']) ?></strong>
                                                        <?php else: ?>
                                                            Aplikasi: <strong><?= htmlspecialchars($inv['no_aplikasi']) ?></strong>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="badge bg-light text-muted border fs-9 px-1">MPN G3 / SIMPONI</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 fs-11 fw-bold px-2 py-1 rounded-pill">
                                                        <?= $inv['jumlah_objek'] ?> Objek
                                                    </span>
                                                    <div class="fs-9 text-muted mt-0.5"><?= htmlspecialchars($inv['wilayah_cakupan']) ?></div>
                                                </td>
                                                <td class="text-end">
                                                    <div class="fw-bold text-danger fs-12 font-monospace">
                                                        Rp <?= number_format($inv['denda_pokok'], 0, ',', '.') ?>
                                                    </div>
                                                    <div class="fs-10 text-muted">Denda Keterlambatan: Rp 0</div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger text-white fs-10 px-2 py-0.5 rounded-pill">
                                                        <?= htmlspecialchars($inv['jatuh_tempo']) ?>
                                                    </span>
                                                    <div class="fs-10 text-muted mt-0.5">Tenggat 30 Hari</div>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1" onclick="showInvoiceDetail(<?= $idx ?>)">
                                                        <i class="fa fa-eye me-0.5"></i> Detail
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot style="background: #f8fafc; border-top: 2px solid #e2e8f0;">
                                        <tr class="align-middle fw-bold">
                                            <td colspan="5" class="text-end text-dark pe-3">
                                                TOTAL KETETAPAN SANKSI DENDA ADMINISTRATIF TA 2026:
                                            </td>
                                            <td class="text-center text-dark">24 Objek</td>
                                            <td class="text-end text-danger fs-13 font-monospace">Rp 201.258.400</td>
                                            <td colspan="2" class="text-success fs-11">
                                                <span class="badge bg-success text-white rounded-pill px-2 py-0.5">100% Ditagihkan</span>
                                                Realisasi PK: 95.0%
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- BAGIAN B: TABEL 12 BULAN PELAKSANAAN RESMI SESUAI PK -->
                        <div class="card border rounded-3 overflow-hidden" style="border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                            <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="background: #f8fafc;">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1 fs-13 d-flex align-items-center">
                                        <i class="material-symbols-outlined text-primary fs-18 me-1.5">calendar_month</i>
                                        Progres Bulanan Pelaksanaan Pengelolaan Sanksi Denda Administratif (Januari - Desember 2026)
                                    </h6>
                                    <span class="fs-11 text-muted">
                                        Tracking target bulanan, realisasi penerbitan surat tagihan denda, akumulasi nilai ketetapan PNBP, serta persentase capaian PK.
                                    </span>
                                </div>
                                <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill">
                                    Target PK: 85.0% • Realisasi: 95.0%
                                </span>
                            </div>

                            <div class="table-responsive">
                                <table class="pk-table-clean">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width: 45px;">No</th>
                                            <th style="width: 110px;">Bulan</th>
                                            <th>Pelaksanaan & Rincian Penanganan Sanksi Denda</th>
                                            <th class="text-center" style="width: 100px;">Target</th>
                                            <th class="text-center" style="width: 110px;">Realisasi</th>
                                            <th class="text-end" style="width: 140px;">Ketetapan Denda (Rp)</th>
                                            <th class="text-center" style="width: 85px;">Capaian (%)</th>
                                            <th class="text-center" style="width: 95px;">Status</th>
                                            <th style="min-width: 210px;">Dokumen Tagihan / Laporan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pk7OfficialTable as $mNum => $r): 
                                            $isRowActive = ($selectedMonth === $mNum);
                                        ?>
                                            <tr data-table-month="<?= $mNum ?>" class="<?= $isRowActive ? 'row-active' : '' ?>">
                                                <td class="text-center fw-bold text-muted"><?= $mNum ?></td>
                                                <td class="fw-bold">
                                                    <a href="pk_view.php?code=PK.7&year=<?= $selectedYear ?>&month=<?= $mNum ?>" onclick="selectMonthRealtime(<?= $mNum ?>); return false;" class="text-decoration-none <?= $isRowActive ? 'text-primary' : 'text-dark' ?>">
                                                        <?= htmlspecialchars($r['month_name']) ?>
                                                        <span class="row-active-dot badge bg-primary rounded-circle p-1 ms-1" style="width: 5px; height: 5px; display: <?= $isRowActive ? 'inline-block' : 'none' ?>;"></span>
                                                    </a>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark fs-12"><?= htmlspecialchars($r['kegiatan']) ?></div>
                                                    <small class="text-muted fs-11"><?= htmlspecialchars($r['ket']) ?></small>
                                                </td>
                                                <td class="text-center text-muted fw-semibold fs-11"><?= htmlspecialchars($r['target_val']) ?></td>
                                                <td class="text-center fw-bold text-success fs-11"><?= htmlspecialchars($r['realization_val']) ?></td>
                                                <td class="text-end fw-bold font-monospace <?= $r['nilai_denda'] > 0 ? 'text-danger' : 'text-muted' ?> fs-11">
                                                    <?= $r['nilai_denda'] > 0 ? 'Rp ' . number_format($r['nilai_denda'], 0, ',', '.') : '-' ?>
                                                </td>
                                                <td class="text-center fw-bold <?= ($r['cum_pct'] >= 85) ? 'text-success' : (($r['cum_pct'] > 0) ? 'text-primary' : 'text-muted') ?> fs-12">
                                                    <?= number_format($r['cum_pct'], 1) ?>%
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($r['status'] === 'achieved'): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                                            <i class="fa fa-check me-0.5"></i>Selesai
                                                        </span>
                                                    <?php elseif ($r['status'] === 'in_progress'): ?>
                                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                                            <i class="fa fa-clock me-0.5"></i>Proses
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                                            Rencana
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="d-block text-dark fw-bold fs-11 text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($r['doc_number']) ?>">
                                                        <?= htmlspecialchars($r['doc_number']) ?>
                                                    </span>
                                                    <small class="text-muted fs-10"><?= htmlspecialchars($r['doc_date']) ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot style="background: #f8fafc; border-top: 2px solid #cbd5e1;">
                                        <tr class="align-middle fw-bold">
                                            <td colspan="5" class="text-end text-dark pe-3">TOTAL REKAPITULASI KINERJA PENANGANAN SANKSI TA 2026:</td>
                                            <td class="text-end text-danger fs-12 font-monospace">Rp 201.258.400</td>
                                            <td class="text-center text-success fs-13">95.0%</td>
                                            <td colspan="2" class="text-success fs-11">
                                                <span class="badge bg-success text-white rounded-pill px-2 py-0.5">TERCAPAI 95.0%</span>
                                                Melampaui Target PK 85.0%
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 2: RINCIAN 24 OBJEK PELANGGARAN SFR    -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade" id="tab-pk7-objek" role="tabpanel" aria-labelledby="tab-pk7-objek-tab">
                        <div class="p-3 mb-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1 fs-13 d-flex align-items-center">
                                        <i class="material-symbols-outlined text-warning fs-18 me-1">format_list_bulleted</i>
                                        Rincian Komprehensif 24 Objek Pelanggaran Spektrum Frekuensi Radio
                                    </h6>
                                    <span class="fs-11 text-muted">
                                        Berdasarkan Lampiran Proses Pelanggaran pada 4 Surat Tagihan Denda Administratif Balmon Samarinda TA 2026.
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning fs-11 px-2.5 py-1 rounded-pill">
                                        Tarif per Poin: Rp 100.000 • Max Poin: 7.000
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle fs-11 mb-0 border">
                                <thead style="background: #f1f5f9; font-size: 11px;">
                                    <tr class="text-dark">
                                        <th class="text-center" style="width: 35px;">No</th>
                                        <th style="min-width: 140px;">Wajib Bayar</th>
                                        <th style="min-width: 120px;">Kabupaten / Kota</th>
                                        <th style="min-width: 140px;">Layanan & Subservice</th>
                                        <th style="min-width: 150px;">Perangkat & Frekuensi</th>
                                        <th class="text-center" style="width: 50px;">Zona</th>
                                        <th style="width: 110px;">Mulai Operasi</th>
                                        <th class="text-center" style="width: 60px;">Bobot</th>
                                        <th class="text-center" style="width: 65px;">Indeks</th>
                                        <th class="text-end" style="width: 115px;">Denda Pokok (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $itemCounter = 1;
                                    foreach ($pk7OfficialInvoices as $inv): 
                                        foreach ($inv['items'] as $it):
                                    ?>
                                        <tr>
                                            <td class="text-center fw-bold text-muted"><?= $itemCounter++ ?></td>
                                            <td class="fw-bold text-dark">
                                                <?= htmlspecialchars($inv['badan_hukum']) ?>
                                                <div class="fs-9 text-muted font-monospace">Surat: <?= explode('/', $inv['tagihan_number'])[0] ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border fs-10"><?= htmlspecialchars($it['wilayah']) ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($it['sub']) ?></div>
                                                <small class="text-muted fs-9"><?= htmlspecialchars($it['layanan']) ?></small>
                                            </td>
                                            <td>
                                                <?php if (isset($it['perangkat']) && $it['perangkat'] !== '-'): ?>
                                                    <div class="fw-bold text-primary"><?= htmlspecialchars($it['perangkat']) ?></div>
                                                <?php endif; ?>
                                                <?php if (isset($it['frekuensi']) && $it['frekuensi'] !== '-'): ?>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-9">
                                                        <?= htmlspecialchars($it['frekuensi']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary fs-9">
                                                    <?= $it['unit'] ?> Unit
                                                </span>
                                            </td>
                                            <td class="text-center fw-bold"><?= $it['zona'] ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($it['tgl_operasi']) ?></td>
                                            <td class="text-center fw-bold <?= $it['bobot'] === '100%' ? 'text-danger' : 'text-primary' ?>">
                                                <?= htmlspecialchars($it['bobot']) ?>
                                            </td>
                                            <td class="text-center font-monospace"><?= htmlspecialchars($it['indeks']) ?></td>
                                            <td class="text-end fw-bold text-danger font-monospace">
                                                Rp <?= number_format($it['denda'], 0, ',', '.') ?>
                                            </td>
                                        </tr>
                                    <?php 
                                        endforeach;
                                    endforeach; 
                                    ?>
                                </tbody>
                                <tfoot style="background: #f8fafc; border-top: 2px solid #e2e8f0;">
                                    <tr class="align-middle fw-bold">
                                        <td colspan="7" class="text-end text-dark pe-3">TOTAL 24 OBJEK PELANGGARAN SFR:</td>
                                        <td colspan="2" class="text-center text-muted">Tarif: Rp 100.000 / Poin</td>
                                        <td class="text-end text-danger fs-12 font-monospace">Rp 201.258.400</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 3: PROSEDUR, FORMULA & REGULASI        -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade" id="tab-pk7-regulasi" role="tabpanel" aria-labelledby="tab-pk7-regulasi-tab">
                        <div class="row g-3">
                            <!-- Kolom Kiri: Alur Prosedur & Formula Bobot -->
                            <div class="col-lg-7">
                                <div class="card border rounded-3 p-3 h-100" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                                    <h6 class="fw-bold text-dark mb-2 fs-13 d-flex align-items-center">
                                        <i class="material-symbols-outlined text-primary fs-18 me-1.5">schema</i>
                                        Tahapan Prosedur Penanganan Sanksi Denda Administratif
                                    </h6>
                                    <p class="fs-11 text-muted mb-3">
                                        Mengacu pada Peraturan Direktur Jenderal SDPPI Nomor 3 Tahun 2024 tentang Tata Cara Pengawasan, Pembinaan, dan Pengenaan Sanksi terhadap Pelanggaran Penggunaan Spektrum Frekuensi Radio.
                                    </p>

                                    <!-- Step 1 to 5 -->
                                    <div class="d-flex flex-column gap-2 mb-3">
                                        <div class="p-2.5 rounded border bg-light d-flex align-items-start gap-2.5">
                                            <span class="badge bg-primary text-white rounded-pill px-2 py-1 fs-11">Tahap 1</span>
                                            <div>
                                                <strong class="text-dark fs-12 d-block">Pemeriksaan Lapangan, Klarifikasi & Surat Teguran</strong>
                                                <span class="fs-11 text-muted">Ditemukan penggunaan SFR tanpa izin (ISR) -> Penyusunan BAP Klarifikasi / Penghentian -> Penerbitan Surat Teguran Tertulis (Tenggat 7 Hari Kerja).</span>
                                            </div>
                                        </div>

                                        <div class="p-2.5 rounded border bg-light d-flex align-items-start gap-2.5">
                                            <span class="badge bg-danger text-white rounded-pill px-2 py-1 fs-11">Tahap 2</span>
                                            <div>
                                                <strong class="text-dark fs-12 d-block">Penerbitan Surat Tagihan Denda Bulan Ke-1 (Bobot: 1.0)</strong>
                                                <span class="fs-11 text-muted">Diterbitkan Surat Tagihan Denda I disertai Kode Billing SIMPONI dengan tenggat pembayaran 30 hari kalender. Realisasi PK dihitung bobot 1.0.</span>
                                            </div>
                                        </div>

                                        <div class="p-2.5 rounded border bg-light d-flex align-items-start gap-2.5">
                                            <span class="badge bg-warning text-dark rounded-pill px-2 py-1 fs-11">Tahap 3</span>
                                            <div>
                                                <strong class="text-dark fs-12 d-block">Surat Tagihan Bulan Ke-2 (+ Bunga 2%/Bulan, Bobot: 0.9)</strong>
                                                <span class="fs-11 text-muted">Jika belum lunas s.d. jatuh tempo Tagihan I, diterbitkan Tagihan Bulan ke-2 dengan tambahan denda keterlambatan 2% per bulan dari denda pokok.</span>
                                            </div>
                                        </div>

                                        <div class="p-2.5 rounded border bg-light d-flex align-items-start gap-2.5">
                                            <span class="badge bg-secondary text-white rounded-pill px-2 py-1 fs-11">Tahap 4</span>
                                            <div>
                                                <strong class="text-dark fs-12 d-block">Surat Tagihan Bulan Ke-3 (+ Denda Bunga Kumulatif, Bobot: 0.8)</strong>
                                                <span class="fs-11 text-muted">Peringatan tagihan terakhir sebelum dilakukan optimalisasi penagihan aktif atau penyerahan berkas piutang negara.</span>
                                            </div>
                                        </div>

                                        <div class="p-2.5 rounded border bg-light d-flex align-items-start gap-2.5">
                                            <span class="badge bg-dark text-white rounded-pill px-2 py-1 fs-11">Tahap 5</span>
                                            <div>
                                                <strong class="text-dark fs-12 d-block">Optimalisasi Penagihan & Pelimpahan ke KPKNL / PUPN (Bobot: 0.7 - 0.6)</strong>
                                                <span class="fs-11 text-muted">Optimalisasi penagihan langsung (Bobot 0.7) atau pelimpahan pengurusan piutang macet ke Panitia Urusan Piutang Negara (PUPN) / KPKNL Kemenkeu (Bobot 0.6).</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Kolom Kanan: Dasar Hukum & Kode Rekening PNBP -->
                            <div class="col-lg-5">
                                <div class="card border rounded-3 p-3 h-100" style="background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08); box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                                    <h6 class="fw-bold text-dark mb-2 fs-13 d-flex align-items-center">
                                        <i class="material-symbols-outlined text-success fs-18 me-1.5">policy</i>
                                        Landasan Hukum Pengenaan Sanksi PNBP
                                    </h6>
                                    
                                    <div class="p-2.5 rounded border bg-light mb-2 fs-11 text-muted">
                                        <strong class="text-dark d-block mb-1"><i class="fa fa-book me-1 text-primary"></i>Dasar Regulasi Resmi:</strong>
                                        <ul class="mb-0 ps-3">
                                            <li>UU No. 36/1999 jo UU No. 6/2023 (Cipta Kerja)</li>
                                            <li>PP No. 5/2021 tentang Perizinan Berusaha Berbasis Risiko</li>
                                            <li>PP No. 46/2021 tentang Pos, Telekomunikasi, dan Penyiaran</li>
                                            <li>PP No. 43/2023 tentang Jenis dan Tarif Atas Jenis PNBP Kominfo</li>
                                            <li>PP No. 28/2025 tentang Perizinan Berusaha Berbasis Risiko</li>
                                            <li>PP No. 44/2025 tentang Penetapan Tarif & Pengelolaan PNBP</li>
                                            <li>Permen Kominfo No. 7/2021 & No. 9/2023</li>
                                            <li>Perdirjen SDPPI No. 3/2024 tentang Tata Cara Pengawasan & Sanksi</li>
                                        </ul>
                                    </div>

                                    <div class="p-2.5 rounded border mb-2" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
                                        <div class="fw-bold text-success fs-12 mb-1">
                                            <i class="fa fa-calculator me-1"></i>Komponen Formula Denda Administratif
                                        </div>
                                        <div class="fs-11 text-muted">
                                            <div class="font-monospace fw-bold text-dark mb-1">Denda Pokok = Indeks Tagihan × Bobot × (Max Poin × Tarif per Poin)</div>
                                            Di mana:
                                            <br>• Max Poin = <strong>7.000 Poin</strong>
                                            <br>• Tarif per Poin = <strong>Rp 100.000</strong>
                                            <br>• Nilai Maksimal Dasar = <strong>Rp 700.000.000</strong>
                                            <br>• Indeks Tagihan disesuaikan band frekuensi, daya, dan zona geografis
                                        </div>
                                    </div>

                                    <div class="p-2.5 rounded border" style="background: #eff6ff; border-color: #bfdbfe !important;">
                                        <div class="fw-bold text-primary fs-12 mb-1">
                                            <i class="fa fa-building-columns me-1"></i>Penyetoran Kas Negara (MPN G3)
                                        </div>
                                        <div class="fs-11 text-muted">
                                            Pembayaran wajib dilakukan melalui Bank Persepsi, Kantor Pos, ATM, Internet Banking, atau E-Commerce resmi menggunakan <strong>15 Digit Nomor Kode Billing SIMPONI</strong> sebelum tanggal jatuh tempo.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($selectedCode === 'PK.8' && $selectedYear === 2026): ?>
                <!-- ============================================================ -->
                <!-- TABEL KHUSUS PK.8: REKAPITULASI PENGUKURAN QOS SELULER 2026 -->
                <!-- Berdasarkan 4 Laporan Resmi Pengukuran Kualitas Layanan Digital -->
                <!-- ============================================================ -->
                
                <!-- Nav Tab Pemisah -->
                <ul class="nav nav-pills mb-3 gap-2" id="pk8Tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-3 py-1.5 fs-12 fw-bold" id="tab-pk8-rekap-tab" data-bs-toggle="pill" data-bs-target="#tab-pk8-rekap" type="button" role="tab" aria-selected="true">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">calendar_month</i>
                            1. Rekapitulasi Pengukuran QoS (8 Laporan Resmi & Progres Bulanan)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1.5 fs-12 fw-bold" id="tab-pk8-operator-tab" data-bs-toggle="pill" data-bs-target="#tab-pk8-operator" type="button" role="tab" aria-selected="false">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">signal_cellular_alt</i>
                            2. Hasil Uji Kinerja Layanan 3 Operator (8 Sesi Pengujian Lapangan)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1.5 fs-12 fw-bold" id="tab-pk8-poi-tab" data-bs-toggle="pill" data-bs-target="#tab-pk8-poi" type="button" role="tab" aria-selected="false">
                            <i class="material-symbols-outlined fs-16 align-middle me-1">pin_drop</i>
                            3. Sebaran 35 Titik Uji Static Test (Point of Interest / POI)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="pk8TabContent">
                    <!-- ========================================== -->
                    <!-- TAB 1: REKAPITULASI RESMI & PROGRES BULANAN -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade show active" id="tab-pk8-rekap" role="tabpanel" aria-labelledby="tab-pk8-rekap-tab">
                        <!-- BAGIAN A: REKAPITULASI 8 BERKAS DOKUMEN LAPORAN RESMI QOS -->
                        <div class="card border rounded-3 mb-4 overflow-hidden" style="border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                            <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="background: #f0fdf4;">
                                <div>
                                    <h6 class="fw-bold text-success mb-1 fs-13 d-flex align-items-center">
                                        <i class="material-symbols-outlined text-success fs-18 me-1.5">verified</i>
                                        Rekapitulasi 8 Laporan Resmi Pengukuran Kualitas Layanan (QoS) Balai Monitor Kelas I Samarinda TA 2026
                                    </h6>
                                    <span class="fs-11 text-muted">
                                        Kompilasi seluruh berkas Nota Dinas, Surat Perintah Tugas (SPT), dan hasil uji lapangan Drive Test & Static Test di 8 Kabupaten/Kota wilayah kerja.
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill">
                                        <i class="fa fa-check me-1"></i>8 Laporan Terverifikasi (100% Target)
                                    </span>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-sm align-middle fs-12 mb-0">
                                    <thead style="background: #f8fafc; font-size: 11px;">
                                        <tr class="text-dark">
                                            <th class="text-center" style="width: 40px;">No</th>
                                            <th style="width: 170px;">Bulan & Wilayah Kerja</th>
                                            <th style="width: 210px;">Dasar Surat Tugas (SPT)</th>
                                            <th style="width: 220px;">Nota Dinas Laporan Resmi</th>
                                            <th>Tim Petugas Penguji</th>
                                            <th>Lingkup Pengujian</th>
                                            <th style="width: 180px;">Throughput Kecepatan</th>
                                            <th class="text-center" style="width: 80px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pk8OfficialReportsList as $rep): ?>
                                            <tr>
                                                <td class="text-center fw-bold text-muted"><?= $rep['no'] ?></td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($rep['wilayah']) ?></div>
                                                    <small class="badge bg-light text-primary border px-1.5 py-0.5 mt-0.5"><?= htmlspecialchars($rep['bulan']) ?></small>
                                                </td>
                                                <td class="fs-11">
                                                    <div class="fw-bold text-dark font-monospace"><?= htmlspecialchars($rep['spt_number']) ?></div>
                                                    <div class="text-muted mt-0.5">
                                                        <i class="material-symbols-outlined fs-12 align-middle">calendar_today</i> Tgl: <?= htmlspecialchars($rep['spt_date']) ?>
                                                    </div>
                                                    <div class="text-muted">
                                                        <i class="material-symbols-outlined fs-12 align-middle">event</i> Uji: <?= htmlspecialchars($rep['pelaksanaan']) ?>
                                                    </div>
                                                </td>
                                                <td class="fs-11">
                                                    <div class="fw-bold text-dark font-monospace"><?= htmlspecialchars($rep['nd_number']) ?></div>
                                                    <div class="text-muted mt-0.5">
                                                        <i class="material-symbols-outlined fs-12 align-middle">schedule</i> Tanggal ND: <?= htmlspecialchars($rep['nd_date']) ?>
                                                    </div>
                                                    <div class="mt-1">
                                                        <a href="monitoring_kualitas_layanan.php" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 text-decoration-none px-2 py-0.5">
                                                            <i class="fa fa-file-lines me-1"></i><?= htmlspecialchars($rep['report_code']) ?>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td class="fs-11 text-muted">
                                                    <i class="material-symbols-outlined fs-13 align-middle text-secondary me-0.5">group</i>
                                                    <?= htmlspecialchars($rep['petugas']) ?>
                                                </td>
                                                <td class="fs-11 text-muted">
                                                    <?= htmlspecialchars($rep['lingkup']) ?>
                                                </td>
                                                <td class="fs-11">
                                                    <span class="badge bg-info bg-opacity-10 text-dark border px-2 py-1 d-block text-start" style="line-height: 1.4;">
                                                        <?= htmlspecialchars($rep['summary_speed']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                                        <i class="fa fa-check me-1"></i><?= htmlspecialchars($rep['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- BAGIAN B: TABEL PROGRES CAPAIAN KINERJA BULANAN (12 BULAN) -->
                        <div class="p-3 mb-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h6 class="fw-bold text-dark mb-0 fs-13 d-flex align-items-center">
                                        <i class="material-symbols-outlined text-primary fs-18 me-1">calendar_view_month</i>
                                        Tabel Progres Capaian Kinerja Bulanan PK.8 TA 2026 (Januari s.d. Desember)
                                    </h6>
                                    <span class="fs-11 text-muted">
                                        Penetapan realisasi kumulatif terhadap target Perjanjian Kinerja (Target: 8 Kab/Kota = 100% Wilayah Kerja).
                                    </span>
                                </div>
                                <span class="badge bg-primary text-white fs-11 px-2.5 py-1 rounded-pill">
                                    Target PK.8: 8 Kab/Kota (100%)
                                </span>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="pk-table-clean">
                                <thead>
                                    <tr>
                                        <th class="text-center align-middle" style="width: 45px; border-right: 1px solid #bae6fd;">No</th>
                                        <th class="align-middle" style="width: 105px; border-right: 1px solid #bae6fd;">Bulan</th>
                                        <th class="align-middle" style="width: 170px; border-right: 1px solid #bae6fd;">Wilayah Sasaran</th>
                                        <th class="align-middle">Rincian Pelaksanaan Pengukuran QoS</th>
                                        <th class="text-center align-middle" style="width: 95px; border-right: 1px solid #bae6fd;">Target</th>
                                        <th class="text-center align-middle" style="width: 95px; border-right: 1px solid #bae6fd;">Realisasi</th>
                                        <th class="text-center align-middle" style="width: 90px; border-right: 1px solid #bae6fd;">Capaian (%)</th>
                                        <th class="align-middle" style="min-width: 200px; border-right: 1px solid #bae6fd;">Dasar SPT & Nota Dinas</th>
                                        <th class="text-center align-middle" style="width: 85px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $runningQos = 0;
                                    foreach ($pk8OfficialTable as $mNum => $row): 
                                        $isRowActive = ($selectedMonth === $mNum);
                                        $isAchieved = ($row['status'] === 'achieved');
                                    ?>
                                        <tr class="<?= $isRowActive ? 'table-primary bg-primary bg-opacity-10 fw-semibold' : '' ?>">
                                            <td class="text-center fw-bold text-muted"><?= $mNum ?></td>
                                            <td>
                                                <a href="pk_view.php?code=PK.8&year=<?= $selectedYear ?>&month=<?= $mNum ?>" onclick="selectMonthRealtime(<?= $mNum ?>); return false;" class="text-decoration-none <?= $isRowActive ? 'text-primary fw-bold' : 'text-dark' ?>">
                                                    <?= $row['month_name'] ?>
                                                </a>
                                            </td>
                                            <td class="fw-bold text-dark">
                                                <i class="material-symbols-outlined fs-15 text-info align-middle me-1">location_on</i>
                                                <?= htmlspecialchars($row['wilayah']) ?>
                                            </td>
                                            <td class="fs-12 text-muted">
                                                <?= htmlspecialchars($row['kegiatan']) ?>
                                            </td>
                                            <td class="text-center text-muted fw-semibold fs-11">
                                                <?= htmlspecialchars($row['target_val']) ?>
                                            </td>
                                            <td class="text-center fw-bold <?= $isAchieved ? 'text-success' : 'text-secondary' ?> fs-12">
                                                <?= htmlspecialchars($row['realization_val']) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $row['cum_pct'] >= 100 ? 'bg-success' : 'bg-primary' ?> text-white fs-11 px-2 py-0.5 rounded-pill">
                                                    <?= number_format($row['cum_pct'], 1) ?>%
                                                </span>
                                            </td>
                                            <td class="fs-11">
                                                <div class="fw-bold text-dark">
                                                    <?= htmlspecialchars($row['nd_number']) ?>
                                                </div>
                                                <div class="text-muted d-flex align-items-center gap-1 mt-0.5">
                                                    <i class="material-symbols-outlined fs-13">assignment</i>
                                                    <span>SPT: <?= htmlspecialchars($row['spt']) ?></span>
                                                    <span>• <?= htmlspecialchars($row['nd_date']) ?></span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($isAchieved): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                                        <i class="fa fa-check me-1"></i>Selesai
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                                        Rencana
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="border-top-2" style="background: #f8fafc;">
                                    <tr class="align-middle fw-bold">
                                        <td colspan="4" class="text-end text-dark pe-3">
                                            TOTAL CAPAIAN KINERJA PK.8 (TA 2026):
                                        </td>
                                        <td class="text-center text-dark">8 Kab/Kota</td>
                                        <td class="text-center text-success">9 Terukur</td>
                                        <td class="text-center text-success fs-13">112.5%</td>
                                        <td colspan="2" class="text-muted fs-11">
                                            <span class="badge bg-success text-white rounded-pill px-2 py-0.5">TERCAPAI 112.5%</span>
                                            Melampaui Target Tahunan (9 dari 8 Kab/Kota Wilayah Kerja)
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- ======================================================== -->
                        <!-- REKAPITULASI BERKAS DOKUMEN LAPORAN & SURAT TUGAS (SPT)  -->
                        <!-- ======================================================== -->
                        <div class="mt-4 pt-2">
                            <div class="p-3 mb-3 rounded-3" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <h6 class="fw-bold text-success mb-1 fs-13 d-flex align-items-center">
                                            <i class="material-symbols-outlined text-success fs-18 me-1.5">verified</i>
                                            Rekapitulasi Berkas Dokumen Laporan Pengukuran Resmi & Surat Perintah Tugas (SPT)
                                        </h6>
                                        <span class="fs-11 text-muted">
                                            Sinkronisasi komprehensif 9 agenda pengukuran lapangan sesuai Nota Dinas, Laporan Pengukuran QoS, dan Master Surat Tugas Balai Monitor Kelas I Samarinda TA 2026.
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-success text-white fs-11 px-2.5 py-1 rounded-pill">
                                            <i class="fa fa-file-circle-check me-1"></i>9 Sesi Pengujian Tervalidasi
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="pk-table-clean">
                                    <thead>
                                        <tr>
                                            <th class="text-center align-middle" style="width: 40px; border-right: 1px solid #bae6fd;">No</th>
                                            <th class="align-middle" style="width: 115px; border-right: 1px solid #bae6fd;">Bulan & Jadwal</th>
                                            <th class="align-middle" style="width: 180px; border-right: 1px solid #bae6fd;">Wilayah Pengukuran</th>
                                            <th class="align-middle" style="min-width: 190px; border-right: 1px solid #bae6fd;">Surat Perintah Tugas (SPT)</th>
                                            <th class="align-middle" style="min-width: 190px; border-right: 1px solid #bae6fd;">Nota Dinas & Tanggal</th>
                                            <th class="align-middle" style="min-width: 180px; border-right: 1px solid #bae6fd;">Tim Petugas / Penguji</th>
                                            <th class="align-middle" style="min-width: 220px; border-right: 1px solid #bae6fd;">Lingkup Pengukuran & POI</th>
                                            <th class="align-middle" style="min-width: 180px; border-right: 1px solid #bae6fd;">Hasil Throughput Rata-rata</th>
                                            <th class="text-center align-middle" style="width: 85px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pk8OfficialReportsList as $rep): ?>
                                            <tr>
                                                <td class="text-center fw-bold text-muted"><?= $rep['no'] ?></td>
                                                <td>
                                                    <div class="fw-bold text-dark fs-12"><?= htmlspecialchars($rep['bulan']) ?></div>
                                                    <div class="text-muted fs-10 d-flex align-items-center gap-1 mt-0.5">
                                                        <i class="material-symbols-outlined fs-12 text-secondary">calendar_today</i>
                                                        <?= htmlspecialchars($rep['pelaksanaan']) ?>
                                                    </div>
                                                </td>
                                                <td class="fw-bold text-dark">
                                                    <i class="material-symbols-outlined fs-15 text-info align-middle me-1">location_on</i>
                                                    <?= htmlspecialchars($rep['wilayah']) ?>
                                                    <div class="mt-0.5">
                                                        <span class="badge bg-light text-secondary border fs-10 px-1.5 py-0.5 font-monospace">
                                                            <?= htmlspecialchars($rep['report_code']) ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="fs-11">
                                                    <div class="fw-bold text-primary">
                                                        <?= htmlspecialchars($rep['spt_number']) ?>
                                                    </div>
                                                    <div class="text-muted fs-10 mt-0.5 d-flex align-items-center gap-1">
                                                        <i class="material-symbols-outlined fs-12 text-muted">date_range</i>
                                                        Tgl SPT: <?= htmlspecialchars($rep['spt_date']) ?>
                                                    </div>
                                                </td>
                                                <td class="fs-11">
                                                    <div class="fw-bold text-dark">
                                                        <?= htmlspecialchars($rep['nd_number']) ?>
                                                    </div>
                                                    <div class="text-muted fs-10 mt-0.5 d-flex align-items-center gap-1">
                                                        <i class="material-symbols-outlined fs-12 text-muted">event_available</i>
                                                        Tgl ND: <?= htmlspecialchars($rep['nd_date']) ?>
                                                    </div>
                                                </td>
                                                <td class="fs-11 text-muted">
                                                    <div class="d-flex align-items-start gap-1">
                                                        <i class="material-symbols-outlined fs-14 text-secondary mt-0.5">group</i>
                                                        <span><?= htmlspecialchars($rep['petugas']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="fs-11 text-muted">
                                                    <?= htmlspecialchars($rep['lingkup']) ?>
                                                </td>
                                                <td class="fs-11">
                                                    <span class="badge bg-light text-dark border fs-10 px-2 py-1 font-monospace d-inline-block text-wrap" style="line-height: 1.4;">
                                                        <?= htmlspecialchars($rep['summary_speed']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                                        <i class="fa fa-check me-1"></i><?= htmlspecialchars($rep['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="border-top-2" style="background: #f8fafc;">
                                        <tr class="align-middle fw-bold">
                                            <td colspan="3" class="text-end text-dark pe-3">
                                                TOTAL DAFTAR PENUGASAN RESMI:
                                            </td>
                                            <td colspan="2" class="text-primary fs-12">
                                                9 Dokumen SPT & Nota Dinas Lengkap
                                            </td>
                                            <td colspan="2" class="text-muted fs-11">
                                                9 Wilayah Kabupaten/Kota Teruji
                                            </td>
                                            <td colspan="2" class="text-center">
                                                <span class="badge bg-success text-white rounded-pill px-2.5 py-1 fs-11">
                                                    100% Selesai & Terverifikasi
                                                </span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 2: KOMPARASI HASIL UJI 3 OPERATOR     -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade" id="tab-pk8-operator" role="tabpanel" aria-labelledby="tab-pk8-operator-tab">
                        <div class="p-3 mb-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1 fs-13 d-flex align-items-center">
                                        <i class="material-symbols-outlined text-primary fs-18 me-1">analytics</i>
                                        Rincian Hasil Pengujian Kualitas Layanan Seluler 3 Operator Berdasarkan Laporan Resmi
                                    </h6>
                                    <span class="fs-11 text-muted">
                                        Data komparatif pengujian Speedtest HTTP DL/UL, Video Streaming YouTube (1 Menit), Instagram, serta Sinyal Radio (RSRP, RSRQ, SINR).
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-info text-white fs-11 px-2.5 py-1 rounded-pill">
                                        Software: SmartMon & Freerider Gen 4
                                    </span>
                                </div>
                            </div>
                        </div>

                        <?php foreach ($pk8MeasurementsDetail as $mKey => $mDetail): ?>
                            <div class="card border rounded-3 mb-4 overflow-hidden" style="border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
                                <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="background: #f0f9ff;">
                                    <div>
                                        <div class="fw-bold text-dark fs-13 d-flex align-items-center">
                                            <i class="material-symbols-outlined text-info fs-18 me-1.5">share_location</i>
                                            <?= htmlspecialchars($mDetail['lokasi']) ?>
                                        </div>
                                        <div class="fs-11 text-muted mt-0.5">
                                            <span>SPT: <strong><?= htmlspecialchars($mDetail['spt']) ?></strong></span>
                                            <span class="mx-1">•</span>
                                            <span>ND: <strong><?= htmlspecialchars($mDetail['nd']) ?></strong></span>
                                            <span class="mx-1">•</span>
                                            <span>POI: <strong><?= $mDetail['poi_count'] ?> Titik Uji</strong> (<?= htmlspecialchars($mDetail['poi_list']) ?>)</span>
                                        </div>
                                    </div>
                                    <div class="fs-11 text-muted">
                                        <i class="material-symbols-outlined fs-14 align-middle me-1">group</i>
                                        Tim Penguji: <?= htmlspecialchars($mDetail['petugas']) ?>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover table-sm align-middle fs-12 mb-0">
                                        <thead style="background: #f8fafc; font-size: 11px;">
                                            <tr class="text-dark">
                                                <th rowspan="2" class="text-center align-middle" style="width: 140px; border-right: 1px solid #e2e8f0;">Operator Seluler</th>
                                                <th colspan="3" class="text-center" style="border-right: 1px solid #e2e8f0; background: #e0f2fe; color: #0369a1;">Throughput & Speedtest</th>
                                                <th colspan="3" class="text-center" style="border-right: 1px solid #e2e8f0; background: #fef3c7; color: #92400e;">Streaming YouTube (1 Menit)</th>
                                                <th colspan="4" class="text-center" style="background: #dcfce7; color: #166534;">Kualitas Sinyal Radio 4G LTE</th>
                                            </tr>
                                            <tr class="text-muted" style="font-size: 10.5px;">
                                                <th class="text-center" style="width: 100px;">Download</th>
                                                <th class="text-center" style="width: 90px;">Upload</th>
                                                <th class="text-center" style="width: 80px; border-right: 1px solid #e2e8f0;">Success Rate</th>
                                                <th class="text-center" style="width: 85px;">Success Rate</th>
                                                <th class="text-center" style="width: 75px;">MOS Visual</th>
                                                <th class="text-center" style="width: 85px; border-right: 1px solid #e2e8f0;">Resolusi 720p</th>
                                                <th class="text-center" style="width: 80px;">RSRP (dBm)</th>
                                                <th class="text-center" style="width: 75px;">RSRQ (dB)</th>
                                                <th class="text-center" style="width: 75px;">SINR (dB)</th>
                                                <th class="text-center" style="width: 95px;">Bad SINR (<0dB)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mDetail['operators'] as $opName => $opData): 
                                                $badgeClass = ($opName === 'XL') ? 'bg-info' : (($opName === 'Telkomsel') ? 'bg-danger' : 'bg-warning text-dark');
                                            ?>
                                                <tr>
                                                    <td class="fw-bold border-right" style="border-right: 1px solid #e2e8f0;">
                                                        <span class="badge <?= $badgeClass ?> me-1.5 px-2 py-1 rounded">
                                                            <?= htmlspecialchars($opName) ?>
                                                        </span>
                                                        <?php if ($opData['rank_dl'] === 1): ?>
                                                            <span class="badge bg-success text-white fs-10 px-1 py-0.2 rounded" title="Peringkat 1 Kecepatan Download">
                                                                <i class="fa fa-trophy"></i> #1
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <!-- Download -->
                                                    <td class="text-center fw-bold text-dark">
                                                        <?= number_format($opData['dl_speed'], 2) ?> <small class="text-muted fw-normal">Mbps</small>
                                                    </td>
                                                    <!-- Upload -->
                                                    <td class="text-center fw-semibold text-secondary">
                                                        <?= number_format($opData['ul_speed'], 2) ?> <small class="text-muted fw-normal">Mbps</small>
                                                    </td>
                                                    <!-- Speed SR -->
                                                    <td class="text-center border-right" style="border-right: 1px solid #e2e8f0;">
                                                        <span class="badge <?= $opData['speed_sr'] >= 90 ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning' ?> fs-10">
                                                            <?= number_format($opData['speed_sr'], 1) ?>%
                                                        </span>
                                                    </td>
                                                    <!-- YT SR -->
                                                    <td class="text-center">
                                                        <span class="badge <?= $opData['yt_sr'] >= 95 ? 'bg-success bg-opacity-10 text-success' : 'bg-primary bg-opacity-10 text-primary' ?> fs-10">
                                                            <?= number_format($opData['yt_sr'], 1) ?>%
                                                        </span>
                                                    </td>
                                                    <!-- YT Visual -->
                                                    <td class="text-center fw-semibold text-dark">
                                                        <?= number_format($opData['yt_vq'], 2) ?>
                                                    </td>
                                                    <!-- YT 720p HD -->
                                                    <td class="text-center border-right" style="border-right: 1px solid #e2e8f0;">
                                                        <span class="badge <?= $opData['yt_720p'] >= 90 ? 'bg-success text-white' : 'bg-light text-dark border' ?> fs-10">
                                                            <?= number_format($opData['yt_720p'], 1) ?>%
                                                        </span>
                                                    </td>
                                                    <!-- RSRP -->
                                                    <td class="text-center <?= $opData['rsrp'] >= -85 ? 'text-success fw-bold' : 'text-dark' ?>">
                                                        <?= number_format($opData['rsrp'], 1) ?>
                                                    </td>
                                                    <!-- RSRQ -->
                                                    <td class="text-center text-muted">
                                                        <?= number_format($opData['rsrq'], 1) ?>
                                                    </td>
                                                    <!-- SINR -->
                                                    <td class="text-center fw-bold <?= $opData['sinr'] >= 10 ? 'text-success' : 'text-primary' ?>">
                                                        <?= number_format($opData['sinr'], 1) ?>
                                                    </td>
                                                    <!-- Bad SINR -->
                                                    <td class="text-center">
                                                        <span class="badge <?= $opData['bad_sinr'] <= 35 ? 'bg-success bg-opacity-10 text-success' : ($opData['bad_sinr'] <= 50 ? 'bg-warning bg-opacity-10 text-warning' : 'bg-danger bg-opacity-10 text-danger') ?> fs-10">
                                                            <?= number_format($opData['bad_sinr'], 1) ?>%
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 3: SEBARAN 24 TITIK STATIC TEST (POI) -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade" id="tab-pk8-poi" role="tabpanel" aria-labelledby="tab-pk8-poi-tab">
                        <!-- KPI Box POI -->
                        <div class="row g-3 mb-3">
                            <div class="col-sm-3">
                                <div class="p-3 rounded-3 bg-white border">
                                    <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Total Titik Uji POI</span>
                                    <h3 class="fw-bold text-primary mb-0"><?= count($pk8PoiList) ?> <small class="fs-12 text-muted">Lokasi</small></h3>
                                    <span class="fs-11 text-muted">Static Test min. 1 Jam / Titik</span>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="p-3 rounded-3 bg-white border">
                                    <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Sebaran Kab/Kota</span>
                                    <h3 class="fw-bold text-success mb-0"><?= count(array_unique(array_column($pk8PoiList, 'kab'))) ?> <small class="fs-12 text-muted">Daerah Uji</small></h3>
                                    <span class="fs-11 text-muted">8 Kab/Kota: Samarinda, Berau, Paser, PPU/IKN, Kubar, Bontang, Kutim, Kukar</span>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="p-3 rounded-3 bg-white border">
                                    <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Standar Kualitas 4G</span>
                                    <h3 class="fw-bold text-info mb-0">100% <small class="fs-12 text-muted">Memenuhi</small></h3>
                                    <span class="fs-11 text-muted">Predikat Baik & Baik Sekali</span>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="p-3 rounded-3 bg-white border">
                                    <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Status Integrasi</span>
                                    <h3 class="fw-bold text-dark mb-0">PMT <small class="fs-12 text-muted">Pusat</small></h3>
                                    <span class="fs-11 text-success"><i class="fa fa-circle-check me-1"></i>Tersinkronisasi Ditjen PPI</span>
                                </div>
                            </div>
                        </div>

                        <!-- Tabel Sebaran 15 Titik POI -->
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle fs-12 mb-0">
                                <thead class="bg-light text-dark">
                                    <tr>
                                        <th class="text-center" style="width: 45px;">No</th>
                                        <th style="width: 170px;">Kabupaten / Kota</th>
                                        <th>Nama Point of Interest (POI)</th>
                                        <th>Kategori Fasilitas</th>
                                        <th>Kecamatan</th>
                                        <th class="text-center" style="width: 130px;">Kualitas Sinyal 4G</th>
                                        <th class="text-center" style="width: 140px;">Durasi Pengujian</th>
                                        <th class="text-center" style="width: 110px;">Status PMT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $noPoi = 1; foreach ($pk8PoiList as $poi): ?>
                                        <tr>
                                            <td class="text-center fw-bold text-muted"><?= $noPoi++ ?></td>
                                            <td class="fw-bold text-dark">
                                                <i class="material-symbols-outlined fs-15 text-primary align-middle me-1">location_city</i>
                                                <?= htmlspecialchars($poi['kab']) ?>
                                            </td>
                                            <td class="fw-bold text-dark">
                                                <i class="material-symbols-outlined fs-15 text-danger align-middle me-1">pin_drop</i>
                                                <?= htmlspecialchars($poi['nama']) ?>
                                            </td>
                                            <td class="text-muted">
                                                <span class="badge bg-light text-dark border fs-11">
                                                    <?= htmlspecialchars($poi['kategori']) ?>
                                                </span>
                                            </td>
                                            <td class="text-muted"><?= htmlspecialchars($poi['kecamatan']) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-0.5 rounded-pill">
                                                    <i class="fa fa-circle-check me-1"></i><?= htmlspecialchars($poi['sinyal_4g']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center text-muted fs-11">
                                                <i class="material-symbols-outlined fs-13 align-middle me-0.5">timer</i> Min. 1 Jam Statis
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-10 px-2 py-0.5 rounded-pill">
                                                    Terunggah PMT
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- TABEL STANDAR UNTUK PK.1 DAN LAINNYA -->
                <div class="table-responsive">
                    <table class="pk-table-clean">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 50px;">No</th>
                                <th style="width: 110px;">Bulan</th>
                                <th>Pelaksanaan & Rincian Kegiatan</th>
                                <th class="text-center" style="width: 100px;">Target</th>
                                <th class="text-center" style="width: 110px;">Realisasi</th>
                                <th class="text-center" style="width: 90px;">Capaian (%)</th>
                                <th class="text-center" style="width: 100px;">Status</th>
                                <th style="min-width: 220px;">Dokumen Laporan / ND</th>
                                <th class="text-center" style="width: 60px;">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($monthlyRecords)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        Belum ada data rekap bulanan untuk indikator ini pada tahun <?= $selectedYear ?>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($monthlyRecords as $idx => $mRow): ?>
                                    <?php 
                                        $isRowSelected = ($selectedMonth === (int)$mRow['month_num']);
                                        $statusBadge = match($mRow['status']) {
                                            'achieved' => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-10 px-2 py-0.5 rounded-pill"><i class="fa fa-check me-1"></i>Tercapai</span>',
                                            'in_progress' => '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fs-10 px-2 py-0.5 rounded-pill"><i class="fa fa-clock me-1"></i>Proses</span>',
                                            'pending' => '<span class="badge bg-light text-muted border fs-10 px-2 py-0.5 rounded-pill">Rencana</span>',
                                            default => '<span class="badge bg-light text-muted fs-10 px-2 py-0.5">-</span>'
                                        };
                                    ?>
                                    <tr data-table-month="<?= (int)$mRow['month_num'] ?>" class="<?= $isRowSelected ? 'row-active' : '' ?>">
                                        <td class="text-center fw-bold text-muted"><?= $mRow['month_num'] ?></td>
                                        <td class="fw-bold text-dark">
                                            <a href="pk_view.php?code=<?= $selectedCode ?>&year=<?= $selectedYear ?>&month=<?= $mRow['month_num'] ?>" onclick="selectMonthRealtime(<?= (int)$mRow['month_num'] ?>); return false;" class="text-decoration-none <?= $isRowSelected ? 'text-primary' : 'text-dark' ?>">
                                                <?= htmlspecialchars($mRow['month_name']) ?>
                                                <span class="row-active-dot badge bg-primary rounded-circle p-1 ms-1" style="width: 6px; height: 6px; display: <?= $isRowSelected ? 'inline-block' : 'none' ?>;"></span>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark fs-13 mb-0">
                                                <?= htmlspecialchars($mRow['activity_title']) ?>
                                            </div>
                                            <small class="text-muted fs-11">
                                                <?= htmlspecialchars($mRow['activity_detail']) ?>
                                            </small>
                                        </td>
                                        <td class="text-center text-muted fw-semibold fs-12">
                                            <?= htmlspecialchars($mRow['target_val']) ?>
                                        </td>
                                        <td class="text-center fw-bold text-success fs-12">
                                            <?= htmlspecialchars($mRow['realization_val']) ?>
                                        </td>
                                        <td class="text-center fw-bold <?= ((float)$mRow['cumulative_pct'] >= 80.0 && $selectedCode === 'PK.1') ? 'text-success' : 'text-primary' ?> fs-12">
                                            <?= $mRow['cumulative_pct'] ?>%
                                        </td>
                                        <td class="text-center">
                                            <?= $statusBadge ?>
                                        </td>
                                        <td>
                                            <span class="d-block text-dark fw-bold fs-11 text-truncate" style="max-width: 230px;" title="<?= htmlspecialchars($mRow['report_number']) ?>">
                                                <?= htmlspecialchars($mRow['report_number']) ?>
                                            </span>
                                            <small class="text-muted fs-10"><?= htmlspecialchars($mRow['report_date']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-xs btn-outline-primary rounded-circle p-1" title="Lihat Detail Bulan Ini" onclick="showMonthDetail(<?= htmlspecialchars(json_encode($mRow)) ?>)">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                            <?php if ($selectedCode === 'PK.4' && $selectedYear === 2026): ?>
                                                <button type="button" class="btn btn-xs btn-warning rounded-circle p-1 ms-1 text-dark" title="Input / Edit Realisasi APT Bulan Ini" onclick='openInputForMonth(<?= htmlspecialchars(json_encode($mRow)) ?>)'>
                                                    <i class="fa fa-pencil"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Catatan & Dasar Hukum Pengukuran -->
        <div class="card border rounded-3 p-3 bg-light" style="border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 3px rgba(10, 22, 40, 0.03);">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="material-symbols-outlined text-primary fs-18">gavel</i>
                <h6 class="fw-bold mb-0 text-dark fs-12">Dasar Hukum & Ketentuan Pengukuran Kinerja</h6>
            </div>
            <p class="fs-11 text-muted mb-0">
                Merujuk pada UU No. 36 Tahun 1999 tentang Telekomunikasi, PP No. 28 Tahun 2025, Permen Kominfo No. 7 Tahun 2021, serta Nota Dinas Direktur Pengendalian Infrastruktur Digital Nomor: 224/DJID.6/PR.04.01/02/2025 perihal Penyampaian Perjanjian Kinerja (PK) UPT Bidang Monitoring dan Pengendalian Spektrum Frekuensi Radio Tahun 2026.
            </p>
        </div>
    </div>
</div>

<!-- MODAL DETAIL BULANAN -->
<div class="modal fade" id="monthDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold fs-15" id="monthDetailModalTitle">Detail Pelaksanaan Bulanan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 rounded-3 bg-light mb-3">
                    <span class="fs-10 text-muted text-uppercase fw-bold d-block">Kegiatan & Rincian</span>
                    <h6 class="fw-bold text-dark my-1 fs-13" id="mDetailTitle"></h6>
                    <p class="text-muted fs-11 mb-0" id="mDetailSub"></p>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 border rounded-3 text-center">
                            <span class="fs-10 text-muted d-block">Target Bulanan</span>
                            <strong class="fs-13 text-dark" id="mDetailTarget"></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded-3 text-center bg-success bg-opacity-10 border-success border-opacity-25">
                            <span class="fs-10 text-success d-block">Realisasi Bulanan</span>
                            <strong class="fs-13 text-success" id="mDetailReal"></strong>
                        </div>
                    </div>
                </div>

                <div class="p-3 border rounded-3 mb-3">
                    <span class="fs-10 text-muted d-block mb-1">Dokumen Laporan / Nota Dinas</span>
                    <strong class="fs-12 text-dark d-block mb-1" id="mDetailReportNum"></strong>
                    <span class="badge bg-secondary fs-10" id="mDetailReportDate"></span>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" id="btnFilterMonthDirect">
                        <i class="fa fa-filter me-1"></i> Tampilkan Bulan Ini
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL INPUT & UPDATE REALISASI MONITORING APT (PK.4) -->
<div class="modal fade" id="modalInputMonApt" tabindex="-1" aria-labelledby="modalInputMonAptLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0 bg-warning bg-opacity-10 py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px;">
                        <i class="material-symbols-outlined fs-20">devices_other</i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="modalInputMonAptLabel">
                            Input Realisasi Pengawasan Alat & Perangkat Telko (PK.4 - TA 2026)
                        </h5>
                        <small class="text-muted fs-11">
                            Standar 16 Perangkat Prioritas Ditjen Infrastruktur Digital & Pelaporan Aplikasi SMART
                        </small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="pk_view.php?code=PK.4&year=2026" id="formMonApt">
                <input type="hidden" name="action" value="save_pk_mon_apt">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Periode & Wilayah -->
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">
                                <i class="material-symbols-outlined fs-15 align-middle me-1 text-primary">calendar_month</i>
                                Periode Bulan Pelaksanaan <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" name="month_num" id="aptMonthNum" required>
                                <?php foreach ($monthNames as $mNum => $mName): ?>
                                    <option value="<?= $mNum ?>" <?= ($mNum === 10) ? 'selected' : '' ?>>
                                        Bulan <?= $mNum ?> - <?= $mName ?> 2026
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">
                                <i class="material-symbols-outlined fs-15 align-middle me-1 text-primary">location_city</i>
                                Wilayah Pengawasan (10 Kab/Kota Kaltim) <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" name="kab_kota" id="aptKabKota" required>
                                <?php foreach ($pk4KaltimRegions as $kabName => $kabInfo): ?>
                                    <option value="<?= htmlspecialchars($kabName) ?>">
                                        <?= htmlspecialchars($kabName) ?> (Target: <?= $kabInfo['unit_target'] ?> Unit)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 16 Perangkat Prioritas & Status Sertifikasi -->
                        <div class="col-md-8">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">
                                <i class="material-symbols-outlined fs-15 align-middle me-1 text-warning">category</i>
                                16 Jenis Perangkat Prioritas Ditjen <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" name="device_type" id="aptDeviceType" required>
                                <?php foreach ($pk4PriorityDevices as $devId => $dev): ?>
                                    <option value="<?= htmlspecialchars($dev['name']) ?>">
                                        <?= $devId ?>. <?= htmlspecialchars($dev['name']) ?> [<?= $dev['category'] ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">
                                <i class="material-symbols-outlined fs-15 align-middle me-1 text-success">verified</i>
                                Status Sertifikasi SDPPI
                            </label>
                            <select class="form-select form-select-sm" name="cert_status" id="aptCertStatus">
                                <option value="Bersertifikat Resmi SDPPI" selected>Bersertifikat Resmi SDPPI</option>
                                <option value="Tidak Bersertifikat / Ilegal">Tidak Bersertifikat / Ilegal</option>
                                <option value="Campuran / Pembinaan">Campuran (Edukasi & Pembinaan)</option>
                            </select>
                        </div>

                        <!-- Target, Realisasi & Capaian Kumulatif -->
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">Target Unit</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" name="target_units" id="aptTargetUnits" value="20" min="1" required oninput="calcCumulativePct()">
                                <span class="input-group-text fs-11">Unit</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">Realisasi Diperiksa</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" name="realized_units" id="aptRealizedUnits" value="20" min="0" required oninput="calcCumulativePct()">
                                <span class="input-group-text fs-11">Unit</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">Capaian Kumulatif (%)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.1" class="form-control" name="cumulative_pct" id="aptCumulativePct" value="100.0" min="0" max="100" required>
                                <span class="input-group-text fs-11">%</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">Status Capaian</label>
                            <select class="form-select form-select-sm" name="status" id="aptStatus">
                                <option value="achieved" selected>Achieved (Tercapai)</option>
                                <option value="in_progress">In Progress (Berjalan)</option>
                                <option value="pending">Pending (Rencana)</option>
                            </select>
                        </div>

                        <!-- SPT, Tanggal, & Pelaporan SMART -->
                        <div class="col-md-5">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">Nomor SPT / BAP Pemeriksaan</label>
                            <input type="text" class="form-control form-select-sm" name="spt_number" id="aptSptNumber" placeholder="Contoh: BAP/APT/SMD/10/2026" value="BAP/APT/SMD/10/2026">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">Tanggal Kegiatan</label>
                            <input type="text" class="form-control form-select-sm" name="report_date" id="aptReportDate" value="<?= date('d M Y') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">
                                Pelaporan Aplikasi SMART
                            </label>
                            <select class="form-select form-select-sm" name="smart_report" id="aptSmartReport">
                                <option value="Aplikasi SMART (smart.postel.go.id)" selected>Aplikasi SMART (smart.postel.go.id)</option>
                                <option value="Proses Upload SMART">Proses Upload SMART</option>
                                <option value="Belum Dilaporkan">Belum Dilaporkan</option>
                            </select>
                        </div>

                        <!-- Target Lokasi & Keterangan Rinci -->
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">Judul Kegiatan</label>
                            <input type="text" class="form-control form-select-sm" name="activity_title" id="aptActivityTitle" value="Pengawasan Pasar Alat dan Perangkat Telekomunikasi">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">Target Lokasi / Gerai / Distributor</label>
                            <input type="text" class="form-control form-select-sm" name="target_name" id="aptTargetName" placeholder="Contoh: Pusat Distribusi Alat Komunikasi & Ritel" value="Pusat Distribusi Alat Komunikasi & Ritel">
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-12 fw-bold text-dark mb-1">Rincian Pelaksanaan / Catatan Pengawasan</label>
                            <textarea class="form-control" name="activity_detail" id="aptActivityDetail" rows="2" placeholder="Catatan hasil sampling pemeriksaan 16 perangkat prioritas..."></textarea>
                        </div>

                        <!-- Sinkronisasi ke Modul Reports -->
                        <div class="col-12">
                            <div class="p-2.5 rounded-3 border bg-light d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <input class="form-check-input mt-0" type="checkbox" name="sync_to_reports" value="1" id="syncToReports" checked>
                                    <label class="form-check-label fs-12 fw-bold text-dark cursor-pointer" for="syncToReports">
                                        Sinkronkan ke Modul Monitoring Laporan (Kategori 6 - Pengawasan Alat & Perangkat)
                                    </label>
                                </div>
                                <span class="badge badge-soft-primary fs-11">Auto-Sync Report Code</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 bg-light py-2 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold btn-sm rounded-pill px-4 shadow-sm">
                        <i class="fa fa-save me-1"></i> Simpan Data & Perbarui Capaian PK.4
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL KASUS PENANGANAN GANGGUAN FREKUENSI RADIO (PK.3) -->
<div class="modal fade" id="modalDetailKasusGangguan" tabindex="-1" aria-labelledby="modalDetailKasusGangguanLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0 bg-warning bg-opacity-10 py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px;">
                        <i class="material-symbols-outlined fs-20">report_problem</i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="modalKasusTitle">
                            Lembar Verifikasi Penanganan Gangguan Frekuensi Radio
                        </h5>
                        <span class="fs-11 text-muted">
                            Balai Monitor Spektrum Frekuensi Radio Kelas I Samarinda • TA 2026
                        </span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 fs-12">
                <!-- BANNER STATUS PENYELESAIAN -->
                <div class="p-3 rounded-3 mb-3 border d-flex flex-wrap align-items-center justify-content-between gap-2" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-danger text-white fs-10 px-2 py-0.5 rounded-pill font-monospace" id="modalKasusNo">KASUS #01</span>
                            <span class="badge bg-success text-white fs-10 px-2 py-0.5 rounded-pill" id="modalKasusStatus">Clean 100% (Selesai Tuntas)</span>
                        </div>
                        <h6 class="fw-bold text-dark fs-14 mb-0" id="modalKasusPelapor">PT. Kideco Jaya Agung</h6>
                    </div>
                    <div class="text-end">
                        <span class="text-muted fs-10 text-uppercase d-block fw-semibold">Kepatuhan SLA Waktu Tanggap</span>
                        <strong class="text-success fs-12" id="modalKasusSLA">&lt; 24 Jam (Memenuhi SLA Ditjen SDPPI)</strong>
                    </div>
                </div>

                <!-- RINCIAN INFORMASI KASUS -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="p-2.5 rounded border bg-light h-100">
                            <h6 class="fw-bold text-dark fs-11 text-uppercase mb-2 pb-1 border-bottom d-flex align-items-center">
                                <i class="material-symbols-outlined fs-15 text-primary me-1">assignment</i> 1. Administrasi Pengaduan & Izin ISR
                            </h6>
                            <table class="table table-sm table-borderless fs-11 mb-0">
                                <tr>
                                    <td class="text-muted py-0.5 ps-0" style="width: 120px;">Surat Pengaduan:</td>
                                    <td class="fw-semibold text-dark py-0.5" id="modalKasusSuratAduan">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-0.5 ps-0">Frekuensi:</td>
                                    <td class="font-monospace fw-bold text-primary py-0.5" id="modalKasusFrekuensi">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-0.5 ps-0">Nomor ISR:</td>
                                    <td class="font-monospace text-dark py-0.5" id="modalKasusISR">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-0.5 ps-0">Dinas / Layanan:</td>
                                    <td class="text-dark py-0.5" id="modalKasusLayanan">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-2.5 rounded border bg-light h-100">
                            <h6 class="fw-bold text-dark fs-11 text-uppercase mb-2 pb-1 border-bottom d-flex align-items-center">
                                <i class="material-symbols-outlined fs-15 text-primary me-1">flight_takeoff</i> 2. Pelaksanaan Operasional Lapangan
                            </h6>
                            <table class="table table-sm table-borderless fs-11 mb-0">
                                <tr>
                                    <td class="text-muted py-0.5 ps-0" style="width: 120px;">Surat Tugas (SPT):</td>
                                    <td class="font-monospace fw-semibold text-dark py-0.5" id="modalKasusSPT">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-0.5 ps-0">Waktu & Durasi:</td>
                                    <td class="text-dark py-0.5" id="modalKasusWaktu">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-0.5 ps-0">Lokasi / Titik:</td>
                                    <td class="text-dark py-0.5" id="modalKasusLokasi">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-0.5 ps-0">Alat Ukur / DF:</td>
                                    <td class="text-dark py-0.5" id="modalKasusAlat">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ANALISIS SUMBER GANGGUAN & TINDAKAN LOKALISIR -->
                <div class="p-3 rounded border mb-3" style="background: #fef2f2; border-color: #fecaca !important;">
                    <span class="text-danger fw-bold fs-11 d-block text-uppercase mb-1">
                        <i class="fa fa-triangle-exclamation me-1"></i>3. Sumber Interferensi & Hasil Direction Finding (DF):
                    </span>
                    <div class="fs-12 text-dark" id="modalKasusSumber">-</div>
                </div>

                <div class="p-3 rounded border mb-3" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
                    <span class="text-success fw-bold fs-11 d-block text-uppercase mb-1">
                        <i class="fa fa-screwdriver-wrench me-1"></i>4. Tindakan Penyelesaian Teknis & Penghentian Gangguan:
                    </span>
                    <div class="fs-12 text-dark" id="modalKasusTindakan">-</div>
                </div>

                <!-- DOKUMEN RESMI BAP & DISPOSISI -->
                <div class="p-2.5 rounded border bg-light mb-3">
                    <h6 class="fw-bold text-dark fs-11 text-uppercase mb-2 pb-1 border-bottom d-flex align-items-center">
                        <i class="material-symbols-outlined fs-15 text-primary me-1">gavel</i> 5. Berita Acara (BAP), Tim Petugas & Disposisi
                    </h6>
                    <table class="table table-sm table-borderless fs-11 mb-0">
                        <tr>
                            <td class="text-muted py-0.5 ps-0" style="width: 140px;">Nomor Berita Acara (BAP):</td>
                            <td class="font-monospace fw-bold text-success py-0.5" id="modalKasusBAP">-</td>
                        </tr>
                        <tr>
                            <td class="text-muted py-0.5 ps-0">Surat Teguran / Sanksi:</td>
                            <td class="font-monospace text-dark py-0.5" id="modalKasusTeguran">-</td>
                        </tr>
                        <tr>
                            <td class="text-muted py-0.5 ps-0">Penanggung Jawab / Ketua:</td>
                            <td class="text-dark fw-semibold py-0.5" id="modalKasusKetua">-</td>
                        </tr>
                        <tr>
                            <td class="text-muted py-0.5 ps-0">Anggota Tim Teknis:</td>
                            <td class="text-dark py-0.5" id="modalKasusAnggota">-</td>
                        </tr>
                        <tr>
                            <td class="text-muted py-0.5 ps-0">Disposisi Kepala Balai:</td>
                            <td class="text-dark fst-italic py-0.5" id="modalKasusDisposisi">-</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="modal-footer border-0 bg-light py-2.5 px-4 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">
                    Tutup
                </button>
                <a href="monitoring_penanganan_gangguan.php" class="btn btn-warning text-dark fw-bold btn-sm rounded-pill px-4 shadow-sm">
                    <i class="material-symbols-outlined fs-16 align-middle me-1">report_problem</i> Buka Menu Penanganan Gangguan
                </a>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DETAIL SURAT TAGIHAN DENDA ADMINISTRATIF (PK.7) -->
<div class="modal fade" id="modalDetailTagihanDenda" tabindex="-1" aria-labelledby="modalDetailTagihanDendaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0 bg-danger bg-opacity-10 py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px;">
                        <i class="material-symbols-outlined fs-20">gavel</i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="modalDetailTagihanDendaLabel">
                            Detail Surat Tagihan Denda Administratif
                        </h5>
                        <small class="text-muted fs-11" id="invModalSubtitle">
                            Balai Monitor Spektrum Frekuensi Radio Kelas I Samarinda
                        </small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- Header Info Box -->
                <div class="p-3 rounded-3 bg-light border mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <span class="fs-10 text-muted fw-bold text-uppercase d-block">Nama Badan Hukum / Pelanggar</span>
                            <h5 class="fw-bold text-dark mb-0 fs-15" id="invModalBadanHukum"></h5>
                            <div class="fs-11 text-muted mt-0.5" id="invModalIdNib"></div>
                            <div class="fs-11 text-muted mt-0.5" id="invModalAlamat"></div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-danger text-white fs-11 px-2.5 py-1 rounded-pill d-inline-block mb-1" id="invModalStatus"></span>
                            <div class="fs-11 text-muted">Jatuh Tempo: <strong class="text-danger" id="invModalJatuhTempo"></strong></div>
                        </div>
                    </div>
                </div>

                <!-- Info Grid: Surat, Tanggal, Billing, Dasar -->
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <div class="p-2.5 border rounded-3 bg-white h-100">
                            <span class="fs-10 text-muted d-block fw-bold text-uppercase">Nomor & Tanggal Tagihan</span>
                            <strong class="fs-12 text-dark font-monospace d-block" id="invModalNoSurat"></strong>
                            <div class="fs-11 text-muted mt-1">Tanggal Terbit: <strong class="text-dark" id="invModalTglSurat"></strong></div>
                            <div class="fs-11 text-muted" id="invModalInvApp"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2.5 border rounded-3 bg-white h-100">
                            <span class="fs-10 text-muted d-block fw-bold text-uppercase">Kode Billing SIMPONI & MPN G3</span>
                            <div class="d-flex align-items-center gap-2 my-1">
                                <strong class="fs-14 text-primary font-monospace" id="invModalKodeBilling"></strong>
                                <button type="button" class="btn btn-xs btn-outline-primary rounded px-1.5 py-0.5" id="btnCopyModalBilling" title="Salin Kode Billing">
                                    <i class="fa fa-copy fs-11"></i> Salin
                                </button>
                            </div>
                            <div class="fs-10 text-muted">Kanal: Teller Bank, ATM, Internet Banking, E-Commerce</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-2.5 border rounded-3 bg-white">
                            <span class="fs-10 text-muted d-block fw-bold text-uppercase">Dasar Penindakan & Klarifikasi</span>
                            <div class="fs-11 text-dark" id="invModalDasarTeguran"></div>
                            <div class="fs-11 text-dark" id="invModalDasarBa"></div>
                        </div>
                    </div>
                </div>

                <!-- Tabel Item Pelanggaran -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1.5">
                        <span class="fs-12 fw-bold text-dark"><i class="fa fa-list me-1 text-primary"></i>Daftar Objek Pelanggaran Terkena Sanksi</span>
                        <span class="badge bg-secondary fs-10" id="invModalJumlahObjek"></span>
                    </div>
                    <div class="table-responsive border rounded-3">
                        <table class="table table-sm table-hover align-middle fs-11 mb-0" id="invModalTableItems">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-center" style="width: 35px;">No</th>
                                    <th>Wilayah</th>
                                    <th>Subservice & Perangkat</th>
                                    <th class="text-center">Zona</th>
                                    <th>Mulai Operasi</th>
                                    <th class="text-center">Bobot</th>
                                    <th class="text-center">Indeks</th>
                                    <th class="text-end">Denda Pokok</th>
                                </tr>
                            </thead>
                            <tbody id="invModalItemsBody"></tbody>
                            <tfoot class="bg-light fw-bold">
                                <tr>
                                    <td colspan="7" class="text-end">Total Denda Pokok:</td>
                                    <td class="text-end text-danger font-monospace fs-12" id="invModalTotalDenda"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Terbilang & Tanda Tangan -->
                <div class="p-3 rounded-3 mb-2" style="background: #fef2f2; border: 1px solid #fecaca;">
                    <span class="fs-10 text-muted text-uppercase fw-bold d-block">Terbilang</span>
                    <strong class="fs-12 text-dark fst-italic d-block" id="invModalTerbilang"></strong>
                </div>

                <div class="d-flex justify-content-between align-items-center fs-11 text-muted mt-2">
                    <span><i class="fa fa-shield me-1 text-success"></i>Ditandatangani secara elektronik (BSrE) oleh <strong>Kepala Balmon Samarinda (Warko)</strong></span>
                    <span class="badge bg-light text-muted border">UU ITE No. 11/2008</span>
                </div>
            </div>

            <div class="modal-footer border-0 bg-light py-2 px-4 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">
                    Tutup
                </button>
                <div class="d-flex gap-2">
                    <a href="https://simponi.kemenkeu.go.id" target="_blank" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                        <i class="material-symbols-outlined fs-15 align-middle me-1">open_in_new</i> Bayar di SIMPONI
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const pkRealtimeData = <?= json_encode($pkRealtimeData) ?>;
const currentPkCode = <?= json_encode($selectedCode) ?>;
const currentPkYear = <?= json_encode($selectedYear) ?>;

function escapeHtmlText(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
    });
}

function selectMonthRealtime(m) {
    m = parseInt(m);
    if (isNaN(m) || m < 0 || m > 12) m = 0;
    const data = pkRealtimeData[m];
    if (!data) return;

    // 1. Update active class on toolbar pills
    document.querySelectorAll('.pk-month-pill').forEach(function(pill) {
        const pillM = parseInt(pill.getAttribute('data-month'));
        if (pillM === m) {
            pill.classList.add('active');
        } else {
            pill.classList.remove('active');
        }
    });

    // 2. Update filter period label
    const labelEl = document.getElementById('filter-period-label-text');
    if (labelEl && data.period_label) {
        labelEl.textContent = data.period_label;
    }

    // 3. Update PK.1
    if (currentPkCode === 'PK.1') {
        const sTitle = document.getElementById('pk1-section-title');
        if (sTitle && data.section_title) {
            sTitle.innerHTML = '<i class="material-symbols-outlined text-primary fs-18 me-1">checklist</i> ' + escapeHtmlText(data.section_title);
        }
        const sSub = document.getElementById('pk1-section-subtitle');
        if (sSub && data.section_subtitle) {
            sSub.innerHTML = data.section_subtitle;
        }

        // Param 1
        const p1Badge = document.getElementById('pk1-param1-badge');
        if (p1Badge) {
            p1Badge.className = 'badge ' + data.param1_badge_class + ' fs-10 px-2 py-0.5 rounded-pill';
            p1Badge.innerHTML = data.param1_badge_text;
        }
        const p1Val = document.getElementById('pk1-param1-value');
        if (p1Val) p1Val.innerHTML = data.param1_value;
        const p1Title = document.getElementById('pk1-param1-title');
        if (p1Title) p1Title.textContent = data.param1_title;
        const p1Desc = document.getElementById('pk1-param1-desc');
        if (p1Desc) {
            p1Desc.textContent = data.param1_desc;
            p1Desc.title = data.param1_desc;
        }

        // Param 2: ISR
        const p2Badge = document.getElementById('pk1-param2-badge');
        if (p2Badge) {
            p2Badge.className = 'badge ' + data.param2_badge_class + ' fs-10 px-2 py-0.5 rounded-pill';
            p2Badge.innerHTML = data.param2_badge_text;
        }
        const p2Val = document.getElementById('pk1-param2-value');
        if (p2Val) {
            p2Val.className = 'fw-bold mb-1 ' + (data.param2_value_class || 'text-dark');
            p2Val.innerHTML = data.param2_value;
        }
        const p2Title = document.getElementById('pk1-param2-title');
        if (p2Title) {
            p2Title.className = 'fs-12 fw-semibold mb-1 ' + (data.param2_title_class || 'text-dark');
            p2Title.textContent = data.param2_title;
        }

        // Param 3: Pengguna
        const p3Badge = document.getElementById('pk1-param3-badge');
        if (p3Badge) {
            p3Badge.className = 'badge ' + data.param3_badge_class + ' fs-10 px-2 py-0.5 rounded-pill';
            p3Badge.innerHTML = data.param3_badge_text;
        }
        const p3Val = document.getElementById('pk1-param3-value');
        if (p3Val) p3Val.innerHTML = data.param3_value;
        const p3Title = document.getElementById('pk1-param3-title');
        if (p3Title) p3Title.textContent = data.param3_title;

        // Param 4: Sanksi
        const p4Badge = document.getElementById('pk1-param4-badge');
        if (p4Badge) {
            p4Badge.className = 'badge ' + data.param4_badge_class + ' fs-10 px-2 py-0.5 rounded-pill';
            p4Badge.innerHTML = data.param4_badge_text;
        }
        const p4Val = document.getElementById('pk1-param4-value');
        if (p4Val) p4Val.innerHTML = data.param4_value;

        // Strip Notice
        const stripContainer = document.getElementById('pk1-strip-container');
        if (stripContainer && data.strip_html !== undefined) {
            stripContainer.innerHTML = data.strip_html;
        }

        // Chart Badges
        const isrBadge = document.getElementById('chart-isr-badge-container');
        if (isrBadge && data.chart_isr_badge) {
            isrBadge.innerHTML = data.chart_isr_badge;
        }
        const usersBadge = document.getElementById('chart-users-badge-container');
        if (usersBadge && data.chart_users_badge) {
            usersBadge.innerHTML = data.chart_users_badge;
        }
    }

    // 4. Update PK.2
    if (currentPkCode === 'PK.2') {
        const sTitle = document.getElementById('pk2-section-title');
        if (sTitle && data.section_title) {
            sTitle.innerHTML = '<i class="material-symbols-outlined text-info fs-18 me-1">domain_verification</i> ' + escapeHtmlText(data.section_title);
        }
        const sSub = document.getElementById('pk2-section-subtitle');
        if (sSub && data.section_subtitle) {
            sSub.innerHTML = data.section_subtitle;
        }

        // FM
        const fmBadge = document.getElementById('pk2-fm-badge');
        if (fmBadge) {
            fmBadge.className = 'badge ' + data.fm_badge_class + ' fs-10 px-2 py-0.5 rounded-pill';
            fmBadge.innerHTML = data.fm_badge;
        }
        const fmVal = document.getElementById('pk2-fm-value');
        if (fmVal) fmVal.innerHTML = data.fm_capaian;
        const fmTitle = document.getElementById('pk2-fm-title');
        if (fmTitle) fmTitle.textContent = data.fm_pct_text;
        const fmBar = document.getElementById('pk2-fm-bar');
        if (fmBar) fmBar.style.width = data.fm_pct_val + '%';
        const fmDesc = document.getElementById('pk2-fm-desc');
        if (fmDesc) fmDesc.textContent = data.fm_desc;

        // TV
        const tvBadge = document.getElementById('pk2-tv-badge');
        if (tvBadge) {
            tvBadge.className = 'badge ' + data.tv_badge_class + ' fs-10 px-2 py-0.5 rounded-pill';
            tvBadge.innerHTML = data.tv_badge;
        }
        const tvVal = document.getElementById('pk2-tv-value');
        if (tvVal) tvVal.innerHTML = data.tv_capaian;
        const tvTitle = document.getElementById('pk2-tv-title');
        if (tvTitle) tvTitle.textContent = data.tv_pct_text;
        const tvBar = document.getElementById('pk2-tv-bar');
        if (tvBar) tvBar.style.width = data.tv_pct_val + '%';
        const tvDesc = document.getElementById('pk2-tv-desc');
        if (tvDesc) tvDesc.textContent = data.tv_desc;

        // Remote
        const remBadge = document.getElementById('pk2-remote-badge');
        if (remBadge) {
            remBadge.className = 'badge ' + data.remote_badge_class + ' fs-10 px-2 py-0.5 rounded-pill';
            remBadge.innerHTML = data.remote_badge;
        }
        const remVal = document.getElementById('pk2-remote-value');
        if (remVal) remVal.innerHTML = data.remote_capaian;
        const remTitle = document.getElementById('pk2-remote-title');
        if (remTitle) remTitle.textContent = data.remote_pct_text;
        const remBar = document.getElementById('pk2-remote-bar');
        if (remBar) remBar.style.width = data.remote_pct_val + '%';

        // Shelter
        const shBadge = document.getElementById('pk2-shelter-badge');
        if (shBadge) {
            shBadge.className = 'badge ' + data.shelter_badge_class + ' fs-10 px-2 py-0.5 rounded-pill';
            shBadge.innerHTML = data.shelter_badge;
        }
        const shVal = document.getElementById('pk2-shelter-value');
        if (shVal) shVal.innerHTML = data.shelter_capaian;
        const shTitle = document.getElementById('pk2-shelter-title');
        if (shTitle) shTitle.textContent = data.shelter_pct_text;
        const shBar = document.getElementById('pk2-shelter-bar');
        if (shBar) shBar.style.width = data.shelter_pct_val + '%';
        const shDesc = document.getElementById('pk2-shelter-desc');
        if (shDesc) shDesc.textContent = data.shelter_desc;

        // Strip
        const stripContainer = document.getElementById('pk2-strip-container');
        if (stripContainer && data.strip_html !== undefined) {
            stripContainer.innerHTML = data.strip_html;
        }
    }

    // 4b. Update PK.3 (Penanganan Gangguan Frekuensi Radio)
    if (currentPkCode === 'PK.3') {
        const p1Badge = document.getElementById('pk3-param1-badge');
        if (p1Badge && data.aduan_badge) {
            p1Badge.className = 'badge ' + (data.aduan_badge_class || 'bg-danger text-white') + ' fs-10 px-2 py-0.5 rounded-pill';
            p1Badge.innerHTML = data.aduan_badge;
        }
        const p1Val = document.getElementById('pk3-param1-val');
        if (p1Val && data.aduan_masuk) p1Val.innerHTML = data.aduan_masuk;

        const p2Badge = document.getElementById('pk3-param2-badge');
        if (p2Badge && data.selesai_badge) {
            p2Badge.className = 'badge ' + (data.selesai_badge_class || 'bg-success text-white') + ' fs-10 px-2 py-0.5 rounded-pill';
            p2Badge.innerHTML = data.selesai_badge;
        }
        const p2Val = document.getElementById('pk3-param2-val');
        if (p2Val) p2Val.innerHTML = (m === 0) ? '100.0% <small class="fs-13 text-muted">(2/2 Kasus)</small>' : ((m === 4 || m === 8) ? '100.0% <small class="fs-13 text-muted">(1/1 Kasus)</small>' : '100.0% <small class="fs-13 text-muted">(Kondusif)</small>');

        const p3Badge = document.getElementById('pk3-param3-badge');
        if (p3Badge) {
            p3Badge.className = 'badge ' + (m === 0 || m === 4 || m === 8 ? 'bg-primary text-white' : 'bg-light text-muted border') + ' fs-10 px-2 py-0.5 rounded-pill';
            p3Badge.innerHTML = (m === 0) ? '2 Perusahaan' : ((m === 4 || m === 8) ? '1 Perusahaan' : 'Kondusif');
        }
        const p3Val = document.getElementById('pk3-param3-val');
        if (p3Val && data.entitas_lindung) p3Val.innerHTML = data.entitas_lindung;

        const p4Badge = document.getElementById('pk3-param4-badge');
        if (p4Badge && data.sla_badge) {
            p4Badge.className = 'badge ' + (data.sla_badge_class || 'bg-info text-white') + ' fs-10 px-2 py-0.5 rounded-pill';
            p4Badge.innerHTML = data.sla_badge;
        }
        const p4Val = document.getElementById('pk3-param4-val');
        if (p4Val && data.sla_capaian) p4Val.innerHTML = data.sla_capaian;

        // Filter tampilan kartu kasus sesuai bulan
        const case1El = document.getElementById('case-card-1');
        const case2El = document.getElementById('case-card-2');
        if (case1El && case2El) {
            if (m === 0) {
                case1El.style.display = '';
                case2El.style.display = '';
            } else if (m === 4) {
                case1El.style.display = '';
                case2El.style.display = 'none';
            } else if (m === 8) {
                case1El.style.display = 'none';
                case2El.style.display = '';
            } else {
                case1El.style.display = 'none';
                case2El.style.display = 'none';
            }
        }
    }

    // 5. Update Table Rows Active State
    document.querySelectorAll('tr[data-table-month]').forEach(function(tr) {
        const rowM = parseInt(tr.getAttribute('data-table-month'));
        const dot = tr.querySelector('.row-active-dot');
        const link = tr.querySelector('td:nth-child(2) a');

        if (rowM === m && m > 0) {
            tr.classList.add('row-active');
            if (dot) dot.style.display = 'inline-block';
            if (link) {
                link.classList.add('text-primary');
                link.classList.remove('text-dark');
            }
        } else {
            tr.classList.remove('row-active');
            if (dot) dot.style.display = 'none';
            if (link) {
                link.classList.remove('text-primary');
                link.classList.add('text-dark');
            }
        }
    });

    // 6. Update URL in browser address bar without refresh
    try {
        const newUrl = 'pk_view.php?code=' + encodeURIComponent(currentPkCode) + '&year=' + encodeURIComponent(currentPkYear) + '&month=' + m;
        window.history.replaceState({month: m}, '', newUrl);
    } catch(e) {}
}

function showMonthDetail(data) {
    document.getElementById('monthDetailModalTitle').innerText = 'Detail Pelaksanaan Bulan ' + data.month_name + ' <?= $selectedYear ?>';
    document.getElementById('mDetailTitle').innerText = data.activity_title;
    document.getElementById('mDetailSub').innerText = data.activity_detail;
    document.getElementById('mDetailTarget').innerText = data.target_val;
    document.getElementById('mDetailReal').innerText = data.realization_val;
    document.getElementById('mDetailReportNum').innerText = data.report_number;
    document.getElementById('mDetailReportDate').innerText = data.report_date;

    document.getElementById('btnFilterMonthDirect').onclick = function() {
        if (typeof selectMonthRealtime === 'function') {
            selectMonthRealtime(parseInt(data.month_num));
            var modalEl = document.getElementById('monthDetailModal');
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
        } else {
            window.location.href = 'pk_view.php?code=<?= $selectedCode ?>&year=<?= $selectedYear ?>&month=' + data.month_num;
        }
    };

    var modal = new bootstrap.Modal(document.getElementById('monthDetailModal'));
    modal.show();
}

const pk7OfficialInvoicesData = <?= json_encode($pk7OfficialInvoices) ?>;

function copyBillingCode(code) {
    if (!code) return;
    navigator.clipboard.writeText(code).then(function() {
        alert('Kode Billing SIMPONI ' + code + ' berhasil disalin ke clipboard!');
    }).catch(function() {
        prompt('Salin Kode Billing SIMPONI:', code);
    });
}

function showInvoiceDetail(idx) {
    const inv = pk7OfficialInvoicesData[idx];
    if (!inv) return;

    document.getElementById('modalDetailTagihanDendaLabel').textContent = 'Detail Tagihan Bulan Ke-' + inv.tagihan_bulan_ke + ' • ' + inv.badan_hukum;
    document.getElementById('invModalSubtitle').textContent = 'Surat No: ' + inv.tagihan_number + ' • Balmon Samarinda';
    document.getElementById('invModalBadanHukum').textContent = inv.badan_hukum;
    document.getElementById('invModalIdNib').innerHTML = (inv.client_id !== '-' ? 'Client ID: <strong>' + inv.client_id + '</strong> • ' : '') + 'NIB: <strong>' + inv.nik_nib + '</strong>' + (inv.npwp !== '-' && inv.npwp !== '' ? ' • NPWP: ' + inv.npwp : '');
    document.getElementById('invModalAlamat').innerHTML = '<i class="fa fa-location-dot me-1 text-muted"></i>' + inv.alamat;
    document.getElementById('invModalStatus').textContent = inv.status;
    document.getElementById('invModalJatuhTempo').textContent = inv.jatuh_tempo;

    document.getElementById('invModalNoSurat').textContent = inv.tagihan_number;
    document.getElementById('invModalTglSurat').textContent = inv.tagihan_date;
    document.getElementById('invModalInvApp').innerHTML = (inv.no_invoice !== '-' ? 'No. Invoice: <strong>' + inv.no_invoice + '</strong>' : 'No. Aplikasi: <strong>' + inv.no_aplikasi + '</strong>');

    document.getElementById('invModalKodeBilling').textContent = inv.kode_billing;
    document.getElementById('btnCopyModalBilling').onclick = function() {
        copyBillingCode(inv.kode_billing);
    };

    document.getElementById('invModalDasarTeguran').innerHTML = '<strong>Surat Teguran Tertulis:</strong> ' + inv.teguran_number + ' (Tgl: ' + inv.teguran_date + ')';
    document.getElementById('invModalDasarBa').innerHTML = '<strong>Berita Acara:</strong> ' + inv.ba_number + ' (Tgl: ' + inv.ba_date + ')';

    document.getElementById('invModalJumlahObjek').textContent = inv.jumlah_objek + ' Objek Stasiun';

    // Populate Items
    const tbody = document.getElementById('invModalItemsBody');
    tbody.innerHTML = '';
    if (inv.items && inv.items.length > 0) {
        inv.items.forEach(function(it, i) {
            const tr = document.createElement('tr');
            tr.innerHTML = 
                '<td class="text-center fw-bold text-muted">' + (i + 1) + '</td>' +
                '<td><span class="badge bg-light text-dark border">' + it.wilayah + '</span></td>' +
                '<td>' +
                    '<div class="fw-semibold text-dark">' + it.sub + '</div>' +
                    (it.perangkat && it.perangkat !== '-' ? '<small class="text-primary fw-bold">' + it.perangkat + '</small> ' : '') +
                    (it.frekuensi && it.frekuensi !== '-' ? '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-9">' + it.frekuensi + '</span> ' : '') +
                    '<span class="badge bg-secondary bg-opacity-10 text-secondary fs-9">' + it.unit + ' Unit</span>' +
                '</td>' +
                '<td class="text-center fw-bold">' + it.zona + '</td>' +
                '<td class="text-muted">' + it.tgl_operasi + '</td>' +
                '<td class="text-center fw-bold ' + (it.bobot === '100%' ? 'text-danger' : 'text-primary') + '">' + it.bobot + '</td>' +
                '<td class="text-center font-monospace">' + it.indeks + '</td>' +
                '<td class="text-end fw-bold text-danger font-monospace">Rp ' + new Intl.NumberFormat('id-ID').format(it.denda) + '</td>';
            tbody.appendChild(tr);
        });
    }

    document.getElementById('invModalTotalDenda').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(inv.total_denda);
    document.getElementById('invModalTerbilang').textContent = '"' + inv.terbilang + '"';

    var modal = new bootstrap.Modal(document.getElementById('modalDetailTagihanDenda'));
    modal.show();
}

function openInputForMonth(row) {
    if (!row) return;
    const mNum = parseInt(row.month_num);
    const mSelect = document.getElementById('aptMonthNum');
    if (mSelect) mSelect.value = mNum;

    // Parse numeric target & realization
    const targetVal = parseInt(row.target_val) || 20;
    const realVal = parseInt(row.realization_val) || 0;
    const cumPct = parseFloat(row.cumulative_pct) || 0;

    const tInput = document.getElementById('aptTargetUnits');
    if (tInput) tInput.value = targetVal;

    const rInput = document.getElementById('aptRealizedUnits');
    if (rInput) rInput.value = realVal;

    const pInput = document.getElementById('aptCumulativePct');
    if (pInput) pInput.value = cumPct;

    const sSelect = document.getElementById('aptStatus');
    if (sSelect && row.status) sSelect.value = row.status;

    const sptInput = document.getElementById('aptSptNumber');
    if (sptInput && row.report_number) sptInput.value = row.report_number;

    const dInput = document.getElementById('aptReportDate');
    if (dInput && row.report_date) dInput.value = row.report_date;

    const titInput = document.getElementById('aptActivityTitle');
    if (titInput && row.activity_title) titInput.value = row.activity_title;

    const detInput = document.getElementById('aptActivityDetail');
    if (detInput && row.activity_detail) detInput.value = row.activity_detail;

    const modalEl = document.getElementById('modalInputMonApt');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        new bootstrap.Modal(modalEl).show();
    }
}

function calcCumulativePct() {
    const tVal = parseFloat(document.getElementById('aptTargetUnits')?.value) || 0;
    const rVal = parseFloat(document.getElementById('aptRealizedUnits')?.value) || 0;
    if (tVal > 0 && rVal > 0) {
        const kSelect = document.getElementById('aptKabKota');
        const dSelect = document.getElementById('aptDeviceType');
        const kText = kSelect ? kSelect.value : '';
        const dText = dSelect ? dSelect.value : '';

        const detInput = document.getElementById('aptActivityDetail');
        if (detInput && (!detInput.value || detInput.value.includes('Pemeriksaan') || detInput.value.includes('Sampling'))) {
            detInput.value = 'Pemeriksaan sampling ' + rVal + ' unit ' + dText + ' di ' + kText;
        }
    }
}

// Bind click listeners on toolbar pills
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.pk-month-pill[data-month]').forEach(function(pill) {
        pill.addEventListener('click', function(e) {
            e.preventDefault();
            const m = parseInt(this.getAttribute('data-month'));
            selectMonthRealtime(m);
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- ApexCharts Script: Grafik-Grafik yang Diperlukan -->
<script>
window.addEventListener('load', function() {
    if (typeof ApexCharts === 'undefined') {
        console.warn('ApexCharts is not available.');
        return;
    }

    <?php if ($selectedCode === 'PK.1'): ?>
    // ----------------------------------------------------
    // CHART 1 PK.1: Tren Capaian (%) Kumulatif vs Target 80%
    // ----------------------------------------------------
    const chartCapaianEl = document.querySelector("#chart-sfr-capaian");
    if (chartCapaianEl) {
        const capaianData = <?= json_encode($chartCapaianSeries) ?>;
        const categories = <?= json_encode($chartCategories) ?>;
        const targetVal = <?= (float)$activePk['target_annual'] ?>;

        const optionsCapaian = {
            series: [{
                name: 'Capaian Kumulatif',
                data: capaianData
            }],
            chart: {
                height: 270,
                type: 'area',
                toolbar: { show: false },
                zoom: { enabled: false }
            },
            colors: ['#0070E0'],
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    if (val === null || val === undefined) return '';
                    return val + "%";
                },
                offsetY: -6,
                style: {
                    fontSize: '10px',
                    fontWeight: 700,
                    colors: ['#0A1628']
                },
                background: {
                    enabled: true,
                    foreColor: '#fff',
                    borderRadius: 4,
                    padding: 3,
                    opacity: 0.85
                }
            },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 95, 100]
                }
            },
            markers: {
                size: 4,
                colors: ['#0070E0'],
                strokeColors: '#ffffff',
                strokeWidth: 2,
                hover: { size: 6 }
            },
            xaxis: {
                categories: categories,
                labels: {
                    style: { colors: '#64748b', fontSize: '11px', fontWeight: 600 }
                },
                axisBorder: { show: false }
            },
            yaxis: {
                min: 0,
                max: 100,
                tickAmount: 5,
                labels: {
                    formatter: function(val) { return val + "%"; },
                    style: { colors: '#64748b', fontSize: '11px' }
                }
            },
            annotations: {
                yaxis: [{
                    y: targetVal,
                    borderColor: '#ef4444',
                    borderWidth: 2,
                    strokeDashArray: 4,
                    label: {
                        borderColor: '#ef4444',
                        style: {
                            color: '#ffffff',
                            background: '#ef4444',
                            fontSize: '11px',
                            fontWeight: 700
                        },
                        text: 'Target PK: ' + targetVal + '%'
                    }
                }]
            },
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 3
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        if (val === null || val === undefined) return "Menunggu bulan berjalan (belum ada data)";
                        return val + "% (Target: " + targetVal + "%)";
                    }
                }
            }
        };

        new ApexCharts(chartCapaianEl, optionsCapaian).render();
    }

    // CHART 2 PK.1: ISR Termonitor vs Target (1.312 ISR) - 1 Tahun Penuh (Jan - Des)
    const chartIsrEl = document.querySelector("#chart-sfr-isr");
    if (chartIsrEl) {
        const isrData = <?= json_encode($pk1IsrChartData) ?>;

        const optionsIsr = {
            series: [{
                name: 'ISR Termonitor',
                data: isrData
            }],
            chart: {
                height: 250,
                type: 'bar',
                toolbar: { show: false }
            },
            colors: ['#0284c7'],
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '50%',
                    dataLabels: {
                        position: 'top'
                    }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    if (val === null || val === undefined) return '';
                    return val.toLocaleString('id-ID');
                },
                offsetY: -18,
                style: {
                    fontSize: '10px',
                    fontWeight: 700,
                    colors: ['#0A1628']
                }
            },
            annotations: {
                yaxis: [{
                    y: 1312,
                    borderColor: '#10b981',
                    borderWidth: 2,
                    strokeDashArray: 4,
                    label: {
                        borderColor: '#10b981',
                        style: {
                            color: '#ffffff',
                            background: '#10b981',
                            fontSize: '10px',
                            fontWeight: 700
                        },
                        text: 'Target: 1.312 ISR (60%)'
                    }
                }]
            },
            xaxis: {
                labels: {
                    style: { colors: '#64748b', fontSize: '11px', fontWeight: 600 }
                }
            },
            yaxis: {
                min: 0,
                max: 2400,
                tickAmount: 4,
                labels: {
                    formatter: function(val) { return val.toLocaleString('id-ID'); },
                    style: { colors: '#64748b', fontSize: '11px' }
                }
            },
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 3
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        if (val === null || val === undefined) return "Menunggu bulan berjalan (belum ada data)";
                        return val.toLocaleString('id-ID') + " ISR (Target: 1.312 ISR)";
                    }
                }
            }
        };

        new ApexCharts(chartIsrEl, optionsIsr).render();
    }

    // CHART 3 PK.1: Tren Pengguna Teridentifikasi - 1 Tahun Penuh (Jan - Des)
    const chartUsersEl = document.querySelector("#chart-sfr-users");
    if (chartUsersEl) {
        const usersData = <?= json_encode($pk1UsersChartData) ?>;

        const optionsUsers = {
            series: [{
                name: 'Pengguna Teridentifikasi',
                data: usersData
            }],
            chart: {
                height: 250,
                type: 'area',
                toolbar: { show: false }
            },
            colors: ['#f59e0b'],
            stroke: {
                curve: 'smooth',
                width: 3
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    opacityTo: 0.05,
                    stops: [0, 95, 100]
                }
            },
            markers: {
                size: 4,
                colors: ['#f59e0b'],
                strokeColors: '#ffffff',
                strokeWidth: 2
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    if (val === null || val === undefined) return '';
                    return val.toLocaleString('id-ID');
                },
                offsetY: -8,
                style: {
                    fontSize: '10px',
                    fontWeight: 700,
                    colors: ['#0A1628']
                }
            },
            xaxis: {
                labels: {
                    style: { colors: '#64748b', fontSize: '11px', fontWeight: 600 }
                }
            },
            yaxis: {
                min: 0,
                max: 6000,
                tickAmount: 4,
                labels: {
                    formatter: function(val) { return val.toLocaleString('id-ID'); },
                    style: { colors: '#64748b', fontSize: '11px' }
                }
            },
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 3
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        if (val === null || val === undefined) return "Menunggu bulan berjalan (belum ada data)";
                        return val.toLocaleString('id-ID') + " Pengguna (100% Teridentifikasi)";
                    }
                }
            }
        };

        new ApexCharts(chartUsersEl, optionsUsers).render();
    }

    <?php elseif ($selectedCode === 'PK.2'): ?>
    // ----------------------------------------------------
    // CHART 1 PK.2: Tren Capaian (%) Bulanan vs Target 100%
    // ----------------------------------------------------
    const chartPk2CapaianEl = document.querySelector("#chart-pk2-capaian");
    if (chartPk2CapaianEl) {
        const pk2Months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep'];
        const pk2Data = [0.00, 0.88, 1.13, 25.14, 29.46, 59.21, 73.19, 76.04, 91.82];

        const optionsPk2Capaian = {
            series: [{
                name: 'Capaian PK Pemeriksaan Stasiun',
                data: pk2Data
            }],
            chart: {
                height: 270,
                type: 'area',
                toolbar: { show: false },
                zoom: { enabled: false }
            },
            colors: ['#0284c7'],
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return val + "%";
                },
                offsetY: -6,
                style: {
                    fontSize: '10px',
                    fontWeight: 700,
                    colors: ['#0A1628']
                },
                background: {
                    enabled: true,
                    foreColor: '#fff',
                    borderRadius: 4,
                    padding: 3,
                    opacity: 0.9
                }
            },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 95, 100]
                }
            },
            markers: {
                size: 4,
                colors: ['#0284c7'],
                strokeColors: '#ffffff',
                strokeWidth: 2,
                hover: { size: 6 }
            },
            xaxis: {
                categories: pk2Months,
                labels: {
                    style: { colors: '#64748b', fontSize: '11px', fontWeight: 600 }
                }
            },
            yaxis: {
                min: 0,
                max: 105,
                tickAmount: 5,
                labels: {
                    formatter: function(val) { return val + "%"; },
                    style: { colors: '#64748b', fontSize: '11px' }
                }
            },
            annotations: {
                yaxis: [{
                    y: 100,
                    borderColor: '#ef4444',
                    borderWidth: 2,
                    strokeDashArray: 4,
                    label: {
                        borderColor: '#ef4444',
                        style: {
                            color: '#ffffff',
                            background: '#ef4444',
                            fontSize: '11px',
                            fontWeight: 700
                        },
                        text: 'Target Tahunan: 100%'
                    }
                }]
            },
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 3
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        return val + "% (dari Target 2.383 Stasiun)";
                    }
                }
            }
        };

        new ApexCharts(chartPk2CapaianEl, optionsPk2Capaian).render();
    }

    // CHART 2 PK.2: Komparasi Target vs Realisasi Tiap Komponen
    const chartPk2CompEl = document.querySelector("#chart-pk2-components");
    if (chartPk2CompEl) {
        const compOptions = {
            series: [
                {
                    name: 'Target Sasaran',
                    data: [50, 18, 262, 2053]
                },
                {
                    name: 'Realisasi s.d. September',
                    data: [50, 17, 330, 1791]
                }
            ],
            chart: {
                type: 'bar',
                height: 240,
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '50%',
                    borderRadius: 4
                }
            },
            colors: ['#94a3b8', '#0070E0'],
            dataLabels: {
                enabled: false
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: ['Radio FM', 'TV Digital', 'Open Shelter', 'Remote Site'],
                labels: {
                    style: { colors: '#64748b', fontSize: '11px', fontWeight: 600 }
                }
            },
            yaxis: {
                labels: {
                    formatter: function(val) { return val.toLocaleString('id-ID'); },
                    style: { colors: '#64748b', fontSize: '11px' }
                }
            },
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 3
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function (val) {
                        return val.toLocaleString('id-ID') + " Stasiun";
                    }
                }
            }
        };

        new ApexCharts(chartPk2CompEl, compOptions).render();
    }

    // CHART 3 PK.2: Akumulasi Stasiun Terperiksa per Bulan
    const chartPk2StationsEl = document.querySelector("#chart-pk2-stations");
    if (chartPk2StationsEl) {
        const stationsData = [0, 21, 27, 599, 702, 1411, 1744, 1812, 2188];
        const stationMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep'];

        const stationsOptions = {
            series: [{
                name: 'Total Stasiun Terperiksa',
                data: stationsData
            }],
            chart: {
                height: 240,
                type: 'area',
                toolbar: { show: false }
            },
            colors: ['#10b981'],
            stroke: {
                curve: 'smooth',
                width: 3
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 95, 100]
                }
            },
            markers: {
                size: 4,
                colors: ['#10b981'],
                strokeColors: '#ffffff',
                strokeWidth: 2
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return val > 0 ? val.toLocaleString('id-ID') : '';
                },
                offsetY: -8,
                style: {
                    fontSize: '10px',
                    fontWeight: 700,
                    colors: ['#0A1628']
                }
            },
            xaxis: {
                categories: stationMonths,
                labels: {
                    style: { colors: '#64748b', fontSize: '11px', fontWeight: 600 }
                }
            },
            yaxis: {
                min: 0,
                max: 2500,
                tickAmount: 5,
                labels: {
                    formatter: function(val) { return val.toLocaleString('id-ID'); },
                    style: { colors: '#64748b', fontSize: '11px' }
                }
            },
            annotations: {
                yaxis: [{
                    y: 2383,
                    borderColor: '#f59e0b',
                    borderWidth: 2,
                    strokeDashArray: 4,
                    label: {
                        borderColor: '#f59e0b',
                        style: {
                            color: '#ffffff',
                            background: '#f59e0b',
                            fontSize: '10px',
                            fontWeight: 700
                        },
                        text: 'Target Total: 2.383 Stn'
                    }
                }]
            },
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 3
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        return val.toLocaleString('id-ID') + " Stasiun (Target: 2.383)";
                    }
                }
            }
        };

        new ApexCharts(chartPk2StationsEl, stationsOptions).render();
    }
    <?php elseif ($selectedCode === 'PK.3' && $selectedYear === 2026): ?>
    // ----------------------------------------------------
    // CHART PK.3: Distribusi Trouble Ticket & Penanganan Gangguan SFR 2026
    // ----------------------------------------------------
    const chartPk3El = document.querySelector("#chart-pk3-gangguan");
    if (chartPk3El) {
        const monthsPk3 = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        const aduanMasuk = [0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 0];
        const aduanSelesai = [0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 0];

        const optionsPk3 = {
            series: [
                {
                    name: 'Pengaduan Masuk (Trouble Ticket)',
                    data: aduanMasuk
                },
                {
                    name: 'Gangguan Diselesaikan (Clean 100%)',
                    data: aduanSelesai
                }
            ],
            chart: {
                height: 270,
                type: 'bar',
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '40%',
                    borderRadius: 4
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(val) {
                    return val > 0 ? val + " Kasus" : "";
                },
                offsetY: -20,
                style: {
                    fontSize: '10px',
                    colors: ["#304758"]
                }
            },
            colors: ['#ef4444', '#10b981'],
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: monthsPk3,
                labels: { style: { fontSize: '11px' } }
            },
            yaxis: {
                title: { 
                    text: 'Jumlah Kasus Gangguan',
                    style: { fontSize: '11px' }
                },
                min: 0,
                max: 2,
                tickAmount: 2
            },
            fill: { opacity: 1 },
            tooltip: {
                y: {
                    formatter: function(val, { dataPointIndex }) {
                        if (val === 0) return '0 Kasus (Spektrum Kondusif / Bebas Gangguan)';
                        return val + ' Kasus Gangguan (' + (dataPointIndex === 3 ? 'PT. Kideco Jaya Agung' : 'PT. Multi Harapan Utama') + ')';
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '11px',
                markers: { radius: 12 }
            }
        };

        new ApexCharts(chartPk3El, optionsPk3).render();
    }

    // Global array data kasus untuk JS modal verifikasi
    const pk3CasesData = <?= json_encode($pk3OfficialCases) ?>;

    function showKasusDetail(caseId) {
        const c = pk3CasesData.find(item => item.id == caseId);
        if (!c) return;

        document.getElementById('modalKasusTitle').textContent = 'Lembar Verifikasi Kasus #' + c.case_no + ' • ' + c.pelapor;
        document.getElementById('modalKasusNo').textContent = 'KASUS #' + (c.id < 10 ? '0' + c.id : c.id);
        document.getElementById('modalKasusPelapor').textContent = c.pelapor;
        document.getElementById('modalKasusSuratAduan').textContent = c.complaint_number + ' (' + c.complaint_date + ')';
        document.getElementById('modalKasusFrekuensi').textContent = c.frequency;
        document.getElementById('modalKasusISR').textContent = c.isr_numbers;
        document.getElementById('modalKasusLayanan').textContent = c.affected_service;
        document.getElementById('modalKasusSPT').textContent = c.spt_number + ' (' + c.spt_date + ')';
        document.getElementById('modalKasusWaktu').textContent = c.execution_dates;
        document.getElementById('modalKasusLokasi').textContent = c.location + ' (' + c.sub_location + ')';
        document.getElementById('modalKasusAlat').textContent = c.equipment_used;
        document.getElementById('modalKasusSumber').textContent = c.source_identified;
        document.getElementById('modalKasusTindakan').textContent = c.action_taken;
        document.getElementById('modalKasusBAP').textContent = c.bap_number + ' (' + c.bap_date + ')';
        document.getElementById('modalKasusTeguran').textContent = c.teguran_number;
        document.getElementById('modalKasusKetua').textContent = c.team_leader;
        document.getElementById('modalKasusAnggota').textContent = c.team_members;
        document.getElementById('modalKasusDisposisi').textContent = c.disposition;
        document.getElementById('modalKasusSLA').textContent = c.sla_compliance;
        document.getElementById('modalKasusStatus').textContent = c.resolution_status;

        const modalEl = document.getElementById('modalDetailKasusGangguan');
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }
    <?php elseif ($selectedCode === 'PK.4' && $selectedYear === 2026): ?>
    // ----------------------------------------------------
    // CHART 1 PK.4: Tren Capaian (%) Bulanan vs Target 90%
    // ----------------------------------------------------
    const chartPk4CapaianEl = document.querySelector("#chart-pk4-capaian");
    if (chartPk4CapaianEl) {
        <?php
        $pk4MonthsJson = json_encode(['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des']);
        $pk4ChartSeries = [];
        for ($m = 1; $m <= 12; $m++) {
            if (isset($monthlyMap[$m]) && in_array($monthlyMap[$m]['status'], ['achieved', 'in_progress'])) {
                $pk4ChartSeries[] = (float)$monthlyMap[$m]['cumulative_pct'];
            } else {
                $pk4ChartSeries[] = null;
            }
        }
        $pk4SeriesJson = json_encode($pk4ChartSeries);
        ?>
        const pk4Months = <?= $pk4MonthsJson ?>;
        const pk4Data = <?= $pk4SeriesJson ?>;

        const optionsPk4Capaian = {
            series: [{
                name: 'Capaian Kumulatif PK.4 (Monitoring APT)',
                data: pk4Data
            }],
            chart: {
                height: 270,
                type: 'area',
                toolbar: { show: false },
                zoom: { enabled: false }
            },
            colors: ['#f59e0b'],
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    if (val === null || val === undefined) return '';
                    return val + "%";
                },
                offsetY: -6,
                style: {
                    fontSize: '10px',
                    fontWeight: 700,
                    colors: ['#0A1628']
                },
                background: {
                    enabled: true,
                    foreColor: '#fff',
                    borderRadius: 4,
                    padding: 3,
                    opacity: 0.9
                }
            },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 95, 100]
                }
            },
            markers: {
                size: 4,
                colors: ['#f59e0b'],
                strokeColors: '#ffffff',
                strokeWidth: 2,
                hover: { size: 6 }
            },
            xaxis: {
                categories: pk4Months,
                labels: {
                    style: { colors: '#64748b', fontSize: '11px', fontWeight: 600 }
                }
            },
            yaxis: {
                min: 0,
                max: 105,
                tickAmount: 5,
                labels: {
                    formatter: function(val) { return val + "%"; },
                    style: { colors: '#64748b', fontSize: '11px' }
                }
            },
            annotations: {
                yaxis: [{
                    y: 100,
                    borderColor: '#10b981',
                    borderWidth: 2,
                    strokeDashArray: 4,
                    label: {
                        borderColor: '#10b981',
                        style: {
                            color: '#ffffff',
                            background: '#10b981',
                            fontSize: '11px',
                            fontWeight: 700
                        },
                        text: 'Target Tahunan: 100% (300 Perangkat)'
                    }
                }]
            },
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 3
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        if (val === null || val === undefined) return "Rencana / Belum Terealisasi";
                        return val + "% (Target Tahunan: 100% / 300 Perangkat)";
                    }
                }
            }
        };

        new ApexCharts(chartPk4CapaianEl, optionsPk4Capaian).render();
    }

    // ----------------------------------------------------
    // CHART 2 PK.4: Komparasi Realisasi vs Target 7 Jenis Perangkat yang Sudah Dimonitor
    // ----------------------------------------------------
    const chartPk4MonitoredEl = document.querySelector("#chart-pk4-monitored-devices");
    if (chartPk4MonitoredEl) {
        const pk4DeviceCategories = [
            '★ Radio Rig (Prioritas #1)',
            '★ Handy Talkie (Prioritas #2)',
            '★ Wireless AP (Prioritas #3)',
            'Microwave Link',
            'Low Power (TWS/IoT)',
            'Pemancar FM & TV',
            'Set Top Box (STB)'
        ];

        const optionsPk4Monitored = {
            series: [
                {
                    name: 'Realisasi Lapangan (Unit)',
                    data: [12, 38, 75, 738, 132, 8, 3]
                },
                {
                    name: 'Target Rencana (Unit)',
                    data: [20, 35, 40, 100, 35, 10, 15]
                }
            ],
            chart: {
                type: 'bar',
                height: 310,
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    dataLabels: {
                        position: 'top',
                    },
                    barHeight: '70%',
                    borderRadius: 4
                }
            },
            colors: ['#f59e0b', '#94a3b8'],
            dataLabels: {
                enabled: true,
                offsetX: 28,
                style: {
                    fontSize: '11px',
                    fontWeight: 700,
                    colors: ['#0f172a']
                },
                formatter: function (val) {
                    return val + " Unit";
                }
            },
            stroke: {
                show: true,
                width: 1,
                colors: ['#fff']
            },
            xaxis: {
                categories: pk4DeviceCategories,
                labels: {
                    formatter: function (val) {
                        return val;
                    },
                    style: { colors: '#64748b', fontSize: '11px' }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: ['#b45309', '#b45309', '#b45309', '#334155', '#334155', '#334155', '#334155'],
                        fontWeight: 600,
                        fontSize: '11px'
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '11px',
                markers: { radius: 12 }
            },
            grid: {
                borderColor: '#e2e8f0',
                strokeDashArray: 3
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function (val) {
                        return val + " Unit";
                    }
                }
            }
        };

        const chartPk4Monitored = new ApexCharts(chartPk4MonitoredEl, optionsPk4Monitored);
        chartPk4Monitored.render();
    }

    // ----------------------------------------------------
    // CHART 3 PK.4: Proporsi Volume Sampling 7 Perangkat (Total 1.006 Unit)
    // ----------------------------------------------------
    const chartPk4DonutEl = document.querySelector("#chart-pk4-donut-devices");
    if (chartPk4DonutEl) {
        const optionsPk4Donut = {
            series: [12, 38, 75, 738, 132, 8, 3],
            labels: [
                'Radio Rig (Prioritas #1)',
                'Handy Talkie (Prioritas #2)',
                'Wireless AP (Prioritas #3)',
                'Microwave Link',
                'Low Power (TWS/IoT)',
                'Pemancar FM & TV',
                'Set Top Box (STB)'
            ],
            chart: {
                type: 'donut',
                height: 250
            },
            colors: ['#f59e0b', '#d97706', '#b45309', '#0284c7', '#06b6d4', '#8b5cf6', '#64748b'],
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            name: { fontSize: '11px', fontWeight: 600 },
                            value: {
                                fontSize: '15px',
                                fontWeight: 700,
                                formatter: function (val) {
                                    return val + ' Unit';
                                }
                            },
                            total: {
                                show: true,
                                label: 'Total Termonitor',
                                fontSize: '10px',
                                formatter: function (w) {
                                    return '1.006 Unit';
                                }
                            }
                        }
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            legend: {
                position: 'bottom',
                fontSize: '10px',
                markers: { radius: 10 }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function (val) {
                        const total = 1006;
                        const pct = ((val / total) * 100).toFixed(1);
                        return val + " Unit (" + pct + "%)";
                    }
                }
            }
        };

        const chartPk4Donut = new ApexCharts(chartPk4DonutEl, optionsPk4Donut);
        chartPk4Donut.render();
    }

    // Trigger resize saat tab chart dibuka agar ApexCharts me-render ukuran secara presisi
    const tabPk4ChartBtn = document.getElementById('tab-pk4-chart-btn');
    if (tabPk4ChartBtn) {
        tabPk4ChartBtn.addEventListener('shown.bs.tab', function () {
            window.dispatchEvent(new Event('resize'));
        });
    }
    <?php elseif ($selectedCode === 'PK.6' && $selectedYear === 2026): ?>
    // ----------------------------------------------------
    // CHART PK.6: Tren Capaian SLA SMFR (Jan-Mei 2026) vs Target 86%
    // ----------------------------------------------------
    const chartPk6SlaEl = document.querySelector("#chart-pk6-sla");
    if (chartPk6SlaEl) {
        const pk6Months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        const pk6DataGabungan = [94.38, 92.50, 92.50, 92.50, 89.38, null, null, null, null, null, null, null];
        const pk6DataTetapTransp = [88.75, 95.00, 95.00, 95.00, 88.75, null, null, null, null, null, null, null];
        const pk6DataBergerak = [100.00, 90.00, 90.00, 90.00, 90.00, null, null, null, null, null, null, null];

        const optionsPk6Sla = {
            series: [
                {
                    name: 'SLA Gabungan UPT (Capaian PK)',
                    type: 'area',
                    data: pk6DataGabungan
                },
                {
                    name: 'Rata-rata Tetap & Transportable',
                    type: 'line',
                    data: pk6DataTetapTransp
                },
                {
                    name: 'Stasiun Bergerak (Mobil Mon DF)',
                    type: 'line',
                    data: pk6DataBergerak
                }
            ],
            chart: {
                height: 270,
                type: 'line',
                toolbar: { show: false },
                zoom: { enabled: false }
            },
            colors: ['#10b981', '#0070E0', '#f59e0b'],
            stroke: {
                curve: 'smooth',
                width: [3, 2, 2],
                dashArray: [0, 0, 3]
            },
            fill: {
                type: ['gradient', 'solid', 'solid'],
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 95, 100]
                }
            },
            dataLabels: {
                enabled: true,
                enabledOnSeries: [0],
                formatter: function (val) {
                    if (val === null || val === undefined) return '';
                    return val + "%";
                },
                offsetY: -6,
                style: {
                    fontSize: '11px',
                    fontWeight: 700,
                    colors: ['#0A1628']
                },
                background: {
                    enabled: true,
                    foreColor: '#fff',
                    borderRadius: 4,
                    padding: 3,
                    opacity: 0.9
                }
            },
            markers: {
                size: [5, 4, 4],
                strokeColors: '#ffffff',
                strokeWidth: 2,
                hover: { size: 7 }
            },
            xaxis: {
                categories: pk6Months,
                labels: {
                    style: { colors: '#64748b', fontSize: '11px', fontWeight: 600 }
                }
            },
            yaxis: {
                min: 70,
                max: 105,
                tickAmount: 7,
                labels: {
                    formatter: function(val) { return val + "%"; },
                    style: { colors: '#64748b', fontSize: '11px' }
                }
            },
            annotations: {
                yaxis: [{
                    y: 86,
                    borderColor: '#ef4444',
                    borderWidth: 2,
                    strokeDashArray: 4,
                    label: {
                        borderColor: '#ef4444',
                        style: {
                            color: '#ffffff',
                            background: '#ef4444',
                            fontSize: '10px',
                            fontWeight: 700
                        },
                        text: 'Target PK: 86% SLA'
                    }
                }]
            },
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 3
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '11px',
                markers: { radius: 12 }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        if (val === null || val === undefined) return "Rencana";
                        return val + "% SLA";
                    }
                }
            }
        };

        new ApexCharts(chartPk6SlaEl, optionsPk6Sla).render();
    }
    <?php elseif ($selectedCode === 'PK.7' && $selectedYear === 2026): ?>
    // ----------------------------------------------------
    // CHART PK.7: Distribusi Sanksi Denda per Entitas & Objek (2026)
    // ----------------------------------------------------
    const chartPk7El = document.querySelector("#chart-pk7-denda");
    if (chartPk7El) {
        const pk7Labels = [
            'PLN (Persero)',
            'PT Lintasmaya Multi Media',
            'PT Perdana Teknologi Persada',
            'PT Hamparan Anugrah Abadi'
        ];
        const pk7Amounts = [135935800, 20097000, 30030000, 15195600];
        const pk7Objects = [17, 1, 2, 4];

        const optionsPk7 = {
            series: [
                {
                    name: 'Nilai Denda Pokok (Rp)',
                    type: 'column',
                    data: pk7Amounts
                },
                {
                    name: 'Jumlah Objek Pelanggaran',
                    type: 'line',
                    data: pk7Objects
                }
            ],
            chart: {
                height: 275,
                type: 'line',
                toolbar: { show: false }
            },
            stroke: {
                width: [0, 3],
                curve: 'smooth'
            },
            plotOptions: {
                bar: {
                    columnWidth: '40%',
                    borderRadius: 4,
                    dataLabels: {
                        position: 'top'
                    }
                }
            },
            dataLabels: {
                enabled: true,
                enabledOnSeries: [0],
                formatter: function (val) {
                    return 'Rp ' + (val / 1000000).toFixed(1) + ' Jt';
                },
                offsetY: -15,
                style: {
                    fontSize: '10px',
                    fontWeight: 700,
                    colors: ['#0A1628']
                }
            },
            colors: ['#ef4444', '#f59e0b'],
            xaxis: {
                categories: pk7Labels,
                labels: {
                    style: {
                        fontSize: '11px',
                        fontWeight: 600,
                        colors: '#334155'
                    }
                }
            },
            yaxis: [
                {
                    title: {
                        text: 'Nilai Denda (Rupiah)',
                        style: { fontSize: '11px', fontWeight: 600, color: '#ef4444' }
                    },
                    labels: {
                        formatter: function (val) {
                            return 'Rp ' + (val / 1000000).toFixed(0) + ' Jt';
                        },
                        style: { fontSize: '10px', colors: '#64748b' }
                    }
                },
                {
                    opposite: true,
                    title: {
                        text: 'Jumlah Objek / Stasiun',
                        style: { fontSize: '11px', fontWeight: 600, color: '#f59e0b' }
                    },
                    min: 0,
                    max: 20,
                    tickAmount: 4,
                    labels: {
                        formatter: function (val) {
                            return val + ' Objek';
                        },
                        style: { fontSize: '10px', colors: '#64748b' }
                    }
                }
            ],
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 3
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '11px',
                markers: { radius: 12 }
            },
            tooltip: {
                theme: 'dark',
                shared: true,
                intersect: false,
                y: {
                    formatter: function (val, { seriesIndex }) {
                        if (seriesIndex === 0) {
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(val);
                        }
                        return val + ' Objek Stasiun';
                    }
                }
            }
        };

        new ApexCharts(chartPk7El, optionsPk7).render();
    }
    <?php elseif ($selectedCode === 'PK.8' && $selectedYear === 2026): ?>
    // ----------------------------------------------------
    // CHART PK.8: Komparasi Throughput Download & Upload 3 Operator (2026)
    // ----------------------------------------------------
    const chartPk8El = document.querySelector("#chart-pk8-throughput");
    if (chartPk8El) {
        const pk8Categories = [
            'PPU & IKN (Mei 2026)',
            'Kubar (Juli 2026)',
            'Samarinda (Ags 2026)',
            'Berau (Ags 2026)',
            'Samarinda (Sep 2026)',
            'Paser (Okt 2026)'
        ];

        const optionsPk8Throughput = {
            series: [
                {
                    name: 'XL Axiata (Download)',
                    data: [98.29, 60.51, 137.48, 91.08, 126.05, 126.58]
                },
                {
                    name: 'Telkomsel (Download)',
                    data: [92.59, 56.47, 104.52, 89.66, 89.69, 83.57]
                },
                {
                    name: 'Indosat Ooredoo (Download)',
                    data: [52.12, 79.84, 87.31, 49.96, 67.80, 61.41]
                },
                {
                    name: 'XL Axiata (Upload)',
                    data: [35.01, 22.31, 77.55, 46.13, 85.48, 55.37]
                },
                {
                    name: 'Telkomsel (Upload)',
                    data: [34.27, 24.65, 28.77, 31.10, 25.66, 23.95]
                },
                {
                    name: 'Indosat Ooredoo (Upload)',
                    data: [7.81, 34.56, 30.92, 15.39, 30.97, 19.03]
                }
            ],
            chart: {
                type: 'bar',
                height: 310,
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    borderRadius: 3,
                    dataLabels: {
                        position: 'top'
                    }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return val.toFixed(1);
                },
                offsetY: -16,
                style: {
                    fontSize: '9px',
                    fontWeight: 700,
                    colors: ['#0A1628']
                }
            },
            colors: ['#0284c7', '#ef4444', '#f59e0b', '#38bdf8', '#f87171', '#fbbf24'],
            stroke: {
                show: true,
                width: 1,
                colors: ['#fff']
            },
            xaxis: {
                categories: pk8Categories,
                labels: {
                    style: {
                        fontSize: '11px',
                        fontWeight: 600,
                        colors: '#334155'
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'Kecepatan Rata-rata (Mbps)',
                    style: { fontSize: '11px', fontWeight: 600, color: '#64748b' }
                },
                max: 160,
                labels: {
                    formatter: function (val) {
                        return val + ' Mbps';
                    },
                    style: { fontSize: '10px', colors: '#64748b' }
                }
            },
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 3
            },
            legend: {
                position: 'top',
                horizontalAlign: 'center',
                fontSize: '11px',
                markers: { radius: 12 }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function (val) {
                        return val + " Mbps";
                    }
                }
            }
        };

        new ApexCharts(chartPk8El, optionsPk8Throughput).render();
    }
    <?php endif; ?>
});
</script>
