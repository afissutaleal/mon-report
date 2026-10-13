<?php
// database/seed_pk8_bontang_kutim_and_schedules.php
require __DIR__ . '/../includes/auth.php';
$pdo = getDbConnection();

echo "=== 1. SEED / UPDATE MONITORING_REPORTS (CATEGORY 11 - QOS) ===\n";

// A. Insert/Update MON-QOS-2026-008 (Kota Bontang dan Kab. Kutai Timur)
$bontangKutimTech = [
    'warrant_number' => '24/BALMON.64/KP.01.06/02/2026 (23 Februari 2026)',
    'nd_number' => '142/BALMON.64/SP.02.06/03/2026 (06 Maret 2026)',
    'operator_name' => 'Telkomsel, Indosat Ooredoo Hutchison, XL Axiata',
    'network_technology' => '4G LTE',
    'download_throughput' => 'Tsel: 85.20 Mbps | XL: 88.60 Mbps | Isat: 72.40 Mbps',
    'upload_throughput' => 'XL: 32.10 Mbps | Tsel: 28.40 Mbps | Isat: 22.60 Mbps',
    'latency_rtt' => 'Normal (<55 ms)',
    'packet_loss' => '0.8%',
    'qos_compliance' => 'Memenuhi Standar Kualitas (QoS Baik)',
    'measurement_route' => 'Drive Test Kota Bontang & Kab. Kutai Timur (min 5 jam/wilayah) & Static Test di 8 POI (5 POI Bontang & 3 POI Sangatta Kutim)',
    'team_leader' => 'Fathul Mufiidah, S.Si (Ketua Tim)',
    'team_members' => 'Tantowi Pahala Hamonangan Sianipar, S.Kom, Ahmad Anwar',
    'devices_used' => '6 Unit Probe Handphone (Telkomsel, XL, Indosat), Software SmartMon, Freerider Backpack System Gen 4, Inverter Mobil Roda 4',
    'test_results' => [
        'drive_test' => [
            'Bontang' => [
                'Indosat' => ['overall_success' => 98.6, 'passed' => 'Dominan Hijau', 'failed' => '1.4%'],
                'Telkomsel' => ['overall_success' => 95.0, 'passed' => 'Dominan Hijau', 'failed' => '5.0%'],
                'XL' => ['overall_success' => 89.7, 'passed' => 'Dominan Hijau', 'failed' => '10.3%']
            ],
            'Kutai_Timur' => [
                'Telkomsel' => ['overall_success' => 88.5, 'passed' => 'Dominan Hijau', 'failed' => '11.5%'],
                'Indosat' => ['overall_success' => 79.1, 'passed' => 'Dominan Hijau', 'failed' => '20.9%'],
                'XL' => ['overall_success' => 73.9, 'passed' => 'Dominan Hijau', 'failed' => '26.1%']
            ]
        ],
        'static_test' => [
            'Bontang' => [
                'Telkomsel' => 'Konsisten mencatat tingkat keberhasilan tertinggi dan stabil di seluruh POI Bontang',
                'Indosat' => 'Menunjukkan peningkatan success rate tinggi (>80%), penurunan di browsing & WA call',
                'XL' => 'Cukup tinggi, namun di POI Ramayana Bontang mengalami penurunan signifikan'
            ],
            'Kutai_Timur' => [
                'XL' => 'Konsisten mencatat tingkat keberhasilan tertinggi hingga 100% pada beberapa parameter',
                'Telkomsel' => 'Tingkat keberhasilan baik dan stabil',
                'Indosat' => 'Tingkat kegagalan cukup besar pada browsing dan ping'
            ]
        ]
    ]
];

$checkBontang = $pdo->prepare("SELECT id FROM monitoring_reports WHERE report_code = 'MON-QOS-2026-008'");
$checkBontang->execute();
$bontangExists = $checkBontang->fetchColumn();

if ($bontangExists) {
    $stmt = $pdo->prepare("
        UPDATE monitoring_reports 
        SET title = ?, target_name = ?, location = ?, date_monitored = ?, technical_data = ?, updated_at = CURRENT_TIMESTAMP
        WHERE report_code = 'MON-QOS-2026-008'
    ");
    $stmt->execute([
        'Pengukuran Kualitas Layanan Infrastruktur Digital di Kota Bontang dan Kab. Kutai Timur (Februari 2026)',
        'Jaringan Bergerak Seluler (Telkomsel, Indosat, XL Axiata)',
        'Kota Bontang dan Sangatta Kabupaten Kutai Timur, Kalimantan Timur',
        '2026-02-28',
        json_encode($bontangKutimTech, JSON_PRETTY_PRINT)
    ]);
    echo "[UPDATED] MON-QOS-2026-008 (Kota Bontang dan Kab. Kutai Timur)\n";
} else {
    $stmt = $pdo->prepare("
        INSERT INTO monitoring_reports (
            report_code, category_id, title, user_id, target_name, frequency, location,
            latitude, longitude, date_monitored, status, technical_data, created_at, updated_at
        ) VALUES (
            'MON-QOS-2026-008', 11,
            'Pengukuran Kualitas Layanan Infrastruktur Digital di Kota Bontang dan Kab. Kutai Timur (Februari 2026)',
            1, 'Jaringan Bergerak Seluler (Telkomsel, Indosat, XL Axiata)',
            '900/1800/2100/2300 MHz (4G LTE)',
            'Kota Bontang dan Sangatta Kabupaten Kutai Timur, Kalimantan Timur',
            0.120589, 117.484579, '2026-02-28', 'verified', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        )
    ");
    $stmt->execute([json_encode($bontangKutimTech, JSON_PRETTY_PRINT)]);
    echo "[INSERTED] MON-QOS-2026-008 (Kota Bontang dan Kab. Kutai Timur)\n";
}

// B. Update MON-QOS-2026-006 (Kab. Kutai Kartanegara) with exact Executive Summary numbers
$kukarTech = [
    'warrant_number' => '57/BALMON.64/KP.01.06/04/2026 (17 April 2026)',
    'nd_number' => '238/BALMON.64/SP.02.06/04/2026 (30 April 2026)',
    'operator_name' => 'Telkomsel, Indosat, XL Axiata',
    'network_technology' => '4G LTE',
    'download_throughput' => 'XL: 92.20 Mbps | Tsel: 71.77 Mbps | Isat: 68.58 Mbps',
    'upload_throughput' => 'XL: 24.82 Mbps | Isat: 18.53 Mbps | Tsel: 12.31 Mbps',
    'latency_rtt' => 'Telkomsel: 96.66 ms | XL: 98.42 ms | Indosat: 99.70 ms',
    'packet_loss' => '0%',
    'qos_compliance' => 'Memenuhi Standar Kualitas (QoS Baik)',
    'measurement_route' => 'Drive Test Poros Tenggarong & Static Test di 3 POI: Taman Tanjong Tenggarong, Kantor Bupati Kutai Kartanegara, RSUD Aji Muhammad Parikesit',
    'team_leader' => 'Rendy Tri Atmadja, ST',
    'team_members' => 'Muclis Ependi, SH',
    'devices_used' => '3 Unit Probe Handphone (Telkomsel, XL, Indosat), Software SmartMon, Freerider Backpack System Gen 4, Kendaraan Roda 4',
    'test_results' => [
        'speed_test' => [
            'XL' => ['dl_speed' => 92.20, 'ul_speed' => 24.82, 'success_rate' => 81.47, 'rank_dl' => 1, 'rank_ul' => 1],
            'Telkomsel' => ['dl_speed' => 71.77, 'ul_speed' => 12.31, 'success_rate' => 92.08, 'rank_dl' => 2, 'rank_ul' => 3],
            'Indosat' => ['dl_speed' => 68.58, 'ul_speed' => 18.53, 'success_rate' => 87.15, 'rank_dl' => 3, 'rank_ul' => 2]
        ],
        'latency_rtt' => [
            'Telkomsel' => 96.66,
            'XL' => 98.42,
            'Indosat' => 99.70
        ],
        'youtube' => [
            'XL' => ['quality_720p' => 94.77, 'quality_480p' => 4.14],
            'Telkomsel' => ['quality_720p' => 98.23, 'quality_480p' => 1.02],
            'Indosat' => ['quality_720p' => 98.31, 'quality_480p' => 1.23]
        ],
        'radio_4g' => [
            'Telkomsel' => ['rsrp' => -88.54, 'total_sample' => 70071, 'bad_sample' => 102, 'bad_rate' => 0.15],
            'Indosat' => ['rsrp' => -94.23, 'total_sample' => 39835, 'bad_sample' => 43, 'bad_rate' => 0.11],
            'XL' => ['rsrp' => -105.53, 'total_sample' => 53269, 'bad_sample' => 155, 'bad_rate' => 0.29]
        ]
    ]
];

$stmt = $pdo->prepare("
    UPDATE monitoring_reports 
    SET title = ?, location = ?, technical_data = ?, updated_at = CURRENT_TIMESTAMP
    WHERE report_code = 'MON-QOS-2026-006'
");
$stmt->execute([
    'Pengukuran Kualitas Layanan Infrastruktur Digital di Kab. Kutai Kartanegara (April 2026)',
    'Tenggarong & Poros Samarinda-Tenggarong, Kab. Kutai Kartanegara',
    json_encode($kukarTech, JSON_PRETTY_PRINT)
]);
echo "[UPDATED] MON-QOS-2026-006 (Kab. Kutai Kartanegara with exact official data)\n";


echo "\n=== 2. SINKRONISASI DAFTAR SURAT TUGAS (MONITORING_SCHEDULES) ===\n";

// Update SPT 24 (Februari - Bontang & Kutim)
$stmt = $pdo->prepare("
    UPDATE monitoring_schedules 
    SET title = 'Pengukuran Kualitas Layanan Infrastruktur Digital di Kota Bontang dan Kabupaten Kutai Timur',
        location = 'Kota Bontang dan Kabupaten Kutai Timur',
        target_object = 'Drive Test & 8 POI Static Test (5 POI Bontang, 3 POI Kutim)',
        start_date = '2026-02-24',
        end_date = '2026-02-28',
        leader_id = 15,
        member_names = 'Tantowi Pahala Hamonangan Sianipar, S.Kom, Ahmad Anwar',
        category_id = 11,
        category_ids = '[11]',
        status = 'completed',
        notes = 'Sesuai Nota Dinas No. 142/BALMON.64/SP.02.06/03/2026 tgl 06 Maret 2026. Laporan hasil kegiatan pengukuran kualitas layanan digital di Kota Bontang dan Kab. Kutai Timur.'
    WHERE spt_number LIKE '%24/%'
");
$stmt->execute();
echo "[UPDATED SCHEDULE] SPT 24 (Bontang & Kutim -> Cat 11, Leader: Fathul Mufiidah)\n";

// Update SPT 57 (April - Kukar)
$stmt = $pdo->prepare("
    UPDATE monitoring_schedules 
    SET title = 'Pengukuran Kualitas Layanan Infrastruktur Digital di Kabupaten Kutai Kartanegara',
        location = 'Kabupaten Kutai Kartanegara',
        target_object = 'Drive Test & 3 POI Static Test (Taman Tanjong, Kantor Bupati Kukar, RSUD Parikesit)',
        start_date = '2026-04-20',
        end_date = '2026-04-23',
        leader_id = 13,
        member_names = 'Muclis Ependi, SH',
        category_id = 11,
        category_ids = '[11]',
        status = 'completed',
        notes = 'Sesuai Nota Dinas No. 238/BALMON.64/SP.02.06/04/2026 tgl 30 April 2026. Laporan hasil kegiatan pengukuran kualitas layanan digital di Kab. Kutai Kartanegara.'
    WHERE spt_number LIKE '%57/%'
");
$stmt->execute();
echo "[UPDATED SCHEDULE] SPT 57 (Kukar -> Cat 11, Leader: Rendy Tri Atmadja)\n";

// Update SPT 78 (Mei - PPU & IKN)
$stmt = $pdo->prepare("
    UPDATE monitoring_schedules 
    SET title = 'Pengukuran Kualitas Layanan Infrastruktur Digital di Kabupaten Penajam Paser Utara & Kawasan IKN',
        location = 'Kabupaten Penajam Paser Utara & KIPP IKN',
        target_object = 'Drive Test & 5 POI Static Test (Pelabuhan Ferry, Rusun ASN IKN, Perkantoran IKN, Nusantara Park, Kemenko IKN)',
        start_date = '2026-05-18',
        end_date = '2026-05-21',
        leader_id = 9,
        member_names = 'Maksudi',
        category_id = 11,
        category_ids = '[11]',
        status = 'completed',
        notes = 'Sesuai Nota Dinas No. 301/BALMON.64/SP.02.06/06/2026 tgl 02 Juni 2026. Laporan hasil kegiatan pengukuran kualitas layanan digital di Kab. Penajam Paser Utara.'
    WHERE spt_number LIKE '%78/%'
");
$stmt->execute();
echo "[UPDATED SCHEDULE] SPT 78 (PPU & IKN -> Cat 11, Leader: Muhammad Alif Anhasdio Fajri)\n";

// Update SPT 104 (Juli - Kubar)
$stmt = $pdo->prepare("
    UPDATE monitoring_schedules 
    SET title = 'Inspeksi Stasiun Microwave Link (Open Shelter) dan Pengukuran Kualitas Layanan di Kab. Kutai Barat',
        location = 'Kabupaten Kutai Barat',
        target_object = '328 Link Microwave Link (52 Site), Drive Test & 4 POI Static Test QoS',
        start_date = '2026-07-20',
        end_date = '2026-07-23',
        leader_id = 15,
        member_names = 'Binti Herwindasari, A.md, Ahmad Anwar',
        category_id = 2,
        category_ids = '[2,11]',
        status = 'completed',
        notes = 'Sesuai Nota Dinas No. 415/BALMON.64/SP.02.06/07/2026 tgl 31 Juli 2026. Risalah resmi T-191, T-192, T-193, T-194/BALMON.64/SP.03.03/07/2026.'
    WHERE spt_number LIKE '%104/%'
");
$stmt->execute();
echo "[UPDATED SCHEDULE] SPT 104 (Kubar -> Cat 2 & 11, Leader: Fathul Mufiidah)\n";

// Update SPT 122 (Agustus - Berau)
$stmt = $pdo->prepare("
    UPDATE monitoring_schedules 
    SET category_id = 11, category_ids = '[11]'
    WHERE spt_number LIKE '%122/%'
");
$stmt->execute();
echo "[UPDATED SCHEDULE] SPT 122 (Berau -> Cat 11)\n";

// Update SPT 142 (September/Oktober - Paser)
$stmt = $pdo->prepare("
    UPDATE monitoring_schedules 
    SET category_id = 11, category_ids = '[11]'
    WHERE spt_number LIKE '%142/%'
");
$stmt->execute();
echo "[UPDATED SCHEDULE] SPT 142 (Paser -> Cat 11)\n";


echo "\n=== 3. SINKRONISASI PK_MONTHLY_REKAP (PK.8 - QOS KUALITAS LAYANAN) ===\n";

$rekapUpdates = [
    2 => [
        'activity_title' => 'Drive Test & Static Test QoS Kota Bontang dan Kab. Kutai Timur',
        'activity_detail' => 'Drive Test (5 jam) dan Static Test di 8 POI (5 POI Bontang & 3 POI Sangatta Kutim)',
        'report_number' => '142/BALMON.64/SP.02.06/03/2026',
        'report_date' => '06 Mar 2026',
        'app_link' => 'SPT: 24/BALMON.64/KP.01.06/02/2026'
    ],
    4 => [
        'activity_title' => 'Drive Test & Static Test QoS Kab. Kutai Kartanegara',
        'activity_detail' => 'Drive Test (2 jam) dan Static Test di 3 POI (Taman Tanjong, Kantor Bupati Kukar, RSUD Parikesit)',
        'report_number' => '238/BALMON.64/SP.02.06/04/2026',
        'report_date' => '30 Apr 2026',
        'app_link' => 'SPT: 57/BALMON.64/KP.01.06/04/2026'
    ],
    5 => [
        'activity_title' => 'Drive Test & Static Test QoS Kawasan IKN & Kab. Penajam Paser Utara',
        'activity_detail' => 'Drive Test (2 jam) dan Static Test di 5 POI KIPP IKN & Pelabuhan Ferry Penajam',
        'report_number' => '301/BALMON.64/SP.02.06/06/2026',
        'report_date' => '02 Jun 2026',
        'app_link' => 'SPT: 78/BALMON.64/KP.01.06/05/2026'
    ],
    7 => [
        'activity_title' => 'Drive Test, Static Test QoS & Validasi 328 MWL Open Shelter Kab. Kutai Barat',
        'activity_detail' => 'Drive Test (5 jam), Static Test di 4 POI & Inspeksi Validasi 328 Link MWL Open Shelter (52 Site)',
        'report_number' => '415/BALMON.64/SP.02.06/07/2026',
        'report_date' => '31 Jul 2026',
        'app_link' => 'SPT: 104/BALMON.64/KP.01.06/07/2026'
    ],
    8 => [
        'activity_title' => 'Drive Test & Static Test QoS Kota Samarinda & Kab. Berau',
        'activity_detail' => 'Drive Test & Static Test Berau (5 POI) dan Samarinda (3 POI)',
        'report_number' => 'ND Tgl 02 September 2026',
        'report_date' => '02 Sep 2026',
        'app_link' => 'SPT: 120 & 122/BALMON.64/KP.01.06/08/2026'
    ],
    9 => [
        'activity_title' => 'Drive Test & Static Test QoS Kota Samarinda & Kab. Paser',
        'activity_detail' => 'Drive Test & Static Test Samarinda (4 POI) dan Paser (3 POI)',
        'report_number' => '526/BALMON.64/SP.02.06/10/2026',
        'report_date' => '06 Okt 2026',
        'app_link' => 'SPT: 133 & 142/BALMON.64/KP.01.06/09/2026'
    ]
];

foreach ($rekapUpdates as $mNum => $rData) {
    $stmt = $pdo->prepare("
        UPDATE pk_monthly_rekap 
        SET activity_title = ?, activity_detail = ?, report_number = ?, report_date = ?, app_link = ?
        WHERE pk_code = 'PK.8' AND year = 2026 AND month_num = ?
    ");
    $stmt->execute([
        $rData['activity_title'],
        $rData['activity_detail'],
        $rData['report_number'],
        $rData['report_date'],
        $rData['app_link'],
        $mNum
    ]);
    echo "[UPDATED REKAP] Bulan $mNum: {$rData['activity_title']} | {$rData['report_number']}\n";
}

echo "=== SINKRONISASI DATABASE SELESAI ===\n";
