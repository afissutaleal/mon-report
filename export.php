<?php
// export.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();

$catId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$format = $_GET['format'] ?? 'view'; // 'excel', 'view'

$sql = "
    SELECT r.*, c.name as category_name, c.short_title,
           u.name as officer_name, u.nip as officer_nip,
           v.name as verifier_name
    FROM monitoring_reports r
    JOIN monitoring_categories c ON r.category_id = c.id
    JOIN users u ON r.user_id = u.id
    LEFT JOIN users v ON r.verified_by = v.id
    WHERE 1=1
";
$params = [];
if ($catId > 0) {
    $sql .= " AND r.category_id = ?";
    $params[] = $catId;
}
$sql .= " ORDER BY r.date_monitored DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

if ($format === 'excel') {
    $filename = "Rekap_Monitoring_Balmon_Samarinda_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr style='background-color:#0070E0; color:white; font-weight:bold;'>";
    echo "<th>No</th><th>Kode Laporan</th><th>Kategori</th><th>Judul</th><th>Target Objek</th><th>Frekuensi</th><th>Lokasi</th><th>Tanggal</th><th>Petugas</th><th>NIP</th><th>Status</th><th>Verifikator</th><th>Catatan</th>";
    echo "</tr>";
    $no = 1;
    foreach ($reports as $r) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($r['report_code']) . "</td>";
        echo "<td>" . htmlspecialchars($r['short_title']) . "</td>";
        echo "<td>" . htmlspecialchars($r['title']) . "</td>";
        echo "<td>" . htmlspecialchars($r['target_name']) . "</td>";
        echo "<td>" . htmlspecialchars($r['frequency']) . "</td>";
        echo "<td>" . htmlspecialchars($r['location']) . "</td>";
        echo "<td>" . htmlspecialchars($r['date_monitored']) . "</td>";
        echo "<td>" . htmlspecialchars($r['officer_name']) . "</td>";
        echo "<td>'" . htmlspecialchars($r['officer_nip']) . "</td>";
        echo "<td>" . strtoupper($r['status']) . "</td>";
        echo "<td>" . htmlspecialchars($r['verifier_name'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($r['verification_notes'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

$pageTitle = "Cetak & Ekspor Data Monitoring";
$categories = $pdo->query("SELECT id, short_title FROM monitoring_categories ORDER BY id ASC")->fetchAll();

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
                <li class="breadcrumb-item active"><a href="javascript:void(0)">Cetak & Ekspor Data</a></li>
            </ol>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius: 18px;">
                    <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-1"><i class="fa fa-print text-primary me-2"></i> Rekapitulasi & Ekspor Laporan</h4>
                            <p class="text-muted fs-13 mb-0">Cetak dokumen resmi atau unduh berkas spreadsheet Microsoft Excel hasil monitoring</p>
                        </div>
                        <div class="d-flex gap-2 mt-3 mt-sm-0">
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="window.print()">
                                <i class="fa fa-print me-1"></i> Cetak Halaman (Print PDF)
                            </button>
                            <a href="export.php?format=excel<?= $catId ? '&category_id='.$catId : '' ?>" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm">
                                <i class="fa fa-file-excel me-1"></i> Unduh File Excel (.XLS)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Printable Report Sheet -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius: 18px;">
                    <div class="card-body p-4 p-md-5">
                        <!-- Kop Balmon Samarinda -->
                        <div class="text-center border-bottom pb-4 mb-4">
                            <h6 class="text-uppercase fw-bold text-muted mb-0 fs-12">KEMENTERIAN KOMUNIKASI DAN DIGITAL REPUBLIK INDONESIA</h6>
                            <h5 class="fw-bold mb-0">DIREKTORAT JENDERAL INFRASTRUKTUR DIGITAL</h5>
                            <h4 class="text-primary fw-bold mb-1">BALAI MONITOR SPEKTRUM FREKUENSI RADIO KELAS I SAMARINDA</h4>
                            <p class="fs-12 text-muted mb-0">REKAPITULASI CAPAIAN DAN HASIL MONITORING TAHUN 2026</p>
                            <small class="text-muted fs-11">Dicetak pada: <?= date('d F Y, H:i') ?> WITA oleh <?= htmlspecialchars($currentUser['name']) ?></small>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle table-nowrap data-table-balmon fs-12 mb-0">
                                <thead class="bg-light text-uppercase">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">No</th>
                                        <th>Kode Laporan</th>
                                        <th>Kategori Kegiatan</th>
                                        <th>Target / Stasiun</th>
                                        <th>Frekuensi</th>
                                        <th>Lokasi</th>
                                        <th>Tanggal</th>
                                        <th>Petugas Pelapor</th>
                                        <th>Status Verifikasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($reports as $r): ?>
                                        <tr>
                                            <td class="text-center text-nowrap"><?= $no++ ?></td>
                                            <td class="font-monospace fw-bold text-primary text-nowrap"><?= htmlspecialchars($r['report_code']) ?></td>
                                            <td class="text-nowrap"><?= htmlspecialchars($r['short_title']) ?></td>
                                            <td><div class="fw-bold truncate-md" title="<?= htmlspecialchars($r['target_name']) ?>"><?= htmlspecialchars($r['target_name']) ?></div></td>
                                            <td class="text-nowrap"><?= htmlspecialchars($r['frequency'] ?: '-') ?></td>
                                            <td><div class="truncate-md" title="<?= htmlspecialchars($r['location']) ?>"><?= htmlspecialchars($r['location']) ?></div></td>
                                            <td class="text-nowrap"><?= date('d/m/Y', strtotime($r['date_monitored'])) ?></td>
                                            <td><span class="truncate-sm d-inline-block align-middle" title="<?= htmlspecialchars($r['officer_name']) ?>"><?= htmlspecialchars($r['officer_name']) ?></span></td>
                                            <td class="text-nowrap">
                                                <span class="badge badge-<?= $r['status'] === 'verified' ? 'success' : ($r['status'] === 'submitted' ? 'warning' : 'danger') ?> light">
                                                    <?= strtoupper($r['status']) ?>
                                                </span>
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
