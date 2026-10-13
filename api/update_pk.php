<?php
// api/update_pk.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$currentUser = currentUser();
if (!in_array($currentUser['role'], ['admin', 'koordinator', 'kepala_balai'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Admin dan Koordinator yang dapat mengelola PK.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$action = $data['action'] ?? 'update_realization';
$pdo = getDbConnection();

try {
    if ($action === 'copy_year') {
        $sourceYear = (int)($data['source_year'] ?? 0);
        $targetYear = (int)($data['target_year'] ?? 0);
        $targetTitle = trim($data['target_title'] ?? "Perjanjian Kinerja Tahun $targetYear");
        $targetLegal = trim($data['target_legal_basis'] ?? "Nota Dinas Penetapan PK TA $targetYear");
        $targetStatus = trim($data['target_status'] ?? 'planning');
        $resetRealization = !empty($data['reset_realization']);

        if (!$sourceYear || !$targetYear || $sourceYear === $targetYear) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tahun sumber dan tahun tujuan tidak valid.']);
            exit;
        }

        // Ambil indikator dari tahun sumber
        $stmtSrc = $pdo->prepare("SELECT * FROM perjanjian_kinerja WHERE year = ? ORDER BY order_num ASC, id ASC");
        $stmtSrc->execute([$sourceYear]);
        $srcIndicators = $stmtSrc->fetchAll();

        if (empty($srcIndicators)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Tidak ada data indikator pada tahun sumber $sourceYear."]);
            exit;
        }

        // Simpan metadata tahun tujuan
        $stmtYear = $pdo->prepare("
            INSERT INTO pk_years_meta (year, title, legal_basis, status, description, updated_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(year) DO UPDATE SET
                title = excluded.title,
                legal_basis = excluded.legal_basis,
                status = excluded.status,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmtYear->execute([$targetYear, $targetTitle, $targetLegal, $targetStatus, "Disalin dari tahun $sourceYear"]);

        // Salin indikator ke tahun tujuan
        $stmtIns = $pdo->prepare("
            INSERT INTO perjanjian_kinerja (
                year, code, title, indicator, target_annual, unit, realization,
                formula, manual_measurement, app_reference, icon, badge_color, order_num, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(year, code) DO UPDATE SET
                title = excluded.title,
                indicator = excluded.indicator,
                target_annual = excluded.target_annual,
                unit = excluded.unit,
                realization = excluded.realization,
                formula = excluded.formula,
                manual_measurement = excluded.manual_measurement,
                app_reference = excluded.app_reference,
                icon = excluded.icon,
                badge_color = excluded.badge_color,
                order_num = excluded.order_num,
                notes = excluded.notes,
                updated_at = CURRENT_TIMESTAMP
        ");

        $copiedCount = 0;
        foreach ($srcIndicators as $idx => $ind) {
            $real = $resetRealization ? 0.0 : (float)$ind['realization'];
            $stmtIns->execute([
                $targetYear,
                $ind['code'],
                $ind['title'],
                $ind['indicator'],
                (float)$ind['target_annual'],
                $ind['unit'] ?? '%',
                $real,
                $ind['formula'],
                $ind['manual_measurement'],
                $ind['app_reference'] ?? '',
                $ind['icon'] ?? 'insights',
                $ind['badge_color'] ?? 'primary',
                $idx + 1,
                $ind['notes'] ?? ''
            ]);
            $copiedCount++;
        }

        echo json_encode([
            'success' => true,
            'message' => "Berhasil menyalin $copiedCount indikator PK dari tahun $sourceYear ke tahun $targetYear.",
            'target_year' => $targetYear
        ]);
        exit;
    }

    if ($action === 'save_year_meta') {
        $year = (int)($data['year'] ?? 0);
        $title = trim($data['title'] ?? '');
        $legalBasis = trim($data['legal_basis'] ?? '');
        $status = trim($data['status'] ?? 'active');
        $desc = trim($data['description'] ?? '');

        if (!$year || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tahun dan judul PK wajib diisi.']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO pk_years_meta (year, title, legal_basis, status, description, updated_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(year) DO UPDATE SET
                title = excluded.title,
                legal_basis = excluded.legal_basis,
                status = excluded.status,
                description = excluded.description,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$year, $title, $legalBasis, $status, $desc]);

        echo json_encode([
            'success' => true,
            'message' => "Data tahun PK $year berhasil disimpan."
        ]);
        exit;
    }

    if ($action === 'save_indicator') {
        $id = (int)($data['id'] ?? 0);
        $year = (int)($data['year'] ?? 2026);
        $code = trim($data['code'] ?? '');
        $title = trim($data['title'] ?? '');
        $indicator = trim($data['indicator'] ?? '');
        $targetAnnual = (float)($data['target_annual'] ?? 100);
        $unit = trim($data['unit'] ?? '%');
        $realization = (float)($data['realization'] ?? 0);
        $formula = trim($data['formula'] ?? '');
        $manual = trim($data['manual_measurement'] ?? '');
        $appRef = trim($data['app_reference'] ?? '');
        $icon = trim($data['icon'] ?? 'insights');
        $badge = trim($data['badge_color'] ?? 'primary');
        $notes = trim($data['notes'] ?? '');

        if (!$year || empty($code) || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tahun, kode (misal PK.1), dan judul wajib diisi.']);
            exit;
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE perjanjian_kinerja SET
                    year = ?, code = ?, title = ?, indicator = ?, target_annual = ?,
                    unit = ?, realization = ?, formula = ?, manual_measurement = ?,
                    app_reference = ?, icon = ?, badge_color = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $year, $code, $title, $indicator, $targetAnnual,
                $unit, $realization, $formula, $manual,
                $appRef, $icon, $badge, $notes, $id
            ]);
            $msg = "Indikator PK $code berhasil diperbarui.";
        } else {
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM perjanjian_kinerja WHERE year = ?");
            $stmtCount->execute([$year]);
            $orderNum = (int)$stmtCount->fetchColumn() + 1;

            $stmt = $pdo->prepare("
                INSERT INTO perjanjian_kinerja (
                    year, code, title, indicator, target_annual, unit, realization,
                    formula, manual_measurement, app_reference, icon, badge_color, order_num, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $year, $code, $title, $indicator, $targetAnnual,
                $unit, $realization, $formula, $manual,
                $appRef, $icon, $badge, $orderNum, $notes
            ]);
            $msg = "Indikator baru $code berhasil ditambahkan ke tahun $year.";
        }

        echo json_encode(['success' => true, 'message' => $msg]);
        exit;
    }

    if ($action === 'delete_indicator') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID indikator tidak valid.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM perjanjian_kinerja WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Indikator berhasil dihapus.']);
        exit;
    }

    // Default action: update_realization
    $id = (int)($data['id'] ?? 0);
    $year = isset($data['year']) ? (int)$data['year'] : 0;
    $code = trim($data['code'] ?? '');
    $realization = isset($data['realization']) ? (float)$data['realization'] : null;
    $notes = isset($data['notes']) ? trim($data['notes']) : null;

    if (($id <= 0 && empty($code)) || $realization === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID/Kode dan nilai realisasi wajib diisi.']);
        exit;
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE perjanjian_kinerja SET realization = ?, notes = COALESCE(?, notes), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$realization, $notes, $id]);
    } else {
        if ($year > 0) {
            $stmt = $pdo->prepare("UPDATE perjanjian_kinerja SET realization = ?, notes = COALESCE(?, notes), updated_at = CURRENT_TIMESTAMP WHERE year = ? AND code = ?");
            $stmt->execute([$realization, $notes, $year, $code]);
        } else {
            $stmt = $pdo->prepare("UPDATE perjanjian_kinerja SET realization = ?, notes = COALESCE(?, notes), updated_at = CURRENT_TIMESTAMP WHERE code = ?");
            $stmt->execute([$realization, $notes, $code]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Capaian Perjanjian Kinerja berhasil diperbarui.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal memproses data: ' . $e->getMessage()]);
}
