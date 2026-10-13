<?php
// chat.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();
$pageTitle = "Diskusi Tim & Pesan Interaksi";

// Parameter Handling
$targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$room = $_GET['room'] ?? null;

if ($targetUserId && empty($room)) {
    $room = 'direct';
} elseif (empty($room)) {
    $room = 'all';
}

if ($room === 'team') {
    $room = 'all';
}

// Fetch all officers
$officers = getActiveOfficersList();

// Categorize officers per Tim Kerja
$pimpinanOfficers = [];
$montibOfficers = [];
$sarprasOfficers = [];
$kualidOfficers = [];

foreach ($officers as $off) {
    $tk = $off['tim_kerja'] ?? '';
    if (stripos($tk, 'Kepala Balai') !== false || ($off['role'] ?? '') === 'kepala_balai') {
        $pimpinanOfficers[] = $off;
    } elseif (stripos($tk, 'Montib') !== false) {
        $montibOfficers[] = $off;
    } elseif (stripos($tk, 'Sarana') !== false || stripos($tk, 'Sarpras') !== false) {
        $sarprasOfficers[] = $off;
    } elseif (stripos($tk, 'Kualitas') !== false || stripos($tk, 'Kualid') !== false) {
        $kualidOfficers[] = $off;
    } else {
        $montibOfficers[] = $off;
    }
}

// Metadata for Team Discussion Rooms
$roomDefs = [
    'all' => [
        'key' => 'all',
        'title' => 'Diskusi Semua Tim Kerja',
        'short_name' => 'Semua Tim Kerja',
        'badge' => count($officers) . ' Personel',
        'desc' => 'Forum koordinasi umum seluruh tim kerja Balmon Kelas I Samarinda',
        'icon' => 'groups',
        'color' => '#38bdf8',
        'members' => $officers,
        'team_filter' => 'all',
    ],
    'sarpras' => [
        'key' => 'sarpras',
        'title' => 'Diskusi Tim Kerja Sarpras',
        'short_name' => 'Tim Kerja Sarpras',
        'badge' => count($sarprasOfficers) . ' Personel',
        'desc' => 'Sarana & prasarana, stasiun monitoring, genset, UPS, armada kendaraan & IT Balmon',
        'icon' => 'construction',
        'color' => '#f59e0b',
        'members' => array_merge($sarprasOfficers, $pimpinanOfficers),
        'team_filter' => 'Sarana',
    ],
    'montib' => [
        'key' => 'montib',
        'title' => 'Diskusi Tim Kerja Montib',
        'short_name' => 'Tim Kerja Montib',
        'badge' => count($montibOfficers) . ' Personel',
        'desc' => 'Monitoring dan Penertiban Spektrum Frekuensi Radio & Alat Perangkat Telekomunikasi',
        'icon' => 'radar',
        'color' => '#10b981',
        'members' => array_merge($montibOfficers, $pimpinanOfficers),
        'team_filter' => 'Montib',
    ],
    'kualid' => [
        'key' => 'kualid',
        'title' => 'Diskusi Tim Kerja Kualid',
        'short_name' => 'Tim Kerja Kualid',
        'badge' => count($kualidOfficers) . ' Personel',
        'desc' => 'Standardisasi perangkat telekomunikasi (SMART) & pengujian kualitas layanan digital',
        'icon' => 'verified',
        'color' => '#a855f7',
        'members' => array_merge($kualidOfficers, $pimpinanOfficers),
        'team_filter' => 'Kualitas',
    ],
];

// Target officer for direct chat
$targetOfficer = null;
if ($room === 'direct' && $targetUserId) {
    $stmtT = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtT->execute([$targetUserId]);
    $targetOfficer = $stmtT->fetch();
}

$activeRoomDef = $roomDefs[$room] ?? $roomDefs['all'];
$activeRoomMembers = $activeRoomDef['members'] ?? $officers;

// Helper to render team badge
function renderTeamBadge($timKerja, $isSmall = false) {
    $fs = $isSmall ? 'fs-10 px-1 py-0' : 'fs-11 px-2 py-1';
    $iconFs = $isSmall ? 'fs-10 me-1' : 'fs-12 me-1';
    if (stripos($timKerja, 'Montib') !== false) {
        return '<span class="badge border ' . $fs . ' d-inline-flex align-items-center" style="background: rgba(16, 185, 129, 0.12); color: #10b981; border-color: rgba(16, 185, 129, 0.25);"><i class="material-symbols-outlined ' . $iconFs . '">radar</i>Tim Montib</span>';
    } elseif (stripos($timKerja, 'Sarana') !== false || stripos($timKerja, 'Sarpras') !== false) {
        return '<span class="badge border ' . $fs . ' d-inline-flex align-items-center" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b; border-color: rgba(245, 158, 11, 0.25);"><i class="material-symbols-outlined ' . $iconFs . '">construction</i>Tim Sarpras</span>';
    } elseif (stripos($timKerja, 'Kualitas') !== false || stripos($timKerja, 'Kualid') !== false) {
        return '<span class="badge border ' . $fs . ' d-inline-flex align-items-center" style="background: rgba(168, 85, 247, 0.12); color: #a855f7; border-color: rgba(168, 85, 247, 0.25);"><i class="material-symbols-outlined ' . $iconFs . '">verified</i>Tim Kualid</span>';
    } else {
        return '<span class="badge border ' . $fs . ' d-inline-flex align-items-center" style="background: rgba(56, 189, 248, 0.12); color: #38bdf8; border-color: rgba(56, 189, 248, 0.25);"><i class="material-symbols-outlined ' . $iconFs . '">shield_person</i>Pimpinan</span>';
    }
}

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav_header.php';
require_once __DIR__ . '/includes/chatbox.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
.room-card-link {
    transition: all 0.2s ease-in-out;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.03);
    text-decoration: none !important;
}
.room-card-link:hover {
    background: rgba(255, 255, 255, 0.07);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.room-card-link.is-active {
    background: rgba(56, 189, 248, 0.1) !important;
    border-color: #38bdf8 !important;
    box-shadow: 0 0 0 1px #38bdf8, 0 4px 14px rgba(56, 189, 248, 0.2);
}
.room-card-link.is-active-sarpras {
    background: rgba(245, 158, 11, 0.1) !important;
    border-color: #f59e0b !important;
    box-shadow: 0 0 0 1px #f59e0b, 0 4px 14px rgba(245, 158, 11, 0.2);
}
.room-card-link.is-active-montib {
    background: rgba(16, 185, 129, 0.1) !important;
    border-color: #10b981 !important;
    box-shadow: 0 0 0 1px #10b981, 0 4px 14px rgba(16, 185, 129, 0.2);
}
.room-card-link.is-active-kualid {
    background: rgba(168, 85, 247, 0.1) !important;
    border-color: #a855f7 !important;
    box-shadow: 0 0 0 1px #a855f7, 0 4px 14px rgba(168, 85, 247, 0.2);
}

.officer-item-link {
    transition: all 0.18s ease-in-out;
    border: 1px solid transparent;
    text-decoration: none !important;
}
.officer-item-link:hover {
    background: rgba(56, 189, 248, 0.06);
    border-color: rgba(56, 189, 248, 0.2);
}
.officer-item-link.is-active {
    background: rgba(56, 189, 248, 0.12) !important;
    border-color: #38bdf8 !important;
}

.filter-team-pill {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 20px;
    cursor: pointer;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.04);
    color: #94a3b8;
    transition: all 0.15s ease-in-out;
}
.filter-team-pill:hover,
.filter-team-pill.active {
    color: #ffffff;
    background: #0284c7;
    border-color: #38bdf8;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.3);
}

.chat-bubble-me {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    color: #ffffff;
    border-radius: 16px 16px 3px 16px;
    box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);
}
.chat-bubble-other {
    background: #ffffff;
    color: #1e293b;
    border-radius: 16px 16px 16px 3px;
    border: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
[data-theme-version="dark"] .chat-bubble-other {
    background: #1e293b;
    color: #f1f5f9;
    border-color: rgba(255, 255, 255, 0.1);
}

.online-dot-pulse {
    width: 10px;
    height: 10px;
    background-color: #10b981;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 2px #ffffff;
}
[data-theme-version="dark"] .online-dot-pulse {
    box-shadow: 0 0 0 2px #0f172a;
}
.offline-dot {
    width: 10px;
    height: 10px;
    background-color: #94a3b8;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 2px #ffffff;
}
[data-theme-version="dark"] .offline-dot {
    box-shadow: 0 0 0 2px #0f172a;
}
</style>

<div class="content-body">
    <div class="container-fluid">
        <!-- Breadcrumb Header -->
        <div class="row page-titles mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="javascript:void(0)">Diskusi Tim</a></li>
                <li class="breadcrumb-item active">
                    <a href="javascript:void(0)">
                        <?= $room === 'direct' ? ($targetOfficer ? 'Pesan Langsung: ' . htmlspecialchars($targetOfficer['name']) : 'Pesan Langsung') : htmlspecialchars($activeRoomDef['title']) ?>
                    </a>
                </li>
            </ol>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius: 18px; overflow: hidden; min-height: 700px;">
                    <div class="row g-0 h-100">
                        
                        <!-- Left Panel: Ruang Diskusi Tim & Pesan Langsung -->
                        <div class="col-xl-4 col-lg-5 border-end d-flex flex-column" style="min-height: 700px;">
                            
                            <!-- Header Panel -->
                            <div class="p-3 border-bottom bg-light bg-opacity-50">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <h5 class="fw-bold mb-0 text-dark d-flex align-items-center">
                                            <i class="material-symbols-outlined text-primary fs-20 me-2">forum</i> Ruang Percakapan
                                        </h5>
                                        <small class="text-muted fs-11">Balmon Spektrum Frekuensi Radio Samarinda</small>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11 px-2 py-1">
                                        <?= count($officers) ?> Petugas
                                    </span>
                                </div>
                            </div>

                            <!-- Section 1: 4 Ruang Diskusi Tim Kerja -->
                            <div class="p-3 border-bottom">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fs-11 fw-bold text-uppercase text-muted d-flex align-items-center">
                                        <i class="material-symbols-outlined fs-14 me-1">groups</i> Ruang Diskusi Tim Kerja
                                    </span>
                                    <span class="badge bg-secondary-subtle text-secondary fs-10">4 Ruang Tim</span>
                                </div>

                                <div class="d-flex flex-column gap-2">
                                    
                                    <!-- 1. Semua Tim Kerja -->
                                    <a href="chat.php?room=all" class="room-card-link p-2 rounded-3 d-flex align-items-center <?= ($room === 'all') ? 'is-active' : '' ?>">
                                        <div class="rounded-3 d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 38px; height: 38px; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3);">
                                            <i class="material-symbols-outlined" style="color: #38bdf8; font-size: 20px;">groups</i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong class="fs-12 text-dark text-truncate">Semua Tim Kerja</strong>
                                                <span class="badge bg-info-subtle text-info fs-10 px-1 py-0"><?= count($officers) ?> Anggota</span>
                                            </div>
                                            <small class="text-muted d-block text-truncate fs-11">Forum koordinasi gabungan seluruh tim</small>
                                        </div>
                                    </a>

                                    <!-- 2. Tim Kerja Sarpras -->
                                    <a href="chat.php?room=sarpras" class="room-card-link p-2 rounded-3 d-flex align-items-center <?= ($room === 'sarpras') ? 'is-active-sarpras' : '' ?>">
                                        <div class="rounded-3 d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 38px; height: 38px; background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3);">
                                            <i class="material-symbols-outlined" style="color: #f59e0b; font-size: 20px;">construction</i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong class="fs-12 text-dark text-truncate">Tim Kerja Sarpras</strong>
                                                <span class="badge bg-warning-subtle text-warning fs-10 px-1 py-0"><?= count($sarprasOfficers) ?> Anggota</span>
                                            </div>
                                            <small class="text-muted d-block text-truncate fs-11">Sarana & prasarana, genset, armada, IT</small>
                                        </div>
                                    </a>

                                    <!-- 3. Tim Kerja Montib -->
                                    <a href="chat.php?room=montib" class="room-card-link p-2 rounded-3 d-flex align-items-center <?= ($room === 'montib') ? 'is-active-montib' : '' ?>">
                                        <div class="rounded-3 d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 38px; height: 38px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3);">
                                            <i class="material-symbols-outlined" style="color: #10b981; font-size: 20px;">radar</i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong class="fs-12 text-dark text-truncate">Tim Kerja Montib</strong>
                                                <span class="badge bg-success-subtle text-success fs-10 px-1 py-0"><?= count($montibOfficers) ?> Anggota</span>
                                            </div>
                                            <small class="text-muted d-block text-truncate fs-11">Monitoring & Penertiban SFR & APT</small>
                                        </div>
                                    </a>

                                    <!-- 4. Tim Kerja Kualid -->
                                    <a href="chat.php?room=kualid" class="room-card-link p-2 rounded-3 d-flex align-items-center <?= ($room === 'kualid') ? 'is-active-kualid' : '' ?>">
                                        <div class="rounded-3 d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 38px; height: 38px; background: rgba(168, 85, 247, 0.15); border: 1px solid rgba(168, 85, 247, 0.3);">
                                            <i class="material-symbols-outlined" style="color: #a855f7; font-size: 20px;">verified</i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong class="fs-12 text-dark text-truncate">Tim Kerja Kualid</strong>
                                                <span class="badge fs-10 px-1 py-0" style="background: rgba(168,85,247,0.15); color: #a855f7;"><?= count($kualidOfficers) ?> Anggota</span>
                                            </div>
                                            <small class="text-muted d-block text-truncate fs-11">Kualitas layanan & sertifikasi SMART</small>
                                        </div>
                                    </a>

                                </div>
                            </div>

                            <!-- Section 2: Pesan Langsung (Direct Chat) -->
                            <div class="p-3 flex-grow-1 d-flex flex-column overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fs-11 fw-bold text-uppercase text-muted d-flex align-items-center">
                                        <i class="material-symbols-outlined fs-14 me-1">chat</i> Pesan Langsung
                                    </span>
                                    <span class="fs-10 text-muted">Per Tim Kerja</span>
                                </div>

                                <!-- Filter Buttons per Tim Kerja -->
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    <button type="button" class="filter-team-pill active" onclick="setOfficerFilter('all', this)">Semua (<?= count($officers) ?>)</button>
                                    <button type="button" class="filter-team-pill" onclick="setOfficerFilter('montib', this)">Montib (<?= count($montibOfficers) ?>)</button>
                                    <button type="button" class="filter-team-pill" onclick="setOfficerFilter('sarpras', this)">Sarpras (<?= count($sarprasOfficers) ?>)</button>
                                    <button type="button" class="filter-team-pill" onclick="setOfficerFilter('kualid', this)">Kualid (<?= count($kualidOfficers) ?>)</button>
                                    <button type="button" class="filter-team-pill" onclick="setOfficerFilter('pimpinan', this)">Pimpinan (<?= count($pimpinanOfficers) ?>)</button>
                                </div>

                                <!-- Search Input Filter -->
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text bg-light border-end-0 py-1"><i class="fa fa-search text-muted fs-11"></i></span>
                                    <input type="text" id="officerSearchInput" class="form-control form-control-sm border-start-0" placeholder="Cari nama personil..." onkeyup="filterOfficersList()">
                                </div>

                                <!-- Officers Scrollable List -->
                                <div class="dlab-scroll flex-grow-1" id="officersListContainer" style="max-height: 320px; overflow-y: auto;">
                                    <ul class="list-unstyled mb-0" id="officersUl">
                                        <?php foreach ($officers as $off): ?>
                                            <?php
                                            $tkOff = $off['tim_kerja'] ?? '';
                                            $catTeam = 'montib';
                                            if (stripos($tkOff, 'Kepala Balai') !== false || ($off['role'] ?? '') === 'kepala_balai') {
                                                $catTeam = 'pimpinan';
                                            } elseif (stripos($tkOff, 'Sarana') !== false || stripos($tkOff, 'Sarpras') !== false) {
                                                $catTeam = 'sarpras';
                                            } elseif (stripos($tkOff, 'Kualitas') !== false || stripos($tkOff, 'Kualid') !== false) {
                                                $catTeam = 'kualid';
                                            }
                                            $isMe = ($off['id'] == $currentUser['id']);
                                            $isTargetActive = ($room === 'direct' && $targetUserId == $off['id']);
                                            ?>
                                            <li class="mb-1 officer-item-row" data-team="<?= $catTeam ?>" data-name="<?= strtolower(htmlspecialchars($off['name'] . ' ' . $off['jabatan'])) ?>">
                                                <a href="chat.php?user_id=<?= $off['id'] ?>&room=direct" class="officer-item-link d-flex align-items-center p-2 rounded-3 <?= $isTargetActive ? 'is-active' : '' ?>">
                                                    <div class="position-relative me-2 flex-shrink-0">
                                                        <img src="<?= htmlspecialchars($off['avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle" width="36" height="36" alt="">
                                                        <span class="position-absolute bottom-0 end-0 <?= $off['is_online'] ? 'online-dot-pulse' : 'offline-dot' ?>"></span>
                                                    </div>
                                                    <div class="flex-grow-1 overflow-hidden">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <strong class="fs-12 text-dark text-truncate d-block" style="max-width: 140px;" title="<?= htmlspecialchars($off['name']) ?>">
                                                                <?= htmlspecialchars($off['name']) ?>
                                                                <?= $isMe ? '<span class="text-primary fs-10">(Anda)</span>' : '' ?>
                                                            </strong>
                                                            <?= renderTeamBadge($off['tim_kerja'], true) ?>
                                                        </div>
                                                        <small class="text-muted d-block text-truncate fs-11" title="<?= htmlspecialchars($off['jabatan']) ?>">
                                                            <?= htmlspecialchars($off['jabatan']) ?>
                                                        </small>
                                                    </div>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Right Area: Chat Content, Messages, and Inputs -->
                        <div class="col-xl-8 col-lg-7 d-flex flex-column" style="min-height: 700px;">
                            
                            <!-- Chat Active Header -->
                            <div class="p-3 border-bottom bg-light bg-opacity-50 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center overflow-hidden">
                                    <?php if ($room !== 'direct'): ?>
                                        <!-- Room Header -->
                                        <div class="rounded-3 d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 44px; height: 44px; background: <?= htmlspecialchars($activeRoomDef['color']) ?>20; border: 1px solid <?= htmlspecialchars($activeRoomDef['color']) ?>50;">
                                            <i class="material-symbols-outlined" style="color: <?= htmlspecialchars($activeRoomDef['color']) ?>; font-size: 26px;">
                                                <?= htmlspecialchars($activeRoomDef['icon']) ?>
                                            </i>
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="d-flex align-items-center gap-2">
                                                <h6 class="fw-bold mb-0 text-truncate text-dark fs-14"><?= htmlspecialchars($activeRoomDef['title']) ?></h6>
                                                <span class="badge bg-secondary-subtle text-secondary fs-10"><?= htmlspecialchars($activeRoomDef['badge']) ?></span>
                                            </div>
                                            <small class="text-muted text-truncate d-block fs-11" title="<?= htmlspecialchars($activeRoomDef['desc']) ?>">
                                                <?= htmlspecialchars($activeRoomDef['desc']) ?>
                                            </small>
                                        </div>
                                    <?php elseif ($targetOfficer): ?>
                                        <!-- Direct Officer Header -->
                                        <div class="position-relative me-3 flex-shrink-0">
                                            <img src="<?= htmlspecialchars($targetOfficer['avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle" width="44" height="44" alt="">
                                            <span class="position-absolute bottom-0 end-0 <?= in_array($targetOfficer['id'], array_column(array_filter($officers, fn($o)=>$o['is_online']), 'id')) ? 'online-dot-pulse' : 'offline-dot' ?>"></span>
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="d-flex align-items-center gap-2">
                                                <h6 class="fw-bold mb-0 text-truncate text-dark fs-14" title="<?= htmlspecialchars($targetOfficer['name']) ?>"><?= htmlspecialchars($targetOfficer['name']) ?></h6>
                                                <?= renderTeamBadge($targetOfficer['tim_kerja']) ?>
                                            </div>
                                            <small class="text-muted d-block text-truncate fs-11" title="<?= htmlspecialchars($targetOfficer['jabatan']) ?> (NIP: <?= htmlspecialchars($targetOfficer['nip']) ?>)">
                                                <?= htmlspecialchars($targetOfficer['jabatan']) ?> • NIP: <?= htmlspecialchars($targetOfficer['nip']) ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <!-- Direct Chat Default (No Officer Selected) -->
                                        <div class="rounded-3 d-flex align-items-center justify-content-center me-3 flex-shrink-0 bg-primary bg-opacity-10 text-primary" style="width: 44px; height: 44px;">
                                            <i class="material-symbols-outlined fs-24">chat</i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark fs-14">Pesan Langsung (Direct Chat)</h6>
                                            <small class="text-muted fs-11">Pilih salah satu personil di sebelah kiri untuk memulai obrolan langsung</small>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <?php if ($room !== 'direct'): ?>
                                        <!-- Tombol Anggota Tim Resmi -->
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fs-11 d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#modalRoomMembers">
                                            <i class="material-symbols-outlined fs-14 me-1">group</i> Anggota (<?= count($activeRoomMembers) ?>)
                                        </button>
                                    <?php endif; ?>
                                    <span class="badge bg-light border text-muted fs-10 px-2 py-1 d-none d-md-inline-block">
                                        <i class="fa fa-shield-alt text-primary me-1"></i> Jalur Komunikasi Resmi Balmon
                                    </span>
                                </div>
                            </div>

                            <!-- Messages Scroll Container -->
                            <div class="p-3 p-md-4 flex-grow-1 dlab-scroll" id="mainChatContainer" style="height: 480px; overflow-y: auto;">
                                <?php if ($room === 'direct' && !$targetOfficer): ?>
                                    <!-- Welcome Screen for Direct Chat with Team Directory -->
                                    <div class="text-center py-4">
                                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary mb-3" style="width: 60px; height: 60px;">
                                            <i class="material-symbols-outlined fs-32">forum</i>
                                        </div>
                                        <h5 class="fw-bold text-dark">Pilih Personil Balmon untuk Memulai Pesan Langsung</h5>
                                        <p class="text-muted fs-12 mb-4" style="max-width: 480px; margin: 0 auto;">
                                            Silakan klik nama personil pada daftar di samping kiri, atau pilih langsung dari perwakilan masing-masing tim kerja di bawah ini:
                                        </p>

                                        <div class="row g-3 justify-content-center text-start">
                                            <!-- Pimpinan -->
                                            <div class="col-md-6 col-xl-3">
                                                <div class="card border h-100 shadow-none bg-light bg-opacity-25 p-2">
                                                    <span class="fs-11 fw-bold text-primary mb-2 d-block"><i class="material-symbols-outlined fs-12 align-middle">shield_person</i> Pimpinan Balai</span>
                                                    <?php foreach ($pimpinanOfficers as $p): ?>
                                                        <a href="chat.php?user_id=<?= $p['id'] ?>&room=direct" class="text-decoration-none d-flex align-items-center p-1 rounded hover-bg text-dark">
                                                            <img src="<?= htmlspecialchars($p['avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle me-2" width="28" height="28" alt="">
                                                            <div class="overflow-hidden">
                                                                <span class="fs-11 fw-bold d-block text-truncate"><?= htmlspecialchars($p['name']) ?></span>
                                                                <span class="fs-10 text-muted d-block text-truncate">Kepala Balai</span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <!-- Montib -->
                                            <div class="col-md-6 col-xl-3">
                                                <div class="card border h-100 shadow-none bg-light bg-opacity-25 p-2">
                                                    <span class="fs-11 fw-bold text-success mb-2 d-block"><i class="material-symbols-outlined fs-12 align-middle">radar</i> Tim Montib (<?= count($montibOfficers) ?>)</span>
                                                    <?php foreach (array_slice($montibOfficers, 0, 3) as $m): ?>
                                                        <a href="chat.php?user_id=<?= $m['id'] ?>&room=direct" class="text-decoration-none d-flex align-items-center p-1 rounded hover-bg text-dark mb-1">
                                                            <img src="<?= htmlspecialchars($m['avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle me-2" width="28" height="28" alt="">
                                                            <div class="overflow-hidden">
                                                                <span class="fs-11 fw-bold d-block text-truncate"><?= htmlspecialchars($m['name']) ?></span>
                                                                <span class="fs-10 text-muted d-block text-truncate"><?= htmlspecialchars($m['jabatan']) ?></span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <!-- Sarpras -->
                                            <div class="col-md-6 col-xl-3">
                                                <div class="card border h-100 shadow-none bg-light bg-opacity-25 p-2">
                                                    <span class="fs-11 fw-bold text-warning mb-2 d-block"><i class="material-symbols-outlined fs-12 align-middle">construction</i> Tim Sarpras (<?= count($sarprasOfficers) ?>)</span>
                                                    <?php foreach (array_slice($sarprasOfficers, 0, 3) as $s): ?>
                                                        <a href="chat.php?user_id=<?= $s['id'] ?>&room=direct" class="text-decoration-none d-flex align-items-center p-1 rounded hover-bg text-dark mb-1">
                                                            <img src="<?= htmlspecialchars($s['avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle me-2" width="28" height="28" alt="">
                                                            <div class="overflow-hidden">
                                                                <span class="fs-11 fw-bold d-block text-truncate"><?= htmlspecialchars($s['name']) ?></span>
                                                                <span class="fs-10 text-muted d-block text-truncate"><?= htmlspecialchars($s['jabatan']) ?></span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <!-- Kualid -->
                                            <div class="col-md-6 col-xl-3">
                                                <div class="card border h-100 shadow-none bg-light bg-opacity-25 p-2">
                                                    <span class="fs-11 fw-bold text-purple mb-2 d-block" style="color: #a855f7;"><i class="material-symbols-outlined fs-12 align-middle">verified</i> Tim Kualid (<?= count($kualidOfficers) ?>)</span>
                                                    <?php foreach (array_slice($kualidOfficers, 0, 3) as $k): ?>
                                                        <a href="chat.php?user_id=<?= $k['id'] ?>&room=direct" class="text-decoration-none d-flex align-items-center p-1 rounded hover-bg text-dark mb-1">
                                                            <img src="<?= htmlspecialchars($k['avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle me-2" width="28" height="28" alt="">
                                                            <div class="overflow-hidden">
                                                                <span class="fs-11 fw-bold d-block text-truncate"><?= htmlspecialchars($k['name']) ?></span>
                                                                <span class="fs-10 text-muted d-block text-truncate"><?= htmlspecialchars($k['jabatan']) ?></span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5 text-muted">
                                        <i class="fa fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
                                        <p class="fs-13">Memuat riwayat percakapan...</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Chat Input Form -->
                            <?php if ($room !== 'direct' || $targetOfficer): ?>
                                <div class="p-3 border-top bg-light bg-opacity-50">
                                    <form id="mainChatForm" onsubmit="sendMainChatMessage(event)">
                                        <div class="input-group">
                                            <?php
                                            $placeholder = 'Ketik pesan koordinasi...';
                                            if ($room === 'all') {
                                                $placeholder = 'Ketik pesan koordinasi untuk seluruh Tim Kerja Balmon...';
                                            } elseif ($room === 'sarpras') {
                                                $placeholder = 'Ketik pesan koordinasi untuk Tim Kerja Sarpras...';
                                            } elseif ($room === 'montib') {
                                                $placeholder = 'Ketik pesan koordinasi untuk Tim Kerja Montib SFR & APT...';
                                            } elseif ($room === 'kualid') {
                                                $placeholder = 'Ketik pesan koordinasi untuk Tim Kerja Kualid...';
                                            } elseif ($targetOfficer) {
                                                $placeholder = 'Ketik pesan langsung ke ' . htmlspecialchars($targetOfficer['name']) . '...';
                                            }
                                            ?>
                                            <input type="text" id="mainChatInput" class="form-control" placeholder="<?= $placeholder ?>" required autocomplete="off">
                                            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm d-flex align-items-center">
                                                <i class="fa-solid fa-paper-plane me-2"></i> Kirim
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Anggota Tim Resmi -->
<?php if ($room !== 'direct'): ?>
<div class="modal fade" id="modalRoomMembers" tabindex="-1" aria-labelledby="modalRoomMembersLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px; background: <?= htmlspecialchars($activeRoomDef['color']) ?>20; border: 1px solid <?= htmlspecialchars($activeRoomDef['color']) ?>40;">
                        <i class="material-symbols-outlined" style="color: <?= htmlspecialchars($activeRoomDef['color']) ?>; font-size: 20px;">
                            <?= htmlspecialchars($activeRoomDef['icon']) ?>
                        </i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 text-dark" id="modalRoomMembersLabel">
                            Daftar Anggota: <?= htmlspecialchars($activeRoomDef['title']) ?>
                        </h6>
                        <small class="text-muted fs-11">Total <?= count($activeRoomMembers) ?> Personel terdaftar di ruang koordinasi ini</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2">
                    <?php foreach ($activeRoomMembers as $m): ?>
                        <div class="col-md-6">
                            <div class="card border h-100 shadow-none mb-0 p-2 d-flex flex-row align-items-center justify-content-between">
                                <div class="d-flex align-items-center overflow-hidden me-2">
                                    <div class="position-relative me-2 flex-shrink-0">
                                        <img src="<?= htmlspecialchars($m['avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle" width="40" height="40" alt="">
                                        <span class="position-absolute bottom-0 end-0 <?= $m['is_online'] ? 'online-dot-pulse' : 'offline-dot' ?>"></span>
                                    </div>
                                    <div class="overflow-hidden">
                                        <strong class="fs-12 text-dark text-truncate d-block" title="<?= htmlspecialchars($m['name']) ?>">
                                            <?= htmlspecialchars($m['name']) ?>
                                        </strong>
                                        <small class="text-muted d-block text-truncate fs-11" title="<?= htmlspecialchars($m['jabatan']) ?>">
                                            <?= htmlspecialchars($m['jabatan']) ?>
                                        </small>
                                        <div class="mt-1">
                                            <?= renderTeamBadge($m['tim_kerja'], true) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($m['id'] != $currentUser['id']): ?>
                                    <a href="chat.php?user_id=<?= $m['id'] ?>&room=direct" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1 fs-11 flex-shrink-0" title="Kirim Pesan Langsung">
                                        <i class="material-symbols-outlined fs-12 align-middle">chat</i> Chat
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted fs-10 flex-shrink-0">Anda</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
const currentRoom = '<?= $room ?>';
const targetUserId = <?= $targetUserId ? (int)$targetUserId : 'null' ?>;
const myUserId = <?= (int)$currentUser['id'] ?>;
let lastMessageCount = 0;
let currentOfficerFilter = 'all';

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, function(m) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m];
    });
}

function renderSenderTeamBadge(timKerja) {
    if (!timKerja) return '';
    let tk = timKerja.toLowerCase();
    if (tk.indexOf('montib') !== -1) {
        return '<span class="badge ms-1 px-1 py-0 fs-10" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">Tim Montib</span>';
    } else if (tk.indexOf('sarana') !== -1 || tk.indexOf('sarpras') !== -1) {
        return '<span class="badge ms-1 px-1 py-0 fs-10" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3);">Tim Sarpras</span>';
    } else if (tk.indexOf('kualitas') !== -1 || tk.indexOf('kualid') !== -1) {
        return '<span class="badge ms-1 px-1 py-0 fs-10" style="background: rgba(168, 85, 247, 0.15); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3);">Tim Kualid</span>';
    } else if (tk.indexOf('kepala') !== -1) {
        return '<span class="badge ms-1 px-1 py-0 fs-10" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3);">Kepala Balai</span>';
    }
    return '';
}

function fetchChatMessages() {
    if (currentRoom === 'direct' && !targetUserId) {
        return; // No officer selected yet
    }

    let url = 'api/chat.php?limit=100';
    if (currentRoom !== 'direct') {
        url += '&room=' + encodeURIComponent(currentRoom);
    } else if (targetUserId) {
        url += '&user_id=' + targetUserId;
    }

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.messages) {
                renderMessages(data.messages);
            }
        })
        .catch(err => console.log('Chat fetch error:', err));
}

function renderMessages(messages) {
    const container = document.getElementById('mainChatContainer');
    if (!container) return;

    if (messages.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="fa-regular fa-comment-dots fa-3x mb-3 text-muted"></i>
                <h6 class="fw-bold">Belum ada percakapan di ruang ini.</h6>
                <p class="fs-12 text-muted">Mulai diskusi atau koordinasi kegiatan monitoring sekarang.</p>
            </div>
        `;
        return;
    }

    let html = '';
    messages.forEach(msg => {
        const isMe = msg.sender_id === myUserId;
        html += `
            <div class="d-flex justify-content-${isMe ? 'end' : 'start'} mb-3">
                ${!isMe ? `
                    <div class="me-2 flex-shrink-0">
                        <img src="${msg.avatar || 'assets/images/avatar/1.jpg'}" class="rounded-circle" width="34" height="34" alt="">
                    </div>
                ` : ''}
                <div style="max-width: 72%;">
                    ${!isMe ? `
                        <div class="d-flex align-items-center mb-1">
                            <span class="fw-bold fs-11 text-dark">${escapeHtml(msg.sender_name)}</span>
                            ${renderSenderTeamBadge(msg.sender_tim_kerja)}
                        </div>
                    ` : ''}
                    <div class="p-3 shadow-sm ${isMe ? 'chat-bubble-me' : 'chat-bubble-other'}">
                        <div class="fs-13" style="word-break: break-word; line-height: 1.5;">${escapeHtml(msg.message)}</div>
                    </div>
                    <small class="fs-10 text-muted d-block mt-1 ${isMe ? 'text-end' : 'text-start'}">
                        ${msg.time_formatted || ''}
                    </small>
                </div>
            </div>
        `;
    });

    const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;
    container.innerHTML = html;

    if (messages.length !== lastMessageCount || isAtBottom) {
        container.scrollTop = container.scrollHeight;
        lastMessageCount = messages.length;
    }
}

function sendMainChatMessage(e) {
    e.preventDefault();
    const input = document.getElementById('mainChatInput');
    const msg = input.value.trim();
    if (!msg) return;

    const payload = {
        is_team: (currentRoom !== 'direct') ? 1 : 0,
        room_key: currentRoom,
        receiver_id: targetUserId,
        message: msg
    };

    fetch('api/chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            input.value = '';
            fetchChatMessages();
        } else {
            alert(res.message || 'Gagal mengirim pesan.');
        }
    })
    .catch(() => alert('Gagal mengirim pesan. Periksa koneksi Anda.'));
}

// Client-side filtering of officers in Direct Chat section
function setOfficerFilter(team, btnEl) {
    currentOfficerFilter = team;
    document.querySelectorAll('.filter-team-pill').forEach(el => el.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');
    filterOfficersList();
}

function filterOfficersList() {
    const searchVal = (document.getElementById('officerSearchInput').value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.officer-item-row');

    rows.forEach(row => {
        const rowTeam = row.getAttribute('data-team');
        const rowName = row.getAttribute('data-name');

        const matchTeam = (currentOfficerFilter === 'all') || (rowTeam === currentOfficerFilter);
        const matchSearch = (!searchVal) || (rowName.indexOf(searchVal) !== -1);

        if (matchTeam && matchSearch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Initial fetch and auto-refresh every 3 seconds
document.addEventListener('DOMContentLoaded', function() {
    fetchChatMessages();
    setInterval(fetchChatMessages, 3000);
});
</script>
