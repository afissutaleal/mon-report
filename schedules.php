<?php
// schedules.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();
$pageTitle = "Rekapitulasi & Jadwal Monitoring";

$msgSuccess = '';
$msgError = '';

// Handle Schedule Update (termasuk Tanggal Keluar SPT)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_schedule') {
    $schedId = (int)($_POST['id'] ?? 0);
    $sptNumber = trim($_POST['spt_number'] ?? '');
    $sptDate = !empty($_POST['spt_date']) ? trim($_POST['spt_date']) : null;
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $targetObject = trim($_POST['target_object'] ?? '');
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $status = $_POST['status'] ?? 'scheduled';
    $notes = trim($_POST['notes'] ?? '');

    if ($schedId > 0 && !empty($sptNumber) && !empty($title)) {
        try {
            $stmtUp = $pdo->prepare("
                UPDATE monitoring_schedules 
                SET spt_number = ?, spt_date = ?, title = ?, location = ?,
                    target_object = ?, start_date = ?, end_date = ?, status = ?, notes = ?
                WHERE id = ?
            ");
            $stmtUp->execute([
                $sptNumber, $sptDate, $title, $location,
                $targetObject, $startDate, $endDate, $status, $notes, $schedId
            ]);
            header("Location: schedules.php?updated=1");
            exit;
        } catch (Exception $e) {
            $msgError = "Gagal memperbarui jadwal: " . $e->getMessage();
        }
    } else {
        $msgError = "Nomor SPT dan Judul Kegiatan wajib diisi.";
    }
}

if (isset($_GET['updated'])) {
    $msgSuccess = "Data Surat Perintah Tugas (SPT) dan Tanggal Keluar berhasil diperbarui.";
}
if (isset($_GET['created'])) {
    $msgSuccess = "Agenda Surat Perintah Tugas (SPT) baru berhasil diterbitkan.";
}

// Categories dictionary for multi-category resolving
$categories = $pdo->query("SELECT id, name, short_title, badge_color, icon FROM monitoring_categories ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$categoriesById = [];
foreach ($categories as $c) {
    $categoriesById[$c['id']] = $c;
}

// Fetch all official monitoring schedules
$stmt = $pdo->query("
    SELECT s.*,
           u.name as leader_name, u.avatar as leader_avatar, u.nip as leader_nip, u.jabatan as leader_jabatan
    FROM monitoring_schedules s
    LEFT JOIN users u ON s.leader_id = u.id
    ORDER BY 
        CASE 
            WHEN s.status = 'in_progress' THEN 1
            WHEN s.status = 'scheduled' THEN 2
            ELSE 3
        END,
        s.start_date DESC
");
$rawSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
$schedules = [];
foreach ($rawSchedules as $sch) {
    $rawCatIds = json_decode($sch['category_ids'] ?? '[]', true);
    if (empty($rawCatIds) && !empty($sch['category_id'])) {
        $rawCatIds = [(int)$sch['category_id']];
    }
    $sch['cat_ids'] = $rawCatIds ?: [1];
    $sch['categories_list'] = [];
    $catTitles = [];
    foreach ($sch['cat_ids'] as $cid) {
        if (isset($categoriesById[$cid])) {
            $sch['categories_list'][] = $categoriesById[$cid];
            $catTitles[] = $categoriesById[$cid]['short_title'];
        }
    }
    if (empty($sch['categories_list'])) {
        $sch['categories_list'][] = [
            'id' => 1, 'name' => 'Monitoring SFR', 'short_title' => 'Monitoring SFR',
            'badge_color' => 'primary', 'icon' => 'fa-tower-broadcast'
        ];
        $catTitles[] = 'Monitoring SFR';
    }
    $primaryCat = $sch['categories_list'][0];
    $sch['category_id'] = $primaryCat['id'];
    $sch['category_name'] = implode(', ', $catTitles);
    $sch['short_title'] = $primaryCat['short_title'];
    $sch['badge_color'] = $primaryCat['badge_color'];
    $sch['category_icon'] = $primaryCat['icon'];
    $sch['category_names_str'] = implode(' / ', $catTitles);
    $schedules[] = $sch;
}

// KPI Stats
$totalCount = count($schedules);
$inProgressCount = count(array_filter($schedules, fn($s) => $s['status'] === 'in_progress'));
$scheduledCount = count(array_filter($schedules, fn($s) => $s['status'] === 'scheduled'));
$completedCount = count(array_filter($schedules, fn($s) => $s['status'] === 'completed'));

// Officers for filter
$officersList = $pdo->query("SELECT id, name FROM users ORDER BY name ASC")->fetchAll();

// Month names in Indonesian
$bulanIndo = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav_header.php';
require_once __DIR__ . '/includes/chatbox.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
/* Scoped Styling for Rekap Jadwal & SPT Monitoring */
.sched-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.sched-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
}

.table-spt thead tr.header-title-row th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background-color: #c7f9cc !important; /* Soft pastel green sesuai screenshot */
    color: #14532d !important;
    border: 1px solid #a7f3d0 !important;
    border-bottom: 2px solid #86efac !important;
    padding: 10px 12px;
    vertical-align: middle;
    white-space: nowrap;
}
[data-theme-version="dark"] .table-spt thead tr.header-title-row th {
    background-color: #064e3b !important;
    color: #a7f3d0 !important;
    border-color: #047857 !important;
    border-bottom-color: #059669 !important;
}

.table-spt thead tr.header-filter-row th {
    background-color: #f0fdf4 !important;
    border: 1px solid #e2e8f0 !important;
    border-bottom: 2px solid #cbd5e1 !important;
    padding: 6px 6px !important;
    vertical-align: middle;
}
[data-theme-version="dark"] .table-spt thead tr.header-filter-row th {
    background-color: rgba(6, 78, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    border-bottom-color: rgba(255, 255, 255, 0.15) !important;
}

.col-search-box {
    font-size: 11px !important;
    font-weight: 500 !important;
    height: 28px !important;
    border-radius: 4px !important;
    border: 1px solid #cbd5e1 !important;
    background-color: #ffffff !important;
    color: #1e293b !important;
    box-shadow: none !important;
    width: 100%;
}
input.col-search-box {
    padding: 3px 8px !important;
}
[data-theme-version="dark"] .col-search-box {
    background-color: #1e293b !important;
    border-color: #475569 !important;
    color: #f1f5f9 !important;
}
.col-search-box:focus {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25) !important;
    outline: none !important;
}

select.col-search-box {
    padding: 2px 24px 2px 8px !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23475569' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 8px center !important;
    background-size: 9px 7px !important;
    cursor: pointer !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    line-height: 22px !important;
}

select.col-search-box.text-center {
    text-align: center !important;
    text-align-last: center !important;
    padding-left: 18px !important;
    padding-right: 22px !important;
}

[data-theme-version="dark"] select.col-search-box {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    background-color: #1e293b !important;
    border-color: #475569 !important;
    color: #f1f5f9 !important;
}

select.col-search-box optgroup {
    font-size: 11px !important;
    font-weight: 700 !important;
    color: #475569 !important;
    background-color: #f8fafc !important;
    padding: 4px 6px !important;
}

[data-theme-version="dark"] select.col-search-box optgroup {
    color: #94a3b8 !important;
    background-color: #0f172a !important;
}

select.col-search-box option {
    font-size: 11px !important;
    font-weight: 500 !important;
    padding: 5px 8px !important;
    color: #1e293b !important;
    background-color: #ffffff !important;
}

[data-theme-version="dark"] select.col-search-box option {
    color: #f1f5f9 !important;
    background-color: #1e293b !important;
}

/* Eradicate any bootstrap-select wrapper artifact on table search boxes */
.header-filter-row .bootstrap-select,
.header-filter-row .dropdown.bootstrap-select,
.table-spt .bootstrap-select,
.table-spt .dropdown.bootstrap-select {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    width: 0 !important;
    height: 0 !important;
    pointer-events: none !important;
}

select.col-search-box {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: static !important;
}

.table-spt td {
    padding: 12px 14px;
    vertical-align: middle;
    font-size: 12px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}
[data-theme-version="dark"] .table-spt td {
    border-bottom-color: rgba(255, 255, 255, 0.06);
}

.table-spt tbody tr {
    transition: background-color 0.15s ease;
}
.table-spt tbody tr:hover {
    background-color: rgba(2, 132, 199, 0.04) !important;
}
[data-theme-version="dark"] .table-spt tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.04) !important;
}

.spt-code-badge {
    font-family: var(--bs-font-monospace, monospace);
    font-size: 11px;
    font-weight: 600;
    background: rgba(2, 132, 199, 0.1);
    color: #0284c7;
    border: 1px solid rgba(2, 132, 199, 0.25);
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-block;
}

.view-btn-toggle.active {
    background: #0284c7 !important;
    color: #ffffff !important;
    border-color: #0284c7 !important;
    box-shadow: 0 2px 8px rgba(2, 132, 199, 0.3) !important;
}

/* Compact column for Uraian Tugas & Target Objek with text wrapping */
.col-task-desc {
    width: 220px !important;
    max-width: 230px !important;
    min-width: 175px !important;
    white-space: normal !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
}
.col-task-desc strong,
.col-task-desc small {
    white-space: normal !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
}


/* Print Specific Styling */
@media print {
    .nav-header, .header, .dlabnav, .footer, .btn, .card-header-actions, .filter-toolbar, .kpi-stats-row, .pagination-row {
        display: none !important;
    }
    .content-body {
        margin: 0 !important;
        padding: 0 !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .table-spt {
        font-size: 10px !important;
        width: 100% !important;
    }
    .table-spt th, .table-spt td {
        padding: 6px 8px !important;
    }
    #schedulesCardsView {
        display: none !important;
    }
    #schedulesTableView {
        display: block !important;
    }
}
</style>

<div class="content-body">
    <div class="container-fluid">
        <!-- Breadcrumb Header -->
        <div class="row page-titles mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="javascript:void(0)">Agenda & SPT</a></li>
                <li class="breadcrumb-item active"><a href="javascript:void(0)">Rekapitulasi Monitoring</a></li>
            </ol>
        </div>

        <?php if (!empty($msgSuccess)): ?>
            <div class="alert alert-success alert-dismissible fade show py-2.5 px-3 fs-13 mb-3 d-flex align-items-center" role="alert">
                <i class="material-symbols-outlined fs-20 me-2 text-success">check_circle</i>
                <div><?= htmlspecialchars($msgSuccess) ?></div>
                <button type="button" class="btn-close ms-auto p-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($msgError)): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2.5 px-3 fs-13 mb-3 d-flex align-items-center" role="alert">
                <i class="material-symbols-outlined fs-20 me-2 text-danger">error</i>
                <div><?= htmlspecialchars($msgError) ?></div>
                <button type="button" class="btn-close ms-auto p-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header & Action Controls -->
        <div class="row mb-3 align-items-center">
            <div class="col-lg-7 col-md-12 mb-2 mb-lg-0">
                <h4 class="fw-bold mb-1 d-flex align-items-center text-dark">
                    <i class="material-symbols-outlined text-primary fs-28 me-2">calendar_clock</i> 
                    Rekapitulasi & Jadwal Monitoring (Surat Perintah Tugas)
                </h4>
                <p class="text-muted fs-12 mb-0">Jadwal kegiatan monitoring Balai Monitor Kelas I Samarinda</p>
            </div>
            <div class="col-lg-5 col-md-12 text-lg-end card-header-actions">
                <div class="d-inline-flex flex-wrap gap-2 align-items-center">
                    <!-- Dual View Mode Toggle -->
                    <div class="btn-group btn-group-sm p-1 rounded-pill bg-light border shadow-none" role="group">
                        <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-bold view-btn-toggle active" id="btnViewTable" onclick="switchView('table')">
                            <i class="material-symbols-outlined fs-15 align-middle me-1">table_rows</i> Tabel Rekap
                        </button>
                        <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-bold view-btn-toggle" id="btnViewCards" onclick="switchView('cards')">
                            <i class="material-symbols-outlined fs-15 align-middle me-1">grid_view</i> Grid Kartu
                        </button>
                    </div>


                    <!-- Tambah Agenda SPT -->
                    <a href="schedule_add.php" class="btn btn-primary btn-sm rounded-pill shadow-sm px-3">
                        <i class="fa fa-plus-circle me-1"></i> Tambah SPT
                    </a>
                </div>
            </div>
        </div>

        <!-- Top KPI Stats Row -->
        <div class="row g-3 mb-4 kpi-stats-row">
            <!-- 1. Total SPT -->
            <div class="col-xl-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100 mb-0" style="border-radius: 14px; background: rgba(2, 132, 199, 0.08); border: 1px solid rgba(2, 132, 199, 0.25) !important;">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-11 fw-bold text-uppercase d-block mb-1" style="color: #0284c7; letter-spacing: 0.5px;">TOTAL SPT MONITORING</span>
                            <div class="d-flex align-items-baseline gap-2">
                                <h3 class="fw-bold mb-0" style="font-size: 28px; line-height: 1.1; font-weight: 800; color: #0284c7;"><?= $totalCount ?></h3>
                                <span class="fs-12 text-muted fw-semibold">Kegiatan</span>
                            </div>
                        </div>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px; background: #0284c7; color: #fff; box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3);">
                            <i class="material-symbols-outlined fs-24">assignment</i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Terjadwal / Aktif -->
            <div class="col-xl-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100 mb-0" style="border-radius: 14px; background: rgba(14, 165, 233, 0.08); border: 1px solid rgba(14, 165, 233, 0.25) !important;">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-11 fw-bold text-uppercase d-block mb-1" style="color: #0ea5e9; letter-spacing: 0.5px;">AGENDA TERJADWAL</span>
                            <div class="d-flex align-items-baseline gap-2">
                                <h3 class="fw-bold mb-0" style="font-size: 28px; line-height: 1.1; font-weight: 800; color: #0ea5e9;"><?= $scheduledCount ?></h3>
                                <span class="fs-12 text-muted fw-semibold">Mendatang</span>
                            </div>
                        </div>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px; background: #0ea5e9; color: #fff; box-shadow: 0 4px 10px rgba(14, 165, 233, 0.3);">
                            <i class="material-symbols-outlined fs-24">schedule</i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Telah Selesai -->
            <div class="col-xl-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100 mb-0" style="border-radius: 14px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25) !important;">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-11 fw-bold text-uppercase d-block mb-1" style="color: #10b981; letter-spacing: 0.5px;">KEGIATAN SELESAI</span>
                            <div class="d-flex align-items-baseline gap-2">
                                <h3 class="fw-bold mb-0" style="font-size: 28px; line-height: 1.1; font-weight: 800; color: #10b981;"><?= $completedCount ?></h3>
                                <span class="fs-12 text-muted fw-semibold">Rampung</span>
                            </div>
                        </div>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px; background: #10b981; color: #fff; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">
                            <i class="material-symbols-outlined fs-24">verified</i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Cakupan Wilayah Kerja -->
            <div class="col-xl-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100 mb-0" style="border-radius: 14px; background: rgba(168, 85, 247, 0.08); border: 1px solid rgba(168, 85, 247, 0.25) !important;">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-11 fw-bold text-uppercase d-block mb-1" style="color: #a855f7; letter-spacing: 0.5px;">CAKUPAN WILAYAH</span>
                            <div class="d-flex align-items-baseline gap-2">
                                <h3 class="fw-bold mb-0" style="font-size: 28px; line-height: 1.1; font-weight: 800; color: #a855f7;">10</h3>
                                <span class="fs-12 text-muted fw-semibold">Kab / Kota</span>
                            </div>
                        </div>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px; background: #a855f7; color: #fff; box-shadow: 0 4px 10px rgba(168, 85, 247, 0.3);">
                            <i class="material-symbols-outlined fs-24">map</i>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- TAMPILAN 1: TABEL REKAPITULASI (DEFAULT VIEW) -->
        <div id="schedulesTableView" class="card border-0 shadow-sm mb-4" style="border-radius: 16px; overflow: hidden;">
            <div class="table-responsive">
                <table class="table table-spt table-hover align-middle mb-0" id="mainSchedulesTable">
                    <thead>
                        <!-- Baris 1: Judul Kolom (Sesuai Referensi Gambar) -->
                        <tr class="header-title-row">
                            <th class="text-center" style="width: 45px;">No.</th>
                            <th style="min-width: 175px;">Nomor & Tgl Keluar SPT</th>
                            <th class="col-task-desc">Uraian Tugas & Target Objek</th>
                            <th style="min-width: 190px;">Jenis Kegiatan</th>
                            <th style="min-width: 185px;">Kota / Wilayah</th>
                            <th style="min-width: 140px;">Periode Tugas</th>
                            <th style="min-width: 190px;">Tim Pelaksana</th>
                            <th class="text-center" style="min-width: 130px;">Status</th>
                            <th class="text-center" style="width: 80px;">Aksi</th>
                        </tr>
                        <!-- Baris 2: Pencarian Langsung di Atas Kolom Data (Sesuai Referensi Gambar) -->
                        <tr class="header-filter-row">
                            <th class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-1" title="Pencarian & Filter Langsung Per Kolom">
                                    <i class="fa fa-search text-success fs-12"></i>
                                    <i class="fa fa-filter text-info fs-11"></i>
                                </div>
                            </th>
                            <th>
                                <input type="text" id="colFilterSpt" class="col-search-box" placeholder="Cari SPT / Tgl Keluar..." onkeyup="applyFilters()">
                            </th>
                            <th class="col-task-desc">
                                <input type="text" id="colFilterTitle" class="col-search-box" placeholder="Cari Uraian / Target..." onkeyup="applyFilters()">
                            </th>
                            <th>
                                <select id="colFilterCat" class="col-search-box form-select no-bootstrap-select" onchange="applyFilters()">
                                    <option value="">Semua Kegiatan</option>
                                    <optgroup label="Monitoring Spektrum (SFR)">
                                        <option value="1">Monitoring SFR</option>
                                        <option value="5">Monitoring HF</option>
                                        <option value="2">Microwavelink</option>
                                        <option value="3">Radio Siaran FM</option>
                                        <option value="4">TV Digital</option>
                                        <option value="9">Monitoring Lainnya</option>
                                    </optgroup>
                                    <optgroup label="Gangguan & Kualitas Layanan">
                                        <option value="10">Penanganan Gangguan</option>
                                        <option value="11">Kualitas Layanan (QoS)</option>
                                    </optgroup>
                                    <optgroup label="Monitoring Perangkat (APT)">
                                        <option value="6">Monitoring APT</option>
                                    </optgroup>
                                    <optgroup label="Penertiban">
                                        <option value="7">Penertiban SFR</option>
                                        <option value="8">Penertiban APT</option>
                                    </optgroup>
                                </select>
                            </th>
                            <th>
                                <select id="colFilterLoc" class="col-search-box form-select no-bootstrap-select" onchange="applyFilters()">
                                    <option value="">Semua Wilayah</option>
                                    <optgroup label="Kota">
                                        <option value="Balikpapan">Kota Balikpapan</option>
                                        <option value="Bontang">Kota Bontang</option>
                                        <option value="Samarinda">Kota Samarinda</option>
                                    </optgroup>
                                    <optgroup label="Kabupaten">
                                        <option value="Berau">Kabupaten Berau</option>
                                        <option value="Kutai Barat">Kabupaten Kutai Barat</option>
                                        <option value="Kutai Kartanegara">Kabupaten Kutai Kartanegara</option>
                                        <option value="Kutai Timur">Kabupaten Kutai Timur</option>
                                        <option value="Mahakam Ulu">Kabupaten Mahakam Ulu</option>
                                        <option value="Paser">Kabupaten Paser</option>
                                        <option value="Penajam">Kabupaten Penajam Paser Utara</option>
                                    </optgroup>
                                </select>
                            </th>
                            <th>
                                <input type="text" id="colFilterDates" class="col-search-box" placeholder="Tgl / Bulan..." onkeyup="applyFilters()">
                            </th>
                            <th>
                                <input type="text" id="colFilterTeam" class="col-search-box" placeholder="Cari Petugas..." onkeyup="applyFilters()">
                            </th>
                            <th class="text-center">
                                <select id="colFilterStatus" class="col-search-box form-select no-bootstrap-select text-center" onchange="applyFilters()">
                                    <option value="">Semua Status</option>
                                    <option value="in_progress">Berjalan</option>
                                    <option value="scheduled">Terjadwal</option>
                                    <option value="completed">Selesai</option>
                                </select>
                            </th>
                            <th class="text-center">
                                <button type="button" class="btn btn-xs btn-outline-danger w-100 py-1" onclick="resetColumnFilters()" title="Bersihkan Filter Kolom">
                                    <i class="fa fa-times"></i>
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="schedulesTableBody">
                        <?php $no = 1; foreach ($schedules as $sch): ?>
                            <?php
                            $sDate = strtotime($sch['start_date']);
                            $eDate = strtotime($sch['end_date']);
                            $monthKey = date('Y-m', $sDate);
                            $sptDateFormatted = !empty($sch['spt_date']) ? date('d M Y', strtotime($sch['spt_date'])) : '';
                            $allCatNames = implode(' ', array_column($sch['categories_list'], 'name')) . ' ' . implode(' ', array_column($sch['categories_list'], 'short_title'));
                            $searchContent = strtolower(htmlspecialchars(
                                $sch['spt_number'] . ' ' . $sptDateFormatted . ' ' . $sch['title'] . ' ' . $sch['location'] . ' ' .
                                $sch['target_object'] . ' ' . $sch['leader_name'] . ' ' . $sch['member_names'] . ' ' . $allCatNames
                            ));
                            ?>
                            <tr class="sched-item-row"
                                data-cat="<?= $sch['category_id'] ?>"
                                data-cats='<?= json_encode($sch['cat_ids']) ?>'
                                data-spt="<?= htmlspecialchars(strtolower($sch['spt_number'] . ' ' . $sptDateFormatted)) ?>"
                                data-spt-date="<?= htmlspecialchars($sch['spt_date'] ?? '') ?>"
                                data-title="<?= htmlspecialchars(strtolower($sch['title'] . ' ' . $sch['target_object'])) ?>"
                                data-location="<?= htmlspecialchars(strtolower($sch['location'])) ?>"
                                data-dates="<?= htmlspecialchars(strtolower(date('d M Y', $sDate) . ' ' . date('d M Y', $eDate) . ' ' . $sptDateFormatted . ' ' . $monthKey)) ?>"
                                data-team="<?= htmlspecialchars(strtolower($sch['leader_name'] . ' ' . $sch['member_names'])) ?>"
                                data-month="<?= $monthKey ?>"
                                data-status="<?= $sch['status'] ?>"
                                data-search="<?= $searchContent ?>"
                                data-json='<?= htmlspecialchars(json_encode($sch), ENT_QUOTES, 'UTF-8') ?>'>
                                
                                <td class="text-center text-muted fw-bold row-index"><?= $no++ ?></td>
                                
                                <td>
                                    <span class="spt-code-badge mb-1"><?= htmlspecialchars($sch['spt_number']) ?></span>
                                    <div class="d-flex align-items-center gap-1 mt-1 text-nowrap">
                                        <span class="badge bg-light border text-primary px-1.5 py-0.5 fs-10 fw-semibold d-inline-flex align-items-center" title="Tanggal Keluar / Terbit SPT">
                                            <i class="material-symbols-outlined fs-12 text-primary me-1">event_available</i>
                                            Keluar: <?= !empty($sch['spt_date']) ? date('d M Y', strtotime($sch['spt_date'])) : '-' ?>
                                        </span>
                                    </div>
                                    <small class="text-muted d-block fs-10 mt-0.5">Klasifikasi: SP.03.03 / KU.01.06</small>
                                </td>

                                <td class="col-task-desc">
                                    <strong class="d-block text-dark fs-12 mb-1" style="line-height: 1.4;">
                                        <?= htmlspecialchars($sch['title']) ?>
                                    </strong>
                                    <?php if (!empty($sch['target_object'])): ?>
                                        <small class="text-muted d-block mt-1" style="line-height: 1.35;" title="<?= htmlspecialchars($sch['target_object']) ?>">
                                            <i class="fa fa-bullseye text-primary me-1"></i> Target: <?= htmlspecialchars($sch['target_object']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="d-flex flex-wrap gap-1 align-items-center">
                                        <?php foreach ($sch['categories_list'] as $cItem): ?>
                                            <span class="badge badge-<?= $cItem['badge_color'] ?> light fs-11 px-2 py-1 d-inline-flex align-items-center" style="line-height: 1.25;">
                                                <i class="fa-solid <?= $cItem['icon'] ?> me-1"></i> <?= htmlspecialchars($cItem['short_title']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge bg-light border text-dark fs-11 px-2 py-1 d-inline-flex align-items-center">
                                        <i class="fa fa-map-pin text-danger me-1"></i> <?= htmlspecialchars($sch['location']) ?>
                                    </span>
                                </td>

                                <td>
                                    <strong class="d-block text-dark fs-11 text-nowrap"><?= date('d M Y', $sDate) ?></strong>
                                    <small class="text-muted d-block text-nowrap">s.d. <?= date('d M Y', $eDate) ?></small>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center overflow-hidden mb-1">
                                        <img src="<?= htmlspecialchars($sch['leader_avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle me-2 flex-shrink-0" width="24" height="24" alt="">
                                        <div class="overflow-hidden">
                                            <strong class="fs-12 text-dark text-truncate d-block" style="max-width: 160px;" title="<?= htmlspecialchars($sch['leader_name'] ?: 'Koordinator Tim') ?>">
                                                <?= htmlspecialchars($sch['leader_name'] ?: 'Koordinator Tim') ?>
                                            </strong>
                                            <small class="text-primary fs-10">Ketua Tim</small>
                                        </div>
                                    </div>
                                    <?php if (!empty($sch['member_names'])): ?>
                                        <small class="text-muted d-block text-truncate fs-10" style="max-width: 190px;" title="<?= htmlspecialchars($sch['member_names']) ?>">
                                            + <?= htmlspecialchars($sch['member_names']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center text-nowrap">
                                    <?php if ($sch['status'] === 'in_progress'): ?>
                                        <span class="badge badge-warning text-dark light fw-bold fs-11"><i class="fa fa-spinner fa-spin me-1"></i> Berjalan</span>
                                    <?php elseif ($sch['status'] === 'completed'): ?>
                                        <span class="badge badge-success light fw-bold fs-11"><i class="fa fa-check me-1"></i> Selesai</span>
                                    <?php else: ?>
                                        <span class="badge badge-info light fw-bold fs-11"><i class="fa fa-calendar me-1"></i> Terjadwal</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center text-nowrap">
                                    <button type="button" class="btn btn-xs btn-outline-primary rounded-circle" onclick="showScheduleDetail(<?= htmlspecialchars(json_encode($sch), ENT_QUOTES, 'UTF-8') ?>)" title="Lihat Lembar Detail SPT">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-warning rounded-circle ms-1" onclick="openEditScheduleModal(<?= htmlspecialchars(json_encode($sch), ENT_QUOTES, 'UTF-8') ?>)" title="Edit Tanggal Keluar & Data SPT">
                                        <i class="fa fa-pencil"></i>
                                    </button>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Pagination Bar -->
            <div class="p-3 border-top bg-light bg-opacity-25 d-flex flex-wrap justify-content-between align-items-center gap-2 pagination-row">
                <span class="fs-12 text-muted" id="pageInfoText">
                    Menampilkan 1-15 dari <?= count($schedules) ?> entri
                </span>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0" id="paginationUl">
                        <!-- Dynamic pagination items -->
                    </ul>
                </nav>
            </div>
        </div>

        <!-- TAMPILAN 2: GRID KARTU (CARD VIEW) -->
        <div id="schedulesCardsView" style="display: none;">
            <div class="row" id="schedulesCardsContainer">
                <?php foreach ($schedules as $sch): ?>
                    <?php
                    $sDate = strtotime($sch['start_date']);
                    $eDate = strtotime($sch['end_date']);
                    $monthKey = date('Y-m', $sDate);
                    $sptDateFormatted = !empty($sch['spt_date']) ? date('d M Y', strtotime($sch['spt_date'])) : '';
                    $allCatNames = implode(' ', array_column($sch['categories_list'], 'name')) . ' ' . implode(' ', array_column($sch['categories_list'], 'short_title'));
                    $searchContent = strtolower(htmlspecialchars(
                        $sch['spt_number'] . ' ' . $sptDateFormatted . ' ' . $sch['title'] . ' ' . $sch['location'] . ' ' .
                        $sch['target_object'] . ' ' . $sch['leader_name'] . ' ' . $sch['member_names'] . ' ' . $allCatNames
                    ));
                    ?>
                    <div class="col-xl-4 col-md-6 mb-4 sched-card-item"
                         data-cat="<?= $sch['category_id'] ?>"
                         data-cats='<?= json_encode($sch['cat_ids']) ?>'
                         data-spt="<?= htmlspecialchars(strtolower($sch['spt_number'] . ' ' . $sptDateFormatted)) ?>"
                         data-spt-date="<?= htmlspecialchars($sch['spt_date'] ?? '') ?>"
                         data-title="<?= htmlspecialchars(strtolower($sch['title'] . ' ' . $sch['target_object'])) ?>"
                         data-location="<?= htmlspecialchars(strtolower($sch['location'])) ?>"
                         data-dates="<?= htmlspecialchars(strtolower(date('d M Y', $sDate) . ' ' . date('d M Y', $eDate) . ' ' . $sptDateFormatted . ' ' . $monthKey)) ?>"
                         data-team="<?= htmlspecialchars(strtolower($sch['leader_name'] . ' ' . $sch['member_names'])) ?>"
                         data-month="<?= $monthKey ?>"
                         data-status="<?= $sch['status'] ?>"
                         data-search="<?= $searchContent ?>">
                        
                        <div class="card h-100 border-0 shadow-sm sched-card" style="border-radius: 18px;">
                            <div class="card-body p-4 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                                    <div class="d-flex flex-wrap gap-1 align-items-center">
                                        <?php foreach ($sch['categories_list'] as $cItem): ?>
                                            <span class="badge badge-<?= $cItem['badge_color'] ?> light fs-10 px-2 py-1">
                                                <i class="fa-solid <?= $cItem['icon'] ?> me-1"></i> <?= htmlspecialchars($cItem['short_title']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if ($sch['status'] === 'in_progress'): ?>
                                        <span class="badge badge-warning text-dark light fw-bold fs-11 text-nowrap"><i class="fa fa-spinner fa-spin me-1"></i> Sedang Berjalan</span>
                                    <?php elseif ($sch['status'] === 'completed'): ?>
                                        <span class="badge badge-success light fw-bold fs-11 text-nowrap"><i class="fa fa-check me-1"></i> Selesai</span>
                                    <?php else: ?>
                                        <span class="badge badge-info light fw-bold fs-11 text-nowrap"><i class="fa fa-calendar me-1"></i> Terjadwal</span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex justify-content-between align-items-baseline mb-1 gap-1">
                                    <span class="font-monospace text-primary fw-bold fs-11 text-truncate" title="<?= htmlspecialchars($sch['spt_number']) ?>">
                                        <?= htmlspecialchars($sch['spt_number']) ?>
                                    </span>
                                </div>
                                <h5 class="fw-bold mb-2 text-dark fs-14" style="line-height: 1.4; min-height: 40px;" title="<?= htmlspecialchars($sch['title']) ?>">
                                    <?= htmlspecialchars($sch['title']) ?>
                                </h5>

                                <div class="p-3 bg-light rounded-3 mb-3 fs-12 text-muted">
                                    <div class="mb-1 text-truncate" title="Tgl Keluar SPT: <?= !empty($sch['spt_date']) ? date('d M Y', strtotime($sch['spt_date'])) : '-' ?>">
                                        <i class="fa fa-calendar-check text-success me-2"></i> Tgl Keluar: <strong class="text-dark"><?= !empty($sch['spt_date']) ? date('d M Y', strtotime($sch['spt_date'])) : '-' ?></strong>
                                    </div>
                                    <div class="mb-1 text-truncate" title="Target: <?= htmlspecialchars($sch['target_object']) ?>">
                                        <i class="fa fa-bullseye text-primary me-2"></i> Target: <strong class="text-dark"><?= htmlspecialchars($sch['target_object']) ?></strong>
                                    </div>
                                    <div class="mb-1 text-truncate" title="Lokasi: <?= htmlspecialchars($sch['location']) ?>">
                                        <i class="fa fa-map-pin text-danger me-2"></i> Lokasi: <strong class="text-dark"><?= htmlspecialchars($sch['location']) ?></strong>
                                    </div>
                                    <div class="text-nowrap">
                                        <i class="fa fa-clock text-info me-2"></i> Periode: <strong class="text-dark"><?= date('d M Y', $sDate) ?> - <?= date('d M Y', $eDate) ?></strong>
                                    </div>
                                </div>

                                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center overflow-hidden me-2">
                                        <img src="<?= htmlspecialchars($sch['leader_avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle me-2 flex-shrink-0" width="28" height="28" alt="">
                                        <div class="overflow-hidden">
                                            <strong class="fs-12 d-block text-dark text-truncate" style="max-width: 130px;" title="<?= htmlspecialchars($sch['leader_name'] ?: 'Koordinator Tim') ?>">
                                                <?= htmlspecialchars($sch['leader_name'] ?: 'Koordinator Tim') ?>
                                            </strong>
                                            <small class="text-primary fs-10 font-w600 text-nowrap">Ketua Tim</small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                        <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1" onclick="showScheduleDetail(<?= htmlspecialchars(json_encode($sch), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="fa fa-eye me-1"></i> Detail
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-2.5 py-1" onclick="openEditScheduleModal(<?= htmlspecialchars(json_encode($sch), ENT_QUOTES, 'UTF-8') ?>)" title="Edit Tanggal Keluar & Data SPT">
                                            <i class="fa fa-pencil"></i>
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Empty State Alert when No Filter Matches -->
        <div id="noSchedFound" class="card border-0 shadow-sm p-5 text-center my-4" style="display: none; border-radius: 18px;">
            <div class="py-4 text-muted">
                <i class="material-symbols-outlined fs-48 text-muted mb-2">event_busy</i>
                <h5 class="fw-bold text-dark">Tidak ada agenda monitoring yang cocok dengan filter.</h5>
                <p class="fs-13 text-muted mb-3">Silakan sesuaikan opsi pencarian, wilayah kabupaten/kota, atau pilihan bulan pelaksanaan.</p>
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" onclick="resetAllFilters()">
                    Reset Semua Filter
                </button>
            </div>
        </div>

    </div>
</div>

<!-- MODAL DETAIL SURAT PERINTAH TUGAS (SPT) -->
<div class="modal fade" id="modalDetailSPT" tabindex="-1" aria-labelledby="modalDetailSPTLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow" style="border-radius: 18px;">
            <div class="modal-header py-3 px-4 border-bottom bg-light">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 d-flex align-items-center justify-content-center me-3 flex-shrink-0 bg-primary text-white" style="width: 40px; height: 40px;">
                        <i class="material-symbols-outlined fs-22">description</i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalDetailSPTLabel">Detail Surat Perintah Tugas (SPT)</h5>
                        <small class="text-muted fs-11">Balai Monitor Spektrum Frekuensi Radio Kelas I Samarinda</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Banner Kode SPT & Status -->
                <div class="p-3 rounded-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2" style="background: rgba(2, 132, 199, 0.08); border: 1px solid rgba(2, 132, 199, 0.2);">
                    <div>
                        <span class="fs-11 text-muted text-uppercase fw-bold d-block">Nomor SPT Resmi:</span>
                        <h5 class="font-monospace fw-bold text-primary mb-1" id="modalSptNumber">-</h5>
                        <div class="d-flex align-items-center gap-1 text-dark fs-12 mt-1">
                            <i class="material-symbols-outlined fs-16 text-primary align-middle">calendar_today</i>
                            <span>Tanggal Keluar / Ditetapkan SPT: <strong class="text-primary font-monospace fw-bold" id="modalSptDate">-</strong></span>
                        </div>
                    </div>
                    <div class="text-end" id="modalStatusBadge">
                        <!-- Dynamic Status Badge -->
                    </div>
                </div>

                <!-- Informasi Pokok Kegiatan -->
                <div class="mb-3">
                    <h6 class="fw-bold text-dark fs-14 mb-1" id="modalTitle">-</h6>
                    <div id="modalCategoriesWrap" class="d-inline-flex flex-wrap gap-1 align-items-center me-1 my-1"></div>
                    <span class="badge bg-light text-muted border" id="modalKlasifikasi">Klasifikasi Arsip: SP.03.03 / KU.01.06</span>
                </div>

                <!-- Grid Rincian Lokasi, Target & Periode -->
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded-3 h-100">
                            <span class="fs-11 text-muted d-block mb-1"><i class="fa fa-calendar-check text-success me-1"></i> Tgl Keluar SPT:</span>
                            <strong class="fs-12 text-dark d-block text-success font-monospace" id="modalSptDateCard">-</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded-3 h-100">
                            <span class="fs-11 text-muted d-block mb-1"><i class="fa fa-calendar-alt text-info me-1"></i> Periode Penugasan:</span>
                            <strong class="fs-12 text-dark d-block" id="modalDates">-</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded-3 h-100">
                            <span class="fs-11 text-muted d-block mb-1"><i class="fa fa-map-pin text-danger me-1"></i> Wilayah / Lokasi:</span>
                            <strong class="fs-12 text-dark d-block" id="modalLocation">-</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded-3 h-100">
                            <span class="fs-11 text-muted d-block mb-1"><i class="fa fa-bullseye text-primary me-1"></i> Target Objek:</span>
                            <strong class="fs-12 text-dark d-block" id="modalTarget">-</strong>
                        </div>
                    </div>
                </div>

                <!-- Tim Pelaksana -->
                <div class="mb-3">
                    <span class="fs-11 fw-bold text-uppercase text-muted d-block mb-2"><i class="fa fa-users me-1 text-primary"></i> Susunan Tim Pelaksana Lapangan:</span>
                    <div class="p-3 bg-light rounded-3">
                        <div class="d-flex align-items-center mb-2 pb-2 border-bottom">
                            <img src="assets/images/avatar/1.jpg" id="modalLeaderAvatar" class="rounded-circle me-3" width="40" height="40" alt="">
                            <div>
                                <strong class="fs-13 text-dark d-block" id="modalLeaderName">-</strong>
                                <small class="text-primary fw-bold" id="modalLeaderRole">Ketua Tim Pelaksana</small>
                            </div>
                        </div>
                        <div>
                            <span class="fs-11 text-muted d-block mb-1">Anggota Tim:</span>
                            <p class="fs-12 text-dark mb-0" id="modalMembersList" style="line-height: 1.5;">-</p>
                        </div>
                    </div>
                </div>

                <!-- Catatan Pelaksanaan -->
                <div id="modalNotesWrap">
                    <span class="fs-11 fw-bold text-uppercase text-muted d-block mb-1"><i class="fa fa-circle-info me-1 text-info"></i> Catatan & Keterangan:</span>
                    <div class="p-2 px-3 bg-light rounded-3 fs-12 text-muted" id="modalNotes">
                        -
                    </div>
                </div>
            </div>

            <div class="modal-footer py-2 px-4 border-top d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="editFromDetailModal()">
                    <i class="fa fa-pencil me-1"></i> Edit Data SPT
                </button>
                <button type="button" class="btn btn-sm btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDIT SURAT PERINTAH TUGAS (SPT) -->
<div class="modal fade" id="modalEditSPT" tabindex="-1" aria-labelledby="modalEditSPTLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow" style="border-radius: 18px;">
            <form action="schedules.php" method="POST">
                <input type="hidden" name="action" value="update_schedule">
                <input type="hidden" name="id" id="editSchedId" value="">
                
                <div class="modal-header py-3 px-4 border-bottom bg-light">
                    <div class="d-flex align-items-center">
                        <div class="rounded-3 d-flex align-items-center justify-content-center me-3 flex-shrink-0 bg-warning text-dark" style="width: 40px; height: 40px;">
                            <i class="material-symbols-outlined fs-22">edit_document</i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="modalEditSPTLabel">Edit Data Surat Perintah Tugas (SPT)</h5>
                            <small class="text-muted fs-11">Pembaruan nomor, tanggal keluar SPT, dan rincian penugasan</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="form-label font-w600 fs-13">Nomor Surat Perintah Tugas (SPT) <span class="text-danger">*</span></label>
                            <input type="text" name="spt_number" id="editSptNumber" class="form-control" required>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label font-w600 fs-13 text-primary">Tanggal Keluar / Ditetapkan SPT <span class="text-danger">*</span></label>
                            <input type="date" name="spt_date" id="editSptDate" class="form-control border-primary" required>
                            <small class="text-muted fs-11">Tanggal resmi penerbitan / penandatanganan SPT</small>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label font-w600 fs-13">Judul Penugasan / Kegiatan <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="editTitle" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-w600 fs-13">Wilayah / Lokasi Tugas <span class="text-danger">*</span></label>
                            <input type="text" name="location" id="editLocation" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-w600 fs-13">Target Objek / Sasaran Monitoring</label>
                            <input type="text" name="target_object" id="editTargetObject" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-w600 fs-13">Tanggal Mulai Penugasan <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="editStartDate" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-w600 fs-13">Tanggal Selesai Penugasan <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="editEndDate" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-w600 fs-13">Status Penugasan <span class="text-danger">*</span></label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="scheduled">Terjadwal</option>
                                <option value="in_progress">Sedang Berjalan</option>
                                <option value="completed">Selesai</option>
                            </select>
                        </div>
                        <div class="col-12 mb-2">
                            <label class="form-label font-w600 fs-13">Catatan / Keterangan Penugasan</label>
                            <textarea name="notes" id="editNotes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer py-2 px-4 border-top">
                    <button type="button" class="btn btn-sm btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold">
                        <i class="fa fa-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// State Management
let currentView = localStorage.getItem('monreport_sched_view') || 'table';
let currentPage = 1;
let pageSize = 15;
let filteredRows = [];

// Cleanup bootstrap-select interference on table header filter selects
function cleanupTableSelects() {
    if (typeof jQuery !== 'undefined') {
        const $targets = jQuery('.header-filter-row select, select.col-search-box');
        if (jQuery.fn.selectpicker) {
            try {
                $targets.selectpicker('destroy');
            } catch(e) {}
        }
        jQuery('.header-filter-row .bootstrap-select > select').each(function() {
            jQuery(this).unwrap();
        });
        jQuery('.header-filter-row .bootstrap-select').remove();
        $targets.removeClass('bs-select-hidden').css({'display': 'block', 'visibility': 'visible', 'opacity': '1'});
    }
}

// Initialize Page
document.addEventListener('DOMContentLoaded', function() {
    cleanupTableSelects();
    switchView(currentView, false);
    applyFilters();
});
if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(cleanupTableSelects);
    jQuery(window).on('load', cleanupTableSelects);
}

// View Switcher (Table vs Cards)
function switchView(viewMode, save = true) {
    currentView = viewMode;
    if (save) {
        try { localStorage.setItem('monreport_sched_view', viewMode); } catch(e) {}
    }

    const btnTable = document.getElementById('btnViewTable');
    const btnCards = document.getElementById('btnViewCards');
    const viewTable = document.getElementById('schedulesTableView');
    const viewCards = document.getElementById('schedulesCardsView');

    if (viewMode === 'cards') {
        btnCards.classList.add('active');
        btnTable.classList.remove('active');
        viewTable.style.display = 'none';
        viewCards.style.display = 'block';
    } else {
        btnTable.classList.add('active');
        btnCards.classList.remove('active');
        viewTable.style.display = 'block';
        viewCards.style.display = 'none';
    }

    renderTablePagination();
}

// Master Filter Function
function applyFilters() {
    // Column header inputs
    const colSpt = (document.getElementById('colFilterSpt')?.value || '').toLowerCase().trim();
    const colTitle = (document.getElementById('colFilterTitle')?.value || '').toLowerCase().trim();
    const colCat = document.getElementById('colFilterCat')?.value || '';
    const colLoc = (document.getElementById('colFilterLoc')?.value || '').toLowerCase().trim();
    const colDates = (document.getElementById('colFilterDates')?.value || '').toLowerCase().trim();
    const colTeam = (document.getElementById('colFilterTeam')?.value || '').toLowerCase().trim();
    const colStatus = document.getElementById('colFilterStatus')?.value || '';

    const tableRows = Array.from(document.querySelectorAll('.sched-item-row'));
    const cardItems = Array.from(document.querySelectorAll('.sched-card-item'));

    filteredRows = [];

    tableRows.forEach((row, idx) => {
        let itemCats = [];
        try {
            itemCats = JSON.parse(row.getAttribute('data-cats') || '[]');
        } catch(e) {
            const singleCat = row.getAttribute('data-cat');
            if (singleCat) itemCats = [parseInt(singleCat, 10)];
        }

        const itemSpt = row.getAttribute('data-spt') || '';
        const itemTitle = row.getAttribute('data-title') || '';
        const itemLocation = row.getAttribute('data-location') || '';
        const itemDates = row.getAttribute('data-dates') || '';
        const itemTeam = row.getAttribute('data-team') || '';
        const itemStatus = row.getAttribute('data-status') || '';

        // Verification checks
        const matchSpt = (!colSpt) || itemSpt.indexOf(colSpt) !== -1;
        const matchTitle = (!colTitle) || itemTitle.indexOf(colTitle) !== -1;
        const matchCat = (!colCat) || itemCats.includes(parseInt(colCat, 10));
        const matchLoc = (!colLoc) || itemLocation.indexOf(colLoc) !== -1;
        const matchDates = (!colDates) || itemDates.indexOf(colDates) !== -1;
        const matchTeam = (!colTeam) || itemTeam.indexOf(colTeam) !== -1;
        const matchStatus = (!colStatus) || (itemStatus === colStatus);

        const isMatch = matchSpt && matchTitle && matchCat && matchLoc && matchDates && matchTeam && matchStatus;

        // Card sync
        if (cardItems[idx]) {
            cardItems[idx].style.display = isMatch ? '' : 'none';
        }

        if (isMatch) {
            filteredRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });

    // Update Counter & Active Filter Tags
    const shownEl = document.getElementById('shownCount');
    if (shownEl) shownEl.innerText = filteredRows.length;
    renderFilterTags(colLoc, colCat, colDates, colStatus, (colSpt || colTitle || colTeam));

    // Empty State Handling
    const noResultEl = document.getElementById('noSchedFound');
    if (noResultEl) {
        noResultEl.style.display = (filteredRows.length === 0) ? 'block' : 'none';
    }

    // Reset pagination to first page
    currentPage = 1;
    renderTablePagination();
}

// Reset column-level filters
function resetColumnFilters() {
    if (document.getElementById('colFilterSpt')) document.getElementById('colFilterSpt').value = '';
    if (document.getElementById('colFilterTitle')) document.getElementById('colFilterTitle').value = '';
    if (document.getElementById('colFilterCat')) document.getElementById('colFilterCat').value = '';
    if (document.getElementById('colFilterLoc')) document.getElementById('colFilterLoc').value = '';
    if (document.getElementById('colFilterDates')) document.getElementById('colFilterDates').value = '';
    if (document.getElementById('colFilterTeam')) document.getElementById('colFilterTeam').value = '';
    if (document.getElementById('colFilterStatus')) document.getElementById('colFilterStatus').value = '';
    applyFilters();
}

// Reset all filters
function resetAllFilters() {
    resetColumnFilters();
}

// Render Table Rows with Pagination
function renderTablePagination() {
    const total = filteredRows.length;
    const tbody = document.getElementById('schedulesTableBody');
    const tableRows = Array.from(document.querySelectorAll('.sched-item-row'));

    // First hide all rows
    tableRows.forEach(r => r.style.display = 'none');

    let start = 0;
    let end = total;

    if (pageSize !== 'all') {
        const numPageSize = parseInt(pageSize, 10);
        const totalPages = Math.ceil(total / numPageSize) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        start = (currentPage - 1) * numPageSize;
        end = Math.min(start + numPageSize, total);

        renderPaginationControls(totalPages);
        document.getElementById('pageInfoText').innerText = total > 0 
            ? `Menampilkan ${start + 1}-${end} dari ${total} entri`
            : 'Menampilkan 0 entri';
    } else {
        document.getElementById('paginationUl').innerHTML = '';
        document.getElementById('pageInfoText').innerText = `Menampilkan semua ${total} entri`;
    }

    // Show only the page slice
    for (let i = start; i < end; i++) {
        if (filteredRows[i]) {
            filteredRows[i].style.display = '';
            const idxCell = filteredRows[i].querySelector('.row-index');
            if (idxCell) idxCell.innerText = (i + 1);
        }
    }
}

// Render Pagination Numbers
function renderPaginationControls(totalPages) {
    const ul = document.getElementById('paginationUl');
    if (!ul) return;
    ul.innerHTML = '';

    if (totalPages <= 1) return;

    // Prev Button
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="javascript:void(0)" onclick="goToPage(${currentPage - 1})"><i class="fa fa-chevron-left"></i></a>`;
    ul.appendChild(prevLi);

    // Page Numbers
    for (let p = 1; p <= totalPages; p++) {
        if (p === 1 || p === totalPages || (p >= currentPage - 2 && p <= currentPage + 2)) {
            const li = document.createElement('li');
            li.className = `page-item ${p === currentPage ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="javascript:void(0)" onclick="goToPage(${p})">${p}</a>`;
            ul.appendChild(li);
        } else if (p === currentPage - 3 || p === currentPage + 3) {
            const li = document.createElement('li');
            li.className = 'page-item disabled';
            li.innerHTML = `<span class="page-link">...</span>`;
            ul.appendChild(li);
        }
    }

    // Next Button
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="javascript:void(0)" onclick="goToPage(${currentPage + 1})"><i class="fa fa-chevron-right"></i></a>`;
    ul.appendChild(nextLi);
}

function goToPage(page) {
    currentPage = page;
    renderTablePagination();
    window.scrollTo({ top: 380, behavior: 'smooth' });
}

function changePageSize(val) {
    pageSize = val;
    currentPage = 1;
    renderTablePagination();
}

// Filter Tag Badges
function renderFilterTags(kabKota, cat, dates, status, textSearch) {
    const container = document.getElementById('activeFilterTags');
    if (!container) return;
    container.innerHTML = '';

    if (kabKota) {
        const locSelect = document.getElementById('colFilterLoc');
        let locText = capitalize(kabKota);
        if (locSelect && locSelect.selectedIndex > 0) {
            locText = locSelect.options[locSelect.selectedIndex].text;
        }
        container.innerHTML += `<span class="badge bg-light text-dark border fs-10">Wilayah: ${locText} <i class="fa fa-times ms-1 text-danger cursor-pointer" onclick="clearSpecificFilter('kabkota')"></i></span>`;
    }
    if (cat) {
        const catSelect = document.getElementById('colFilterCat');
        let catText = 'Kategori #' + cat;
        if (catSelect) {
            for (let opt of catSelect.options) {
                if (opt.value == cat) {
                    catText = opt.text;
                    break;
                }
            }
        }
        container.innerHTML += `<span class="badge bg-light text-dark border fs-10">Kegiatan: ${catText} <i class="fa fa-times ms-1 text-danger cursor-pointer" onclick="clearSpecificFilter('cat')"></i></span>`;
    }
    if (dates) {
        container.innerHTML += `<span class="badge bg-light text-dark border fs-10">Periode: ${dates} <i class="fa fa-times ms-1 text-danger cursor-pointer" onclick="clearSpecificFilter('dates')"></i></span>`;
    }
    if (status) {
        const statusLabel = (status === 'completed') ? 'Selesai' : ((status === 'in_progress') ? 'Berjalan' : 'Terjadwal');
        container.innerHTML += `<span class="badge bg-light text-dark border fs-10">Status: ${statusLabel} <i class="fa fa-times ms-1 text-danger cursor-pointer" onclick="clearSpecificFilter('status')"></i></span>`;
    }
    if (textSearch) {
        container.innerHTML += `<span class="badge bg-light text-dark border fs-10">Teks: "${textSearch}" <i class="fa fa-times ms-1 text-danger cursor-pointer" onclick="clearSpecificFilter('search')"></i></span>`;
    }
}

function clearSpecificFilter(type) {
    if (type === 'kabkota') {
        if (document.getElementById('colFilterLoc')) document.getElementById('colFilterLoc').value = '';
    }
    if (type === 'cat') {
        if (document.getElementById('colFilterCat')) document.getElementById('colFilterCat').value = '';
    }
    if (type === 'dates') {
        if (document.getElementById('colFilterDates')) document.getElementById('colFilterDates').value = '';
    }
    if (type === 'status') {
        if (document.getElementById('colFilterStatus')) document.getElementById('colFilterStatus').value = '';
    }
    if (type === 'search') {
        if (document.getElementById('colFilterSpt')) document.getElementById('colFilterSpt').value = '';
        if (document.getElementById('colFilterTitle')) document.getElementById('colFilterTitle').value = '';
        if (document.getElementById('colFilterTeam')) document.getElementById('colFilterTeam').value = '';
    }
    applyFilters();
}

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatIndoDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const d = parseInt(parts[2], 10);
    const m = months[parseInt(parts[1], 10) - 1] || parts[1];
    const y = parts[0];
    return `${d} ${m} ${y}`;
}

let currentDetailData = null;

// Modal Detail SPT
function showScheduleDetail(data) {
    if (!data) return;
    currentDetailData = data;

    document.getElementById('modalSptNumber').innerText = data.spt_number || '-';
    document.getElementById('modalTitle').innerText = data.title || '-';

    // Tanggal Keluar SPT
    const sptDateFormatted = data.spt_date ? formatIndoDate(data.spt_date) : '-';
    const sptDateEl = document.getElementById('modalSptDate');
    if (sptDateEl) sptDateEl.innerText = sptDateFormatted;
    const sptDateCardEl = document.getElementById('modalSptDateCard');
    if (sptDateCardEl) sptDateCardEl.innerText = sptDateFormatted;
    
    // Multi Categories Rendering
    const catWrap = document.getElementById('modalCategoriesWrap');
    if (catWrap) {
        catWrap.innerHTML = '';
        if (data.categories_list && data.categories_list.length > 0) {
            data.categories_list.forEach(c => {
                catWrap.innerHTML += `<span class="badge badge-${c.badge_color || 'primary'} light fs-11 px-2.5 py-1.5 d-inline-flex align-items-center me-1"><i class="fa-solid ${c.icon || 'fa-tower-broadcast'} me-1"></i> ${c.short_title || c.name}</span>`;
            });
        } else {
            catWrap.innerHTML = `<span class="badge bg-light text-primary border me-1">${data.category_name || data.short_title || 'Monitoring'}</span>`;
        }
    }

    document.getElementById('modalLocation').innerText = data.location || '-';
    document.getElementById('modalTarget').innerText = data.target_object || '-';
    
    // Dates formatted
    const sDate = formatIndoDate(data.start_date);
    const eDate = formatIndoDate(data.end_date);
    document.getElementById('modalDates').innerText = (sDate && eDate) ? `${sDate} s.d. ${eDate}` : '-';

    // Leader info
    document.getElementById('modalLeaderName').innerText = data.leader_name || 'Koordinator Tim Monitoring';
    if (data.leader_avatar) {
        document.getElementById('modalLeaderAvatar').src = data.leader_avatar;
    } else {
        document.getElementById('modalLeaderAvatar').src = 'assets/images/avatar/1.jpg';
    }

    // Members
    document.getElementById('modalMembersList').innerText = data.member_names || 'Tidak ada anggota tambahan.';
    document.getElementById('modalNotes').innerText = data.notes || 'Penugasan operasional pemantauan spektrum frekuensi radio Balmon Samarinda.';

    // Status Badge
    const statusWrap = document.getElementById('modalStatusBadge');
    if (data.status === 'in_progress') {
        statusWrap.innerHTML = '<span class="badge badge-warning text-dark fw-bold fs-12 px-3 py-2"><i class="fa fa-spinner fa-spin me-1"></i> Sedang Berjalan</span>';
    } else if (data.status === 'completed') {
        statusWrap.innerHTML = '<span class="badge badge-success light fw-bold fs-12 px-3 py-2"><i class="fa fa-check me-1"></i> Selesai Dilaksanakan</span>';
    } else {
        statusWrap.innerHTML = '<span class="badge badge-info light fw-bold fs-12 px-3 py-2"><i class="fa fa-calendar me-1"></i> Terjadwal (Mendatang)</span>';
    }

    const modal = new bootstrap.Modal(document.getElementById('modalDetailSPT'));
    modal.show();
}

// Modal Edit SPT
function openEditScheduleModal(data) {
    if (!data) return;
    document.getElementById('editSchedId').value = data.id || '';
    document.getElementById('editSptNumber').value = data.spt_number || '';
    document.getElementById('editSptDate').value = data.spt_date || '';
    document.getElementById('editTitle').value = data.title || '';
    document.getElementById('editLocation').value = data.location || '';
    document.getElementById('editTargetObject').value = data.target_object || '';
    document.getElementById('editStartDate').value = data.start_date || '';
    document.getElementById('editEndDate').value = data.end_date || '';
    document.getElementById('editStatus').value = data.status || 'scheduled';
    document.getElementById('editNotes').value = data.notes || '';

    const modal = new bootstrap.Modal(document.getElementById('modalEditSPT'));
    modal.show();
}

// Trigger edit modal directly from detail modal
function editFromDetailModal() {
    const detailModalEl = document.getElementById('modalDetailSPT');
    const detailModal = bootstrap.Modal.getInstance(detailModalEl);
    if (detailModal) {
        detailModal.hide();
    }
    if (currentDetailData) {
        setTimeout(() => {
            openEditScheduleModal(currentDetailData);
        }, 350);
    }
}

// Export Filtered Table to CSV
function exportFilteredCsv() {
    if (filteredRows.length === 0) {
        alert('Tidak ada data yang cocok untuk diekspor.');
        return;
    }

    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "No,Nomor SPT,Tanggal Keluar SPT,Judul Kegiatan,Jenis Kegiatan (Semua),Lokasi,Target Objek,Tanggal Mulai,Tanggal Selesai,Ketua Tim,Anggota Tim,Status\n";

    filteredRows.forEach((row, idx) => {
        try {
            const data = JSON.parse(row.getAttribute('data-json'));
            const clean = str => `"${(str || '').replace(/"/g, '""')}"`;
            const catDisplay = data.category_names_str || data.category_name || (data.categories_list ? data.categories_list.map(c => c.short_title).join(' / ') : '');
            const line = [
                idx + 1,
                clean(data.spt_number),
                clean(data.spt_date || ''),
                clean(data.title),
                clean(catDisplay),
                clean(data.location),
                clean(data.target_object),
                clean(data.start_date),
                clean(data.end_date),
                clean(data.leader_name),
                clean(data.member_names),
                clean(data.status)
            ].join(',');
            csvContent += line + "\n";
        } catch(e) {}
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `rekap_spt_monitoring_balmon_samarinda_2026.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
