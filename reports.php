<?php
// reports.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();
$pageTitle = "Semua Data Monitoring";

// Filters
$selectedCat = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$selectedStatus = $_GET['status'] ?? '';

$sql = "
    SELECT r.*, c.name as category_name, c.short_title, c.badge_color,
           u.name as officer_name, u.avatar as officer_avatar
    FROM monitoring_reports r
    JOIN monitoring_categories c ON r.category_id = c.id
    JOIN users u ON r.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($selectedCat > 0) {
    $sql .= " AND r.category_id = ?";
    $params[] = $selectedCat;
}
if (!empty($selectedStatus)) {
    $sql .= " AND r.status = ?";
    $params[] = $selectedStatus;
}

$sql .= " ORDER BY r.date_monitored DESC, r.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Categories for filter dropdown
$categories = $pdo->query("SELECT id, name, short_title FROM monitoring_categories ORDER BY id ASC")->fetchAll();

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
                <li class="breadcrumb-item active"><a href="javascript:void(0)">Data Monitoring</a></li>
            </ol>
        </div>

        <!-- Filter Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                    <div class="card-body p-3">
                        <form method="GET" action="reports.php" class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <label class="form-label fs-12 fw-semibold mb-1 text-muted">Kategori Monitoring:</label>
                                <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="0">Semua Kategori (<?= count($categories) ?> Kegiatan)</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $selectedCat == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['short_title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fs-12 fw-semibold mb-1 text-muted">Status Verifikasi:</label>
                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="">Semua Status</option>
                                    <option value="submitted" <?= $selectedStatus === 'submitted' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                                    <option value="verified" <?= $selectedStatus === 'verified' ? 'selected' : '' ?>>Terverifikasi (Disetujui)</option>
                                    <option value="revision" <?= $selectedStatus === 'revision' ? 'selected' : '' ?>>Perlu Revisi</option>
                                    <option value="draft" <?= $selectedStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-1">
                                <button type="submit" class="btn btn-primary btn-sm rounded-pill w-100 mt-md-4">
                                    <i class="fa fa-filter me-1"></i> Terapkan
                                </button>
                                <a href="reports.php" class="btn btn-outline-secondary btn-sm rounded-pill mt-md-4">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Table -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                    <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0">Semua Laporan Hasil Monitoring</h5>
                            <small class="text-muted">Balai Monitor Spektrum Frekuensi Radio dan Infrastruktur Digital Kelas I Samarinda</small>
                        </div>
                        <a href="export.php" class="btn btn-outline-primary btn-sm rounded-pill">
                            <i class="fa fa-file-excel me-1"></i> Ekspor Laporan
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle data-table-balmon mb-0">
                                <thead class="bg-light fs-12 text-uppercase text-muted">
                                    <tr>
                                        <th>Kode / Kategori</th>
                                        <th>Judul & Target Stasiun</th>
                                        <th>Frekuensi</th>
                                        <th>Lokasi</th>
                                        <th>Tanggal</th>
                                        <th>Petugas</th>
                                        <th>Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13">
                                    <?php foreach ($reports as $rep): ?>
                                        <tr>
                                            <td class="text-nowrap">
                                                <span class="badge badge-<?= $rep['badge_color'] ?> light mb-1 text-nowrap"><?= htmlspecialchars($rep['short_title']) ?></span>
                                                <div class="fw-bold text-primary font-monospace fs-12 text-nowrap"><?= htmlspecialchars($rep['report_code']) ?></div>
                                            </td>
                                            <td>
                                                <div class="truncate-lg fw-bold text-dark fs-13" title="<?= htmlspecialchars($rep['title']) ?>">
                                                    <?= htmlspecialchars($rep['title']) ?>
                                                </div>
                                                <div class="text-muted fs-11 truncate-lg" title="<?= htmlspecialchars($rep['target_name']) ?>">
                                                    <i class="fa fa-crosshairs me-1 text-primary"></i><?= htmlspecialchars($rep['target_name']) ?>
                                                </div>
                                            </td>
                                            <td class="text-nowrap">
                                                <span class="badge badge-secondary light fs-11 text-nowrap"><i class="fa fa-wave-square me-1"></i><?= htmlspecialchars($rep['frequency'] ?: 'Multi-band') ?></span>
                                            </td>
                                            <td class="text-nowrap">
                                                <div class="truncate-sm fs-12 text-muted" title="<?= htmlspecialchars($rep['location']) ?>">
                                                    <i class="fa fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($rep['location']) ?>
                                                </div>
                                            </td>
                                            <td class="text-nowrap fs-12">
                                                <?= date('d/m/Y', strtotime($rep['date_monitored'])) ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <div class="d-flex align-items-center text-nowrap">
                                                    <img src="<?= htmlspecialchars($rep['officer_avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle me-1" width="22" height="22">
                                                    <span class="fs-12 text-nowrap"><?= htmlspecialchars($rep['officer_name']) ?></span>
                                                </div>
                                            </td>
                                            <td class="text-nowrap">
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
                                                <a href="report_detail.php?id=<?= $rep['id'] ?>" class="btn btn-primary btn-xs sharp" title="Detail Lengkap">
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
