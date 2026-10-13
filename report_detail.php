<?php
// report_detail.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/category_definitions.php';

$pdo = getDbConnection();
$currentUser = currentUser();
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$reportId) {
    header("Location: reports.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.*, c.name as category_name, c.short_title, c.icon as category_icon, c.badge_color,
           u.name as officer_name, u.nip as officer_nip, u.jabatan as officer_jabatan, u.phone as officer_phone, u.avatar as officer_avatar,
           v.name as verifier_name, v.nip as verifier_nip, v.jabatan as verifier_jabatan
    FROM monitoring_reports r
    JOIN monitoring_categories c ON r.category_id = c.id
    JOIN users u ON r.user_id = u.id
    LEFT JOIN users v ON r.verified_by = v.id
    WHERE r.id = ?
");
$stmt->execute([$reportId]);
$report = $stmt->fetch();

if (!$report) {
    die("Laporan monitoring tidak ditemukan.");
}

$pageTitle = "Detail Laporan " . $report['report_code'];
$techData = json_decode($report['technical_data'], true) ?? [];
$techFields = getCategoryTechnicalFields($report['category_id']);

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
                <li class="breadcrumb-item"><a href="reports.php">Data Laporan</a></li>
                <li class="breadcrumb-item active"><a href="javascript:void(0)"><?= htmlspecialchars($report['report_code']) ?></a></li>
            </ol>
        </div>

        <div class="row">
            <div class="col-xl-9 col-lg-8">
                <!-- Main Report Card -->
                <div class="card border-0 shadow-sm print-area" style="border-radius: 18px;">
                    <div class="card-body p-4 p-md-5">
                        <!-- Official Letterhead (Kop Surat Resmi) -->
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-4 mb-4">
                            <div class="d-flex align-items-center">
                                <div class="me-3 text-center d-flex align-items-center justify-content-center" style="width: 58px; height: 58px;">
                                    <img src="assets/images/logo-monreport.png" alt="Logo Balmon" style="width: 100%; height: 100%; object-fit: contain; filter: drop-shadow(0 2px 6px rgba(0, 112, 224, 0.2));">
                                </div>
                                <div>
                                    <h6 class="text-uppercase fw-bold text-muted mb-0 fs-12">KEMENTERIAN KOMUNIKASI DAN DIGITAL REPUBLIK INDONESIA</h6>
                                    <h5 class="fw-bold mb-0">DIREKTORAT JENDERAL INFRASTRUKTUR DIGITAL</h5>
                                    <h6 class="text-primary fw-bold mb-0">BALAI MONITOR SPEKTRUM FREKUENSI RADIO KELAS I SAMARINDA</h6>
                                    <small class="text-muted fs-11">Jl. Pemuda No. 01, Telp. (0541) 743123, Samarinda, Kalimantan Timur 75117</small>
                                </div>
                            </div>
                            <div class="text-end d-none d-sm-block">
                                <span class="badge badge-<?= $report['badge_color'] ?> light fs-12 mb-1"><?= htmlspecialchars($report['short_title']) ?></span>
                                <div class="font-monospace fw-bold text-dark fs-14"><?= htmlspecialchars($report['report_code']) ?></div>
                            </div>
                        </div>

                        <!-- Title and Status Bar -->
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
                            <div>
                                <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($report['title']) ?></h4>
                                <span class="text-muted fs-13"><i class="fa fa-calendar-day me-1"></i> Tanggal Pengamatan: <strong><?= date('d F Y', strtotime($report['date_monitored'])) ?></strong></span>
                            </div>
                            <div>
                                <?php if ($report['status'] === 'verified'): ?>
                                    <span class="badge badge-success light fs-13 px-3 py-2 status-badge"><i class="fa fa-check-circle"></i> Terverifikasi (Disetujui)</span>
                                <?php elseif ($report['status'] === 'submitted'): ?>
                                    <span class="badge badge-warning light fs-13 px-3 py-2 status-badge"><i class="fa fa-clock"></i> Menunggu Verifikasi</span>
                                <?php elseif ($report['status'] === 'revision'): ?>
                                    <span class="badge badge-danger light fs-13 px-3 py-2 status-badge"><i class="fa fa-rotate-left"></i> Perlu Revisi</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary light fs-13 px-3 py-2 status-badge"><?= strtoupper($report['status']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- General Information Grid -->
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="fs-12 text-muted d-block mb-1">Target / Nama Objek</span>
                                    <strong class="fs-14 text-dark"><?= htmlspecialchars($report['target_name']) ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="fs-12 text-muted d-block mb-1">Frekuensi Kerja / Band</span>
                                    <strong class="fs-14 text-primary"><?= htmlspecialchars($report['frequency'] ?: 'Multi-band') ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="fs-12 text-muted d-block mb-1">Lokasi Pemantauan / Alamat</span>
                                    <strong class="fs-14 text-dark"><i class="fa fa-map-pin text-danger me-1"></i> <?= htmlspecialchars($report['location']) ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="fs-12 text-muted d-block mb-1">Koordinat GPS (WGS84)</span>
                                    <strong class="fs-14 text-dark"><?= htmlspecialchars($report['latitude']) ?>, <?= htmlspecialchars($report['longitude']) ?></strong>
                                    <a href="https://www.google.com/maps?q=<?= $report['latitude'] ?>,<?= $report['longitude'] ?>" target="_blank" class="badge badge-primary light ms-2">Buka Peta</a>
                                </div>
                            </div>
                        </div>

                        <!-- Technical Parameter Specific Table -->
                        <h5 class="fw-bold mb-3 border-bottom pb-2 text-primary">
                            <i class="fa-solid <?= $report['category_icon'] ?> me-2"></i> Parameter Teknis & Hasil Pengukuran
                        </h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-striped align-middle">
                                <thead class="bg-light fs-12 text-uppercase">
                                    <tr>
                                        <th style="width: 40%;">Parameter / Item Pengujian</th>
                                        <th style="width: 60%;">Hasil Pengukuran / Status Teknis</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13">
                                    <?php if (!empty($techFields)): ?>
                                        <?php foreach ($techFields as $tf): 
                                            $val = $techData[$tf['name']] ?? '-';
                                        ?>
                                            <tr>
                                                <td class="fw-semibold text-muted bg-light bg-opacity-50"><?= htmlspecialchars($tf['label']) ?></td>
                                                <td class="fw-bold text-dark"><?= nl2br(htmlspecialchars($val)) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">Tidak ada data teknis spesifik.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Verification & Approval Signatures -->
                        <div class="row pt-4 mt-4 border-top">
                            <div class="col-6 text-center">
                                <p class="fs-12 text-muted mb-1">Petugas Pelaksana Monitoring,</p>
                                <div style="height: 60px;"></div>
                                <strong class="d-block fs-14 text-dark"><?= htmlspecialchars($report['officer_name']) ?></strong>
                                <span class="fs-12 text-muted">NIP. <?= htmlspecialchars($report['officer_nip']) ?></span>
                            </div>
                            <div class="col-6 text-center">
                                <p class="fs-12 text-muted mb-1">Mengetahui / Memverifikasi,</p>
                                <div style="height: 60px;">
                                    <?php if ($report['status'] === 'verified'): ?>
                                        <span class="badge badge-success light fs-11 mt-3 px-3 py-1">TELAH DIVERIFIKASI DIGITAL</span>
                                    <?php endif; ?>
                                </div>
                                <strong class="d-block fs-14 text-dark"><?= htmlspecialchars($report['verifier_name'] ?? 'Andreo Yustiantoro Anjaya, ST') ?></strong>
                                <span class="fs-12 text-muted">Ketua Tim Kerja Montib SFR dan APT</span>
                            </div>
                        </div>

                        <?php if (!empty($report['verification_notes'])): ?>
                            <div class="mt-4 p-3 bg-light rounded-3 border-start border-4 border-warning">
                                <h6 class="fw-bold mb-1 text-dark"><i class="fa fa-comment-dots me-1"></i> Catatan Verifikasi:</h6>
                                <p class="fs-13 text-muted mb-0"><?= nl2br(htmlspecialchars($report['verification_notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Actions Side Column -->
            <div class="col-xl-3 col-lg-4">
                <div class="card border-0 shadow-sm mb-3" style="border-radius: 18px;">
                    <div class="card-header border-0 pb-0">
                        <h6 class="fw-bold mb-0">Aksi Dokumen</h6>
                    </div>
                    <div class="card-body p-3 d-grid gap-2">
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                            <i class="fa fa-print me-1"></i> Cetak Dokumen Laporan
                        </button>
                        <a href="export.php?id=<?= $report['id'] ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-file-excel me-1"></i> Unduh Data Excel / CSV
                        </a>
                        <a href="reports.php" class="btn btn-outline-dark btn-sm">
                            <i class="fa fa-arrow-left me-1"></i> Kembali ke Daftar Laporan
                        </a>
                    </div>
                </div>

                <!-- Verification Card (For coordinators/heads/admin) -->
                <?php if (in_array($currentUser['role'], ['admin', 'koordinator', 'kepala_balai'])): ?>
                    <div class="card border-0 shadow-sm mb-3" style="border-radius: 18px;">
                        <div class="card-header border-0 pb-0">
                            <h6 class="fw-bold mb-0"><i class="fa fa-stamp text-primary me-1"></i> Panel Verifikasi</h6>
                        </div>
                        <div class="card-body p-3">
                            <form onsubmit="updateStatusDirect(event)">
                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold">Ubah Status:</label>
                                    <select id="quickStatus" class="form-select form-select-sm">
                                        <option value="verified" <?= $report['status'] === 'verified' ? 'selected' : '' ?>>Disetujui (Terverifikasi)</option>
                                        <option value="revision" <?= $report['status'] === 'revision' ? 'selected' : '' ?>>Kembalikan (Perlu Revisi)</option>
                                        <option value="rejected" <?= $report['status'] === 'rejected' ? 'selected' : '' ?>>Tolak Laporan</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold">Catatan Evaluasi:</label>
                                    <textarea id="quickNotes" class="form-control form-control-sm" rows="3" placeholder="Tuliskan catatan teknis..."><?= htmlspecialchars($report['verification_notes'] ?? '') ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm w-100 fw-bold">
                                    Simpan Status Verifikasi
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Officer Info Card -->
                <div class="card border-0 shadow-sm" style="border-radius: 18px;">
                    <div class="card-header border-0 pb-0">
                        <h6 class="fw-bold mb-0">Petugas Pembuat</h6>
                    </div>
                    <div class="card-body p-3 text-center">
                        <img src="<?= htmlspecialchars($report['officer_avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle mb-2" width="60" height="60" alt="">
                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($report['officer_name']) ?></h6>
                        <span class="fs-12 text-muted d-block"><?= htmlspecialchars($report['officer_jabatan']) ?></span>
                        <small class="badge badge-primary light mt-1">NIP: <?= htmlspecialchars($report['officer_nip']) ?></small>
                        <div class="mt-3">
                            <a href="chat.php?user_id=<?= $report['user_id'] ?>" class="btn btn-outline-primary btn-xs w-100">
                                <i class="fa fa-comment me-1"></i> Hubungi Petugas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
function updateStatusDirect(e) {
    e.preventDefault();
    const st = document.getElementById('quickStatus').value;
    const nt = document.getElementById('quickNotes').value;

    fetch('api/verification.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            report_id: <?= $report['id'] ?>,
            status: st,
            notes: nt
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Status verifikasi berhasil disimpan!');
            location.reload();
        } else {
            alert(data.message || 'Gagal update verifikasi');
        }
    });
}
</script>
