<?php
require __DIR__ . '/../includes/auth.php';
$pdo = getDbConnection();

echo "=== 1. UPDATING SCHEDULES CATEGORY TO PENANGANAN GANGGUAN (ID 10) ===\n";
// Update SPT 46
$stmt1 = $pdo->prepare("
    UPDATE monitoring_schedules 
    SET category_id = 10, category_ids = '[10]'
    WHERE spt_number = '46/BALMON.64/KP.01.06/04/2026'
");
$stmt1->execute();
echo "Updated SPT 46 to category 10 (Penanganan Gangguan).\n";

// Update SPT 113
$stmt2 = $pdo->prepare("
    UPDATE monitoring_schedules 
    SET category_id = 10, category_ids = '[10]'
    WHERE spt_number = '113/BALMON.64/KP.01.06/08/2026'
");
$stmt2->execute();
echo "Updated SPT 113 to category 10 (Penanganan Gangguan).\n";

echo "\n=== 2. INSERTING OR UPDATING MONITORING REPORTS ===\n";

// Report 1: PT. Kideco Jaya Agung
$checkRep1 = $pdo->prepare("SELECT id FROM monitoring_reports WHERE report_code = 'REP-2026-04-KIDECO'");
$checkRep1->execute();
$rep1Id = $checkRep1->fetchColumn();

$techData1 = json_encode([
    'complaint_number' => '145/180/C/III/2026-01 (13 Maret 2026)',
    'affected_service' => 'Radio Komunikasi Tambang Land Mobile (Kideco)',
    'source_identified' => 'Intermodulasi frekuensi 173.625 MHz & 173.675 MHz serta external noise induksi Gardu Induk PLN Batu Sopang pada 173.250 MHz',
    'action_taken' => 'Pelacakan sinyal (DF) di 13 titik ukur, pengukuran rasio proteksi & level noise dengan R&S PR100 & Anritsu MS2090A. Diterbitkan BAP Pemeriksaan & Penanganan Gangguan dengan rekomendasi modifikasi ISR.',
    'resolution_status' => 'Terselesaikan Tuntas (Clean - Bebas Gangguan)',
    'bap_number' => 'T-67/BALMON.64/SP.03.05/04/2026 & T-68/BALMON.64/SP.03.03/04/2026',
    'spt_number' => '46/BALMON.64/KP.01.06/04/2026',
    'team_leader' => 'Sri Sukisno, ST (NIP: 19831107 200502 1 001)',
    'team_members' => 'Rifani Tria Agustin, A.md.T, Rendy Tri Atmadja, ST',
    'equipment_used' => 'R&S PR100, Anritsu MS2090A, R&S HE300, HE400UWB, Laptop R&S Argus, GPS Garmin GPSMAP-65S',
    'isr_numbers' => '03037474-000SU/2620252030 (173.675 MHz) & 03037508-000SU/2620252030 (173.250 MHz)',
    'disposition' => 'Disposisi Kepala Balai: Tindaklanjuti dan diselesaikan, input laporan secara online, file sebagai lampiran pertanggungjawaban periodik'
], JSON_UNESCAPED_UNICODE);

if (!$rep1Id) {
    $ins1 = $pdo->prepare("
        INSERT INTO monitoring_reports (
            report_code, category_id, title, user_id, target_name, frequency, location,
            latitude, longitude, date_monitored, status, verified_by, verified_at,
            verification_notes, technical_data, created_at, updated_at
        ) VALUES (
            'REP-2026-04-KIDECO', 10, 'Penanganan Gangguan Frekuensi Radio PT. Kideco Jaya Agung',
            5, 'PT. Kideco Jaya Agung', '173.675 MHz & 173.250 MHz', 'Kabupaten Paser (Kec. Batu Sopang, Site SIMS, Desa Songka)',
            -1.847466, 115.906545, '2026-04-11', 'verified', 1, '2026-04-15 10:00:00',
            'Laporan penanganan gangguan lengkap dan terverifikasi tuntas. Disposisi Kepala Balai: Tindaklanjuti dan diselesaikan, input laporan secara online, file sebagai lampiran pertanggungjawaban periodik.',
            ?, '2026-04-15 09:00:00', '2026-04-15 10:00:00'
        )
    ");
    $ins1->execute([$techData1]);
    echo "Inserted Report: PT. Kideco Jaya Agung (ID: " . $pdo->lastInsertId() . ").\n";
} else {
    $upd1 = $pdo->prepare("
        UPDATE monitoring_reports SET
            category_id = 10,
            title = 'Penanganan Gangguan Frekuensi Radio PT. Kideco Jaya Agung',
            target_name = 'PT. Kideco Jaya Agung',
            frequency = '173.675 MHz & 173.250 MHz',
            location = 'Kabupaten Paser (Kec. Batu Sopang, Site SIMS, Desa Songka)',
            latitude = -1.847466, longitude = 115.906545,
            date_monitored = '2026-04-11', status = 'verified',
            verified_by = 1, verified_at = '2026-04-15 10:00:00',
            verification_notes = 'Laporan penanganan gangguan lengkap dan terverifikasi tuntas.',
            technical_data = ?
        WHERE id = ?
    ");
    $upd1->execute([$techData1, $rep1Id]);
    echo "Updated Report: PT. Kideco Jaya Agung (ID: $rep1Id).\n";
}

// Report 2: PT. Multi Harapan Utama (MHU)
$checkRep2 = $pdo->prepare("SELECT id FROM monitoring_reports WHERE report_code = 'REP-2026-08-MHU'");
$checkRep2->execute();
$rep2Id = $checkRep2->fetchColumn();

$techData2 = json_encode([
    'complaint_number' => '0458/OL/MHU-KTT/VII/2026 (27 Juli 2026)',
    'affected_service' => 'Radio Repeater Komunikasi Tambang MHU (Land Mobile)',
    'source_identified' => 'Kebocoran Booster Penyelenggara Siaran TV Kabel (Surya Kabel TV Digital & TV Kabel Haji Amir) pada Channel S08 (154.250 MHz)',
    'action_taken' => 'Pelacakan sumber interferensi menggunakan Mobil Monitoring & DF Toyota Hilux R&S ESMD & PR100. Ditemukan kebocoran booster TV kabel di Loa Janan & Loa Kulu. Diterbitkan Surat Teguran T-444/BALMON.64/SP.03.03/08/2026 dan dilakukan penghentian pancaran booster TV kabel hingga frekuensi Rx 154.250 MHz & Tx 159.250 MHz clear 100%.',
    'resolution_status' => 'Terselesaikan Tuntas (Clean - Bebas Gangguan)',
    'bap_number' => 'T-228/BALMON.64/SP.03.03/08/2026 & T-229/BALMON.64/SP.03.03/08/2026',
    'spt_number' => '113/BALMON.64/KP.01.06/08/2026',
    'team_leader' => 'Andreo Yustiantoro Anjaya, ST (NIP: 19770506 200803 1 001)',
    'team_members' => 'Warko, S.Kom, M.Si, Sri Sukisno, ST, Tantowi Pahala Hamonangan S, S.Kom',
    'equipment_used' => 'Mobil Monitoring & DF Toyota Hilux, R&S PR100, R&S ESMD DF Mode (20 MHz - 3 GHz), HE300, HE600, Laptop Dell R&S Argus, GPS Garmin GPSMAP-65S',
    'isr_numbers' => '03112651-000SU/2620262031 (Tx 159.250 MHz / Rx 154.250 MHz Repeater)',
    'disposition' => 'Disposisi Kepala Balai: Dilakukan pemantauan dan koordinasi lebih lanjut ke pihak terganggu terkait hasil penanganan gangguan. Laporan disimpan dan diarsipkan sebagai pertanggungjawaban perjalanan dinas'
], JSON_UNESCAPED_UNICODE);

if (!$rep2Id) {
    $ins2 = $pdo->prepare("
        INSERT INTO monitoring_reports (
            report_code, category_id, title, user_id, target_name, frequency, location,
            latitude, longitude, date_monitored, status, verified_by, verified_at,
            verification_notes, technical_data, created_at, updated_at
        ) VALUES (
            'REP-2026-08-MHU', 10, 'Penanganan Gangguan Frekuensi Radio PT. Multi Harapan Utama',
            2, 'PT. Multi Harapan Utama', 'Tx 159.250 MHz, Rx 154.250 MHz', 'Kabupaten Kutai Kartanegara (Kec. Loa Kulu, Desa Loa Duri, Desa Bakungan)',
            -0.536879, 117.027428, '2026-08-13', 'verified', 1, '2026-08-18 09:30:00',
            'Laporan penanganan gangguan lengkap dan terverifikasi tuntas. Sumber kebocoran booster TV kabel telah dihentikan (clear 100%). Disposisi Kepala Balai: Laporan disimpan dan diarsipkan sebagai pertanggungjawaban perjalanan dinas.',
            ?, '2026-08-18 08:30:00', '2026-08-18 09:30:00'
        )
    ");
    $ins2->execute([$techData2]);
    echo "Inserted Report: PT. Multi Harapan Utama (ID: " . $pdo->lastInsertId() . ").\n";
} else {
    $upd2 = $pdo->prepare("
        UPDATE monitoring_reports SET
            category_id = 10,
            title = 'Penanganan Gangguan Frekuensi Radio PT. Multi Harapan Utama',
            target_name = 'PT. Multi Harapan Utama',
            frequency = 'Tx 159.250 MHz, Rx 154.250 MHz',
            location = 'Kabupaten Kutai Kartanegara (Kec. Loa Kulu, Desa Loa Duri, Desa Bakungan)',
            latitude = -0.536879, longitude = 117.027428,
            date_monitored = '2026-08-13', status = 'verified',
            verified_by = 1, verified_at = '2026-08-18 09:30:00',
            verification_notes = 'Laporan penanganan gangguan lengkap dan terverifikasi tuntas. Sumber kebocoran booster TV kabel telah dihentikan (clear 100%).',
            technical_data = ?
        WHERE id = ?
    ");
    $upd2->execute([$techData2, $rep2Id]);
    echo "Updated Report: PT. Multi Harapan Utama (ID: $rep2Id).\n";
}

echo "\n=== 3. UPDATING PK.3 MONTHLY REKAP RECORDS ===\n";
// Update April PK.3
$updPkApr = $pdo->prepare("
    UPDATE pk_monthly_rekap 
    SET activity_title = 'Penanganan Gangguan PT. Kideco Jaya Agung (Kab. Paser)',
        activity_detail = 'Aduan frek 173.675 & 173.250 MHz: investigasi intermodulasi & external noise induksi GI PLN tuntas di 13 titik ukur',
        report_number = 'T-67/BALMON.64/SP.03.05/04/2026',
        report_date = '11 Apr 2026',
        status = 'achieved'
    WHERE pk_code = 'PK.3' AND year = 2026 AND month_num = 4
");
$updPkApr->execute();
echo "Updated PK.3 April monthly rekap with Kideco report.\n";

// Update Agustus PK.3
$updPkAgs = $pdo->prepare("
    UPDATE pk_monthly_rekap 
    SET activity_title = 'Penanganan Gangguan PT. Multi Harapan Utama (Kab. Kukar)',
        activity_detail = 'Aduan repeater Tx 159.250 / Rx 154.250 MHz: pelacakan DF eliminasi kebocoran booster TV kabel Loa Janan (Clear 100%)',
        report_number = 'T-229/BALMON.64/SP.03.03/08/2026',
        report_date = '13 Ags 2026',
        status = 'achieved'
    WHERE pk_code = 'PK.3' AND year = 2026 AND month_num = 8
");
$updPkAgs->execute();
echo "Updated PK.3 Agustus monthly rekap with MHU report.\n";

echo "\nDone!\n";
