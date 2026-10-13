<?php
// api/import_process.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/SpreadsheetReader.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan login kembali.']);
    exit;
}

$currentUser = currentUser();
$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode HTTP tidak diizinkan. Gunakan POST.']);
    exit;
}

// Cek file upload
if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['import_file']['error'] ?? 'No file';
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "Gagal mengunggah berkas. Kode error: $errCode"]);
    exit;
}

$file = $_FILES['import_file'];
$originalName = $file['name'];
$fileSize = (int)$file['size'];
$tmpPath = $file['tmp_name'];
$importType = $_POST['import_type'] ?? 'pk1_monitoring_sfr'; // 'pk1_monitoring_sfr' | 'pk_all_targets'
$userNotes = trim($_POST['notes'] ?? '');
$isSimulation = isset($_POST['simulation']) && $_POST['simulation'] == '1';
$targetYear = isset($_POST['target_year']) ? (int)$_POST['target_year'] : 2026;

// Cek ekstensi
$pathInfo = pathinfo($originalName);
$extension = strtolower($pathInfo['extension'] ?? '');
if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Format berkas tidak didukung. Harap unggah berkas .xlsx, .xls, atau .csv.']);
    exit;
}

// Siapkan folder penyimpanan
$uploadDir = __DIR__ . '/../uploads/imports';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate batch code unik
$batchCode = 'IMP-' . date('Ymd-His') . '-' . substr(uniqid(), -4);
$savedFilename = $batchCode . '.' . $extension;
$destination = $uploadDir . '/' . $savedFilename;
$relPath = 'uploads/imports/' . $savedFilename;

$uploadOk = is_uploaded_file($tmpPath) ? move_uploaded_file($tmpPath, $destination) : copy($tmpPath, $destination);
if (!$uploadOk) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal memindahkan berkas yang diunggah ke folder penyimpanan server.']);
    exit;
}

try {
    // 1. Parse berkas menggunakan SpreadsheetReader
    $rows = SpreadsheetReader::parseFile($destination, $extension);
    if (empty($rows)) {
        throw new Exception("Berkas kosong atau tidak memuat data yang dapat dibaca.");
    }

    $totalRows = count($rows);
    $validRows = 0;
    $invalidRows = 0;
    $rowLogs = [];
    $realizationBefore = null;
    $realizationAfter = null;
    $summaryResult = '';
    $indicatorCode = 'PK.1';

    // Helper untuk membersihkan persentase & angka
    $cleanNumber = function($val) {
        $val = trim((string)$val);
        $val = str_replace(['%', ' '], '', $val);
        $val = str_replace(',', '.', $val);
        if ($val === '') return 0.0;
        $num = (float)$val;
        // Jika desimal misal 0.8, jadikan 80
        if ($num > 0 && $num <= 1.0) {
            $num = $num * 100;
        }
        return $num;
    };

    // -------------------------------------------------------------
    // PROSES 1: IMPORT HASIL MONITORING SFR KAB/KOTA (PK.1)
    // Sesuai Nota Dinas No. 224/DJID.6/PR.04.01/02/2026
    // -------------------------------------------------------------
    if ($importType === 'pk1_monitoring_sfr') {
        $indicatorCode = 'PK.1';

        // Ambil nilai realisasi PK.1 saat ini sesuai targetYear
        $stmtCurrent = $pdo->prepare("SELECT realization FROM perjanjian_kinerja WHERE code = 'PK.1' AND year = ?");
        $stmtCurrent->execute([$targetYear]);
        $realizationBefore = (float)$stmtCurrent->fetchColumn();

        // Cari baris header
        $headerIndex = -1;
        $colKab = -1;
        $colPita = -1;
        $colRol = -1;
        $colIden = -1;
        $colSanksi = -1;
        $colKet = -1;

        foreach ($rows as $idx => $r) {
            $rowStr = strtolower(implode(' ', $r));
            if (strpos($rowStr, 'kabupaten') !== false || strpos($rowStr, 'kota') !== false || strpos($rowStr, 'pita') !== false) {
                $headerIndex = $idx;
                foreach ($r as $cIdx => $cVal) {
                    $cLow = strtolower(trim($cVal));
                    if (strpos($cLow, 'kabupaten') !== false || strpos($cLow, 'kota') !== false) $colKab = $cIdx;
                    if (strpos($cLow, 'pita') !== false) $colPita = $cIdx;
                    if (strpos($cLow, 'rol') !== false || strpos($cLow, 'isr') !== false) $colRol = $cIdx;
                    if (strpos($cLow, 'identifikasi') !== false || strpos($cLow, 'teridentifikasi') !== false) $colIden = $cIdx;
                    if (strpos($cLow, 'sanksi') !== false) $colSanksi = $cIdx;
                    if (strpos($cLow, 'keterangan') !== false || strpos($cLow, 'catatan') !== false) $colKet = $cIdx;
                }
                break;
            }
        }

        // Posisi default jika format tabel standar
        if ($colKab === -1) $colKab = 1;
        if ($colPita === -1) $colPita = 2;
        if ($colRol === -1) $colRol = 3;
        if ($colIden === -1) $colIden = 4;
        if ($colSanksi === -1) $colSanksi = 5;
        if ($colKet === -1) $colKet = 7;

        $startIndex = ($headerIndex >= 0) ? $headerIndex + 1 : 1;
        $monitoredCount = 0;
        $processedCount = 0;

        for ($i = $startIndex; $i < $totalRows; $i++) {
            $r = $rows[$i];
            $kabName = trim($r[$colKab] ?? '');
            
            // Skip baris kosong atau footer keterangan
            if ($kabName === '' || stripos($kabName, 'total') !== false || stripos($kabName, 'catatan') !== false || stripos($kabName, 'petunjuk') !== false) {
                continue;
            }

            $pitaVal = (int)($r[$colPita] ?? 0);
            $rolVal = $cleanNumber($r[$colRol] ?? 0);
            $idenVal = $cleanNumber($r[$colIden] ?? 0);
            $sanksiVal = $cleanNumber($r[$colSanksi] ?? 0);
            $ketVal = trim($r[$colKet] ?? ($r[6] ?? ''));

            // Evaluasi 4 kriteria Nota Dinas No. 224:
            // 1. Min 15 Pita Frekuensi
            // 2. Min 60% ISR acuan ROL
            // 3. 100% Teridentifikasi
            // 4. 100% Tindak Lanjut Sanksi
            $syarat1 = ($pitaVal >= 15);
            $syarat2 = ($rolVal >= 60.0);
            $syarat3 = ($idenVal >= 100.0);
            $syarat4 = ($sanksiVal >= 100.0);

            $isMemenuhi = ($syarat1 && $syarat2 && $syarat3 && $syarat4);
            if ($isMemenuhi) {
                $monitoredCount++;
                $statusRow = 'valid';
                $evalNotes = "MEMENUHI 4 SYARAT: Pita ($pitaVal >= 15), ROL ($rolVal% >= 60%), Identifikasi ($idenVal% == 100%), Sanksi ($sanksiVal% == 100%)";
            } else {
                $statusRow = 'valid'; // baris valid tapi belum memenuhi target
                $alasanKurang = [];
                if (!$syarat1) $alasanKurang[] = "Pita < 15 ($pitaVal)";
                if (!$syarat2) $alasanKurang[] = "ROL < 60% ($rolVal%)";
                if (!$syarat3) $alasanKurang[] = "Identifikasi < 100% ($idenVal%)";
                if (!$syarat4) $alasanKurang[] = "Sanksi < 100% ($sanksiVal%)";
                $evalNotes = "BELUM MEMENUHI: " . implode(', ', $alasanKurang);
            }

            $processedCount++;
            $validRows++;

            $rowLogs[] = [
                'row_number' => $processedCount,
                'identifier' => $kabName,
                'status' => $statusRow,
                'evaluation' => $evalNotes,
                'data' => [
                    'kabupaten_kota' => $kabName,
                    'pita_frekuensi' => $pitaVal,
                    'persen_rol' => $rolVal . '%',
                    'persen_identifikasi' => $idenVal . '%',
                    'persen_sanksi' => $sanksiVal . '%',
                    'memenuhi_syarat' => $isMemenuhi ? 'YA' : 'TIDAK',
                    'keterangan' => $ketVal
                ]
            ];
        }

        // Kalkulasi Capaian PK.1 Balmon Samarinda (Wilayah Kerja: 10 Kab/Kota)
        $totalKabWilayah = 10;
        $calculatedPercentage = round(($monitoredCount / $totalKabWilayah) * 100, 2);
        $realizationAfter = $calculatedPercentage;

        $summaryResult = "$monitoredCount dari $totalKabWilayah Kabupaten/Kota di wilayah kerja memenuhi seluruh kriteria Nota Dinas No. 224. Capaian PK.1 dihitung sebesar $calculatedPercentage%.";

        // Update database jika bukan simulasi
        if (!$isSimulation) {
            $stmtUpdatePK = $pdo->prepare("
                UPDATE perjanjian_kinerja 
                SET realization = ?, 
                    notes = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE code = 'PK.1' AND year = ?
            ");
            $updateNote = "Diperbarui via Impor Berkas ($originalName) pada " . date('d M Y H:i') . " TA $targetYear. Realisasi: $monitoredCount/10 Kab/Kota termonitor ($calculatedPercentage%).";
            $stmtUpdatePK->execute([$calculatedPercentage, $updateNote, $targetYear]);
        }

    // -------------------------------------------------------------
    // PROSES 2: IMPORT MASSAL CAPAIAN SELURUH INDIKATOR PK (PK.1 s/d PK.8)
    // -------------------------------------------------------------
    } elseif ($importType === 'pk_all_targets') {
        $indicatorCode = 'PK.ALL';
        $updatedCodes = [];

        $startIndex = 1;
        // Cek jika baris pertama adalah header
        if (isset($rows[0]) && stripos(implode(' ', $rows[0]), 'kode') !== false) {
            $startIndex = 1;
        }

        for ($i = $startIndex; $i < $totalRows; $i++) {
            $r = $rows[$i];
            $code = strtoupper(trim($r[0] ?? ''));
            if (!preg_match('/^PK\.[1-8]$/', $code)) {
                continue;
            }

            $targetVal = isset($r[2]) && $r[2] !== '' ? $cleanNumber($r[2]) : null;
            $realizationVal = isset($r[4]) && $r[4] !== '' ? $cleanNumber($r[4]) : null;
            // Jika kolom realisasi berada di indeks 3
            if ($realizationVal === null && isset($r[3]) && is_numeric(str_replace(['%', ','], ['', '.'], $r[3]))) {
                $realizationVal = $cleanNumber($r[3]);
            }
            $notesVal = trim($r[5] ?? ($r[4] ?? ''));

            if ($realizationVal !== null) {
                $validRows++;
                $updatedCodes[] = $code;

                if (!$isSimulation) {
                    $stmtBulk = $pdo->prepare("
                        UPDATE perjanjian_kinerja 
                        SET realization = ?,
                            notes = CASE WHEN ? != '' THEN ? ELSE notes END,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE code = ? AND year = ?
                    ");
                    $stmtBulk->execute([$realizationVal, $notesVal, $notesVal, $code, $targetYear]);
                }

                $rowLogs[] = [
                    'row_number' => $validRows,
                    'identifier' => $code,
                    'status' => 'valid',
                    'evaluation' => "Indikator $code disinkronkan dengan realisasi $realizationVal%",
                    'data' => [
                        'code' => $code,
                        'realization' => $realizationVal,
                        'notes' => $notesVal
                    ]
                ];
            }
        }

        $summaryResult = "Berhasil memperbarui realisasi " . count($updatedCodes) . " indikator PK (" . implode(', ', $updatedCodes) . ").";
    }

    // 2. Simpan Riwayat Audit ke Tabel `import_history`
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $statusAudit = ($validRows > 0) ? 'success' : 'warning';
    if ($isSimulation) {
        $statusAudit = 'simulation';
        $summaryResult = '[SIMULASI] ' . $summaryResult;
    }

    $stmtHist = $pdo->prepare("
        INSERT INTO import_history (
            import_type, indicator_code, batch_code, original_filename, saved_filename, 
            file_path, file_size, file_extension, total_rows, valid_rows, invalid_rows, 
            realization_before, realization_after, summary_result, status, notes, 
            user_id, uploader_name, uploader_nip, ip_address, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, CURRENT_TIMESTAMP
        )
    ");
    $stmtHist->execute([
        $importType, $indicatorCode, $batchCode, $originalName, $savedFilename,
        $relPath, $fileSize, $extension, $totalRows, $validRows, $invalidRows,
        $realizationBefore, $realizationAfter, $summaryResult, $statusAudit, $userNotes,
        $currentUser['id'], $currentUser['name'], $currentUser['nip'] ?? '', $clientIp
    ]);
    $historyId = (int)$pdo->lastInsertId();

    // 3. Simpan Riwayat Tiap Baris ke `import_history_logs`
    $stmtRowLog = $pdo->prepare("
        INSERT INTO import_history_logs (
            import_history_id, row_number, row_identifier, row_data, status, evaluation_notes, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");

    foreach ($rowLogs as $log) {
        $stmtRowLog->execute([
            $historyId,
            $log['row_number'],
            $log['identifier'],
            json_encode($log['data'], JSON_UNESCAPED_UNICODE),
            $log['status'],
            $log['evaluation']
        ]);
    }

    echo json_encode([
        'success' => true,
        'is_simulation' => $isSimulation,
        'batch_code' => $batchCode,
        'history_id' => $historyId,
        'original_filename' => $originalName,
        'total_rows' => $totalRows,
        'valid_rows' => $validRows,
        'realization_before' => $realizationBefore,
        'realization_after' => $realizationAfter,
        'summary_result' => $summaryResult,
        'logs' => $rowLogs,
        'message' => $isSimulation ? 'Simulasi impor berhasil dijalankan.' : 'Data monitoring berhasil diimpor dan capaian kinerja berhasil diperbarui!'
    ]);

} catch (Exception $e) {
    // Jika gagal, catat kegagalan ke database
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    try {
        $stmtFail = $pdo->prepare("
            INSERT INTO import_history (
                import_type, indicator_code, batch_code, original_filename, saved_filename, 
                file_path, file_size, file_extension, total_rows, valid_rows, invalid_rows, 
                summary_result, status, notes, user_id, uploader_name, uploader_nip, ip_address, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, 0, 0, 0, 
                ?, 'failed', ?, ?, ?, ?, ?, CURRENT_TIMESTAMP
            )
        ");
        $stmtFail->execute([
            $importType, $indicatorCode ?? 'PK.1', $batchCode, $originalName, $savedFilename,
            $relPath, $fileSize, $extension,
            'Gagal: ' . $e->getMessage(), $userNotes, $currentUser['id'], $currentUser['name'], $currentUser['nip'] ?? '', $clientIp
        ]);
    } catch (Exception $logEx) {
        // Abaikan error logging sekunder
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat memproses berkas: ' . $e->getMessage()
    ]);
}
