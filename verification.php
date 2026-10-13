<?php
// verification.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();
$pageTitle = "Monitoring Pelaporan & Verifikasi";

$statusFilter = $_GET['status'] ?? '';
$catFilter = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

$sql = "
    SELECT r.*, c.name as category_name, c.short_title, c.badge_color,
           u.name as officer_name, u.nip as officer_nip, u.avatar as officer_avatar,
           v.name as verifier_name
    FROM monitoring_reports r
    JOIN monitoring_categories c ON r.category_id = c.id
    JOIN users u ON r.user_id = u.id
    LEFT JOIN users v ON r.verified_by = v.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}
if (!empty($catFilter)) {
    $sql .= " AND r.category_id = ?";
    $params[] = $catFilter;
}

$sql .= " ORDER BY CASE WHEN r.status = 'submitted' THEN 0 WHEN r.status = 'revision' THEN 1 ELSE 2 END, r.date_monitored DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Counts
$totalAll = $pdo->query("SELECT COUNT(*) FROM monitoring_reports")->fetchColumn();
$totalPending = $pdo->query("SELECT COUNT(*) FROM monitoring_reports WHERE status = 'submitted'")->fetchColumn();
$totalVerified = $pdo->query("SELECT COUNT(*) FROM monitoring_reports WHERE status = 'verified'")->fetchColumn();
$totalRevision = $pdo->query("SELECT COUNT(*) FROM monitoring_reports WHERE status = 'revision'")->fetchColumn();

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav_header.php';
require_once __DIR__ . '/includes/chatbox.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="content-body">
    <div class="container-fluid">
        <!-- Page Title & Subtitle -->
        <div class="row page-titles mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active"><a href="javascript:void(0)">Verifikasi Pelaporan</a></li>
            </ol>
        </div>

        <!-- Filter Stat Tabs -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="fs-13 fw-bold me-2 text-muted"><i class="fa fa-filter me-1"></i> Filter Status:</span>
                            <a href="verification.php" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill px-3">
                                Semua Laporan <span class="badge bg-white text-dark ms-1"><?= $totalAll ?></span>
                            </a>
                            <a href="verification.php?status=submitted" class="btn btn-sm <?= $statusFilter === 'submitted' ? 'btn-warning' : 'btn-outline-warning' ?> rounded-pill px-3">
                                <i class="fa fa-clock me-1"></i> Menunggu Verifikasi <span class="badge bg-danger text-white ms-1"><?= $totalPending ?></span>
                            </a>
                            <a href="verification.php?status=verified" class="btn btn-sm <?= $statusFilter === 'verified' ? 'btn-success' : 'btn-outline-success' ?> rounded-pill px-3">
                                <i class="fa fa-check-circle me-1"></i> Terverifikasi (Disetujui) <span class="badge bg-white text-dark ms-1"><?= $totalVerified ?></span>
                            </a>
                            <a href="verification.php?status=revision" class="btn btn-sm <?= $statusFilter === 'revision' ? 'btn-danger' : 'btn-outline-danger' ?> rounded-pill px-3">
                                <i class="fa fa-rotate-left me-1"></i> Perlu Revisi <span class="badge bg-white text-dark ms-1"><?= $totalRevision ?></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports List Table -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0">Daftar Pelaporan Kegiatan Monitoring</h5>
                            <small class="text-muted">Kelola alur verifikasi hasil monitoring lapangan oleh koordinator dan pimpinan</small>
                        </div>
                        <span class="text-muted fs-12">Total: <strong><?= count($reports) ?></strong> data</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle table-nowrap data-table-balmon mb-0">
                                <thead class="bg-light fs-12 text-uppercase text-muted">
                                    <tr>
                                        <th>Kode / Kategori</th>
                                        <th>Judul & Target Stasiun</th>
                                        <th>Frekuensi & Lokasi</th>
                                        <th>Petugas Pelapor</th>
                                        <th>Tanggal Monitoring</th>
                                        <th>Status Verifikasi</th>
                                        <th class="text-center">Aksi Verifikasi</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13">
                                    <?php foreach ($reports as $rep): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-<?= $rep['badge_color'] ?> light mb-1 text-nowrap"><?= htmlspecialchars($rep['short_title']) ?></span>
                                                <div class="fw-bold text-primary font-monospace text-nowrap"><?= htmlspecialchars($rep['report_code']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold truncate-lg" title="<?= htmlspecialchars($rep['title']) ?>"><?= htmlspecialchars($rep['title']) ?></div>
                                                <div class="text-muted fs-12 truncate-md" title="Target: <?= htmlspecialchars($rep['target_name']) ?>">Target: <?= htmlspecialchars($rep['target_name']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary light fs-11 mb-1 text-nowrap"><i class="fa fa-wave-square me-1"></i><?= htmlspecialchars($rep['frequency'] ?: 'Multi-band') ?></span>
                                                <div class="truncate-md fs-12 text-muted" title="<?= htmlspecialchars($rep['location']) ?>">
                                                    <i class="fa fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($rep['location']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center text-nowrap">
                                                    <img src="<?= htmlspecialchars($rep['officer_avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle me-2 flex-shrink-0" width="30" height="30" alt="">
                                                    <div class="overflow-hidden">
                                                        <span class="fw-semibold d-block truncate-sm" title="<?= htmlspecialchars($rep['officer_name']) ?>"><?= htmlspecialchars($rep['officer_name']) ?></span>
                                                        <small class="text-muted fs-11 text-nowrap">NIP: <?= htmlspecialchars($rep['officer_nip']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-nowrap"><?= date('d M Y', strtotime($rep['date_monitored'])) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($rep['status'] === 'verified'): ?>
                                                    <span class="badge badge-success light status-badge text-nowrap">
                                                        <i class="fa fa-check-circle"></i> Terverifikasi
                                                    </span>
                                                    <div class="fs-11 text-muted mt-1 truncate-sm" title="oleh <?= htmlspecialchars($rep['verifier_name'] ?? 'Koordinator') ?>">oleh <?= htmlspecialchars($rep['verifier_name'] ?? 'Koordinator') ?></div>
                                                <?php elseif ($rep['status'] === 'submitted'): ?>
                                                    <span class="badge badge-warning light status-badge text-nowrap">
                                                        <i class="fa fa-hourglass-half"></i> Menunggu
                                                    </span>
                                                <?php elseif ($rep['status'] === 'revision'): ?>
                                                    <span class="badge badge-danger light status-badge text-nowrap">
                                                        <i class="fa fa-rotate-left"></i> Perlu Revisi
                                                    </span>
                                                    <?php if (!empty($rep['verification_notes'])): ?>
                                                        <div class="fs-11 text-danger mt-1 truncate-md" title="<?= htmlspecialchars($rep['verification_notes']) ?>">
                                                            "<?= htmlspecialchars($rep['verification_notes']) ?>"
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary light status-badge text-nowrap"><?= strtoupper($rep['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <div class="btn-group">
                                                    <a href="report_detail.php?id=<?= $rep['id'] ?>" class="btn btn-primary btn-xs sharp" title="Lihat Detail Lengkap">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-warning btn-xs sharp" onclick="openVerifyModal(<?= htmlspecialchars(json_encode($rep)) ?>)" title="Proses Verifikasi">
                                                        <i class="fa fa-check-to-slot"></i>
                                                    </button>
                                                </div>
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

<!-- Modal Verifikasi Pelaporan -->
<div class="modal fade" id="verifyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-stamp text-primary me-2"></i> Verifikasi Laporan Monitoring</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="verifyForm" onsubmit="submitVerification(event)">
                <input type="hidden" id="modalReportId" name="report_id">
                <div class="modal-body">
                    <div class="p-3 bg-light rounded-3 mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold text-primary" id="modalReportCode">MON-2026</span>
                            <span class="badge badge-info light" id="modalCategory">Kategori</span>
                        </div>
                        <h6 class="fw-bold mb-1" id="modalTitle">Judul Laporan</h6>
                        <small class="text-muted d-block" id="modalTarget">Target Stasiun</small>
                        <small class="text-muted d-block" id="modalOfficer">Pelapor: -</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-w600 fs-13">Keputusan Verifikasi <span class="text-danger">*</span></label>
                        <select class="form-select" id="modalStatus" required>
                            <option value="verified">Setujui (Terverifikasi Sesuai Ketentuan)</option>
                            <option value="revision">Kembalikan (Perlu Revisi / Perbaikan Data)</option>
                            <option value="rejected">Tolak Laporan</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-w600 fs-13">Catatan Verifikasi / Instruksi Revisi</label>
                        <textarea class="form-control" id="modalNotes" rows="3" placeholder="Tuliskan catatan teknis, instruksi revisi, atau rekomendasi pimpinan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">Simpan Keputusan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
let verifyModalInstance = null;

function openVerifyModal(report) {
    document.getElementById('modalReportId').value = report.id;
    document.getElementById('modalReportCode').innerText = report.report_code;
    document.getElementById('modalCategory').innerText = report.short_title;
    document.getElementById('modalTitle').innerText = report.title;
    document.getElementById('modalTarget').innerText = 'Target: ' + report.target_name + ' (' + (report.frequency || 'N/A') + ')';
    document.getElementById('modalOfficer').innerText = 'Petugas Pelapor: ' + report.officer_name;
    document.getElementById('modalStatus').value = report.status === 'submitted' ? 'verified' : report.status;
    document.getElementById('modalNotes').value = report.verification_notes || '';

    if (!verifyModalInstance) {
        verifyModalInstance = new bootstrap.Modal(document.getElementById('verifyModal'));
    }
    verifyModalInstance.show();
}

function submitVerification(e) {
    e.preventDefault();
    const id = document.getElementById('modalReportId').value;
    const status = document.getElementById('modalStatus').value;
    const notes = document.getElementById('modalNotes').value;

    fetch('api/verification.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            report_id: id,
            status: status,
            notes: notes
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Status verifikasi berhasil disimpan!');
            location.reload();
        } else {
            alert(data.message || 'Gagal memproses verifikasi.');
        }
    })
    .catch(err => {
        alert('Terjadi kesalahan jaringan.');
    });
}
</script>
