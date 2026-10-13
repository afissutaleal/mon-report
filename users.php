<?php
// users.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();
$pageTitle = "Daftar Personel Balmon";

$officers = getActiveOfficersList();

// Filter by Tim Kerja if requested
$filterTim = $_GET['tim'] ?? 'all';
if ($filterTim !== 'all') {
    $filteredOfficers = array_filter($officers, function($o) use ($filterTim) {
        return stripos($o['tim_kerja'] ?? '', $filterTim) !== false;
    });
} else {
    $filteredOfficers = $officers;
}

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
                <li class="breadcrumb-item active"><a href="javascript:void(0)">Daftar Personel</a></li>
            </ol>
        </div>

        <!-- Filter Tim Kerja Badges -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="fs-13 fw-bold text-muted me-2"><i class="fa-solid fa-filter me-1"></i> Filter Tim Kerja:</span>
                    <a href="users.php" class="btn btn-sm <?= $filterTim === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Semua Personel (<?= count($officers) ?>)
                    </a>
                    <a href="users.php?tim=Montib" class="btn btn-sm <?= $filterTim === 'Montib' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Tim Montib SFR & APT
                    </a>
                    <a href="users.php?tim=Sarana" class="btn btn-sm <?= $filterTim === 'Sarana' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Tim Sarana & Prasarana
                    </a>
                    <a href="users.php?tim=Kualitas" class="btn btn-sm <?= $filterTim === 'Kualitas' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Tim Kualitas Layanan
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius: 18px;">
                    <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0">Daftar Personel Resmi Balmon Kelas I Samarinda</h5>
                            <small class="text-muted">Balai Monitor Spektrum Frekuensi Radio dan Infrastruktur Digital Kelas I Samarinda - Ditjend Infrastruktur Digital</small>
                        </div>
                        <span class="badge badge-primary light fs-13 py-2 px-3"><?= count($filteredOfficers) ?> Personel Ditampilkan</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle data-table-balmon mb-0">
                                <thead class="bg-light fs-12 text-uppercase text-muted">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">No</th>
                                        <th>Nama Petugas</th>
                                        <th>NIP Dinas</th>
                                        <th>Pangkat / Golongan</th>
                                        <th>Jabatan Kedinasan</th>
                                        <th>Tim Kerja (Keterangan)</th>
                                        <th>Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13">
                                    <?php $no = 1; foreach ($filteredOfficers as $off): ?>
                                        <tr>
                                            <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                                            <td>
                                                <div class="d-flex align-items-center text-nowrap">
                                                    <div class="position-relative me-2 flex-shrink-0">
                                                        <img src="<?= htmlspecialchars($off['avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle" width="36" height="36" alt="">
                                                        <span class="online_icon <?= $off['is_online'] ? '' : 'offline' ?>"></span>
                                                    </div>
                                                    <div>
                                                        <strong class="text-dark d-block fs-13 text-nowrap"><?= htmlspecialchars($off['name']) ?></strong>
                                                        <small class="text-muted fs-11 text-nowrap"><?= htmlspecialchars($off['email']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="font-monospace fw-bold text-primary fs-12 text-nowrap"><?= htmlspecialchars($off['nip']) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-light text-dark fw-bold px-2 py-1 text-nowrap">
                                                    <?= htmlspecialchars($off['pangkat_gol'] ?: '-') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="truncate-md fw-bold text-dark fs-13" title="<?= htmlspecialchars($off['jabatan']) ?>">
                                                    <?= htmlspecialchars($off['jabatan']) ?>
                                                </div>
                                                <span class="badge badge-xs badge-outline-secondary text-uppercase text-nowrap">
                                                    <?= htmlspecialchars($off['role']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                    $tim = $off['tim_kerja'] ?: 'Balmon Samarinda';
                                                    $badgeBg = 'badge-primary';
                                                    if (stripos($tim, 'Montib') !== false) $badgeBg = 'badge-info';
                                                    elseif (stripos($tim, 'Sarana') !== false) $badgeBg = 'badge-warning';
                                                    elseif (stripos($tim, 'Kualitas') !== false) $badgeBg = 'badge-success';
                                                    elseif (stripos($tim, 'Kepala') !== false) $badgeBg = 'badge-danger';
                                                ?>
                                                <span class="badge <?= $badgeBg ?> light fs-11 text-nowrap">
                                                    <?= htmlspecialchars($tim) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($off['is_online']): ?>
                                                    <span class="badge badge-success light status-badge text-nowrap">
                                                        <span class="online-dot me-1"></span> ONLINE (<?= strtoupper($off['device_type'] ?? 'WEB') ?>)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-light text-muted status-badge text-nowrap">Offline</span>
                                                    <small class="text-muted d-inline-block fs-11 ms-1 text-nowrap"><?= date('d/m H:i', strtotime($off['last_active'])) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <a href="chat.php?user_id=<?= $off['id'] ?>" class="btn btn-outline-primary btn-xs sharp" title="Kirim Pesan">
                                                    <i class="fa fa-comment"></i>
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

<?php
require_once __DIR__ . '/includes/footer.php';
?>
