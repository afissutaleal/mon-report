<?php
// schedule_add.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();
$pageTitle = "Tambah Agenda Monitoring";

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $spt = trim($_POST['spt_number'] ?? '');
    $sptDate = !empty($_POST['spt_date']) ? trim($_POST['spt_date']) : date('Y-m-d');
    $title = trim($_POST['title'] ?? '');
    $catIds = isset($_POST['category_ids']) && is_array($_POST['category_ids']) 
        ? array_map('intval', $_POST['category_ids']) 
        : [(int)($_POST['category_id'] ?? 1)];
    if (empty($catIds)) $catIds = [1];
    $catId = $catIds[0];
    $catIdsJson = json_encode(array_values(array_unique($catIds)));

    $leaderId = (int)($_POST['leader_id'] ?? $currentUser['id']);
    $members = trim($_POST['members'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $target = trim($_POST['target_object'] ?? '');
    $start = $_POST['start_date'] ?? date('Y-m-d');
    $end = $_POST['end_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($spt) || empty($title) || empty($location)) {
        $err = "Nomor SPT, Judul Kegiatan, dan Lokasi wajib diisi.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO monitoring_schedules (
                    spt_number, spt_date, title, category_id, category_ids, leader_id, member_names,
                    location, target_object, start_date, end_date, status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
            ");
            $stmt->execute([
                $spt, $sptDate, $title, $catId, $catIdsJson, $leaderId, $members,
                $location, $target, $start, $end, $notes
            ]);
            header("Location: schedules.php?created=1");
            exit;
        } catch (Exception $e) {
            $err = "Gagal membuat jadwal: " . $e->getMessage();
        }
    }
}

$categories = $pdo->query("SELECT id, name, short_title FROM monitoring_categories ORDER BY id ASC")->fetchAll();
$officers = $pdo->query("SELECT id, name, jabatan FROM users ORDER BY name ASC")->fetchAll();

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
                <li class="breadcrumb-item"><a href="schedules.php">Jadwal Monitoring</a></li>
                <li class="breadcrumb-item active"><a href="javascript:void(0)">Tambah Agenda</a></li>
            </ol>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-9 col-lg-10">
                <div class="card border-0 shadow-sm" style="border-radius: 18px;">
                    <div class="card-header border-0 pb-0">
                        <h5 class="fw-bold mb-0"><i class="fa fa-calendar-plus text-primary me-2"></i> Formulir Surat Perintah Tugas (SPT) Monitoring</h5>
                        <small class="text-muted">Masukkan rincian penugasan tim lapangan Balmon Samarinda</small>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($err): ?>
                            <div class="alert alert-danger py-2 fs-13 mb-3"><?= htmlspecialchars($err) ?></div>
                        <?php endif; ?>

                        <form action="" method="POST">
                            <div class="row">
                                <div class="col-md-7 mb-3">
                                    <label class="form-label font-w600 fs-13">Nomor Surat Perintah Tugas (SPT) <span class="text-danger">*</span></label>
                                    <input type="text" name="spt_number" class="form-control" placeholder="Contoh: 104/BALMON.64/KP.01.06/07/2026" required>
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label class="form-label font-w600 fs-13">Tanggal Keluar / Ditetapkan SPT <span class="text-danger">*</span></label>
                                    <input type="date" name="spt_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    <small class="text-muted fs-11">Tanggal resmi penerbitan / penandatanganan SPT</small>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label font-w600 fs-13">Jenis Kegiatan Monitoring <span class="text-danger">*</span> <small class="text-muted">(Dapat memilih lebih dari 1 kegiatan)</small></label>
                                    <div class="row g-2 p-3 border rounded bg-light">
                                        <?php foreach ($categories as $cat): ?>
                                            <div class="col-md-4 col-sm-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="category_ids[]" value="<?= $cat['id'] ?>" id="cat_<?= $cat['id'] ?>" <?= $cat['id'] == 1 ? 'checked' : '' ?>>
                                                    <label class="form-check-label fs-12 fw-semibold" for="cat_<?= $cat['id'] ?>">
                                                        <?= htmlspecialchars($cat['name']) ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label font-w600 fs-13">Judul Penugasan / Kegiatan <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" placeholder="Contoh: Pengukuran Spektrum Radio FM dan Inspeksi Microwave di Bontang" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-w600 fs-13">Ketua Tim Penugasan <span class="text-danger">*</span></label>
                                    <select name="leader_id" class="form-select" required>
                                        <?php foreach ($officers as $off): ?>
                                            <option value="<?= $off['id'] ?>" <?= $currentUser['id'] == $off['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($off['name']) ?> (<?= htmlspecialchars($off['jabatan']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-w600 fs-13">Anggota Petugas Lapangan</label>
                                    <input type="text" name="members" class="form-control" placeholder="Nama-nama anggota tim dipisahkan koma">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-w600 fs-13">Lokasi / Wilayah Tugas <span class="text-danger">*</span></label>
                                    <input type="text" name="location" class="form-control" placeholder="Contoh: Kota Bontang, Kab. Kutai Timur, IKN Sepaku" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-w600 fs-13">Target Objek / Sasaran Monitoring</label>
                                    <input type="text" name="target_object" class="form-control" placeholder="Contoh: Stasiun Radio Siaran, BTS Seluler, Microwave Link">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-w600 fs-13">Tanggal Mulai Penugasan <span class="text-danger">*</span></label>
                                    <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-w600 fs-13">Tanggal Selesai Penugasan <span class="text-danger">*</span></label>
                                    <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" required>
                                </div>
                                <div class="col-12 mb-4">
                                    <label class="form-label font-w600 fs-13">Catatan / Keterangan Penugasan</label>
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Informasi kendaraan dinas, instrumen yang dibawa, atau koordinasi pihak ketiga..."></textarea>
                                </div>
                                <div class="col-12 text-end">
                                    <a href="schedules.php" class="btn btn-light btn-sm me-2">Batal</a>
                                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                                        <i class="fa fa-save me-1"></i> Terbitkan Agenda SPT
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
