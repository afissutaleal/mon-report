<?php
// category_view.php
// Reusable template for the 9 monitoring category pages
require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/category_definitions.php';

if (!isset($categoryId) || empty($categoryId)) {
    header("Location: index.php");
    exit;
}

$pdo = getDbConnection();
$currentUser = currentUser();

// Fetch category details
$stmtCat = $pdo->prepare("SELECT * FROM monitoring_categories WHERE id = ?");
$stmtCat->execute([$categoryId]);
$category = $stmtCat->fetch();

if (!$category) {
    die("Kategori monitoring tidak ditemukan.");
}

$pageTitle = $category['short_title'];
$technicalFields = getCategoryTechnicalFields($categoryId);

// Handle form submission for new report in this category
$successMsg = '';
$errorMsg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_report') {
    $title = trim($_POST['title'] ?? '');
    $targetName = trim($_POST['target_name'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $lat = (float)($_POST['latitude'] ?? -0.502106);
    $lng = (float)($_POST['longitude'] ?? 117.153709);
    $dateMonitored = $_POST['date_monitored'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'submitted';

    // Technical data from dynamic fields
    $techData = [];
    foreach ($technicalFields as $field) {
        $fieldName = $field['name'];
        if (isset($_POST[$fieldName])) {
            $techData[$fieldName] = $_POST[$fieldName];
        }
    }

    if (empty($title) || empty($targetName) || empty($location)) {
        $errorMsg = "Judul, Target/Stasiun, dan Lokasi wajib diisi.";
    } else {
        // Generate code
        $prefixMap = [
            1 => 'SPF', 2 => 'MWL', 3 => 'RFM', 4 => 'TVD',
            5 => 'HFR', 6 => 'APT', 7 => 'PSF', 8 => 'PAT', 9 => 'TGS',
            10 => 'GGN', 11 => 'QOS'
        ];
        $prefix = 'MON-' . ($prefixMap[$categoryId] ?? 'GEN') . '-' . date('Y');
        $stmtSeq = $pdo->prepare("SELECT COUNT(*) FROM monitoring_reports WHERE report_code LIKE ?");
        $stmtSeq->execute([$prefix . '%']);
        $seq = (int)$stmtSeq->fetchColumn() + 1;
        $reportCode = sprintf("%s-%03d", $prefix, $seq);

        try {
            $stmtIns = $pdo->prepare("
                INSERT INTO monitoring_reports (
                    report_code, category_id, title, user_id, target_name, frequency,
                    location, latitude, longitude, date_monitored, status, technical_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtIns->execute([
                $reportCode, $categoryId, $title, $currentUser['id'], $targetName, $frequency,
                $location, $lat, $lng, $dateMonitored, $status, json_encode($techData)
            ]);
            $successMsg = "Data hasil monitoring berhasil disimpan dengan kode: <strong>$reportCode</strong>!";
        } catch (Exception $e) {
            $errorMsg = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}

// Fetch stats for this category
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_count,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
        SUM(CASE WHEN status = 'revision' THEN 1 ELSE 0 END) as revision_count
    FROM monitoring_reports
    WHERE category_id = ?
");
$stmtStats->execute([$categoryId]);
$stat = $stmtStats->fetch();

$realizedCount = (int)$stat['total_count'];
$verifiedCount = (int)$stat['verified_count'];
$submittedCount = (int)$stat['submitted_count'];
$revisionCount = (int)$stat['revision_count'];
$targetAnnual = (int)$category['target_annual'];
$pct = $targetAnnual > 0 ? min(100, round(($realizedCount / $targetAnnual) * 100, 1)) : 0;

// Fetch reports in this category
$stmtReports = $pdo->prepare("
    SELECT r.*, u.name as officer_name, u.avatar as officer_avatar, v.name as verifier_name
    FROM monitoring_reports r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN users v ON r.verified_by = v.id
    WHERE r.category_id = ?
    ORDER BY r.date_monitored DESC, r.id DESC
");
$stmtReports->execute([$categoryId]);
$reports = $stmtReports->fetchAll();

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav_header.php';
require_once __DIR__ . '/includes/chatbox.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="content-body">
    <div class="container-fluid">
        <!-- Breadcrumb -->
        <div class="row page-titles mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="reports.php">Kegiatan Monitoring</a></li>
                <li class="breadcrumb-item active"><a href="javascript:void(0)"><?= htmlspecialchars($category['short_title']) ?></a></li>
            </ol>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert alert-success alert-dismissible fade show fs-13 py-2">
                <i class="fa fa-check-circle me-1"></i> <?= $successMsg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger alert-dismissible fade show fs-13 py-2">
                <i class="fa fa-circle-exclamation me-1"></i> <?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Category Banner with KPI -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius: 18px; border-left: 6px solid var(--bs-<?= $category['badge_color'] ?>) !important;">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-lg-8">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded-3 d-flex align-items-center justify-content-center bg-<?= $category['badge_color'] ?> bg-opacity-10 text-<?= $category['badge_color'] ?> me-3" style="width: 52px; height: 52px;">
                                        <i class="material-symbols-outlined" style="font-size: 28px;"><?= htmlspecialchars($category['material_icon'] ?? getCategoryMaterialIcon($category['id'])) ?></i>
                                    </div>
                                    <div>
                                        <h4 class="fw-bold mb-0 text-truncate" title="<?= htmlspecialchars($category['name']) ?>"><?= htmlspecialchars($category['name']) ?></h4>
                                        <span class="badge badge-<?= $category['badge_color'] ?> light fs-11 text-nowrap">Kategori #<?= $category['id'] ?></span>
                                    </div>
                                </div>
                                <p class="text-muted fs-13 mb-3 text-truncate" title="<?= htmlspecialchars($category['description']) ?>"><?= htmlspecialchars($category['description']) ?></p>
                                <button type="button" class="btn btn-<?= $category['badge_color'] ?> btn-sm rounded-pill px-4 fw-bold shadow-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#addReportModal">
                                    <i class="fa fa-plus-circle me-1"></i> Input Data Hasil Monitoring
                                </button>
                                <a href="export.php?category_id=<?= $categoryId ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 ms-2 text-nowrap">
                                    <i class="fa fa-print me-1"></i> Cetak / Ekspor
                                </a>
                            </div>
                            <div class="col-lg-4 mt-3 mt-lg-0">
                                <div class="p-3 bg-light rounded-3 text-center">
                                    <span class="text-muted fs-12 d-block">Target Capaian Tahunan (<?= date('Y') ?>)</span>
                                    <div class="d-flex justify-content-center align-items-baseline my-1 text-nowrap">
                                        <h2 class="fw-bold text-<?= $category['badge_color'] ?> mb-0 me-2"><?= $realizedCount ?></h2>
                                        <span class="fs-13 text-muted">/ <?= $targetAnnual ?> <?= $category['unit'] ?> (<?= $pct ?>%)</span>
                                    </div>
                                    <div class="progress my-2" style="height: 6px;">
                                        <div class="progress-bar bg-<?= $category['badge_color'] ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <div class="d-flex justify-content-around fs-11 text-muted mt-2 pt-1 border-top text-nowrap">
                                        <span><i class="fa fa-check text-success me-1"></i><?= $verifiedCount ?> Disetujui</span>
                                        <span><i class="fa fa-clock text-warning me-1"></i><?= $submittedCount ?> Antrian</span>
                                        <span><i class="fa fa-rotate-left text-danger me-1"></i><?= $revisionCount ?> Revisi</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Output Data Table -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0 text-truncate">Data Output & Rekapitulasi Hasil Monitoring</h5>
                            <small class="text-muted">Hasil pengukuran teknis spesifik <?= htmlspecialchars($category['short_title']) ?></small>
                        </div>
                        <span class="badge badge-light text-dark fs-12 text-nowrap"><?= count($reports) ?> Data Tersimpan</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle table-nowrap data-table-balmon mb-0">
                                <thead class="bg-light fs-12 text-uppercase text-muted">
                                    <tr>
                                        <th>Kode Laporan</th>
                                        <th>Target / Stasiun</th>
                                        <th>Frekuensi / Band</th>
                                        <th>Lokasi Pelaksanaan</th>
                                        <th>Parameter Teknis Spesifik</th>
                                        <th>Petugas</th>
                                        <th>Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13">
                                    <?php foreach ($reports as $rep): 
                                        $tech = json_decode($rep['technical_data'], true) ?? [];
                                    ?>
                                        <tr>
                                            <td>
                                                <strong class="text-primary font-monospace text-nowrap"><?= htmlspecialchars($rep['report_code']) ?></strong>
                                                <small class="text-muted d-block text-nowrap"><?= date('d M Y', strtotime($rep['date_monitored'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold truncate-md" title="<?= htmlspecialchars($rep['target_name']) ?>"><?= htmlspecialchars($rep['target_name']) ?></div>
                                                <div class="text-muted fs-12 truncate-md" title="<?= htmlspecialchars($rep['title']) ?>"><?= htmlspecialchars($rep['title']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary light fs-11 text-nowrap"><i class="fa fa-wave-square me-1"></i><?= htmlspecialchars($rep['frequency'] ?: '-') ?></span>
                                            </td>
                                            <td>
                                                <div class="truncate-sm" title="<?= htmlspecialchars($rep['location']) ?>">
                                                    <i class="fa fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($rep['location']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fs-12 truncate-lg" title="<?php
                                                    $paramTxt = [];
                                                    foreach ($technicalFields as $tf) {
                                                        if (!empty($tech[$tf['name']])) $paramTxt[] = "{$tf['label']}: {$tech[$tf['name']]}";
                                                    }
                                                    echo htmlspecialchars(implode(' | ', $paramTxt));
                                                ?>">
                                                    <?php 
                                                    $paramPills = [];
                                                    foreach ($technicalFields as $tf) {
                                                        if (!empty($tech[$tf['name']])) {
                                                            $paramPills[] = "<span class='badge bg-light text-dark border me-1 text-nowrap'><strong>{$tf['label']}:</strong> " . htmlspecialchars($tech[$tf['name']]) . "</span>";
                                                        }
                                                    }
                                                    echo !empty($paramPills) ? implode('', array_slice($paramPills, 0, 2)) : '<span class="text-muted">-</span>';
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center text-nowrap">
                                                    <img src="<?= htmlspecialchars($rep['officer_avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle me-1 flex-shrink-0" width="26" height="26">
                                                    <span class="fs-12 truncate-sm" title="<?= htmlspecialchars($rep['officer_name']) ?>"><?= htmlspecialchars($rep['officer_name']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($rep['status'] === 'verified'): ?>
                                                    <span class="badge badge-success light status-badge text-nowrap"><i class="fa fa-check"></i> Terverifikasi</span>
                                                <?php elseif ($rep['status'] === 'submitted'): ?>
                                                    <span class="badge badge-warning light status-badge text-nowrap"><i class="fa fa-clock"></i> Menunggu</span>
                                                <?php elseif ($rep['status'] === 'revision'): ?>
                                                    <span class="badge badge-danger light status-badge text-nowrap"><i class="fa fa-rotate-left"></i> Revisi</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary light status-badge text-nowrap"><?= strtoupper($rep['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <a href="report_detail.php?id=<?= $rep['id'] ?>" class="btn btn-primary btn-xs sharp" title="Detail Lengkap & Output">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form Input Data Hasil Monitoring (Category-Specific) -->
<div class="modal fade" id="addReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-<?= $category['badge_color'] ?> text-white p-2 me-2">
                        <i class="fa-solid <?= $category['icon'] ?>"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Input Data: <?= htmlspecialchars($category['name']) ?></h5>
                        <small class="text-muted">Formulir input data teknis Balmon Samarinda</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="save_report">
                <div class="modal-body p-4">
                    <!-- General Information -->
                    <h6 class="fw-bold text-primary mb-3 border-bottom pb-2"><i class="fa fa-info-circle me-1"></i> Informasi Dasar Kegiatan</h6>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label font-w600 fs-13">Judul Laporan Monitoring <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="Contoh: Pengukuran Stasiun Radio Siaran..." required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-w600 fs-13">Target / Nama Stasiun / Penyelenggara <span class="text-danger">*</span></label>
                            <input type="text" name="target_name" class="form-control" placeholder="Nama stasiun, perusahaan, atau objek" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-w600 fs-13">Frekuensi Kerja / Kanal</label>
                            <input type="text" name="frequency" class="form-control" placeholder="Contoh: 98.4 MHz, 15 GHz, UHF CH 30">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-w600 fs-13">Lokasi / Alamat Pelaksanaan <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control" placeholder="Kota Samarinda, Balikpapan, Kukar, IKN..." required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-w600 fs-13">Tanggal Pelaksanaan Monitoring <span class="text-danger">*</span></label>
                            <input type="date" name="date_monitored" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-w600 fs-13">Koordinat Latitude</label>
                            <input type="text" name="latitude" class="form-control" value="-0.502106" placeholder="-0.502106">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-w600 fs-13">Koordinat Longitude</label>
                            <input type="text" name="longitude" class="form-control" value="117.153709" placeholder="117.153709">
                        </div>
                    </div>

                    <!-- Category Specific Technical Parameters -->
                    <h6 class="fw-bold text-primary mt-3 mb-3 border-bottom pb-2">
                        <i class="fa-solid <?= $category['icon'] ?> me-1"></i> Parameter Teknis Spesifik (Input Khusus)
                    </h6>
                    <div class="row">
                        <?php foreach ($technicalFields as $field): ?>
                            <div class="<?= in_array($field['type'], ['textarea']) ? 'col-md-12' : 'col-md-6' ?> mb-3">
                                <label class="form-label font-w600 fs-13">
                                    <?= htmlspecialchars($field['label']) ?>
                                    <?php if (!empty($field['required'])): ?><span class="text-danger">*</span><?php endif; ?>
                                </label>
                                
                                <?php if ($field['type'] === 'select'): ?>
                                    <select name="<?= $field['name'] ?>" class="form-select" <?= !empty($field['required']) ? 'required' : '' ?>>
                                        <option value="">-- Pilih <?= htmlspecialchars($field['label']) ?> --</option>
                                        <?php foreach ($field['options'] as $opt): ?>
                                            <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($field['type'] === 'textarea'): ?>
                                    <textarea name="<?= $field['name'] ?>" class="form-control" rows="3" placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>" <?= !empty($field['required']) ? 'required' : '' ?>></textarea>
                                <?php else: ?>
                                    <input type="<?= $field['type'] ?>" name="<?= $field['name'] ?>" class="form-control" placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-<?= $category['badge_color'] ?> btn-sm px-4 fw-bold">
                        <i class="fa fa-save me-1"></i> Simpan Data Monitoring
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
