<?php
// active_officers.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();
$pageTitle = "Petugas Aktif di Sistem";

$officers = getActiveOfficersList();
$onlineCount = count(array_filter($officers, fn($o) => $o['is_online']));

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
                <li class="breadcrumb-item"><a href="javascript:void(0)">Interaksi Petugas</a></li>
                <li class="breadcrumb-item active"><a href="javascript:void(0)">Petugas Aktif</a></li>
            </ol>
        </div>

        <!-- Header Info -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius: 18px;">
                    <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-1"><i class="material-symbols-outlined text-success me-2 align-middle">sensors</i> Status Petugas Balmon Samarinda</h4>
                            <p class="text-muted fs-13 mb-0">Pantau kehadiran petugas yang sedang aktif di sistem baik via Website maupun Aplikasi Android Flutter</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <span class="badge badge-success light fs-13 px-3 py-2">
                                <span class="online-dot me-1"></span> <?= $onlineCount ?> Petugas Online
                            </span>
                            <a href="chat.php?room=team" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                                <i class="fa fa-users me-1"></i> Buka Chat Tim
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Officers Cards Grid -->
        <div class="row" id="officersGrid">
            <?php foreach ($officers as $off): ?>
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm kpi-card" style="border-radius: 18px;">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="position-relative me-3">
                                    <img src="<?= htmlspecialchars($off['avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle border" width="60" height="60" alt="">
                                    <span class="online_icon <?= $off['is_online'] ? '' : 'offline' ?>" style="width: 14px; height: 14px; border-width: 2px;"></span>
                                </div>
                                <div class="overflow-hidden">
                                    <h5 class="fw-bold mb-0 text-truncate" title="<?= htmlspecialchars($off['name']) ?>"><?= htmlspecialchars($off['name']) ?></h5>
                                    <span class="fs-12 text-muted d-block text-truncate" title="<?= htmlspecialchars($off['jabatan']) ?>"><?= htmlspecialchars($off['jabatan']) ?></span>
                                    <span class="badge badge-xs <?= $off['is_online'] ? 'badge-success' : 'badge-light' ?> light mt-1 text-nowrap">
                                        <?= $off['is_online'] ? 'ONLINE' : 'OFFLINE' ?>
                                    </span>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded-3 mb-3 fs-12 text-muted">
                                <div class="mb-1 text-nowrap"><i class="fa fa-id-badge text-primary me-2"></i> NIP: <strong><?= htmlspecialchars($off['nip']) ?></strong></div>
                                <div class="mb-1 text-truncate" title="<?= htmlspecialchars($off['email']) ?>"><i class="fa fa-envelope text-info me-2"></i> <?= htmlspecialchars($off['email']) ?></div>
                                <?php if (!empty($off['phone'])): ?>
                                    <div class="mb-1 text-nowrap"><i class="fa fa-phone text-success me-2"></i> <?= htmlspecialchars($off['phone']) ?></div>
                                <?php endif; ?>
                                <div class="mb-1 text-nowrap">
                                    <i class="fa fa-mobile-screen text-warning me-2"></i> Perangkat: 
                                    <strong class="text-uppercase"><?= htmlspecialchars($off['device_type'] ?? 'WEB') ?></strong>
                                </div>
                                <div class="text-nowrap">
                                    <i class="fa fa-clock text-secondary me-2"></i> Aktivitas Terakhir: 
                                    <strong><?= date('d M Y, H:i', strtotime($off['last_active'])) ?> WITA</strong>
                                </div>
                            </div>

                            <div class="mt-auto d-flex gap-2">
                                <?php if ($currentUser['id'] != $off['id']): ?>
                                    <a href="chat.php?user_id=<?= $off['id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill flex-grow-1">
                                        <i class="fa fa-comment-dots me-1"></i> Kirim Pesan
                                    </a>
                                <?php else: ?>
                                    <span class="badge badge-light text-muted w-100 py-2 fs-12">Ini Akun Anda Sendiri</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
