<?php
// database/migrate_pk_monthly.php
require_once __DIR__ . '/../config/database.php';
$pdo = getDbConnection();

$pdo->exec("
CREATE TABLE IF NOT EXISTS pk_monthly_rekap (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pk_code VARCHAR(20) NOT NULL,
    year INTEGER NOT NULL DEFAULT 2026,
    month_num INTEGER NOT NULL,
    month_name VARCHAR(50) NOT NULL,
    activity_title TEXT NOT NULL,
    activity_detail TEXT NOT NULL,
    target_val VARCHAR(100) NOT NULL,
    realization_val VARCHAR(100) NOT NULL,
    cumulative_pct REAL NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'achieved',
    report_number VARCHAR(150) NOT NULL,
    report_date VARCHAR(50) NOT NULL,
    app_link VARCHAR(150) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_pk_monthly_lookup ON pk_monthly_rekap (pk_code, year, month_num);
");

// Cek apakah data sudah ada
$count = $pdo->query("SELECT count(*) FROM pk_monthly_rekap WHERE year = 2026")->fetchColumn();
if ($count > 0) {
    echo "pk_monthly_rekap already populated ($count rows)\n";
    exit;
}

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$items = [];

// ==========================================
// PK.1: Monitoring SFR di Kabupaten/Kota
// Target: 80% Kab/Kota (8 dari 10), Realisasi: 90% Kab/Kota (9 dari 10)
// ==========================================
$pk1Data = [
    1 => ['2 Kab/Kota (Kukar & Bontang)', '15 Pita Frekuensi, 680 ISR termonitor (Target 1.312 ISR), 850 pengguna teridentifikasi, sanksi = 0', '80% (8 Kab/Kota)', '2 Kab/Kota', 20.0, 'achieved', 'Laporan Bulanan SFR No. 73/BALMON.64/SP.02.10/01/2026', '31 Jan 2026'],
    2 => ['3 Kab/Kota: Kab. Kutai Kartanegara, Kab. Kutai Barat dan Kota Bontang', '15 Pita Frekuensi, 1.004 ISR termonitor (Target 1.312 dari 2.186 ISR), 1.300 pengguna teridentifikasi, sanksi = 0', '80% (8 Kab/Kota)', '3 Kab/Kota', 30.0, 'achieved', 'Laporan Bulanan SFR No. 132/BALMON.64/SP.02.10/02/2026', '28 Feb 2026'],
    3 => ['6 Kab/Kota: Kukar, Kubar, Bontang, Balikpapan, Samarinda dan Paser', '15 Pita Frekuensi, 1.174 ISR termonitor (Target 1.312 dari 2.186 ISR), 3.164 pengguna teridentifikasi, sanksi = 0', '80% (8 Kab/Kota)', '6 Kab/Kota', 60.0, 'achieved', 'Laporan Bulanan SFR No. 183/BALMON.64/SP.02.10/03/2026', '31 Mar 2026'],
    4 => ['7 Kab/Kota: Kukar, Kubar, Bontang, Balikpapan, Samarinda, Paser dan Kutim', '15 Pita Frekuensi, 1.614 ISR termonitor (Target 1.312 ISR terlampaui), 3.931 pengguna teridentifikasi, sanksi = 0', '80% (8 Kab/Kota)', '7 Kab/Kota', 70.0, 'achieved', 'Laporan Bulanan SFR No. 245/BALMON.64/SP.02.10/04/2026', '30 Apr 2026'],
    5 => ['7 Kab/Kota: Kukar, Kubar, Bontang, Balikpapan, Samarinda, Paser dan Kutim', '15 Pita Frekuensi, 1.614 ISR termonitor (Target 1.312 ISR), 4.127 pengguna teridentifikasi, sanksi = 0', '80% (8 Kab/Kota)', '7 Kab/Kota', 70.0, 'achieved', 'Laporan Bulanan SFR No. 311/BALMON.64/SP.02.10/05/2026', '31 Mei 2026'],
    6 => ['8 Kab/Kota: Kukar, Kubar, Bontang, Balikpapan, Samarinda, Paser, Kutim dan Berau', '15 Pita Frekuensi, 1.857 ISR termonitor (Target 1.312 ISR), 4.630 pengguna teridentifikasi, sanksi = 0 (Target 80% Terpenuhi)', '80% (8 Kab/Kota)', '8 Kab/Kota', 80.0, 'achieved', 'Laporan Bulanan SFR No. 378/BALMON.64/SP.02.10/06/2026', '30 Jun 2026'],
    7 => ['8 Kab/Kota (Pemeliharaan & Observasi Frekuensi Maritim/Penerbangan)', 'Observasi spektrum frekuensi maritim dan penerbangan pada 8 Kab/Kota termonitor, verifikasi kepatuhan ISR', '80% (8 Kab/Kota)', '8 Kab/Kota', 80.0, 'achieved', 'Laporan Bulanan SFR No. 420/BALMON.64/SP.02.10/07/2026', '31 Jul 2026'],
    8 => ['9 Kab/Kota (+ Kab. Penajam Paser Utara / Kawasan IKN)', 'Perluasan monitoring spektrum ke Kab. Penajam Paser Utara & kawasan Nusantara (IKN), 18 pita frekuensi aman', '80% (8 Kab/Kota)', '9 Kab/Kota', 90.0, 'achieved', 'Laporan Bulanan SFR No. 463/BALMON.64/SP.02.10/08/2026', '31 Ags 2026'],
    9 => ['9 Kab/Kota (Pengamanan Frekuensi & Koordinasi Stakeholder)', 'Monitoring dinas marabahaya dan dinas siaran, koordinasi lintas instansi pengamanan pita frekuensi', '80% (8 Kab/Kota)', '9 Kab/Kota', 90.0, 'achieved', 'Laporan Bulanan SFR No. 512/BALMON.64/SP.02.10/09/2026', '30 Sep 2026'],
    10 => ['10 Kab/Kota (+ Rencana Monitoring Kab. Mahakam Ulu)', 'Penjadwalan observasi stasiun bergerak ke Kab. Mahakam Ulu untuk pemenuhan 100% seluruh wilayah kerja', '80% (8 Kab/Kota)', '9 Kab/Kota', 90.0, 'in_progress', 'SPT-MON/10/BALMON.64/2026', '15 Okt 2026'],
    11 => ['10 Kab/Kota (Monitoring Terpadu Wilayah Perbatasan & Pedalaman)', 'Pelaksanaan observasi spektrum frekuensi radio di Kab. Mahakam Ulu (Ujoh Bilang & Long Bagun)', '80% (8 Kab/Kota)', '10 Kab/Kota', 100.0, 'pending', 'Draft SPT-MON/11/2026', 'Nov 2026'],
    12 => ['Evaluasi Tahunan 10 Kab/Kota (Konsolidasi ROL & Ditjen SDPPI)', 'Penyusunan laporan tahunan capaian PK monitoring SFR UPT Balmon Samarinda ke Ditjen Infrastruktur Digital', '80% (8 Kab/Kota)', '10 Kab/Kota', 100.0, 'pending', 'Draft Laporan Tahunan PK 2026', 'Des 2026']
];
foreach ($pk1Data as $m => $d) {
    $items[] = ['PK.1', $m, $months[$m], $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $d[7], 'Report Online (ROL)'];
}

// ==========================================
// PK.2: Pemeriksaan Stasiun (Target: 2.383 Stasiun)
// Realisasi s.d. September 2026: 2.188 Stasiun (91.82%)
// Komponen: FM Siaran (50), TV Digital (18), Remote Site (2.053), Open Shelter (262)
// ==========================================
$pk2Data = [
    1 => ['Persiapan Pemeriksaan Stasiun', 'FM: 0/50, TV: 0/18, Remote Site: 0/2053, Open Shelter: 0/262', '2.383 Stn', '0 Stasiun', 0.00, 'pending', 'Lap. APSTARD Jan 2026', '31 Jan 2026'],
    2 => ['Pengukuran Siaran FM & TV Digital', 'FM: 19/50 (38%), TV: 2/18 (11.1%), Remote Site: 0, Open Shelter: 0', '2.383 Stn', '21 Stasiun', 0.88, 'achieved', 'Lap. APSTARD Feb 2026', '28 Feb 2026'],
    3 => ['Pengukuran Siaran FM & TV Digital Lanjutan', 'FM: 23/50 (46%), TV: 4/18 (22.2%), Remote Site: 0, Open Shelter: 0', '2.383 Stn', '27 Stasiun', 1.13, 'achieved', 'Lap. APSTARD Mar 2026', '31 Mar 2026'],
    4 => ['Pemeriksaan Siaran, Remote Site & Open Shelter', 'FM: 32/50, TV: 10/18, Remote Site: 377/2053 (18.4%), Open Shelter: 180/262 (68.7%)', '2.383 Stn', '599 Stasiun', 25.14, 'achieved', 'Lap. APSTARD Apr 2026', '30 Apr 2026'],
    5 => ['Inspeksi Remote Site & Pengukuran Siaran', 'FM: 42/50, TV: 10/18, Remote Site: 470/2053 (22.9%), Open Shelter: 180/262 (68.7%)', '2.383 Stn', '702 Stasiun', 29.46, 'achieved', 'Lap. APSTARD Mei 2026', '31 Mei 2026'],
    6 => ['Realisasi Penuh Siaran FM & Open Shelter', 'FM: 50/50 (100%), TV: 12/18 (66.7%), Remote Site: 1087/2053 (53%), Open Shelter: 262/262 (100%)', '2.383 Stn', '1.411 Stasiun', 59.21, 'achieved', 'Lap. APSTARD Jun 2026', '30 Jun 2026'],
    7 => ['Pemeriksaan Lanjutan Remote Site & TV Digital', 'FM: 50/50, TV: 17/18 (94.4%), Remote Site: 1415/2053 (68.9%), Open Shelter: 262/262 (100%)', '2.383 Stn', '1.744 Stasiun', 73.19, 'achieved', 'Lap. APSTARD Jul 2026', '31 Jul 2026'],
    8 => ['Pemeriksaan Open Shelter Melampaui Target', 'FM: 50/50, TV: 17/18, Remote Site: 1415/2053 (68.9%), Open Shelter: 330/262 (125.9%)', '2.383 Stn', '1.812 Stasiun', 76.04, 'achieved', 'Lap. APSTARD Ags 2026', '31 Ags 2026'],
    9 => ['Pemeriksaan Intensif Remote Site Microwave', 'FM: 50/50 (100%), TV: 17/18 (94.4%), Remote Site: 1791/2053 (87.3%), Open Shelter: 330/262 (125.9%)', '2.383 Stn', '2.188 Stasiun', 91.82, 'achieved', 'Lap. APSTARD Sep 2026', '30 Sep 2026'],
    10 => ['Pemeriksaan Berjalan Triwulan IV', 'Target sisa 262 Remote Site & 1 TV Digital menuju target 100% tahunan', '2.383 Stn', '2.188 Stasiun', 91.82, 'in_progress', 'SPT-INS/10/2026', '15 Okt 2026'],
    11 => ['Verifikasi Lapangan & Sinkronisasi APSTARD', 'Pemeriksaan sampling sisa stasiun microwave remote area', '2.383 Stn', '2.188 Stasiun', 91.82, 'pending', 'Draft SPT-INS/11/2026', 'Nov 2026'],
    12 => ['Finalisasi Laporan Tahunan APSTARD & Sanksi', 'Rekapitulasi tahunan 2.383 stasiun radio & evaluasi tindak lanjut sanksi', '2.383 Stn', '2.188 Stasiun', 91.82, 'pending', 'Lap. Tahunan APSTARD 2026', 'Des 2026']
];
foreach ($pk2Data as $m => $d) {
    $items[] = ['PK.2', $m, $months[$m], $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $d[7], 'APSTARD & ROL'];
}


// ==========================================
// PK.3: Penyelesaian Gangguan Frekuensi Radio
// Target: 100%, Realisasi: 100.0% (2 Kasus Riil Selesai Tuntas)
// Sumber: SPT 46/2026 (Kideco, Paser) & SPT 113/2026 (MHU, Kukar)
// ==========================================
$pk3Data = [
    1 => ['Tidak Ada Kegiatan Penanganan Gangguan', 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Januari 2026', '-', '-', 100.0, 'achieved', '-', 'Jan 2026'],
    2 => ['Tidak Ada Kegiatan Penanganan Gangguan', 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Februari 2026', '-', '-', 100.0, 'achieved', '-', 'Feb 2026'],
    3 => ['Tidak Ada Kegiatan Penanganan Gangguan', 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Maret 2026', '-', '-', 100.0, 'achieved', '-', 'Mar 2026'],
    4 => ['Penanganan Gangguan Frekuensi Radio PT. Kideco Jaya Agung', 'Aduan radio komunikasi tambang (173.675 & 173.250 MHz): pelacakan DF 13 titik ukur, eliminasi intermodulasi & noise PLN (BAP T-67 & T-68, Clean 100%)', '1 Aduan', '1 Selesai', 100.0, 'achieved', 'T-67/BALMON.64/SP.03.05/04/2026', '14 Apr 2026'],
    5 => ['Tidak Ada Kegiatan Penanganan Gangguan', 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Mei 2026', '-', '-', 100.0, 'achieved', '-', 'Mei 2026'],
    6 => ['Tidak Ada Kegiatan Penanganan Gangguan', 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Juni 2026', '-', '-', 100.0, 'achieved', '-', 'Jun 2026'],
    7 => ['Tidak Ada Kegiatan Penanganan Gangguan', 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Juli 2026', '-', '-', 100.0, 'achieved', '-', 'Jul 2026'],
    8 => ['Penanganan Gangguan Frekuensi Radio PT. Multi Harapan Utama', 'Aduan repeater radio tambang Tx 159.250 / Rx 154.250 MHz: DF Mobil Hilux ESMD, eliminasi kebocoran booster TV kabel (BAP T-228 & T-229, Clean 100%)', '1 Aduan', '1 Selesai', 100.0, 'achieved', 'T-228/BALMON.64/SP.03.03/08/2026', '13 Ags 2026'],
    9 => ['Tidak Ada Kegiatan Penanganan Gangguan', 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan September 2026', '-', '-', 100.0, 'achieved', '-', 'Sep 2026'],
    10 => ['Tidak Ada Kegiatan Penanganan Gangguan', 'Tidak ada laporan pengaduan gangguan spektrum frekuensi radio yang masuk pada bulan Oktober 2026', '-', '-', 100.0, 'achieved', '-', 'Okt 2026'],
    11 => ['Tidak Ada Kegiatan Penanganan Gangguan', 'Siaga monitoring & respon cepat penanganan gangguan frekuensi radio 1x24 jam (dinas keselamatan) / 7x24 jam (non-keselamatan)', '-', '-', 100.0, 'pending', 'SOP Respon Cepat', 'Nov 2026'],
    12 => ['Tidak Ada Kegiatan Penanganan Gangguan', 'Rekonsiliasi tahunan trouble ticket laporgangguansfr.postel.go.id dan evaluasi penanganan gangguan frekuensi radio TA 2026', '-', '-', 100.0, 'pending', 'Laporan Tahunan', 'Des 2026']
];
foreach ($pk3Data as $m => $d) {
    $items[] = ['PK.3', $m, $months[$m], $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $d[7], 'Trouble Ticket SFR'];
}

// ==========================================
// PK.4: Monitoring Perangkat Telekomunikasi (APT)
// Target: 90% (200 Perangkat), Realisasi: 88% (176 Perangkat)
// ==========================================
$pk4Data = [
    1 => ['Pengawasan Toko Elektronik Samarinda', 'Sampling 20 perangkat HT, Access Point & Smart TV', '18 Unit', '20 Unit', 10.0, 'achieved', '82/BALMON.64/SP.02.10/01/2026', '31 Jan 2026'],
    2 => ['Pengawasan Distributor IT Balikpapan', 'Sampling 25 router WiFi 6, switch & perangkat nirkabel', '20 Unit', '25 Unit', 22.5, 'achieved', '142/BALMON.64/SP.02.10/02/2026', '28 Feb 2026'],
    3 => ['Pengawasan Pasar Elektronik Tenggarong', 'Sampling 18 perangkat radio komunikasi & pemancar', '18 Unit', '18 Unit', 31.5, 'achieved', '195/BALMON.64/SP.02.10/03/2026', '31 Mar 2026'],
    4 => ['Inspeksi Perangkat Nirkabel Bontang', 'Sampling 22 perangkat jaringan IoT & drone komersial', '20 Unit', '22 Unit', 42.5, 'achieved', '255/BALMON.64/SP.02.10/04/2026', '30 Apr 2026'],
    5 => ['Monitoring Perangkat Cerdas IKN', 'Sampling 20 sensor pintar & gateway telekomunikasi KIPP', '20 Unit', '20 Unit', 52.5, 'achieved', '320/BALMON.64/SP.02.10/05/2026', '31 Mei 2026'],
    6 => ['Pengawasan Perangkat Kutai Timur', 'Sampling 18 perangkat HT pertambangan & modem GSM', '18 Unit', '18 Unit', 61.5, 'achieved', '388/BALMON.64/SP.02.10/06/2026', '30 Jun 2026'],
    7 => ['Monitoring Perangkat Paser', 'Sampling 16 perangkat pemancar radio & perangkat telekomunikasi', '16 Unit', '16 Unit', 69.5, 'achieved', '431/BALMON.64/SP.02.10/07/2026', '31 Jul 2026'],
    8 => ['Pengawasan Pasar Maritim Berau', 'Sampling 21 perangkat radio maritim VHF & GPS receiver', '20 Unit', '21 Unit', 80.0, 'achieved', '474/BALMON.64/SP.02.10/08/2026', '31 Ags 2026'],
    9 => ['Monitoring Perangkat Perbatasan Tarakan', 'Sampling 16 perangkat telekomunikasi impor dan lokal', '16 Unit', '16 Unit', 88.0, 'achieved', '522/BALMON.64/SP.02.10/09/2026', '30 Sep 2026'],
    10 => ['Pengawasan Perangkat Bulungan', 'Sampling berjalan di distributor Tanjung Selor', '15 Unit', '5 Unit', 88.0, 'in_progress', 'SPT-APT/10/2026', '16 Okt 2026'],
    11 => ['Pengawasan Pedalaman Kubar', 'Sampling perangkat komunikasi remote area', '15 Unit', '0 Unit', 88.0, 'pending', 'Draft Agenda', 'Nov 2026'],
    12 => ['Rekap Konsolidasi SMART', 'Finalisasi seluruh berita acara hasil monitoring APT', '10 Unit', '0 Unit', 88.0, 'pending', 'Lap. SMART Tahunan', 'Des 2026']
];
foreach ($pk4Data as $m => $d) {
    $items[] = ['PK.4', $m, $months[$m], $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $d[7], 'Aplikasi SMART'];
}

// ==========================================
// PK.5: Kepatuhan Standar Teknis & Sanksi APT (Tertib APT)
// Target: 100%, Realisasi: 100%
// ==========================================
$pk5Data = [
    1 => ['Penindakan HT Non-Sertifikasi', '2 temuan perangkat tanpa sertifikat SDPPI diterbitkan Surat Teguran I', '2 Kasus', '2 Teguran (100%)', 100.0, 'achieved', '84/BALMON.64/SP.02.10/01/2026', '31 Jan 2026'],
    2 => ['Operasi Penertiban Repeater Ilegal', '3 repeater GSM ilegal ditarik dan diberikan sanksi administratif', '3 Kasus', '3 Teguran (100%)', 100.0, 'achieved', '144/BALMON.64/SP.02.10/02/2026', '28 Feb 2026'],
    3 => ['Tindak Lanjut Wireless Tenggarong', '1 temuan router outdoor non-standar dikenakan sanksi tertulis', '1 Kasus', '1 Teguran (100%)', 100.0, 'achieved', '197/BALMON.64/SP.02.10/03/2026', '31 Mar 2026'],
    4 => ['Penindakan Pemancar Liar Bontang', '2 temuan peralatan transmisi tanpa izin ditindaklanjuti', '2 Kasus', '2 Teguran (100%)', 100.0, 'achieved', '257/BALMON.64/SP.02.10/04/2026', '30 Apr 2026'],
    5 => ['Penertiban Booster Sinyal IKN', '1 kasus penggunaan penguat sinyal tidak sesuai spek diamankan', '1 Kasus', '1 Teguran (100%)', 100.0, 'achieved', '322/BALMON.64/SP.02.10/05/2026', '31 Mei 2026'],
    6 => ['Tindak Lanjut Perangkat Tambang Kutim', '2 temuan perangkat komunikasi tambang non-sertifikasi diberi SPP', '2 Kasus', '2 Teguran (100%)', 100.0, 'achieved', '390/BALMON.64/SP.02.10/06/2026', '30 Jun 2026'],
    7 => ['Penindakan Perangkat Paser', '1 kasus penjualan perangkat tanpa label Komdigi ditindak', '1 Kasus', '1 Teguran (100%)', 100.0, 'achieved', '433/BALMON.64/SP.02.10/07/2026', '31 Jul 2026'],
    8 => ['Penertiban Radio Maritim Berau', '2 perangkat radio kapal non-standar diberikan surat peringatan', '2 Kasus', '2 Teguran (100%)', 100.0, 'achieved', '476/BALMON.64/SP.02.10/08/2026', '31 Ags 2026'],
    9 => ['Penindakan Toko Elektronik Tarakan', '1 temuan perangkat ilegal ditarik dari peredaran', '1 Kasus', '1 Teguran (100%)', 100.0, 'achieved', '524/BALMON.64/SP.02.10/09/2026', '30 Sep 2026'],
    10 => ['Monitoring Kepatuhan Pasca-Sanksi', 'Verifikasi 7 hari kalender atas pemenuhan surat teguran', 'Semua Temuan', '100% Patuh', 100.0, 'in_progress', 'SPT-TL/10/2026', '20 Okt 2026'],
    11 => ['Evaluasi Kepatuhan Pelaku Usaha', 'Pemeriksaan ulang gerai distributor yang pernah ditegur', 'Sampling Usaha', '100% Patuh', 100.0, 'pending', 'Draft Verifikasi', 'Nov 2026'],
    12 => ['Laporan Tahunan Penegakan Hukum APT', 'Konsolidasi data penertiban dan pemenuhan standar teknis', '15 Total Kasus', '15 Selesai (100%)', 100.0, 'pending', 'Lap. Kepatuhan APT', 'Des 2026']
];
foreach ($pk5Data as $m => $d) {
    $items[] = ['PK.5', $m, $months[$m], $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $d[7], 'Aplikasi SMART'];
}

// ==========================================
// PK.6: Operasional & SLA Stasiun Monitor (SMFR)
// Target: 86% SLA, Realisasi: 89.5% SLA
// ==========================================
$pk6Data = [
    1 => ['SLA Stasiun Monitor Samarinda & Mobile', 'Stasiun Tetap SLA 98.2%, Stasiun Bergerak SLA 80.2%, upload SMSN 4x', 'SLA 86%', 'SLA 89.2%', 89.2, 'achieved', '86/BALMON.64/SP.02.10/01/2026', '31 Jan 2026'],
    2 => ['SLA Monitoring Frekuensi Balikpapan', 'Stasiun Tetap SLA 97.8%, Stasiun Bergerak SLA 82.4%, upload SMSN 4x', 'SLA 86%', 'SLA 90.1%', 89.6, 'achieved', '146/BALMON.64/SP.02.10/02/2026', '28 Feb 2026'],
    3 => ['Pemeliharaan Modul PMU & SLA Maret', 'Maintenance receiver tetap, ketersediaan operasional terjaga prima', 'SLA 86%', 'SLA 88.7%', 89.3, 'achieved', '199/BALMON.64/SP.02.10/03/2026', '31 Mar 2026'],
    4 => ['Operasional Penuh Stasiun Tetap April', 'Monitoring otomatis pita 88-108 MHz & 400-470 MHz 24/7 aktif', 'SLA 86%', 'SLA 91.0%', 89.7, 'achieved', '259/BALMON.64/SP.02.10/04/2026', '30 Apr 2026'],
    5 => ['Dukungan Mobile SMFR di Kawasan IKN', 'Mobil monitoring operasional penuh pengawalan spektrum VVIP IKN', 'SLA 86%', 'SLA 92.4%', 90.3, 'achieved', '324/BALMON.64/SP.02.10/05/2026', '31 Mei 2026'],
    6 => ['SLA Stasiun Transportable Berau', 'Pemeriksaan link antena RDF & sinkronisasi data okupansi', 'SLA 86%', 'SLA 89.8%', 90.2, 'achieved', '392/BALMON.64/SP.02.10/06/2026', '30 Jun 2026'],
    7 => ['Maintenance Periodik Mast Antenna', 'Kalibrasi arah kompas antena & peremajaan kabel feeder', 'SLA 86%', 'SLA 88.5%', 90.0, 'achieved', '435/BALMON.64/SP.02.10/07/2026', '31 Jul 2026'],
    8 => ['Inspeksi Berkala Aplikasi SMFR v2', 'Verifikasi ketersediaan sensor remote & server database', 'SLA 86%', 'SLA 90.3%', 90.0, 'achieved', '478/BALMON.64/SP.02.10/08/2026', '31 Ags 2026'],
    9 => ['Sinkronisasi SMSN Nasional September', 'Data okupansi seluruh sensor terkirim tepat waktu ke pusat', 'SLA 86%', 'SLA 89.6%', 89.5, 'achieved', '526/BALMON.64/SP.02.10/09/2026', '30 Sep 2026'],
    10 => ['Operasional Sensor Berjalan Oktober', 'Pemantauan real-time pita frekuensi seluler & penerbangan', 'SLA 86%', 'SLA 89.0%', 89.5, 'in_progress', 'SMFR-LOG/10/2026', '22 Okt 2026'],
    11 => ['Kalibrasi Akhir Tahun Perangkat Monitoring', 'Uji sensitivitas receiver & akurasi Direction Finding', 'SLA 86%', 'SLA 88.5%', 89.5, 'pending', 'Draft Kalibrasi', 'Nov 2026'],
    12 => ['Rekapitulasi Ketersediaan SMFR Tahunan', 'Konsolidasi SLA gabungan stasiun tetap & bergerak UPT', 'SLA 86%', 'SLA 89.5%', 89.5, 'pending', 'Lap. Kinerja SMFR', 'Des 2026']
];
foreach ($pk6Data as $m => $d) {
    $items[] = ['PK.6', $m, $months[$m], $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $d[7], 'SMFR v2 & SMSN'];
}

// ==========================================
// PK.7: Penanganan Sanksi Denda Administratif
// Target: 85%, Realisasi: 78.5%
// ==========================================
$pk7Data = [
    1 => ['Pengelolaan SPP Tagihan Denda Januari', '45 surat tagihan diterbitkan, 32 terbayar lunas ke kas negara', '45 SPP', '32 Terbayar', 71.1, 'achieved', '88/BALMON.64/SP.02.10/01/2026', '31 Jan 2026'],
    2 => ['Penagihan Aktif Tagihan II Februari', '50 tagihan denda diproses, 38 terbayar lunas tepat waktu', '50 SPP', '38 Terbayar', 76.0, 'achieved', '148/BALMON.64/SP.02.10/02/2026', '28 Feb 2026'],
    3 => ['Optimalisasi Penerimaan Denda Maret', '48 tagihan denda ditindaklanjuti, 39 terbayar lunas', '48 SPP', '39 Terbayar', 81.2, 'achieved', '201/BALMON.64/SP.02.10/03/2026', '31 Mar 2026'],
    4 => ['Penerbitan Surat Peringatan April', '52 tagihan dipantau, 40 terbayar, 12 masuk penagihan intensif', '52 SPP', '40 Terbayar', 76.9, 'achieved', '261/BALMON.64/SP.02.10/04/2026', '30 Apr 2026'],
    5 => ['Penanganan Piutang Denda Mei', '44 surat tagihan dikelola, 35 terbayar lunas', '44 SPP', '35 Terbayar', 79.5, 'achieved', '326/BALMON.64/SP.02.10/05/2026', '31 Mei 2026'],
    6 => ['Rekonsiliasi Semester I Denda Administratif', '60 tagihan diproses, 47 lunas, 13 optimalisasi penagihan', '60 SPP', '47 Terbayar', 78.3, 'achieved', '394/BALMON.64/SP.02.10/06/2026', '30 Jun 2026'],
    7 => ['Penanganan Tagihan Denda Juli', '40 surat tagihan dipantau, 32 terbayar lunas', '40 SPP', '32 Terbayar', 80.0, 'achieved', '437/BALMON.64/SP.02.10/07/2026', '31 Jul 2026'],
    8 => ['Penagihan Lanjutan & Konsultasi Wajib Bayar', '55 surat tagihan dikawal, 43 terbayar ke kas negara', '55 SPP', '43 Terbayar', 78.2, 'achieved', '480/BALMON.64/SP.02.10/08/2026', '31 Ags 2026'],
    9 => ['Pemberkasan Piutang Macet September', '46 tagihan diproses, 36 terbayar, 10 persiapan berkas KPKNL', '46 SPP', '36 Terbayar', 78.5, 'achieved', '528/BALMON.64/SP.02.10/09/2026', '30 Sep 2026'],
    10 => ['Optimalisasi Penagihan Berjalan Oktober', 'Pemanggilan dan koordinasi wajib bayar denda IPFR/APT', '40 SPP', '20 Terbayar', 78.5, 'in_progress', 'SPT-DND/10/2026', '24 Okt 2026'],
    11 => ['Pelimpahan Berkas Piutang ke KPKNL', 'Penyerahan penagihan piutang macet ke Panitia Urusan Piutang Negara', '15 Berkas', '10 Diproses', 78.5, 'pending', 'Draft Nota Dinas', 'Nov 2026'],
    12 => ['Rekonsiliasi Akhir Tahun PNBP Denda', 'Penutupan buku kas penerimaan denda administratif tahun anggaran', 'Semua SPP', 'Real 78.5%', 78.5, 'pending', 'Lap. Keuangan Tahunan', 'Des 2026']
];
foreach ($pk7Data as $m => $d) {
    $items[] = ['PK.7', $m, $months[$m], $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $d[7], 'Aplikasi Denda Administratif'];
}

// ==========================================
// PK.8: Data Kualitas Layanan Seluler (QoS)
// Target: 100% Kab/Kota (8 Kab/Kota prioritas), Realisasi: 90% Kab/Kota (7 Kab/Kota)
// ==========================================
$pk8Data = [
    1 => ['Drive Test QoS Kota Samarinda', 'Pengukuran throughput, latensi, video streaming & call Telkomsel, IOH, XL', '1 Kab/Kota', '1 Selesai', 12.5, 'achieved', '90/BALMON.64/SP.02.10/01/2026', '31 Jan 2026'],
    2 => ['Drive Test QoS Kota Balikpapan', 'Pengukuran 5G/4G di rute protokol, pelabuhan Semayang & bandara', '1 Kab/Kota', '1 Selesai', 25.0, 'achieved', '150/BALMON.64/SP.02.10/02/2026', '28 Feb 2026'],
    3 => ['Drive Test QoS Kab. Kutai Kartanegara', 'Pengukuran rute Tenggarong & poros jalan trans-Kalimantan', '1 Kab/Kota', '1 Selesai', 37.5, 'achieved', '203/BALMON.64/SP.02.10/03/2026', '31 Mar 2026'],
    4 => ['Drive Test QoS Kota Bontang', 'Pengukuran area permukiman, industri pupuk & gas alam', '1 Kab/Kota', '1 Selesai', 50.0, 'achieved', '263/BALMON.64/SP.02.10/04/2026', '30 Apr 2026'],
    5 => ['Drive Test QoS Kawasan Inti IKN & PPU', 'Pengukuran coverage & quality 5G/4G di seluruh titik penting KIPP IKN', '1 Kab/Kota', '1 Selesai', 62.5, 'achieved', '328/BALMON.64/SP.02.10/05/2026', '31 Mei 2026'],
    6 => ['Drive Test QoS Kab. Kutai Timur', 'Pengukuran jaringan seluler di Sangatta Utara & kawasan komersial', '1 Kab/Kota', '1 Selesai', 75.0, 'achieved', '396/BALMON.64/SP.02.10/06/2026', '30 Jun 2026'],
    7 => ['Drive Test QoS Kab. Paser', 'Pengukuran kecepatan download/upload & packet loss di Tanah Grogot', '1 Kab/Kota', '1 Selesai', 87.5, 'achieved', '439/BALMON.64/SP.02.10/07/2026', '31 Jul 2026'],
    8 => ['Post-Processing & Upload PMT Pusat', 'Validasi data hasil ukur 7 Kab/Kota ke server Pusat Monitoring Telko', 'Proses PMT', '7 Terverifikasi', 87.5, 'achieved', '482/BALMON.64/SP.02.10/08/2026', '31 Ags 2026'],
    9 => ['Drive Test QoS Kab. Berau', 'Pengukuran kualitas seluler di Tanjung Redeb & jalur wisata', '1 Kab/Kota', '1 Selesai', 90.0, 'achieved', '530/BALMON.64/SP.02.10/09/2026', '30 Sep 2026'],
    10 => ['Analisis PMT & Verifikasi Komdigi', 'Verifikasi parameter QoE/QoS dengan Ditjen PPI & operator seluler', '1 Analisis', 'On Progress', 90.0, 'in_progress', 'PMT-LOG/10/2026', '25 Okt 2026'],
    11 => ['Pengukuran QoS Kota Tarakan', 'Rencana drive test kualitas seluler wilayah perbatasan Kaltara', '1 Kab/Kota', '0 Selesai', 90.0, 'pending', 'Draft Agenda', 'Nov 2026'],
    12 => ['Laporan Tahunan Kualitas Layanan QoS', 'Penyusunan benchmark kualitas seluler se-Kaltim & IKN 2026', '8 Kab/Kota', '7 Selesai (90%)', 90.0, 'pending', 'Lap. QoS Tahunan', 'Des 2026']
];
foreach ($pk8Data as $m => $d) {
    $items[] = ['PK.8', $m, $months[$m], $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $d[7], 'Pusat Monitoring Telekomunikasi (PMT)'];
}

$stmt = $pdo->prepare("
    INSERT INTO pk_monthly_rekap 
    (pk_code, year, month_num, month_name, activity_title, activity_detail, target_val, realization_val, cumulative_pct, status, report_number, report_date, app_link)
    VALUES (?, 2026, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$inserted = 0;
foreach ($items as $row) {
    $stmt->execute([
        $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11]
    ]);
    $inserted++;
}

echo "Successfully migrated pk_monthly_rekap table with $inserted monthly records!\n";
