<?php
// database/seed_kualitas_layanan_dan_mwl_juli.php
require __DIR__ . '/../includes/auth.php';
$pdo = getDbConnection();

echo "=== 1. SEEDING MONITORING_REPORTS (CATEGORY 2 - MICROWAVE LINK) ===\n";

$mwlReports = [
    [
        'report_code' => 'MON-MWL-2026-003',
        'title' => 'Inspeksi Validasi Data ISR Microwave Link (Open Shelter) PT. Telkomsel di Kab. Kutai Barat',
        'target_name' => 'PT. Telekomunikasi Selular (13 Site, 71 Link)',
        'frequency' => '7 / 8 / 15 / 23 GHz Microwave Link',
        'location' => 'Kabupaten Kutai Barat (Kec. Barong Tongkok, Melak, Muara Pahu, Penyinggahan)',
        'latitude' => -0.233188,
        'longitude' => 115.815688,
        'date_monitored' => '2026-07-23',
        'status' => 'verified',
        'technical_data' => [
            'link_name' => '71 Link Hop Microwave Link PT. Telkomsel (SDW002 Melak, SDW037 Muara Pahu, SDW011 Penyinggahan, dll.)',
            'antenna_polarization' => 'Dual Polarized',
            'antenna_diameter' => '0.6 - 1.2 meter',
            'antenna_height' => '45 - 60 meter',
            'tx_power' => '+24 dBm',
            'rsl' => '-41.5 s.d. -52.0 dBm',
            'azimuth' => '115° - 280°',
            'isr_number' => 'Risalah No. T-191/BALMON.64/SP.03.03/07/2026 (71 Link ISR)',
            'device_condition' => 'Baik / Prima',
            'link_status' => 'Aktif Beroperasi Normal (17 Link Aktif, 54 Link Off Air)',
            'warrant_number' => '104/BALMON.64/KP.01.06/07/2026',
            'nd_number' => '415/BALMON.64/SP.02.06/07/2026',
            'inspection_summary' => [
                'total_site' => 13,
                'total_link' => 71,
                'sesuai_isr' => 17,
                'tidak_sesuai_param' => 0,
                'tidak_berizin' => 0,
                'tidak_aktif' => 54,
                'sertifikat_ada' => 71,
                'pihak_kedua' => 'Wendy Adewijaya (POH GM Region Network Operation Kalimantan)',
                'saksi' => 'Zainal Arifin, Jupri (PT Nexwave), Binti Herwindasari, Ahmad Anwar'
            ]
        ]
    ],
    [
        'report_code' => 'MON-MWL-2026-004',
        'title' => 'Inspeksi Validasi Data ISR Microwave Link (Open Shelter) PT. Telkom Indonesia di Kab. Kutai Barat',
        'target_name' => 'PT. Telekomunikasi Indonesia, Tbk (22 Site, 125 Link)',
        'frequency' => '6 / 7 / 8 / 15 GHz Microwave Link',
        'location' => 'Kabupaten Kutai Barat (Melak, Muara Pahu, Muara Muntai, Barong Tongkok)',
        'latitude' => -0.327886,
        'longitude' => 116.060956,
        'date_monitored' => '2026-07-23',
        'status' => 'verified',
        'technical_data' => [
            'link_name' => '125 Link Hop Microwave Link PT. Telkom (SDW037 Muara Pahu, SDW002 Melak, SDW115 Tanjung Isui, dll.)',
            'antenna_polarization' => 'Dual Polarized',
            'antenna_diameter' => '0.6 - 1.8 meter',
            'antenna_height' => '50 - 72 meter',
            'tx_power' => '+26 dBm',
            'rsl' => '-38.0 s.d. -49.5 dBm',
            'azimuth' => '092° - 310°',
            'isr_number' => 'Risalah No. T-192/BALMON.64/SP.03.03/07/2026 (125 Link ISR)',
            'device_condition' => 'Baik / Prima',
            'link_status' => 'Aktif Beroperasi Normal (64 Link Sesuai ISR, 61 Off Air)',
            'warrant_number' => '104/BALMON.64/KP.01.06/07/2026',
            'nd_number' => '415/BALMON.64/SP.02.06/07/2026',
            'inspection_summary' => [
                'total_site' => 22,
                'total_link' => 125,
                'sesuai_isr' => 64,
                'tidak_sesuai_param' => 0,
                'tidak_berizin' => 0,
                'tidak_aktif' => 61,
                'sertifikat_ada' => 125,
                'pihak_kedua' => 'Liu Vendy (Mgr District Balikpapan)',
                'saksi' => 'Jupri (PT Alfar Multi Solusi), Binti Herwindasari, Ahmad Anwar'
            ]
        ]
    ],
    [
        'report_code' => 'MON-MWL-2026-005',
        'title' => 'Inspeksi Validasi Data ISR Microwave Link (Open Shelter) PT. Indosat, Tbk di Kab. Kutai Barat',
        'target_name' => 'PT. Indosat, Tbk (12 Site, 91 Link)',
        'frequency' => '6 / 7 / 8 / 11 / 23 GHz Microwave Link',
        'location' => 'Kabupaten Kutai Barat (Petrosea Port, Long Iram, Muara Pahu, Linggang Bigung)',
        'latitude' => 0.000739,
        'longitude' => 115.690631,
        'date_monitored' => '2026-07-23',
        'status' => 'verified',
        'technical_data' => [
            'link_name' => '91 Link Hop Microwave Link PT. Indosat (22SDW0055 Petrosea, 22SDW0019 Muara Pahu, 19SDW005 Linggang Bigung, dll.)',
            'antenna_polarization' => 'Dual Polarized',
            'antenna_diameter' => '0.6 - 1.2 meter',
            'antenna_height' => '45 - 65 meter',
            'tx_power' => '+25 dBm',
            'rsl' => '-40.0 s.d. -51.2 dBm',
            'azimuth' => '045° - 275°',
            'isr_number' => 'Risalah No. T-193/BALMON.64/SP.03.03/07/2026 (91 Link ISR)',
            'device_condition' => 'Baik / Prima',
            'link_status' => 'Aktif Beroperasi Normal (86 Link Sesuai ISR, 5 Off Air)',
            'warrant_number' => '104/BALMON.64/KP.01.06/07/2026',
            'nd_number' => '415/BALMON.64/SP.02.06/07/2026',
            'inspection_summary' => [
                'total_site' => 12,
                'total_link' => 91,
                'sesuai_isr' => 86,
                'tidak_sesuai_param' => 0,
                'tidak_berizin' => 0,
                'tidak_aktif' => 5,
                'sertifikat_ada' => 91,
                'pihak_kedua' => 'Dian Ardhiana (RAN & MW Operation Kalimantan)',
                'saksi' => 'Iim Badi Nursiam (PT Huawei), Binti Herwindasari, Ahmad Anwar'
            ]
        ]
    ],
    [
        'report_code' => 'MON-MWL-2026-006',
        'title' => 'Inspeksi Validasi Data ISR Microwave Link (Open Shelter) PT. XL Smart Telecom di Kab. Kutai Barat',
        'target_name' => 'PT. XL Smart Telecom Sejahtera (5 Site, 41 Link)',
        'frequency' => '7 / 8 / 11 / 15 / 23 GHz Microwave Link',
        'location' => 'Kabupaten Kutai Barat (Jaksa Mamahit Damai, Penyinggahan, Barong Tongkok)',
        'latitude' => -0.450438,
        'longitude' => 115.696813,
        'date_monitored' => '2026-07-23',
        'status' => 'verified',
        'technical_data' => [
            'link_name' => '41 Link Hop Microwave Link PT. XL (4144587E Jaksa Mamahit, 3986 Penyinggahan, 3970 Barong Tongkok, dll.)',
            'antenna_polarization' => 'Dual Polarized',
            'antenna_diameter' => '0.6 - 1.2 meter',
            'antenna_height' => '40 - 55 meter',
            'tx_power' => '+23 dBm',
            'rsl' => '-42.0 s.d. -53.5 dBm',
            'azimuth' => '080° - 290°',
            'isr_number' => 'Risalah No. T-194/BALMON.64/SP.03.03/07/2026 (41 Link ISR)',
            'device_condition' => 'Baik / Prima',
            'link_status' => 'Aktif Beroperasi Normal (28 Link Sesuai ISR, 13 Off Air)',
            'warrant_number' => '104/BALMON.64/KP.01.06/07/2026',
            'nd_number' => '415/BALMON.64/SP.02.06/07/2026',
            'inspection_summary' => [
                'total_site' => 5,
                'total_link' => 41,
                'sesuai_isr' => 28,
                'tidak_sesuai_param' => 0,
                'tidak_berizin' => 0,
                'tidak_aktif' => 13,
                'sertifikat_ada' => 41,
                'pihak_kedua' => 'Angga Agus Hudayanto (Head of Government & Stakeholder Relations)',
                'saksi' => 'Astri Mukarromahwati (PT XL), Binti Herwindasari, Ahmad Anwar'
            ]
        ]
    ],
    [
        'report_code' => 'MON-MWL-2026-007',
        'title' => 'Laporan Rekapitulasi Kolektif Validasi 328 Link Microwave Link Open Shelter di Kab. Kutai Barat',
        'target_name' => '4 Penyelenggara Telekomunikasi (Telkomsel, Telkom, Indosat, XL)',
        'frequency' => '6 s.d. 23 GHz (Point-to-Point Link)',
        'location' => 'Kabupaten Kutai Barat, Kalimantan Timur',
        'latitude' => -0.233188,
        'longitude' => 115.815688,
        'date_monitored' => '2026-07-23',
        'status' => 'verified',
        'technical_data' => [
            'link_name' => 'Rekapitulasi 328 Stasiun Radio Microwave Link Open Shelter (52 Site)',
            'antenna_polarization' => 'Dual Polarized',
            'antenna_diameter' => '0.6 - 1.8 meter',
            'antenna_height' => '40 - 72 meter',
            'tx_power' => '+23 s.d. +26 dBm',
            'rsl' => '-38.0 s.d. -53.5 dBm',
            'azimuth' => 'Multi-Directional Network Backhaul',
            'isr_number' => 'Risalah No. T-191 s.d. T-194/BALMON.64/SP.03.03/07/2026',
            'device_condition' => 'Baik / Prima (100% Bersertifikat)',
            'link_status' => '195 Link Aktif Sesuai ISR, 133 Link Tidak Aktif (Off Air)',
            'warrant_number' => '104/BALMON.64/KP.01.06/07/2026',
            'nd_number' => '415/BALMON.64/SP.02.06/07/2026',
            'inspection_summary' => [
                'grand_total_site' => 52,
                'grand_total_link' => 328,
                'total_sesuai_isr' => 195,
                'total_tidak_sesuai_param' => 0,
                'total_tidak_berizin' => 0,
                'total_tidak_aktif' => 133,
                'total_sertifikat' => 328,
                'petugas' => 'Fathul Mufiidah, S.Si (Ketua Tim), Binti Herwindasari, A.md, Ahmad Anwar'
            ]
        ]
    ]
];

foreach ($mwlReports as $rep) {
    $stmtCheck = $pdo->prepare("SELECT id FROM monitoring_reports WHERE report_code = ?");
    $stmtCheck->execute([$rep['report_code']]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        $stmtUpd = $pdo->prepare("
            UPDATE monitoring_reports SET
                title = ?, target_name = ?, frequency = ?, location = ?,
                latitude = ?, longitude = ?, date_monitored = ?, status = ?,
                technical_data = ?
            WHERE id = ?
        ");
        $stmtUpd->execute([
            $rep['title'], $rep['target_name'], $rep['frequency'], $rep['location'],
            $rep['latitude'], $rep['longitude'], $rep['date_monitored'], $rep['status'],
            json_encode($rep['technical_data']), $existing['id']
        ]);
        echo "Updated MWL report: {$rep['report_code']}\n";
    } else {
        $stmtIns = $pdo->prepare("
            INSERT INTO monitoring_reports (
                report_code, category_id, title, user_id, target_name, frequency,
                location, latitude, longitude, date_monitored, status, technical_data
            ) VALUES (?, 2, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIns->execute([
            $rep['report_code'], $rep['title'], $rep['target_name'], $rep['frequency'],
            $rep['location'], $rep['latitude'], $rep['longitude'], $rep['date_monitored'],
            $rep['status'], json_encode($rep['technical_data'])
        ]);
        echo "Inserted MWL report: {$rep['report_code']}\n";
    }
}

echo "\n=== 2. SEEDING MONITORING_REPORTS (CATEGORY 11 - QOS) ===\n";

$qosReportsNew = [
    [
        'report_code' => 'MON-QOS-2026-005',
        'title' => 'Pengukuran Kualitas Layanan Infrastruktur Digital di Kab. Penajam Paser Utara & Kawasan IKN (Mei 2026)',
        'target_name' => 'Jaringan Bergerak Seluler (Telkomsel, XL, Indosat)',
        'frequency' => '4G LTE (700/900/1800/2100/2300 MHz) & 2G (900/1800 MHz)',
        'location' => 'Kabupaten Penajam Paser Utara & KIPP Ibu Kota Nusantara',
        'latitude' => -0.963400,
        'longitude' => 116.711800,
        'date_monitored' => '2026-05-21',
        'status' => 'verified',
        'technical_data' => [
            'warrant_number' => '78/BALMON.64/KP.01.06/05/2026',
            'nd_number' => '301/BALMON.64/SP.02.06/06/2026 (02 Juni 2026)',
            'operator_name' => 'XL Axiata, Telkomsel, Indosat Ooredoo Hutchison',
            'network_technology' => '4G LTE',
            'download_throughput' => 'XL: 98.29 Mbps | Tsel: 92.59 Mbps | Isat: 52.12 Mbps',
            'upload_throughput' => 'XL: 35.01 Mbps | Tsel: 34.27 Mbps | Isat: 7.81 Mbps',
            'latency_rtt' => 'Tsel: 73.30 ms (Terbaik) | XL: 88.52 ms | Isat: 96.49 ms',
            'packet_loss' => 'Isat: 6.56% | Tsel: 10.04% | XL: 16.21%',
            'qos_compliance' => 'Memenuhi Standar Kualitas (QoS Baik Sekali di Kawasan IKN)',
            'measurement_route' => 'Drive Test Rute Protokol Kab. PPU & Kawasan Inti IKN (2 jam) & Static Test di 5 POI: Pelabuhan Ferry Penajam, Rusun ASN IKN, Perkantoran IKN, Nusantara Park, Perkantoran Kemenko IKN',
            'team_leader' => 'Muhammad Alif Anhasdio Fajri, ST (Pengendali Frekuensi Radio Ahli Muda)',
            'team_members' => 'Maksudi (Analis Sumber Daya Monitoring SFR Level 1)',
            'devices_used' => '3 Unit Probe Handphone (Telkomsel, XL, Indosat), Software SmartMon & Qualipoc, Freerider Backpack System Gen 4, Kendaraan Roda 4',
            'test_results' => [
                'speed_test' => [
                    'XL' => ['dl_speed' => 98.29, 'ul_speed' => 35.01, 'success_rate' => 82.40, 'rank_dl' => 1, 'rank_ul' => 1],
                    'Telkomsel' => ['dl_speed' => 92.59, 'ul_speed' => 34.27, 'success_rate' => 90.21, 'rank_dl' => 2, 'rank_ul' => 2],
                    'Indosat' => ['dl_speed' => 52.12, 'ul_speed' => 7.81, 'success_rate' => 96.88, 'rank_dl' => 3, 'rank_ul' => 3]
                ],
                'youtube' => [
                    'Indosat' => ['success_rate' => 98.05, 'visual_quality' => 4.31, 'fhd_1080p' => 75.13],
                    'Telkomsel' => ['success_rate' => 91.60, 'visual_quality' => 4.37, 'fhd_1080p' => 94.93],
                    'XL' => ['success_rate' => 91.24, 'visual_quality' => 4.27, 'fhd_1080p' => 77.09]
                ],
                'radio_4g' => [
                    'Telkomsel' => ['rsrp' => -88.54, 'rsrq' => -12.56, 'sinr' => 8.23, 'bad_rsrp' => 14.79, 'bad_sinr' => 44.32],
                    'Indosat' => ['rsrp' => -94.23, 'rsrq' => -12.39, 'sinr' => 6.05, 'bad_rsrp' => 13.62, 'bad_sinr' => 55.49],
                    'XL' => ['rsrp' => -105.53, 'rsrq' => -14.10, 'sinr' => 3.08, 'bad_rsrp' => 37.91, 'bad_sinr' => 62.21]
                ]
            ]
        ]
    ],
    [
        'report_code' => 'MON-QOS-2026-006',
        'title' => 'Pengukuran Kualitas Layanan Infrastruktur Digital di Kab. Kutai Kartanegara (April 2026)',
        'target_name' => 'Jaringan Bergerak Seluler (Telkomsel, XL, Indosat)',
        'frequency' => '4G LTE (900/1800/2100/2300 MHz)',
        'location' => 'Tenggarong & Poros Samarinda-Tenggarong, Kab. Kutai Kartanegara',
        'latitude' => -0.443800,
        'longitude' => 116.991200,
        'date_monitored' => '2026-04-23',
        'status' => 'verified',
        'technical_data' => [
            'warrant_number' => '57/BALMON.64/KP.01.06/04/2026 (17 April 2026)',
            'nd_number' => '238/BALMON.64/SP.02.06/04/2026 (30 April 2026)',
            'operator_name' => 'Telkomsel, XL, Indosat',
            'network_technology' => '4G LTE',
            'download_throughput' => 'Tsel: 94.15 Mbps | XL: 91.80 Mbps | Isat: 68.45 Mbps',
            'upload_throughput' => 'XL: 48.20 Mbps | Tsel: 32.10 Mbps | Isat: 26.50 Mbps',
            'latency_rtt' => 'Normal (<45 ms)',
            'packet_loss' => '0.5%',
            'qos_compliance' => 'Memenuhi Standar Kualitas (QoS Baik)',
            'measurement_route' => 'Drive Test Poros Tenggarong - Jalur Trans Kaltim & Static Test di 3 POI: Kompleks Kantor Bupati Kukar, Kedaton Kutai Kartanegara, Jembatan Kartanegara',
            'team_leader' => 'Widhy Baskoro (Plh. Kepala Balmon Samarinda)',
            'team_members' => 'Tim Kerja Kualitas Layanan Infrastruktur Digital',
            'devices_used' => '3 Unit Probe Handphone, Software SmartMon, Freerider Backpack System Gen 4, Mobil Operasional',
            'test_results' => [
                'speed_test' => [
                    'Telkomsel' => ['dl_speed' => 94.15, 'ul_speed' => 32.10, 'success_rate' => 95.80, 'rank_dl' => 1, 'rank_ul' => 2],
                    'XL' => ['dl_speed' => 91.80, 'ul_speed' => 48.20, 'success_rate' => 92.40, 'rank_dl' => 2, 'rank_ul' => 1],
                    'Indosat' => ['dl_speed' => 68.45, 'ul_speed' => 26.50, 'success_rate' => 88.60, 'rank_dl' => 3, 'rank_ul' => 3]
                ],
                'youtube' => [
                    'Telkomsel' => ['success_rate' => 98.50, 'visual_quality' => 4.30, 'hd_720p' => 95.10],
                    'Indosat' => ['success_rate' => 94.20, 'visual_quality' => 4.25, 'hd_720p' => 82.40],
                    'XL' => ['success_rate' => 93.80, 'visual_quality' => 4.20, 'hd_720p' => 79.50]
                ],
                'radio_4g' => [
                    'Telkomsel' => ['rsrp' => -84.20, 'rsrq' => -11.40, 'sinr' => 11.20, 'bad_rsrp' => 8.50, 'bad_sinr' => 38.20],
                    'XL' => ['rsrp' => -86.50, 'rsrq' => -11.80, 'sinr' => 12.40, 'bad_rsrp' => 11.20, 'bad_sinr' => 34.60],
                    'Indosat' => ['rsrp' => -92.30, 'rsrq' => -12.10, 'sinr' => 7.80, 'bad_rsrp' => 16.40, 'bad_sinr' => 48.10]
                ]
            ]
        ]
    ],
    [
        'report_code' => 'MON-QOS-2026-007',
        'title' => 'Pengukuran Kualitas Layanan Infrastruktur Digital di Kabupaten Kutai Barat (Juli 2026)',
        'target_name' => 'Jaringan Bergerak Seluler (Telkomsel, XL, Indosat)',
        'frequency' => '4G LTE & 2G GSM',
        'location' => 'Barong Tongkok, Melak, Tering, Kab. Kutai Barat',
        'latitude' => -0.233188,
        'longitude' => 115.815688,
        'date_monitored' => '2026-07-23',
        'status' => 'verified',
        'technical_data' => [
            'warrant_number' => '104/BALMON.64/KP.01.06/07/2026 (17 Juli 2026)',
            'nd_number' => '415/BALMON.64/SP.02.06/07/2026 (31 Juli 2026)',
            'operator_name' => 'PT. Indosat Tbk, PT. Telekomunikasi Seluler Tbk, PT. XL Smart Telecom Sejahtera',
            'network_technology' => '4G LTE & 2G GSM',
            'download_throughput' => 'Isat: 79.84 Mbps | XL: 60.51 Mbps | Tsel: 56.47 Mbps',
            'upload_throughput' => 'Isat: 34.56 Mbps | Tsel: 24.65 Mbps | XL: 22.31 Mbps',
            'latency_rtt' => 'Normal / Memenuhi Standar',
            'packet_loss' => '0%',
            'qos_compliance' => 'Memenuhi Standar Kualitas (Indosat Unggul Throughput, Telkomsel Unggul Sinyal RSRP)',
            'measurement_route' => 'Drive Test Rute Protokol Kab. Kutai Barat (5 jam, ±40 km/jam) & Static Test di 4 POI: RSUD Harapan Insan Sendawar, Al-Muttaqin Islamic Center, Pelabuhan Tering, Business Center Barong Tongkok',
            'team_leader' => 'Fathul Mufiidah, S.Si (Pengendali Frekuensi Radio Ahli Pertama)',
            'team_members' => 'Binti Herwindasari, A.md & Ahmad Anwar',
            'devices_used' => '3 Unit Probe Handphone (3 Operator), Aplikasi Qualipoc, GPS Garmin 65S, Freerider Backpack Gen 4, Kendaraan Roda 4',
            'test_results' => [
                'speed_test' => [
                    'Indosat' => ['dl_speed' => 79.84, 'ul_speed' => 34.56, 'success_rate' => 90.14, 'rank_dl' => 1, 'rank_ul' => 1],
                    'XL' => ['dl_speed' => 60.51, 'ul_speed' => 22.31, 'success_rate' => 53.23, 'rank_dl' => 2, 'rank_ul' => 3],
                    'Telkomsel' => ['dl_speed' => 56.47, 'ul_speed' => 24.65, 'success_rate' => 88.44, 'rank_dl' => 3, 'rank_ul' => 2]
                ],
                'youtube' => [
                    'Indosat' => ['success_rate' => 91.49, 'visual_quality' => 4.28, 'hd_720p' => 73.13],
                    'Telkomsel' => ['success_rate' => 90.00, 'visual_quality' => 4.22, 'hd_720p' => 96.73],
                    'XL' => ['success_rate' => 69.39, 'visual_quality' => 4.23, 'hd_720p' => 40.12]
                ],
                'radio_4g' => [
                    'Telkomsel' => ['rsrp' => -87.28, 'rsrq' => -13.97, 'sinr' => 7.86, 'bad_rsrp' => 23.88, 'bad_sinr' => 56.54],
                    'Indosat' => ['rsrp' => -90.98, 'rsrq' => -10.17, 'sinr' => 11.77, 'bad_rsrp' => 18.43, 'bad_sinr' => 30.75],
                    'XL' => ['rsrp' => -94.81, 'rsrq' => -11.80, 'sinr' => 11.90, 'bad_rsrp' => 48.55, 'bad_sinr' => 56.05]
                ]
            ]
        ]
    ]
];

foreach ($qosReportsNew as $rep) {
    $stmtCheck = $pdo->prepare("SELECT id FROM monitoring_reports WHERE report_code = ?");
    $stmtCheck->execute([$rep['report_code']]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        $stmtUpd = $pdo->prepare("
            UPDATE monitoring_reports SET
                title = ?, target_name = ?, frequency = ?, location = ?,
                latitude = ?, longitude = ?, date_monitored = ?, status = ?,
                technical_data = ?
            WHERE id = ?
        ");
        $stmtUpd->execute([
            $rep['title'], $rep['target_name'], $rep['frequency'], $rep['location'],
            $rep['latitude'], $rep['longitude'], $rep['date_monitored'], $rep['status'],
            json_encode($rep['technical_data']), $existing['id']
        ]);
        echo "Updated QoS report: {$rep['report_code']}\n";
    } else {
        $stmtIns = $pdo->prepare("
            INSERT INTO monitoring_reports (
                report_code, category_id, title, user_id, target_name, frequency,
                location, latitude, longitude, date_monitored, status, technical_data
            ) VALUES (?, 11, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIns->execute([
            $rep['report_code'], $rep['title'], $rep['target_name'], $rep['frequency'],
            $rep['location'], $rep['latitude'], $rep['longitude'], $rep['date_monitored'],
            $rep['status'], json_encode($rep['technical_data'])
        ]);
        echo "Inserted QoS report: {$rep['report_code']}\n";
    }
}

echo "\n>>> SEEDING SELESAI DENGAN SUKSES! <<<\n";
