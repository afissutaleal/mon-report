<?php
// api/download_template.php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$type = $_GET['type'] ?? 'pk1_sfr_xls';

if ($type === 'pk1_sfr_csv') {
    $filename = "Template_Monitoring_SFR_PK1_Balmon_Samarinda.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Output UTF-8 BOM so Excel opens it correctly with accents
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    // Header
    fputcsv($output, [
        'No',
        'Kabupaten_Kota',
        'Jumlah_Pita_Frekuensi',
        'Persentase_ISR_ROL',
        'Persentase_Teridentifikasi',
        'Tindak_Lanjut_Sanksi_Persen',
        'Keterangan'
    ]);
    
    // Sample rows for 10 Kab/Kota Balmon Samarinda & IKN
    $data = [
        [1, 'Kota Samarinda', 18, '75%', '100%', '100%', 'Pita >= 15, ROL >= 60%, Teridentifikasi 100%, Sanksi 100%'],
        [2, 'Kota Balikpapan', 22, '80%', '100%', '100%', 'Pita >= 15, ROL >= 60%, Teridentifikasi 100%, Sanksi 100%'],
        [3, 'Kabupaten Kutai Kartanegara', 17, '65%', '100%', '100%', 'Pita >= 15, ROL >= 60%, Teridentifikasi 100%, Sanksi 100%'],
        [4, 'Kabupaten Kutai Timur', 16, '62%', '100%', '100%', 'Pita >= 15, ROL >= 60%, Teridentifikasi 100%, Sanksi 100%'],
        [5, 'Kota Bontang', 17, '70%', '100%', '100%', 'Pita >= 15, ROL >= 60%, Teridentifikasi 100%, Sanksi 100%'],
        [6, 'Kabupaten Penajam Paser Utara (IKN)', 19, '76%', '100%', '100%', 'Pita >= 15, ROL >= 60%, Teridentifikasi 100%, Sanksi 100%'],
        [7, 'Kabupaten Paser', 15, '60%', '100%', '100%', 'Pita >= 15, ROL >= 60%, Teridentifikasi 100%, Sanksi 100%'],
        [8, 'Kabupaten Berau', 16, '64%', '100%', '100%', 'Pita >= 15, ROL >= 60%, Teridentifikasi 100%, Sanksi 100%'],
        [9, 'Kabupaten Kutai Barat', 12, '52%', '100%', '100%', 'Perlu penambahan pita frekuensi min. 15'],
        [10, 'Kabupaten Mahakam Ulu', 8, '45%', '100%', '100%', 'Perlu penambahan sampling ROL dan pita frekuensi']
    ];
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;

} elseif ($type === 'pk1_sfr_xls') {
    $filename = "Template_Monitoring_SFR_PK1_Balmon_Samarinda.xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <style>
            body { font-family: Arial, sans-serif; }
            .header-title { font-size: 14pt; font-weight: bold; color: #1e3a8a; }
            .header-sub { font-size: 10pt; color: #475569; }
            .th-blue { background-color: #0070E0; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #94a3b8; }
            .th-info { background-color: #f1f5f9; font-size: 9pt; color: #334155; border: 1px solid #cbd5e1; }
            .td-data { border: 1px solid #cbd5e1; font-size: 10pt; }
            .td-num { border: 1px solid #cbd5e1; font-size: 10pt; text-align: center; }
            .td-target-ok { background-color: #dcfce7; color: #166534; font-weight: bold; text-align: center; }
            .td-target-no { background-color: #fee2e2; color: #991b1b; font-weight: bold; text-align: center; }
        </style>
    </head>
    <body>
        <table>
            <tr>
                <td colspan="8" class="header-title">FORMAT REKAPITULASI CAPAIAN MONITORING SFR KABUPATEN/KOTA (PK.1)</td>
            </tr>
            <tr>
                <td colspan="8" class="header-sub">Berdasarkan Nota Dinas Dirjen ID No. 224/DJID.6/PR.04.01/02/2026 - Wilayah Kerja Balmon Kelas I Samarinda (10 Kab/Kota)</td>
            </tr>
            <tr><td colspan="8"></td></tr>
            <tr style="height: 28px;">
                <th class="th-blue" style="width: 40px;">No</th>
                <th class="th-blue" style="width: 220px;">Kabupaten_Kota</th>
                <th class="th-blue" style="width: 140px;">Jumlah_Pita_Frekuensi<br/><small>(Syarat: >= 15)</small></th>
                <th class="th-blue" style="width: 140px;">Persentase_ISR_ROL<br/><small>(Syarat: >= 60%)</small></th>
                <th class="th-blue" style="width: 150px;">Persentase_Teridentifikasi<br/><small>(Syarat: 100%)</small></th>
                <th class="th-blue" style="width: 160px;">Tindak_Lanjut_Sanksi_Persen<br/><small>(Syarat: 100%)</small></th>
                <th class="th-blue" style="width: 140px;">Status_Kab_Kota<br/><small>(Otomatis oleh Sistem)</small></th>
                <th class="th-blue" style="width: 250px;">Keterangan</th>
            </tr>
            <tr>
                <td class="td-num">1</td>
                <td class="td-data">Kota Samarinda</td>
                <td class="td-num">18</td>
                <td class="td-num">75%</td>
                <td class="td-num">100%</td>
                <td class="td-num">100%</td>
                <td class="td-target-ok">MEMENUHI</td>
                <td class="td-data">Lengkap 4 kriteria ND No. 224</td>
            </tr>
            <tr>
                <td class="td-num">2</td>
                <td class="td-data">Kota Balikpapan</td>
                <td class="td-num">22</td>
                <td class="td-num">80%</td>
                <td class="td-num">100%</td>
                <td class="td-num">100%</td>
                <td class="td-target-ok">MEMENUHI</td>
                <td class="td-data">Lengkap 4 kriteria ND No. 224</td>
            </tr>
            <tr>
                <td class="td-num">3</td>
                <td class="td-data">Kabupaten Kutai Kartanegara</td>
                <td class="td-num">17</td>
                <td class="td-num">65%</td>
                <td class="td-num">100%</td>
                <td class="td-num">100%</td>
                <td class="td-target-ok">MEMENUHI</td>
                <td class="td-data">Lengkap 4 kriteria ND No. 224</td>
            </tr>
            <tr>
                <td class="td-num">4</td>
                <td class="td-data">Kabupaten Kutai Timur</td>
                <td class="td-num">16</td>
                <td class="td-num">62%</td>
                <td class="td-num">100%</td>
                <td class="td-num">100%</td>
                <td class="td-target-ok">MEMENUHI</td>
                <td class="td-data">Lengkap 4 kriteria ND No. 224</td>
            </tr>
            <tr>
                <td class="td-num">5</td>
                <td class="td-data">Kota Bontang</td>
                <td class="td-num">17</td>
                <td class="td-num">70%</td>
                <td class="td-num">100%</td>
                <td class="td-num">100%</td>
                <td class="td-target-ok">MEMENUHI</td>
                <td class="td-data">Lengkap 4 kriteria ND No. 224</td>
            </tr>
            <tr>
                <td class="td-num">6</td>
                <td class="td-data">Kabupaten Penajam Paser Utara (IKN)</td>
                <td class="td-num">19</td>
                <td class="td-num">76%</td>
                <td class="td-num">100%</td>
                <td class="td-num">100%</td>
                <td class="td-target-ok">MEMENUHI</td>
                <td class="td-data">Lengkap 4 kriteria ND No. 224</td>
            </tr>
            <tr>
                <td class="td-num">7</td>
                <td class="td-data">Kabupaten Paser</td>
                <td class="td-num">15</td>
                <td class="td-num">60%</td>
                <td class="td-num">100%</td>
                <td class="td-num">100%</td>
                <td class="td-target-ok">MEMENUHI</td>
                <td class="td-data">Lengkap 4 kriteria ND No. 224</td>
            </tr>
            <tr>
                <td class="td-num">8</td>
                <td class="td-data">Kabupaten Berau</td>
                <td class="td-num">16</td>
                <td class="td-num">64%</td>
                <td class="td-num">100%</td>
                <td class="td-num">100%</td>
                <td class="td-target-ok">MEMENUHI</td>
                <td class="td-data">Lengkap 4 kriteria ND No. 224</td>
            </tr>
            <tr>
                <td class="td-num">9</td>
                <td class="td-data">Kabupaten Kutai Barat</td>
                <td class="td-num">12</td>
                <td class="td-num">52%</td>
                <td class="td-num">100%</td>
                <td class="td-num">100%</td>
                <td class="td-target-no">BELUM MEMENUHI</td>
                <td class="td-data">Perlu penambahan minimal 3 pita frekuensi</td>
            </tr>
            <tr>
                <td class="td-num">10</td>
                <td class="td-data">Kabupaten Mahakam Ulu</td>
                <td class="td-num">8</td>
                <td class="td-num">45%</td>
                <td class="td-num">100%</td>
                <td class="td-num">100%</td>
                <td class="td-target-no">BELUM MEMENUHI</td>
                <td class="td-data">Perlu penambahan sampling ROL dan pita frekuensi</td>
            </tr>
            <tr><td colspan="8"></td></tr>
            <tr>
                <td colspan="8" style="font-size: 9pt; color: #64748b; font-style: italic;">
                    * Petunjuk: Unggah berkas ini di menu "Impor Data & Tracing". Sistem akan otomatis menghitung jumlah Kabupaten/Kota yang memenuhi syarat, mengkalkulasi persentase capaian PK.1 ((Termonitor / 10) * 100%), dan memperbarui data dashboard serta mencatat jejak audit per baris data.
                </td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    exit;

} elseif ($type === 'bulk_pk_csv') {
    $filename = "Template_Bulk_Capaian_PK_2026.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Kode_PK', 'Judul_Indikator', 'Target_Tahunan', 'Satuan', 'Realisasi', 'Keterangan_Dukung']);
    
    $pdo = getDbConnection();
    $pkItems = $pdo->query("SELECT code, title, target_annual, unit, realization, notes FROM perjanjian_kinerja ORDER BY id ASC")->fetchAll();
    
    foreach ($pkItems as $pk) {
        fputcsv($output, [
            $pk['code'],
            $pk['title'],
            $pk['target_annual'],
            $pk['unit'],
            $pk['realization'],
            $pk['notes']
        ]);
    }
    fclose($output);
    exit;

} elseif ($type === 'bulk_pk_xls') {
    $filename = "Template_Bulk_Capaian_PK_2026.xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $pdo = getDbConnection();
    $pkItems = $pdo->query("SELECT code, title, target_annual, unit, realization, notes FROM perjanjian_kinerja ORDER BY id ASC")->fetchAll();
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <style>
            .th-blue { background-color: #0070E0; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #94a3b8; }
            .td-data { border: 1px solid #cbd5e1; font-size: 10pt; }
            .td-num { border: 1px solid #cbd5e1; font-size: 10pt; text-align: center; }
        </style>
    </head>
    <body>
        <table>
            <tr><th colspan="6" style="font-size: 14pt; font-weight: bold; color: #1e3a8a;">FORMAT PEMBARUAN CAPAIAN 8 INDIKATOR PERJANJIAN KINERJA 2026</th></tr>
            <tr><th colspan="6" style="font-size: 10pt; color: #64748b;">Nota Dinas Komdigi No. 224/DJID.6/PR.04.01/02/2026 - Balmon Kelas I Samarinda</th></tr>
            <tr><td colspan="6"></td></tr>
            <tr>
                <th class="th-blue" style="width: 100px;">Kode_PK</th>
                <th class="th-blue" style="width: 280px;">Judul_Indikator</th>
                <th class="th-blue" style="width: 120px;">Target_Tahunan</th>
                <th class="th-blue" style="width: 100px;">Satuan</th>
                <th class="th-blue" style="width: 120px;">Realisasi</th>
                <th class="th-blue" style="width: 320px;">Keterangan_Dukung</th>
            </tr>
            <?php foreach ($pkItems as $pk): ?>
            <tr>
                <td class="td-num"><strong><?= htmlspecialchars($pk['code']) ?></strong></td>
                <td class="td-data"><?= htmlspecialchars($pk['title']) ?></td>
                <td class="td-num"><?= htmlspecialchars($pk['target_annual']) ?></td>
                <td class="td-num"><?= htmlspecialchars($pk['unit']) ?></td>
                <td class="td-num" style="background-color: #eff6ff; font-weight: bold;"><?= htmlspecialchars($pk['realization']) ?></td>
                <td class="td-data"><?= htmlspecialchars($pk['notes']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </body>
    </html>
    <?php
    exit;
} else {
    http_response_code(400);
    echo "Tipe template tidak dikenal.";
    exit;
}
