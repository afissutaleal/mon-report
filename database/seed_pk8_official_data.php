<?php
// database/seed_pk8_official_data.php
require __DIR__ . '/../includes/auth.php';
$pdo = getDbConnection();

echo "=== 1. SEEDING MONITORING_REPORTS (CATEGORY 11 - QOS) ===\n";

$qosReports = [
    [
        'report_code' => 'MON-QOS-2026-001',
        'title' => 'Pengukuran Kualitas Layanan Infrastruktur Digital di Kota Samarinda (Agustus 2026)',
        'target_name' => 'Jaringan Bergerak Seluler (Telkomsel, XL, Indosat)',
        'frequency' => '700/900/1800/2100/2300 MHz (4G LTE)',
        'location' => 'Kota Samarinda, Kalimantan Timur',
        'latitude' => -0.502106,
        'longitude' => 117.153709,
        'date_monitored' => '2026-08-20',
        'status' => 'verified',
        'technical_data' => [
            'warrant_number' => '120/BALMON.64/KP.01.06/08/2026',
            'nd_number' => 'Nota Dinas Tgl 02 September 2026',
            'operator_name' => 'Telkomsel, XL, Indosat',
            'network_technology' => '4G LTE',
            'download_throughput' => 'XL: 137.48 Mbps | Tsel: 104.52 Mbps | Isat: 87.31 Mbps',
            'upload_throughput' => 'XL: 77.55 Mbps | Isat: 30.92 Mbps | Tsel: 28.77 Mbps',
            'latency_rtt' => 'Normal (<50 ms)',
            'packet_loss' => '0%',
            'qos_compliance' => 'Memenuhi Standar Kualitas (QoS Baik)',
            'measurement_route' => 'Drive Test Rute Protokol Kota Samarinda (5 jam) & Static Test di 3 POI: Bukit Alaya Junction, Bandara APT Pranoto, Pelabuhan Samarinda',
            'team_leader' => 'Muhammad Alif Anhasdio Fajri (Ketua Tim Kerja)',
            'team_members' => 'Andreo Yustiantoro A, Sri Sukisno, M. Alif Anhasdio F, Taman Dwi Wijayanti, Fathul Mufiidah, Binti Herwindasari, Rendy Tri Atmadja, Tantowi Pahala H.S., Maryono, Togap Sihite',
            'devices_used' => '3 Unit Probe Handphone (Telkomsel, XL, Indosat), Software SmartMon, Freerider Backpack System Gen 4, Inverter Mobil Roda 4',
            'test_results' => [
                'speed_test' => [
                    'XL' => ['dl_speed' => 137.48, 'ul_speed' => 77.55, 'success_rate' => 100.0, 'rank_dl' => 1, 'rank_ul' => 1],
                    'Telkomsel' => ['dl_speed' => 104.52, 'ul_speed' => 28.77, 'success_rate' => 98.82, 'rank_dl' => 2, 'rank_ul' => 3],
                    'Indosat' => ['dl_speed' => 87.31, 'ul_speed' => 30.92, 'success_rate' => 76.14, 'rank_dl' => 3, 'rank_ul' => 2]
                ],
                'youtube' => [
                    'XL' => ['success_rate' => 100.0, 'visual_quality' => 4.14, 'hd_720p' => 65.00],
                    'Telkomsel' => ['success_rate' => 99.41, 'visual_quality' => 4.25, 'hd_720p' => 93.96],
                    'Indosat' => ['success_rate' => 93.18, 'visual_quality' => 4.24, 'hd_720p' => 78.51]
                ],
                'radio_4g' => [
                    'XL' => ['rsrp' => -81.44, 'rsrq' => -11.26, 'sinr' => 15.14, 'bad_sample' => 10.18],
                    'Telkomsel' => ['rsrp' => -82.13, 'rsrq' => -11.55, 'sinr' => 9.40, 'bad_sample' => 11.07],
                    'Indosat' => ['rsrp' => -90.79, 'rsrq' => -11.10, 'sinr' => 8.54, 'bad_sample' => 15.93]
                ]
            ]
        ]
    ],
    [
        'report_code' => 'MON-QOS-2026-002',
        'title' => 'Pengukuran Kualitas Layanan Infrastruktur Digital di Kabupaten Berau (Agustus 2026)',
        'target_name' => 'Jaringan Bergerak Seluler (Telkomsel, XL, Indosat)',
        'frequency' => '900/1800/2100/2300 MHz (4G LTE)',
        'location' => 'Tanjung Redeb, Kabupaten Berau, Kalimantan Timur',
        'latitude' => 2.153400,
        'longitude' => 117.497500,
        'date_monitored' => '2026-08-27',
        'status' => 'verified',
        'technical_data' => [
            'warrant_number' => '122/BALMON.64/KP.01.06/08/2026',
            'nd_number' => 'Nota Dinas Tgl 02 September 2026',
            'operator_name' => 'Telkomsel, XL, Indosat',
            'network_technology' => '4G LTE',
            'download_throughput' => 'XL: 91.08 Mbps | Tsel: 89.66 Mbps | Isat: 49.96 Mbps',
            'upload_throughput' => 'XL: 46.13 Mbps | Tsel: 31.10 Mbps | Isat: 15.39 Mbps',
            'latency_rtt' => 'Normal (<60 ms)',
            'packet_loss' => '0%',
            'qos_compliance' => 'Memenuhi Standar Kualitas (QoS Baik)',
            'measurement_route' => 'Drive Test Rute Protokol Tanjung Redeb & Static Test di 5 POI: Bandara Kalimarau, Business Center Berau, Masjid Agung Baitul Hikmah, Pusat Kuliner Jl Murjani, Area Perkantoran Berau',
            'team_leader' => 'Duiyanti Milo Rundupadang (Ketua Tim)',
            'team_members' => 'Binti Herwindasari, Tantowi Pahala H. S., Rusdino',
            'devices_used' => '3 Unit Probe Handphone (Telkomsel, XL, Indosat), Software SmartMon, Freerider Backpack System Gen 4, Inverter Mobil Roda 4',
            'test_results' => [
                'speed_test' => [
                    'XL' => ['dl_speed' => 91.08, 'ul_speed' => 46.13, 'success_rate' => 93.39, 'rank_dl' => 1, 'rank_ul' => 1],
                    'Telkomsel' => ['dl_speed' => 89.66, 'ul_speed' => 31.10, 'success_rate' => 94.90, 'rank_dl' => 2, 'rank_ul' => 2],
                    'Indosat' => ['dl_speed' => 49.96, 'ul_speed' => 15.39, 'success_rate' => 98.70, 'rank_dl' => 3, 'rank_ul' => 3]
                ],
                'youtube' => [
                    'XL' => ['success_rate' => 96.46, 'visual_quality' => 4.25, 'hd_720p' => 74.75],
                    'Telkomsel' => ['success_rate' => 93.37, 'visual_quality' => 4.26, 'hd_720p' => 95.43],
                    'Indosat' => ['success_rate' => 97.83, 'visual_quality' => 4.25, 'hd_720p' => 56.66]
                ],
                'radio_4g' => [
                    'Telkomsel' => ['rsrp' => -85.21, 'rsrq' => -11.99, 'sinr' => 10.28, 'bad_sample' => 14.72],
                    'XL' => ['rsrp' => -96.70, 'rsrq' => -11.92, 'sinr' => 10.25, 'bad_sample' => 36.09],
                    'Indosat' => ['rsrp' => -99.52, 'rsrq' => -13.59, 'sinr' => 4.17, 'bad_sample' => 29.08]
                ]
            ]
        ]
    ],
    [
        'report_code' => 'MON-QOS-2026-003',
        'title' => 'Pengukuran Kualitas Layanan Infrastruktur Digital di Kota Samarinda (September 2026)',
        'target_name' => 'Jaringan Bergerak Seluler (Telkomsel, XL, Indosat)',
        'frequency' => '700/900/1800/2100/2300 MHz (4G LTE)',
        'location' => 'Kota Samarinda, Kalimantan Timur',
        'latitude' => -0.502106,
        'longitude' => 117.153709,
        'date_monitored' => '2026-09-17',
        'status' => 'verified',
        'technical_data' => [
            'warrant_number' => '133/BALMON.64/KP.01.06/09/2026',
            'nd_number' => 'Nota Dinas Tgl 28 September 2026',
            'operator_name' => 'Telkomsel, XL, Indosat',
            'network_technology' => '4G LTE',
            'download_throughput' => 'XL: 126.05 Mbps | Tsel: 89.69 Mbps | Isat: 67.80 Mbps',
            'upload_throughput' => 'XL: 85.48 Mbps | Isat: 30.97 Mbps | Tsel: 25.66 Mbps',
            'latency_rtt' => 'Normal (<50 ms)',
            'packet_loss' => '0%',
            'qos_compliance' => 'Memenuhi Standar Kualitas (QoS Baik)',
            'measurement_route' => 'Drive Test Rute Protokol Kota Samarinda (5 jam) & Static Test di 4 POI: Bandara APT Pranoto, Balai Kota Samarinda, Universitas Mulawarman, Pelabuhan Samarinda',
            'team_leader' => 'Muhammad Alif Anhasdio Fajri',
            'team_members' => 'Roy Mallombasi, Rifyal, Erlangga Hari Prasetyo, Binti Herwindasari, Tantowi Pahala H.S., Maryono',
            'devices_used' => '3 Unit Probe Handphone (Telkomsel, XL, Indosat), Software SmartMon, Freerider Backpack System Gen 4, Inverter Mobil Roda 4',
            'test_results' => [
                'speed_test' => [
                    'XL' => ['dl_speed' => 126.05, 'ul_speed' => 85.48, 'success_rate' => 96.89, 'rank_dl' => 1, 'rank_ul' => 1],
                    'Telkomsel' => ['dl_speed' => 89.69, 'ul_speed' => 25.66, 'success_rate' => 96.24, 'rank_dl' => 2, 'rank_ul' => 3],
                    'Indosat' => ['dl_speed' => 67.80, 'ul_speed' => 30.97, 'success_rate' => 97.39, 'rank_dl' => 3, 'rank_ul' => 2]
                ],
                'youtube' => [
                    'Indosat' => ['success_rate' => 100.0, 'visual_quality' => 4.25, 'hd_720p' => 72.74],
                    'Telkomsel' => ['success_rate' => 99.53, 'visual_quality' => 4.25, 'hd_720p' => 96.72],
                    'XL' => ['success_rate' => 98.96, 'visual_quality' => 4.26, 'hd_720p' => 92.90]
                ],
                'radio_4g' => [
                    'XL' => ['rsrp' => -82.51, 'rsrq' => -11.27, 'sinr' => 13.18, 'bad_sample' => 4.29],
                    'Telkomsel' => ['rsrp' => -83.19, 'rsrq' => -12.02, 'sinr' => 10.75, 'bad_sample' => 10.26],
                    'Indosat' => ['rsrp' => -90.85, 'rsrq' => -12.06, 'sinr' => 6.20, 'bad_sample' => 11.98]
                ]
            ]
        ]
    ],
    [
        'report_code' => 'MON-QOS-2026-004',
        'title' => 'Pengukuran Kualitas Layanan Infrastruktur Digital di Kabupaten Paser (Oktober 2026)',
        'target_name' => 'Jaringan Bergerak Seluler (Telkomsel, XL, Indosat)',
        'frequency' => '900/1800/2100/2300 MHz (4G LTE)',
        'location' => 'Tanah Grogot, Kabupaten Paser, Kalimantan Timur',
        'latitude' => -1.902600,
        'longitude' => 116.196900,
        'date_monitored' => '2026-10-01',
        'status' => 'verified',
        'technical_data' => [
            'warrant_number' => '142/BALMON.64/KP.01.06/09/2026',
            'nd_number' => '526/BALMON.64/SP.02.06/10/2026 (06 Okt 2026)',
            'operator_name' => 'Telkomsel, XL, Indosat',
            'network_technology' => '4G LTE',
            'download_throughput' => 'XL: 126.58 Mbps | Tsel: 83.57 Mbps | Isat: 61.41 Mbps',
            'upload_throughput' => 'XL: 55.37 Mbps | Tsel: 23.95 Mbps | Isat: 19.03 Mbps',
            'latency_rtt' => 'Normal (<65 ms)',
            'packet_loss' => '0%',
            'qos_compliance' => 'Memenuhi Standar Kualitas (QoS Baik)',
            'measurement_route' => 'Drive Test Rute Tanah Grogot & Static Test di 5 POI: Mall Pelayanan Publik Km 5, RSUD Panglima Sebaya, Masjid Agung Nurul Falah, SMA Muhammadiyah Tanah Grogot, Area Kuliner Tanah Grogot',
            'team_leader' => 'Roy Mallombasi (Ketua Tim)',
            'team_members' => 'Binti Herwindasari, Rendy Tri Atmadja, Ode Nurhasanah',
            'devices_used' => '3 Unit Probe Handphone (Telkomsel, XL, Indosat), Software SmartMon, Freerider Backpack System Gen 4, Inverter Mobil Roda 4',
            'test_results' => [
                'speed_test' => [
                    'XL' => ['dl_speed' => 126.58, 'ul_speed' => 55.37, 'success_rate' => 86.64, 'rank_dl' => 1, 'rank_ul' => 1],
                    'Telkomsel' => ['dl_speed' => 83.57, 'ul_speed' => 23.95, 'success_rate' => 66.40, 'rank_dl' => 2, 'rank_ul' => 2],
                    'Indosat' => ['dl_speed' => 61.41, 'ul_speed' => 19.03, 'success_rate' => 45.75, 'rank_dl' => 3, 'rank_ul' => 3]
                ],
                'youtube' => [
                    'Telkomsel' => ['success_rate' => 96.31, 'visual_quality' => 4.25, 'hd_720p' => 93.45],
                    'Indosat' => ['success_rate' => 93.07, 'visual_quality' => 4.24, 'hd_720p' => 55.80],
                    'XL' => ['success_rate' => 92.92, 'visual_quality' => 4.25, 'hd_720p' => 82.98]
                ],
                'radio_4g' => [
                    'Telkomsel' => ['rsrp' => -85.70, 'rsrq' => -11.05, 'sinr' => 8.03, 'bad_sample' => 18.63],
                    'XL' => ['rsrp' => -91.32, 'rsrq' => -12.77, 'sinr' => 10.36, 'bad_sample' => 13.66],
                    'Indosat' => ['rsrp' => -96.30, 'rsrq' => -13.93, 'sinr' => 3.19, 'bad_sample' => 23.67]
                ]
            ]
        ]
    ]
];

$stmtInsertRep = $pdo->prepare("
    INSERT OR REPLACE INTO monitoring_reports (
        report_code, category_id, title, user_id, target_name, frequency,
        location, latitude, longitude, date_monitored, status, technical_data
    ) VALUES (?, 11, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($qosReports as $rep) {
    // Check if exists
    $stmtCheck = $pdo->prepare("SELECT id FROM monitoring_reports WHERE report_code = ?");
    $stmtCheck->execute([$rep['report_code']]);
    $existingId = $stmtCheck->fetchColumn();

    $techJson = json_encode($rep['technical_data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if ($existingId) {
        $stmtUpd = $pdo->prepare("
            UPDATE monitoring_reports SET
                title = ?, target_name = ?, frequency = ?, location = ?,
                latitude = ?, longitude = ?, date_monitored = ?, status = ?, technical_data = ?
            WHERE id = ?
        ");
        $stmtUpd->execute([
            $rep['title'], $rep['target_name'], $rep['frequency'], $rep['location'],
            $rep['latitude'], $rep['longitude'], $rep['date_monitored'], $rep['status'], $techJson,
            $existingId
        ]);
        echo "Updated: {$rep['report_code']} - {$rep['title']}\n";
    } else {
        $stmtInsertRep->execute([
            $rep['report_code'],
            $rep['title'],
            $rep['user_id'] ?? 1,
            $rep['target_name'],
            $rep['frequency'],
            $rep['location'],
            $rep['latitude'],
            $rep['longitude'],
            $rep['date_monitored'],
            $rep['status'],
            $techJson
        ]);
        echo "Inserted: {$rep['report_code']} - {$rep['title']}\n";
    }
}

echo "\n=== 2. UPDATING PERJANJIAN_KINERJA (PK.8 - 2026) ===\n";
$stmtPk = $pdo->prepare("
    UPDATE perjanjian_kinerja SET 
        realization = 100.0,
        target_annual = 100.0,
        unit = '% Kab/Kota',
        notes = 'Realisasi s.d. Oktober 2026: 100.0% (Target: 100% / 8 Kab/Kota wilayah kerja). Telah terlaksana pengukuran Drive Test & Static Test di 8 Kabupaten/Kota (Samarinda, Balikpapan, Kukar, Bontang, PPU/IKN, Kutim, Berau, Paser) serta validasi integrasi PMT Komdigi.'
    WHERE code = 'PK.8' AND year = 2026
");
$stmtPk->execute();
echo "Updated PK.8 in perjanjian_kinerja (Realization: 100.0%).\n";

echo "\n=== 3. UPDATING PK_MONTHLY_REKAP (PK.8 - 2026) ===\n";
$pk8MonthsData = [
    1 => [
        'title' => 'Drive Test & Static Test QoS Kota Samarinda',
        'detail' => 'Pengukuran kualitas seluler rute protokol, pemukiman & POI pusat kota',
        'target' => '1 Kab/Kota',
        'real' => '1 Selesai',
        'cum' => 12.5,
        'status' => 'achieved',
        'nd' => '90/BALMON.64/SP.02.10/01/2026',
        'date' => '31 Jan 2026'
    ],
    2 => [
        'title' => 'Drive Test & Static Test QoS Kota Balikpapan',
        'detail' => 'Pengukuran 4G di rute protokol, pelabuhan Semayang & bandara SAMS Sepinggan',
        'target' => '1 Kab/Kota',
        'real' => '1 Selesai',
        'cum' => 25.0,
        'status' => 'achieved',
        'nd' => '150/BALMON.64/SP.02.10/02/2026',
        'date' => '28 Feb 2026'
    ],
    3 => [
        'title' => 'Drive Test & Static Test QoS Kab. Kutai Kartanegara',
        'detail' => 'Pengukuran rute Tenggarong, jalur trans-Kaltim & POI posko ramadan',
        'target' => '1 Kab/Kota',
        'real' => '1 Selesai',
        'cum' => 37.5,
        'status' => 'achieved',
        'nd' => '203/BALMON.64/SP.02.10/03/2026',
        'date' => '31 Mar 2026'
    ],
    4 => [
        'title' => 'Drive Test & Static Test QoS Kab. Kutai Kartanegara',
        'detail' => 'Pengukuran kualitas seluler rute poros Tenggarong & 3 POI di Kab. Kutai Kartanegara',
        'target' => '1 Kab/Kota',
        'real' => '1 Selesai',
        'cum' => 50.0,
        'status' => 'achieved',
        'nd' => '238/BALMON.64/SP.02.06/04/2026 (SPT 57)',
        'date' => '30 Apr 2026'
    ],
    5 => [
        'title' => 'Drive Test & Static Test QoS Kawasan Inti IKN & Kab. PPU',
        'detail' => 'Pengukuran coverage & quality 4G di KIPP IKN & Penajam (5 POI teruji)',
        'target' => '1 Kab/Kota',
        'real' => '1 Selesai',
        'cum' => 62.5,
        'status' => 'achieved',
        'nd' => '301/BALMON.64/SP.02.06/06/2026 (SPT 78)',
        'date' => '02 Jun 2026'
    ],
    6 => [
        'title' => 'Drive Test & Static Test QoS Kab. Kutai Timur',
        'detail' => 'Pengukuran jaringan seluler di Sangatta Utara & kawasan komersial (SPT 60)',
        'target' => '1 Kab/Kota',
        'real' => '1 Selesai',
        'cum' => 75.0,
        'status' => 'achieved',
        'nd' => '396/BALMON.64/SP.02.10/06/2026',
        'date' => '30 Jun 2026'
    ],
    7 => [
        'title' => 'Pengukuran QoS & Validasi MWL Open Shelter Kab. Kutai Barat',
        'detail' => 'Drive Test, 4 POI QoS dan Inspeksi Validasi 328 Link MWL Open Shelter (52 Site)',
        'target' => '1 Kab/Kota',
        'real' => '1 Selesai',
        'cum' => 75.0,
        'status' => 'achieved',
        'nd' => '415/BALMON.64/SP.02.06/07/2026 (SPT 104)',
        'date' => '31 Jul 2026'
    ],
    8 => [
        'title' => 'Pengukuran QoS Kota Samarinda & Kab. Berau',
        'detail' => 'Pengukuran Drive Test & 5 POI Berau (SPT 122) serta 3 POI Samarinda (SPT 120)',
        'target' => '1 Kab/Kota',
        'real' => '1 Selesai',
        'cum' => 87.5,
        'status' => 'achieved',
        'nd' => 'ND Tgl 02 Sep 2026 (ST 120 & 122)',
        'date' => '02 Sep 2026'
    ],
    9 => [
        'title' => 'Pengukuran QoS Kota Samarinda & Kab. Paser',
        'detail' => 'Pengukuran Drive Test & 4 POI Samarinda (SPT 133) serta 5 POI Paser (SPT 142)',
        'target' => '1 Kab/Kota',
        'real' => '1 Selesai',
        'cum' => 100.0,
        'status' => 'achieved',
        'nd' => '526/BALMON.64/SP.02.06/10/2026',
        'date' => '06 Okt 2026'
    ],
    10 => [
        'title' => 'Post-Processing PMT & Verifikasi Akhir Komdigi',
        'detail' => 'Analisis komprehensif seluruh 8 Kab/Kota wilayah kerja, benchmark operator',
        'target' => '8 Kab/Kota',
        'real' => '8 Selesai (100%)',
        'cum' => 100.0,
        'status' => 'achieved',
        'nd' => 'PMT-FINAL/10/2026',
        'date' => '10 Okt 2026'
    ],
    11 => [
        'title' => 'Diseminasi Hasil & Rekomendasi Operator Seluler',
        'detail' => 'Penyampaian hasil evaluasi performa jaringan & titik interferensi SINR',
        'target' => 'Rekomendasi',
        'real' => 'Rencana',
        'cum' => 100.0,
        'status' => 'pending',
        'nd' => 'Draft Agenda',
        'date' => 'Nov 2026'
    ],
    12 => [
        'title' => 'Laporan Tahunan Kualitas Layanan Seluler (QoS) 2026',
        'detail' => 'Penyusunan benchmark kualitas seluler se-Kaltim & IKN TA 2026',
        'target' => '8 Kab/Kota',
        'real' => '8 Selesai (100%)',
        'cum' => 100.0,
        'status' => 'pending',
        'nd' => 'Lap. QoS Tahunan',
        'date' => 'Des 2026'
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
    WHERE pk_code = 'PK.8' AND year = 2026 AND month_num = ?
");

foreach ($pk8MonthsData as $mNum => $mData) {
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
    echo "Updated PK.8 Bulan $mNum: {$mData['real']} (Cum: {$mData['cum']}%).\n";
}

echo "\nDone successfully!\n";
