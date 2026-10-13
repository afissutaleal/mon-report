<?php
require __DIR__ . '/../includes/auth.php';
$pdo = getDbConnection();

echo "=== 1. UPDATING PERJANJIAN_KINERJA (PK.6 - 2026) ===\n";
$stmtPk = $pdo->prepare("
    UPDATE perjanjian_kinerja SET 
        realization = 92.25,
        target_annual = 86,
        notes = 'Realisasi rata-rata s.d. Mei 2026: 92.25% (Target: 86%, Kualitas: 100%). Januari: 94.38%, Februari: 92.50%, Maret: 92.50%, April: 92.50%, Mei: 89.38%. Terjaganya operasional 3 Stasiun Transportable, 1 Stasiun Bergerak DF, dan Stasiun Tetap VUHF Samarinda.'
    WHERE code = 'PK.6' AND year = 2026
");
$stmtPk->execute();
echo "Updated PK.6 in perjanjian_kinerja (Realization: 92.25%).\n";

echo "\n=== 2. UPDATING PK_MONTHLY_REKAP (PK.6 - 2026) ===\n";
$monthsData = [
    1 => [
        'title' => 'Laporan Capaian Kinerja Perangkat SMFR Januari 2026',
        'detail' => 'SLA Tetap/Transportable 88.75% (Kutim 75%, Samarinda 80%, Bpp/Berau 100%), Mobil DF 100%, SLA Gabungan 94.38% (Kualitas 100%). Okupansi SMSN 4x.',
        'target' => 'SLA 86%',
        'real' => 'SLA 94.38%',
        'cum' => 94.38,
        'status' => 'achieved',
        'nd' => '60/BALMON.64/SP.03.01/02/2026',
        'date' => '02 Feb 2026'
    ],
    2 => [
        'title' => 'Laporan Capaian Kinerja Perangkat SMFR Februari 2026',
        'detail' => 'SLA Tetap/Transportable 95.00%, Mobil DF 90.00% (Front panel ESMD rusak), SLA Gabungan 92.50% (Kualitas 100%). Okupansi SMSN 6x.',
        'target' => 'SLA 86%',
        'real' => 'SLA 92.50%',
        'cum' => 93.44,
        'status' => 'achieved',
        'nd' => '127/BALMON.64/SP.03.01/03/2026',
        'date' => '03 Mar 2026'
    ],
    3 => [
        'title' => 'Laporan Capaian Kinerja Perangkat SMFR Maret 2026',
        'detail' => 'SLA Tetap/Transportable 95.00%, Mobil DF 90.00%, SLA Gabungan 92.50% (Kualitas 100%). Okupansi SMSN 4x. Pemanfaatan Posko Ramadan & Idul Fitri.',
        'target' => 'SLA 86%',
        'real' => 'SLA 92.50%',
        'cum' => 93.13,
        'status' => 'achieved',
        'nd' => '178/BALMON.64/SP.03.01/04/2026',
        'date' => '01 Apr 2026'
    ],
    4 => [
        'title' => 'Laporan Capaian Kinerja Perangkat SMFR April 2026',
        'detail' => 'SLA Tetap/Transportable 95.00%, Mobil DF 90.00%, SLA Gabungan 92.50% (Kualitas 100%). Okupansi SMSN 4x. Penanganan Gangguan PT Kideco (SPT 46).',
        'target' => 'SLA 86%',
        'real' => 'SLA 92.50%',
        'cum' => 92.97,
        'status' => 'achieved',
        'nd' => '246/BALMON.64/SP.03.01/05/2026',
        'date' => '04 Mei 2026'
    ],
    5 => [
        'title' => 'Laporan Capaian Kinerja Perangkat SMFR Mei 2026',
        'detail' => 'SLA Tetap/Transportable 88.75% (Receiver Berau rusak 75%), Mobil DF 90.00%, SLA Gabungan 89.38% (Kualitas 100%). Okupansi SMSN 4x. Operasi Penertiban Tahap I.',
        'target' => 'SLA 86%',
        'real' => 'SLA 89.38%',
        'cum' => 92.25,
        'status' => 'achieved',
        'nd' => '313/BALMON.64/SP.03.01/06/2026',
        'date' => '03 Jun 2026'
    ]
];

$stmtMonth = $pdo->prepare("
    UPDATE pk_monthly_rekap SET
        activity_title = ?,
        activity_detail = ?,
        target_val = ?,
        realization_val = ?,
        cumulative_pct = ?,
        status = ?,
        report_number = ?,
        report_date = ?
    WHERE pk_code = 'PK.6' AND year = 2026 AND month_num = ?
");

foreach ($monthsData as $mNum => $mData) {
    $stmtMonth->execute([
        $mData['title'],
        $mData['detail'],
        $mData['target'],
        $mData['real'],
        $mData['cum'],
        $mData['status'],
        $mData['nd'],
        $mData['date'],
        $mNum
    ]);
    echo "Updated Bulan $mNum: {$mData['real']} (ND: {$mData['nd']}).\n";
}

echo "\nDone successfully!\n";
