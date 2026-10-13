<?php
// index.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();
$pageTitle = "Dashboard Capaian Kinerja";

// Fetch Dashboard Data
// Multi-Year Perjanjian Kinerja (PK) Architecture
$availableYears = $pdo->query("SELECT * FROM pk_years_meta ORDER BY year DESC")->fetchAll();
if (empty($availableYears)) {
    $availableYears = [
        ['year' => 2026, 'title' => 'Perjanjian Kinerja Tahun 2026', 'legal_basis' => 'Nota Dinas No: 224/DJID.6/PR.04.01/02/2026', 'status' => 'active', 'description' => 'Target operasional TA 2026.']
    ];
}

$selectedYear = function_exists('getActiveAppYear') ? getActiveAppYear() : (isset($_GET['year']) ? (int)$_GET['year'] : 2026);

// Find metadata for selected year
$currentYearMeta = null;
foreach ($availableYears as $ym) {
    if ((int)$ym['year'] === $selectedYear) {
        $currentYearMeta = $ym;
        break;
    }
}
if (!$currentYearMeta) {
    $currentYearMeta = [
        'year' => $selectedYear,
        'title' => "Perjanjian Kinerja Tahun $selectedYear",
        'legal_basis' => "Nota Dinas Penetapan PK TA $selectedYear",
        'status' => ($selectedYear < 2026 ? 'archived' : ($selectedYear == 2026 ? 'active' : 'planning')),
        'description' => ''
    ];
}

// 1. Fetch PK indicators for selected year
$stmtPK = $pdo->prepare("SELECT * FROM perjanjian_kinerja WHERE year = ? ORDER BY order_num ASC, id ASC");
$stmtPK->execute([$selectedYear]);
$perjanjianKinerja = $stmtPK->fetchAll();

$totalPkScore = 0;
$pkAchievedCount = 0;
$pkCount = count($perjanjianKinerja);

foreach ($perjanjianKinerja as &$pk) {
    $target = (float)$pk['target_annual'];
    $real = (float)$pk['realization'];
    $pk['percent_of_target'] = $target > 0 ? min(100, round(($real / $target) * 100, 1)) : 0;
    
    if ($real >= $target) {
        $pk['status_label'] = 'Tercapai';
        $pk['status_class'] = 'success';
        $pkAchievedCount++;
    } elseif ($pk['percent_of_target'] >= 80) {
        $pk['status_label'] = 'On Track';
        $pk['status_class'] = 'info';
    } else {
        $pk['status_label'] = 'Perlu Percepatan';
        $pk['status_class'] = 'warning';
    }
    
    $totalPkScore += $pk['percent_of_target'];
}
unset($pk);

$averagePkScore = $pkCount > 0 ? round($totalPkScore / $pkCount, 1) : 0;

// Multi-Year Tracking Comparison Summary
$yearSummaries = [];
foreach ($availableYears as $ay) {
    $y = (int)$ay['year'];
    $stY = $pdo->prepare("SELECT target_annual, realization FROM perjanjian_kinerja WHERE year = ?");
    $stY->execute([$y]);
    $itemsY = $stY->fetchAll();
    $cntY = count($itemsY);
    $sumScoreY = 0;
    $achievedY = 0;
    foreach ($itemsY as $iy) {
        $tarY = (float)$iy['target_annual'];
        $relY = (float)$iy['realization'];
        $pctY = $tarY > 0 ? min(100, round(($relY / $tarY) * 100, 1)) : 0;
        $sumScoreY += $pctY;
        if ($relY >= $tarY) $achievedY++;
    }
    $avgScoreY = $cntY > 0 ? round($sumScoreY / $cntY, 1) : 0;
    $yearSummaries[] = [
        'year' => $y,
        'title' => $ay['title'],
        'status' => $ay['status'],
        'legal_basis' => $ay['legal_basis'],
        'count' => $cntY,
        'achieved' => $achievedY,
        'average_score' => $avgScoreY
    ];
}

// 2. 9 Kategori Kegiatan Lapangan
$stmtCats = $pdo->query("SELECT * FROM monitoring_categories ORDER BY id ASC");
$categories = $stmtCats->fetchAll();

$totalTarget = 0;
$totalRealized = 0;
$totalVerified = 0;
$totalPending = 0;
$totalRevision = 0;

foreach ($categories as &$cat) {
    $stmtC = $pdo->prepare("
        SELECT 
            COUNT(*) as total_count,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_count,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
            SUM(CASE WHEN status = 'revision' THEN 1 ELSE 0 END) as revision_count
        FROM monitoring_reports
        WHERE category_id = ?
    ");
    $stmtC->execute([$cat['id']]);
    $counts = $stmtC->fetch();

    $cat['realized'] = (int)$counts['total_count'];
    $cat['verified'] = (int)$counts['verified_count'];
    $cat['pending'] = (int)$counts['submitted_count'];
    $cat['revision'] = (int)$counts['revision_count'];
    
    $catTarget = (int)$cat['target_annual'];
    $cat['percentage'] = $catTarget > 0 ? min(100, round(($cat['realized'] / $catTarget) * 100, 1)) : 0;

    $totalTarget += $catTarget;
    $totalRealized += $cat['realized'];
    $totalVerified += $cat['verified'];
    $totalPending += $cat['pending'];
    $totalRevision += $cat['revision'];
}
unset($cat);

$overallPercentage = $totalTarget > 0 ? min(100, round(($totalRealized / $totalTarget) * 100, 1)) : 0;

// Monthly Trend
$monthlyCounts = array_fill(1, 12, 0);
$stmtM = $pdo->query("
    SELECT strftime('%m', date_monitored) as mo, COUNT(*) as cnt
    FROM monitoring_reports
    WHERE strftime('%Y', date_monitored) = '2026'
    GROUP BY mo
");
while ($r = $stmtM->fetch()) {
    $m = (int)$r['mo'];
    if ($m >= 1 && $m <= 12) {
        $monthlyCounts[$m] = (int)$r['cnt'];
    }
}

// Recent Reports
$stmtRecent = $pdo->query("
    SELECT r.*, c.name as category_name, c.short_title, c.badge_color, u.name as officer_name, u.avatar as officer_avatar
    FROM monitoring_reports r
    JOIN monitoring_categories c ON r.category_id = c.id
    JOIN users u ON r.user_id = u.id
    ORDER BY r.date_monitored DESC, r.id DESC
    LIMIT 6
");
$recentReports = $stmtRecent->fetchAll();

// Schedules with Multi-Category Support
$allCatsDict = $pdo->query("SELECT id, name, short_title, badge_color, icon FROM monitoring_categories ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$catsByIdIndex = [];
foreach ($allCatsDict as $c) {
    $catsByIdIndex[$c['id']] = $c;
}

$stmtSched = $pdo->query("
    SELECT s.*, u.name as leader_name
    FROM monitoring_schedules s
    JOIN users u ON s.leader_id = u.id
    ORDER BY 
        CASE 
            WHEN s.status = 'in_progress' THEN 1
            WHEN s.status = 'scheduled' THEN 2
            ELSE 3
        END,
        s.start_date DESC
    LIMIT 6
");
$rawUpcomingSchedules = $stmtSched->fetchAll(PDO::FETCH_ASSOC);
$upcomingSchedules = [];
foreach ($rawUpcomingSchedules as $sched) {
    $cids = json_decode($sched['category_ids'] ?? '[]', true);
    if (empty($cids) && !empty($sched['category_id'])) $cids = [(int)$sched['category_id']];
    $sched['cats_list'] = [];
    foreach ($cids ?: [1] as $cid) {
        if (isset($catsByIdIndex[$cid])) {
            $sched['cats_list'][] = $catsByIdIndex[$cid];
        }
    }
    $sched['category_title'] = !empty($sched['cats_list']) ? $sched['cats_list'][0]['short_title'] : 'Monitoring';
    $upcomingSchedules[] = $sched;
}

// Schedule KPI Stats
$stmtSchedCounts = $pdo->query("
    SELECT 
        COUNT(*) as total_schedules,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_schedules,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as upcoming_schedules
    FROM monitoring_schedules
");
$schedCounts = $stmtSchedCounts->fetch();
$totalSchedules = (int)($schedCounts['total_schedules'] ?? 0);
$activeSchedules = (int)($schedCounts['active_schedules'] ?? 0);
$upcomingSchedulesCount = (int)($schedCounts['upcoming_schedules'] ?? 0);

// Active Officers
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
        <!-- Welcome Banner Capaian Kinerja (Tema Kuning Emas Logo MonReport) -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card welcome-banner-kpi border-0 shadow-sm mb-4 position-relative overflow-hidden" style="border-radius: 16px; background: linear-gradient(118deg, #FDB813 0%, #F59E0B 55%, #E68A00 100%); box-shadow: 0 2px 10px rgba(245, 158, 11, 0.16) !important; border: 1px solid rgba(245, 158, 11, 0.25) !important;">
                    <!-- Aksen Ornamen Gelombang Spektrum -->
                    <svg class="position-absolute end-0 top-0 d-none d-md-block" width="260" height="200" viewBox="0 0 260 200" fill="none" style="pointer-events: none; opacity: 0.08; transform: translate(30px, -20px);">
                        <circle cx="180" cy="50" r="45" stroke="#0A1628" stroke-width="9" />
                        <circle cx="180" cy="50" r="90" stroke="#0A1628" stroke-width="12" />
                        <circle cx="180" cy="50" r="135" stroke="#0A1628" stroke-width="15" />
                        <circle cx="180" cy="50" r="18" fill="#0A1628" />
                    </svg>

                    <div class="card-body p-3 p-md-4 position-relative" style="z-index: 2;">
                        <div class="row align-items-center">
                            <div class="col-lg-8">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="badge px-3 py-1 text-nowrap" style="background: #0A1628; color: #ffffff; font-weight: 700; letter-spacing: 0.5px; box-shadow: 0 1px 4px rgba(10, 22, 40, 0.12); border-radius: 30px;">
                                        <i class="fa-solid fa-tower-broadcast me-1 text-warning"></i> BALMON SAMARINDA
                                    </span>
                                    <span class="badge px-3 py-1 text-nowrap" style="background: rgba(10, 22, 40, 0.85); color: #FDB813; font-weight: 600; border-radius: 30px;">
                                        <i class="fa-solid fa-file-contract me-1"></i> <?= htmlspecialchars($currentYearMeta['legal_basis']) ?>
                                    </span>
                                    <span class="badge px-3 py-1 text-nowrap" style="background: rgba(0, 112, 224, 0.15); color: #0070E0; font-weight: 700; border-radius: 30px;">
                                        <i class="fa-solid fa-calendar-check me-1"></i> TA <?= $selectedYear ?>
                                    </span>
                                    <?php if ($currentYearMeta['status'] === 'active'): ?>
                                        <span class="badge bg-success text-white px-3 py-1 text-nowrap" style="border-radius: 30px;">
                                            <i class="fa-solid fa-circle-check me-1"></i> Tahun Berjalan (Aktif)
                                        </span>
                                    <?php elseif ($currentYearMeta['status'] === 'archived'): ?>
                                        <span class="badge bg-secondary text-white px-3 py-1 text-nowrap" style="border-radius: 30px;">
                                            <i class="fa-solid fa-box-archive me-1"></i> Arsip (Tutup Buku)
                                        </span>
                                    <?php elseif ($currentYearMeta['status'] === 'planning'): ?>
                                        <span class="badge bg-info text-dark px-3 py-1 text-nowrap" style="border-radius: 30px;">
                                            <i class="fa-solid fa-compass me-1"></i> Perencanaan (Proyeksi)
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h3 class="fw-bold mb-1" style="color: #0A1628; letter-spacing: -0.3px;"><?= htmlspecialchars($currentYearMeta['title']) ?></h3>
                                <p class="mb-2 fs-14" style="color: #1e293b; font-weight: 600; line-height: 1.45;">
                                    Bekerja dengan integritas dan profesionalisme demi terwujudnya spektrum frekuensi radio yang tertib dan berkualitas.
                                </p>
                            </div>
                            <div class="col-lg-4 d-none d-lg-flex justify-content-end align-items-center">
                                <div class="text-center p-3 rounded-4 kpi-percentage-box" style="min-width: 210px; background: rgba(255, 255, 255, 0.92); border: 2px solid rgba(245, 158, 11, 0.4); box-shadow: 0 2px 8px rgba(10, 22, 40, 0.05); backdrop-filter: blur(8px);">
                                    <span class="fs-11 text-uppercase fw-bold d-block text-muted">Capaian PK <?= $selectedYear ?></span>
                                    <h1 class="fw-bold my-1 kpi-percentage-number" style="font-size: 2.4rem; letter-spacing: -0.5px; color: #0A1628 !important;"><?= $averagePkScore ?>%</h1>
                                    <span class="fs-12 fw-semibold text-kpi-sub" style="color: #0A1628;">
                                        <i class="fa fa-circle-check text-success me-1"></i><?= $pkAchievedCount ?> dari <?= $pkCount ?> Indikator Memenuhi
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Rekap Setiap PK Langsung Tanpa Box (1 Baris Rapi & Divider Tipis) -->
                        <!-- Rekap Setiap PK 1 Baris Presisi (8 Kolom Merata Tanpa Terpotong) -->
                        <div class="pt-3 mt-3 border-top overflow-x-auto pb-1" style="border-color: rgba(10, 22, 40, 0.15) !important;">
                            <div class="d-grid" style="grid-template-columns: repeat(8, minmax(105px, 1fr)); width: 100%;">
                                <?php 
                                $rekapShortTitles = [
                                    'PK.1' => 'MONITORING SFR',
                                    'PK.2' => 'PEMERIKSAAN STN',
                                    'PK.3' => 'GANGGUAN SFR',
                                    'PK.4' => 'MONITORING APT',
                                    'PK.5' => 'TERTIB APT',
                                    'PK.6' => 'SLA SMFR',
                                    'PK.7' => 'SANKSI DENDA',
                                    'PK.8' => 'QOS',
                                ];
                                $totalItems = count($perjanjianKinerja);
                                foreach ($perjanjianKinerja as $idx => $pk): 
                                    $code = $pk['code'];
                                    $shortTitle = ($idx + 1) . '. ' . ($rekapShortTitles[$code] ?? strtoupper(mb_strimwidth($pk['title'], 0, 16, '...')));
                                    $isMet = (float)$pk['realization'] >= (float)$pk['target_annual'];
                                    $valFormatted = rtrim(rtrim(number_format((float)$pk['realization'], 1, ',', '.'), '0'), ',');
                                    
                                    // Pemisahan unit utama (%) dan label satuan tambahan (Kab/Kota, SLA) agar rapi & tidak terpotong
                                    $hasPercent = str_contains($pk['unit'], '%');
                                    $extraUnit = trim(str_replace('%', '', $pk['unit']));
                                ?>
                                    <div class="px-1 px-xl-2 py-1 <?= ($idx < $totalItems - 1) ? 'border-end' : '' ?>" style="border-color: rgba(10, 22, 40, 0.15) !important;">
                                        <a href="pk_view.php?code=<?= $code ?>&year=<?= $selectedYear ?>" class="text-decoration-none d-block" title="Buka Rekap & Capaian Bulanan <?= htmlspecialchars($pk['title']) ?>">
                                            <span class="d-block text-uppercase fw-bold text-truncate" style="font-size: 9px; color: rgba(10, 22, 40, 0.75); letter-spacing: -0.1px;">
                                                <?= htmlspecialchars($shortTitle) ?>
                                            </span>
                                            <div class="d-flex flex-wrap align-items-baseline gap-1 my-0">
                                                <span class="fw-bold" style="font-size: 0.98rem; letter-spacing: -0.2px; color: <?= $isMet ? '#064E3B' : '#0A1628' ?> !important;">
                                                    <?= $valFormatted ?><?= $hasPercent ? '%' : htmlspecialchars($pk['unit']) ?>
                                                </span>
                                                <?php if ($extraUnit): ?>
                                                    <small style="font-size: 8px; font-weight: 600; color: rgba(10, 22, 40, 0.65);"><?= htmlspecialchars($extraUnit) ?></small>
                                                <?php endif; ?>
                                                <?php if ($isMet): ?>
                                                    <i class="fa fa-check text-success" style="font-size: 8.5px;" title="Target Terpenuhi"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-1" style="line-height: 1.25;">
                                                <div style="font-size: 8.5px; color: rgba(10, 22, 40, 0.7); font-weight: 500; white-space: nowrap;">
                                                    Target: <?= $pk['target_annual'] ?><?= $hasPercent ? '%' : htmlspecialchars($pk['unit']) ?><?= $extraUnit ? ' ' . htmlspecialchars($extraUnit) : '' ?>
                                                </div>
                                                <div style="font-size: 8.5px; font-weight: 700; color: <?= $isMet ? '#064E3B' : 'rgba(10, 22, 40, 0.65)' ?>;">
                                                    <?= htmlspecialchars($pk['status_label']) ?>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Top KPI Stat Cards -->
        <div class="row">
            <div class="col-xl-3 col-sm-6">
                <div class="card kpi-card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <span class="text-muted fs-12 fw-semibold text-uppercase">Capaian PK <?= $selectedYear ?></span>
                                <h3 class="fw-bold my-1 text-primary"><?= $averagePkScore ?>% <small class="fs-13 text-muted">Rata-rata</small></h3>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary" style="width: 50px; height: 50px;">
                                <i class="fa-solid fa-award fa-lg"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between fs-12 text-muted mb-1">
                            <span>Evaluasi UPT <?= $selectedYear ?></span>
                            <span class="fw-bold text-primary"><?= $pkCount ?> Indikator Sasaran</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= min(100, $averagePkScore) ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="card kpi-card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <span class="text-muted fs-12 fw-semibold text-uppercase">Memenuhi Target</span>
                                <h3 class="fw-bold my-1 text-success"><?= $pkAchievedCount ?> <small class="fs-13 text-muted">/ <?= $pkCount ?> Indikator</small></h3>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success" style="width: 50px; height: 50px;">
                                <i class="fa-solid fa-circle-check fa-lg"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between fs-12 text-muted mb-1">
                            <span>Status Kinerja <?= $selectedYear ?></span>
                            <span class="fw-bold text-success">Target Tercapai</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pkCount > 0 ? round(($pkAchievedCount / $pkCount)*100, 1) : 0 ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="card kpi-card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <span class="text-muted fs-12 fw-semibold text-uppercase">Dalam Pemantauan</span>
                                <h3 class="fw-bold my-1 text-warning"><?= $pkCount - $pkAchievedCount ?> <small class="fs-13 text-muted">/ <?= $pkCount ?> Indikator</small></h3>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning" style="width: 50px; height: 50px;">
                                <i class="fa-solid fa-clock-rotate-left fa-lg"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between fs-12 text-muted mb-1">
                            <span>Percepatan Kinerja</span>
                            <span class="fw-bold text-warning"><?= $currentYearMeta['status'] === 'archived' ? 'Arsip Final' : 'Periode Berjalan' ?></span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $pkCount > 0 ? round((($pkCount - $pkAchievedCount) / $pkCount)*100, 1) : 0 ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="card kpi-card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <span class="text-muted fs-12 fw-semibold text-uppercase">Agenda SPT Lapangan</span>
                                <h3 class="fw-bold my-1 text-info"><?= $activeSchedules ?> <small class="fs-13 text-muted">/ <?= $totalSchedules ?> Penugasan</small></h3>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info" style="width: 50px; height: 50px;">
                                <i class="fa-solid fa-calendar-check fa-lg"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between fs-12 text-muted mb-1">
                            <span>Jadwal Mendatang</span>
                            <span class="fw-bold text-info"><i class="fa-solid fa-clock me-1"></i> <?= $upcomingSchedulesCount ?> Terjadwal</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: <?= $totalSchedules > 0 ? round(($activeSchedules / $totalSchedules)*100, 1) : 0 ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 1: INDIKATOR PERJANJIAN KINERJA (PK) MULTI-TAHUN -->
        <div class="row mb-3 mt-3" id="section-pk-indikator">
            <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-award me-2 text-warning"></i> <?= $pkCount ?> Indikator Perjanjian Kinerja (PK) UPT Tahun <?= $selectedYear ?></h4>
                        <span class="badge badge-primary light font-w600 fs-11"><?= htmlspecialchars($currentYearMeta['legal_basis']) ?></span>
                        <?php if ($currentYearMeta['status'] === 'archived'): ?>
                            <span class="badge badge-secondary light font-w600 fs-11"><i class="fa fa-box-archive me-1"></i> Arsip Tertutup</span>
                        <?php elseif ($currentYearMeta['status'] === 'active'): ?>
                            <span class="badge badge-success light font-w600 fs-11"><i class="fa fa-circle-check me-1"></i> Aktif Berjalan</span>
                        <?php else: ?>
                            <span class="badge badge-info light font-w600 fs-11"><i class="fa fa-compass me-1"></i> Perencanaan</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted fs-13 mb-0">Sasaran Kegiatan: <em>Meningkatnya Kepatuhan dan Kualitas Pengendalian Spektrum Frekuensi Radio, Perangkat Telekomunikasi dan Layanan Telekomunikasi</em></p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <!-- Dropdown Pilihan Tahun PK (Tepat di Atas Box PK.1 dan PK Lainnya) -->
                    <div class="dropdown">
                        <button class="btn btn-primary btn-sm rounded-pill shadow-sm fw-bold px-3 py-1.5 d-flex align-items-center gap-2" 
                                type="button" id="dropdownYearPkBoxes" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.82rem;">
                            <i class="fa-solid fa-calendar-days"></i>
                            <span>Tahun PK: <strong><?= $selectedYear ?></strong></span>
                            <span class="badge bg-white text-primary rounded-pill px-2 py-0.5 fs-10">
                                <?= $currentYearMeta['status'] === 'active' ? 'Aktif' : ($currentYearMeta['status'] === 'archived' ? 'Arsip' : 'Rencana') ?>
                            </span>
                            <i class="fa-solid fa-chevron-down fs-10 opacity-75"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-2 rounded-3" aria-labelledby="dropdownYearPkBoxes" style="min-width: 230px;">
                            <li class="dropdown-header text-uppercase fs-10 fw-bold text-muted py-1 px-3">
                                <i class="fa-solid fa-calendar-alt text-primary me-1"></i> Pilih Tahun Anggaran (PK)
                            </li>
                            <?php foreach ($availableYears as $ay): 
                                $isCur = ($selectedYear == $ay['year']);
                            ?>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center justify-content-between py-2 px-3 fs-12 <?= $isCur ? 'active fw-bold' : '' ?>" 
                                       href="index.php?year=<?= $ay['year'] ?>">
                                        <span class="d-flex align-items-center">
                                            <i class="fa-solid <?= $isCur ? 'fa-circle-check text-success' : 'fa-circle-dot text-muted' ?> me-2 fs-11"></i>
                                            Tahun <?= $ay['year'] ?>
                                        </span>
                                        <span class="badge <?= $ay['status'] === 'active' ? 'bg-success text-white' : ($ay['status'] === 'archived' ? 'bg-secondary text-white' : 'bg-info text-dark') ?> fs-10">
                                            <?= $ay['status'] === 'active' ? 'Aktif' : ($ay['status'] === 'archived' ? 'Arsip' : 'Rencana') ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Dropdown 8 Indikator Kinerja -->
                    <div class="dropdown">
                        <button class="btn btn-outline-primary btn-sm rounded-pill dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="material-symbols-outlined align-middle fs-16 me-1">tune</i> Navigasi Indikator
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-2" style="border-radius: 14px; min-width: 320px;">
                            <li class="dropdown-header text-uppercase fs-10 fw-bold text-muted px-3 py-1">Pilih Indikator Kinerja (PK)</li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <?php foreach ($perjanjianKinerja as $pkNav): ?>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center justify-content-between py-2 px-3" href="pk_view.php?code=<?= $pkNav['code'] ?>&year=<?= $selectedYear ?>">
                                        <div class="d-flex align-items-center me-2 text-truncate">
                                            <span class="badge badge-<?= $pkNav['status_class'] ?> light me-2 fs-11"><?= $pkNav['code'] ?></span>
                                            <span class="fs-12 fw-semibold text-truncate"><?= htmlspecialchars($pkNav['title']) ?></span>
                                        </div>
                                        <span class="badge badge-<?= $pkNav['status_class'] ?> fs-11"><?= $pkNav['realization'] ?><?= $pkNav['unit'] ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalInfoNotaDinas">
                        <i class="fa fa-book-open me-1"></i> Info Nota Dinas & Rujukan
                    </button>
                    <?php if (in_array($currentUser['role'], ['admin', 'koordinator', 'kepala_balai'])): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" onclick="openCopyYearModal()">
                            <i class="fa fa-copy me-1"></i> Salin/Buka Tahun Baru
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm rounded-pill" onclick="openAddIndicatorModal(<?= $selectedYear ?>)">
                            <i class="fa fa-plus-circle me-1"></i> Tambah Indikator
                        </button>
                        <?php if ($pkCount > 0): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" onclick="openEditAllPkModal()">
                                <i class="fa fa-pen-to-square me-1"></i> Perbarui Realisasi <?= $selectedYear ?>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Grid Kartu Indikator PK Tahun Terpilih -->
        <div class="row mb-4">
            <?php if ($pkCount === 0): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm text-center p-5" style="border-radius: 18px;">
                        <div class="my-3">
                            <i class="fa-solid fa-folder-open text-muted fa-3x mb-3"></i>
                            <h5 class="fw-bold text-dark">Belum Ada Indikator PK untuk Tahun <?= $selectedYear ?></h5>
                            <p class="text-muted fs-13">Tahun anggaran ini belum memiliki indikator sasaran kinerja.</p>
                            <?php if (in_array($currentUser['role'], ['admin', 'koordinator', 'kepala_balai'])): ?>
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-4" onclick="openCopyYearModal()">
                                        <i class="fa fa-copy me-1"></i> Salin Indikator dari Tahun 2026
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-4" onclick="openAddIndicatorModal(<?= $selectedYear ?>)">
                                        <i class="fa fa-plus-circle me-1"></i> Tambah Indikator Manual
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($perjanjianKinerja as $pk): ?>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card h-100 border-0 shadow-sm kpi-card position-relative overflow-hidden" style="border-radius: 16px;">
                            <div class="card-body p-3 d-flex flex-column">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge badge-primary px-2 py-1 fw-bold fs-12"><?= htmlspecialchars($pk['code']) ?></span>
                                    <span class="badge badge-<?= $pk['status_class'] ?> light fs-11">
                                        <i class="fa fa-circle fs-8 me-1"></i><?= $pk['status_label'] ?>
                                    </span>
                                </div>
                                
                                <h6 class="fw-bold mb-1 fs-13 text-dark text-truncate" title="<?= htmlspecialchars($pk['title']) ?>">
                                    <?= htmlspecialchars($pk['title']) ?>
                                </h6>
                                <p class="text-muted fs-11 mb-2" style="min-height: 32px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4;" title="<?= htmlspecialchars($pk['indicator']) ?>">
                                    <?= htmlspecialchars($pk['indicator']) ?>
                                </p>

                                <div class="p-2 rounded-3 bg-light mb-2">
                                    <div class="d-flex justify-content-between align-items-center fs-11 mb-1">
                                        <span class="text-muted">Target <?= $selectedYear ?>:</span>
                                        <strong class="text-dark"><?= $pk['target_annual'] ?><?= $pk['unit'] ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center fs-11">
                                        <span class="text-muted">Realisasi:</span>
                                        <strong class="text-<?= $pk['status_class'] ?> fs-14"><?= $pk['realization'] ?><?= $pk['unit'] ?></strong>
                                    </div>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar bg-<?= $pk['status_class'] ?>" role="progressbar" style="width: <?= $pk['percent_of_target'] ?>%"></div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center fs-11 text-muted mb-2">
                                    <span class="badge badge-xs badge-light border text-truncate" style="max-width: 140px;" title="Aplikasi Rujukan: <?= htmlspecialchars($pk['app_reference']) ?>">
                                        <i class="fa fa-laptop-code me-1 text-primary"></i><?= htmlspecialchars($pk['app_reference']) ?>
                                    </span>
                                    <span class="fw-bold text-dark fs-11"><?= $pk['percent_of_target'] ?>% Capaian</span>
                                </div>

                                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                    <div class="d-flex gap-1">
                                        <a href="pk_view.php?code=<?= $pk['code'] ?>&year=<?= $selectedYear ?>" class="btn btn-xs btn-primary rounded-pill px-2 text-white" title="Buka Rekap Bulanan & Capaian Kinerja <?= $pk['code'] ?>">
                                            <i class="fa fa-chart-line me-1"></i> Rekap
                                        </a>
                                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2" onclick="showPkDetail(<?= htmlspecialchars(json_encode($pk)) ?>)" title="Info Singkat">
                                            <i class="fa fa-circle-info"></i>
                                        </button>
                                    </div>
                                    <?php if (in_array($currentUser['role'], ['admin', 'koordinator', 'kepala_balai'])): ?>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-xs btn-light rounded-circle text-primary" onclick="editSinglePk(<?= htmlspecialchars(json_encode($pk)) ?>)" title="Ubah Angka Realisasi">
                                                <i class="fa fa-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-xs btn-light rounded-circle text-secondary" onclick="editIndicatorFull(<?= htmlspecialchars(json_encode($pk)) ?>)" title="Edit Indikator Lengkap (Target, Judul, Formula)">
                                                <i class="fa fa-gear"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Multi-Year Year-on-Year Tracking & Comparison Table -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border" style="border-radius: 16px; background: #ffffff; border: 1px solid rgba(10, 22, 40, 0.08) !important; box-shadow: 0 1px 4px rgba(10, 22, 40, 0.03);">
                    <div class="card-header border-0 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-timeline text-primary me-2"></i> Pelacakan & Perbandingan Capaian PK Antar-Tahun</h5>
                            <small class="text-muted">Tracking historis perkembangan Perjanjian Kinerja Balmon Kelas I Samarinda dari tahun ke tahun</small>
                        </div>
                        <div class="d-flex gap-1">
                            <?php foreach ($yearSummaries as $ys): ?>
                                <a href="index.php?year=<?= $ys['year'] ?>" class="btn btn-xs rounded-pill <?= $selectedYear == $ys['year'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
                                    Tahun <?= $ys['year'] ?> (<?= $ys['average_score'] ?>%)
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-13">
                                <thead class="bg-light text-uppercase text-muted fs-11">
                                    <tr>
                                        <th>Tahun Anggaran</th>
                                        <th>Nama Perjanjian Kinerja</th>
                                        <th>Dasar Hukum / Regulasi</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Jumlah Indikator</th>
                                        <th class="text-center">Target Memenuhi</th>
                                        <th style="min-width: 180px;">Rata-rata Capaian</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($yearSummaries as $ys): ?>
                                        <tr class="<?= $selectedYear == $ys['year'] ? 'table-primary bg-opacity-10 fw-bold' : '' ?>">
                                            <td>
                                                <span class="badge <?= $selectedYear == $ys['year'] ? 'badge-primary' : 'badge-light border text-dark' ?> fs-12 px-3 py-2 font-monospace">
                                                    <?= $ys['year'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="text-dark fw-bold"><?= htmlspecialchars($ys['title']) ?></div>
                                            </td>
                                            <td>
                                                <span class="text-muted fs-12"><i class="fa fa-file-contract me-1"></i><?= htmlspecialchars($ys['legal_basis']) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($ys['status'] === 'active'): ?>
                                                    <span class="badge badge-success light fs-11 px-2 py-1"><i class="fa fa-circle-check me-1"></i> Aktif Berjalan</span>
                                                <?php elseif ($ys['status'] === 'archived'): ?>
                                                    <span class="badge badge-secondary light fs-11 px-2 py-1"><i class="fa fa-box-archive me-1"></i> Arsip Final</span>
                                                <?php else: ?>
                                                    <span class="badge badge-info light fs-11 px-2 py-1"><i class="fa fa-compass me-1"></i> Perencanaan</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center fw-bold"><?= $ys['count'] ?> Sasaran</td>
                                            <td class="text-center text-success fw-bold"><?= $ys['achieved'] ?> / <?= $ys['count'] ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 7px;">
                                                        <div class="progress-bar <?= $ys['average_score'] >= 80 ? 'bg-success' : 'bg-primary' ?>" style="width: <?= min(100, $ys['average_score']) ?>%"></div>
                                                    </div>
                                                    <span class="fw-bold fs-12" style="min-width: 45px;"><?= $ys['average_score'] ?>%</span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <a href="index.php?year=<?= $ys['year'] ?>" class="btn btn-xs <?= $selectedYear == $ys['year'] ? 'btn-primary disabled' : 'btn-outline-primary' ?> rounded-pill px-3">
                                                    <?= $selectedYear == $ys['year'] ? 'Sedang Dilihat' : 'Buka Dashboard' ?>
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

        <!-- Charts Row: Tren Bulanan & Verifikasi -->
        <div class="row">
            <div class="col-xl-8 col-lg-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0">Tren Realisasi Kegiatan Monitoring Bulanan (Tahun 2026)</h5>
                            <small class="text-muted">Grafik perkembangan pelaksanaan monitoring di lapangan per bulan</small>
                        </div>
                        <span class="badge badge-primary light">Tahun Berjalan 2026</span>
                    </div>
                    <div class="card-body">
                        <div id="chart-monthly-trend" style="min-height: 290px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-0 pb-0">
                        <h5 class="fw-bold mb-0">Status Verifikasi Laporan</h5>
                        <small class="text-muted">Distribusi kelayakan pelaporan</small>
                    </div>
                    <div class="card-body">
                        <div id="chart-verification-radial" style="min-height: 230px;"></div>
                        <div class="row text-center mt-3 pt-2 border-top">
                            <div class="col-4">
                                <span class="fs-11 text-muted d-block">Terverifikasi</span>
                                <strong class="fs-14 text-success"><?= $totalVerified ?></strong>
                            </div>
                            <div class="col-4">
                                <span class="fs-11 text-muted d-block">Menunggu</span>
                                <strong class="fs-14 text-warning"><?= $totalPending ?></strong>
                            </div>
                            <div class="col-4">
                                <span class="fs-11 text-muted d-block">Revisi</span>
                                <strong class="fs-14 text-danger"><?= $totalRevision ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: KATEGORI KEGIATAN MONITORING -->
        <div class="row mb-2" id="section-kegiatan-lapangan">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i> <?= count($categories) ?> Kategori Kegiatan Monitoring</h4>
                        <span class="badge badge-secondary light font-w600 fs-11">Operasional Pemantauan Spektrum</span>
                    </div>
                    <p class="text-muted fs-13 mb-3">Aktivitas teknis pemantauan dan pengawasan spektrum frekuensi radio di Balmon Samarinda</p>
                </div>
                <a href="reports.php" class="btn btn-outline-primary btn-sm rounded-pill">
                    Lihat Semua Data <i class="fa fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <div class="row">
            <?php 
            $catPages = [
                1 => 'monitoring_spektrum.php',
                2 => 'monitoring_microwavelink.php',
                3 => 'monitoring_radio_fm.php',
                4 => 'monitoring_tv_digital.php',
                5 => 'monitoring_hf.php',
                6 => 'monitoring_alat.php',
                7 => 'monitoring_penertiban_frekuensi.php',
                8 => 'monitoring_penertiban_alat.php',
                9 => 'monitoring_penugasan_lainnya.php',
                10 => 'monitoring_penanganan_gangguan.php',
                11 => 'monitoring_kualitas_layanan.php',
            ];
            foreach ($categories as $cat): 
                $pageLink = $catPages[$cat['id']] ?? 'reports.php';
            ?>
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm kpi-card">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="rounded-3 d-flex align-items-center justify-content-center bg-<?= $cat['badge_color'] ?> bg-opacity-10 text-<?= $cat['badge_color'] ?>" style="width: 46px; height: 46px;">
                                    <i class="material-symbols-outlined" style="font-size: 24px;"><?= htmlspecialchars($cat['material_icon'] ?? 'sensors') ?></i>
                                </div>
                                <span class="badge badge-<?= $cat['badge_color'] ?> light fs-11">
                                    Target: <?= $cat['target_annual'] ?> <?= $cat['unit'] ?>
                                </span>
                            </div>

                            <h5 class="fw-bold mb-1 text-truncate" title="<?= htmlspecialchars($cat['name']) ?>">
                                <?= htmlspecialchars($cat['short_title']) ?>
                            </h5>
                            <p class="text-muted fs-12 mb-3 text-truncate" title="<?= htmlspecialchars($cat['description']) ?>">
                                <?= htmlspecialchars($cat['description']) ?>
                            </p>

                            <div class="mt-auto">
                                <div class="d-flex align-items-center justify-content-between fs-12 mb-1">
                                    <span>Capaian: <strong><?= $cat['realized'] ?> <?= $cat['unit'] ?></strong></span>
                                    <span class="fw-bold text-<?= $cat['badge_color'] ?>"><?= $cat['percentage'] ?>%</span>
                                </div>
                                <div class="progress mb-3" style="height: 6px;">
                                    <div class="progress-bar bg-<?= $cat['badge_color'] ?>" role="progressbar" style="width: <?= $cat['percentage'] ?>%"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted fs-11">
                                        <i class="fa fa-check text-success me-1"></i><?= $cat['verified'] ?> Terverifikasi
                                    </small>
                                    <a href="<?= $pageLink ?>" class="btn btn-xs btn-<?= $cat['badge_color'] ?> rounded-pill px-3">
                                        Kelola Menu <i class="fa fa-angle-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Reports & Active Schedules -->
        <div class="row">
            <!-- Recent Reports Table -->
            <div class="col-xl-8 col-lg-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0">Laporan Monitoring Terkini</h5>
                            <small class="text-muted">Aktivitas pelaporan terbaru dari petugas lapangan</small>
                        </div>
                        <a href="reports.php" class="btn btn-xs btn-primary rounded-pill">Lihat Semua</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle table-nowrap data-table-balmon mb-0">
                                <thead class="bg-light fs-12 text-uppercase text-muted">
                                    <tr>
                                        <th class="ps-4">Kode / Kegiatan</th>
                                        <th>Target / Frekuensi</th>
                                        <th>Lokasi</th>
                                        <th>Petugas</th>
                                        <th>Status</th>
                                        <th class="text-center pe-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13">
                                    <?php foreach ($recentReports as $rep): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <span class="badge badge-<?= $rep['badge_color'] ?> light mb-1 text-nowrap"><?= htmlspecialchars($rep['short_title']) ?></span>
                                                <div class="fw-bold text-primary font-monospace text-nowrap"><?= htmlspecialchars($rep['report_code']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold truncate-md" title="<?= htmlspecialchars($rep['target_name']) ?>"><?= htmlspecialchars($rep['target_name']) ?></div>
                                                <div class="text-muted fs-12 text-nowrap"><i class="fa fa-wave-square me-1"></i><?= htmlspecialchars($rep['frequency'] ?: '-') ?></div>
                                            </td>
                                            <td>
                                                <div class="truncate-sm" title="<?= htmlspecialchars($rep['location']) ?>">
                                                    <i class="fa fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($rep['location']) ?>
                                                </div>
                                                <small class="text-muted text-nowrap"><?= date('d M Y', strtotime($rep['date_monitored'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center text-nowrap">
                                                    <img src="<?= htmlspecialchars($rep['officer_avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle me-2" width="28" height="28" alt="">
                                                    <span class="fs-12 fw-semibold truncate-sm" title="<?= htmlspecialchars($rep['officer_name']) ?>"><?= htmlspecialchars($rep['officer_name']) ?></span>
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
                                            <td class="text-center pe-4 text-nowrap">
                                                <a href="report_detail.php?id=<?= $rep['id'] ?>" class="btn btn-primary btn-xs sharp me-1" title="Lihat Detail Teknis">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                                <?php if ($rep['status'] === 'submitted' && in_array($currentUser['role'], ['admin', 'koordinator', 'kepala_balai'])): ?>
                                                    <a href="verification.php?verify_id=<?= $rep['id'] ?>" class="btn btn-success btn-xs sharp" title="Verifikasi Sekarang">
                                                        <i class="fa fa-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Schedules (SPT) -->
            <div class="col-xl-4 col-lg-12">
                <!-- Upcoming Schedules -->
                <div class="card border-0 shadow-sm h-100 mb-4" style="border-radius: 16px;">
                    <div class="card-header border-0 pb-1 pt-3 px-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0 text-dark d-inline-block align-middle fs-14">Agenda Monitoring Lapangan</h5>
                            <span class="badge badge-primary light fs-10 rounded-pill ms-1"><?= $totalSchedules ?> SPT</span>
                        </div>
                        <a href="schedules.php" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 text-nowrap" style="font-size: 11px;">
                            Lihat Semua <i class="fa fa-angle-right ms-0.5"></i>
                        </a>
                    </div>
                    <div class="card-body p-3 pt-2">
                        <?php if (empty($upcomingSchedules)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fa fa-calendar-xmark fa-2x mb-2 text-muted"></i>
                                <p class="fs-12 mb-0">Belum ada agenda penugasan aktif.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcomingSchedules as $sched): ?>
                                <?php
                                $statusBorderClass = ($sched['status'] === 'in_progress') ? 'border-warning' : (($sched['status'] === 'completed') ? 'border-success' : 'border-info');
                                $statusBgClass = ($sched['status'] === 'in_progress') ? 'rgba(245, 158, 11, 0.05)' : (($sched['status'] === 'completed') ? 'rgba(16, 185, 129, 0.04)' : 'rgba(14, 165, 233, 0.04)');
                                ?>
                                <div class="mb-2 rounded-3 border-start border-3 <?= $statusBorderClass ?> p-2.5 px-3" style="background: <?= $statusBgClass ?>; border: 1px solid rgba(0,0,0,0.06); transition: all 0.2s ease;">
                                    <div class="d-flex justify-content-between align-items-center mb-1 gap-2">
                                        <div class="d-flex align-items-center gap-1.5 overflow-hidden">
                                            <span class="font-monospace fw-bold text-primary fs-11 text-truncate" title="<?= htmlspecialchars($sched['spt_number']) ?>" style="max-width: 150px;">
                                                <?= htmlspecialchars($sched['spt_number']) ?>
                                            </span>
                                            <?php if (!empty($sched['spt_date'])): ?>
                                                <span class="badge bg-light border text-primary px-1.5 py-0 fs-9 fw-semibold text-nowrap d-inline-flex align-items-center" title="Tanggal Keluar / Ditetapkan SPT">
                                                    <i class="material-symbols-outlined fs-11 align-middle me-0.5">event_available</i>Keluar: <?= date('d M Y', strtotime($sched['spt_date'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-muted fw-semibold fs-11 text-nowrap">
                                            <i class="fa-regular fa-calendar me-1 text-muted fs-10"></i><?= date('d M', strtotime($sched['start_date'])) ?> - <?= date('d M Y', strtotime($sched['end_date'])) ?>
                                        </span>
                                    </div>
                                    <div class="fw-bold text-dark fs-12 mb-1" style="line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= htmlspecialchars($sched['title']) ?>">
                                        <?= htmlspecialchars($sched['title']) ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center text-muted fs-11 mb-1.5 gap-2">
                                        <span class="text-truncate text-muted" title="<?= htmlspecialchars($sched['location']) ?>">
                                            <i class="fa fa-map-pin text-danger me-1"></i><?= htmlspecialchars($sched['location']) ?>
                                        </span>
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            <?php foreach ($sched['cats_list'] as $catBadge): ?>
                                                <span class="badge badge-light border text-primary px-1.5 py-0.5 fs-10 text-nowrap"><?= htmlspecialchars($catBadge['short_title']) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center pt-1 border-top border-light fs-11 text-muted">
                                        <span class="text-truncate" style="max-width: 170px;">
                                            Ketua: <strong class="text-dark fw-semibold" title="<?= htmlspecialchars($sched['leader_name']) ?>"><?= htmlspecialchars($sched['leader_name']) ?></strong>
                                        </span>
                                        <span class="badge badge-xs badge-<?= $sched['status'] === 'in_progress' ? 'warning' : ($sched['status'] === 'completed' ? 'success' : 'info') ?> light text-uppercase fw-bold" style="font-size: 9.5px;">
                                            <?= $sched['status'] === 'in_progress' ? 'Berjalan' : ($sched['status'] === 'completed' ? 'Selesai' : 'Terjadwal') ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Indikator PK -->
<div class="modal fade" id="modalDetailPK" tabindex="-1" aria-labelledby="modalDetailPKLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <div class="d-flex align-items-center">
                    <span class="badge bg-white text-primary fw-bold me-2 fs-12" id="pkDetailCode">PK</span>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="pkDetailTitle">Detail Indikator Perjanjian Kinerja</h5>
                        <small class="text-white text-opacity-75 fs-11">Nota Dinas Komdigi No. 224/DJID.6/PR.04.01/02/2026</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <span class="text-muted fs-12 fw-semibold text-uppercase d-block mb-1">Indikator Kinerja Sasaran Kegiatan (IKSK)</span>
                    <p class="fs-14 fw-bold text-dark mb-0 p-3 bg-light rounded-3 border-start border-4 border-primary" id="pkDetailIndicator">-</p>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Target Resmi Tahun 2026</span>
                            <h4 class="fw-bold text-primary my-1" id="pkDetailTarget">-</h4>
                            <small class="text-muted fs-11" id="pkDetailTargetSub">Sesuai Perjanjian Kinerja UPT</small>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Capaian Realisasi Saat Ini</span>
                            <h4 class="fw-bold text-success my-1" id="pkDetailRealization">-</h4>
                            <small class="text-muted fs-11" id="pkDetailPercentOfTarget">Pemenuhan Target</small>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <span class="text-muted fs-12 fw-semibold text-uppercase d-block mb-1">Metode & Formula Pengukuran</span>
                    <div class="p-3 bg-light rounded-3 font-monospace fs-12 text-dark border">
                        <i class="fa fa-calculator text-primary me-2"></i><span id="pkDetailFormula">-</span>
                    </div>
                </div>

                <div class="mb-3">
                    <span class="text-muted fs-12 fw-semibold text-uppercase d-block mb-1">Manual Pengukuran & Parameter Teknis</span>
                    <div class="p-3 bg-light rounded-3 fs-12 text-secondary border" id="pkDetailManual" style="line-height: 1.6;">
                        -
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <span class="text-muted fs-12 fw-semibold text-uppercase d-block mb-1">Aplikasi Rujukan Pelaporan</span>
                        <div class="p-2 px-3 bg-light rounded-3 fs-12 border fw-semibold text-primary">
                            <i class="fa fa-laptop-code me-2"></i><span id="pkDetailApp">-</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted fs-12 fw-semibold text-uppercase d-block mb-1">Wilayah / Catatan Khusus UPT</span>
                        <div class="p-2 px-3 bg-light rounded-3 fs-12 border text-muted">
                            <i class="fa fa-info-circle me-2"></i><span id="pkDetailNotes">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light py-2 px-4">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Informasi Dokumen Nota Dinas & Rujukan Sesuai Tahun Terpilih -->
<div class="modal fade" id="modalInfoNotaDinas" tabindex="-1" aria-labelledby="modalInfoNotaDinasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-file-contract text-warning fa-lg me-2"></i>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="modalInfoNotaDinasLabel">Informasi Dokumen Penetapan PK Tahun <?= $selectedYear ?></h5>
                        <small class="text-white text-opacity-75 fs-11">Kementerian Komunikasi dan Digital RI &bull; UPT Balmon Kelas I Samarinda</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 fs-13" style="line-height: 1.6;">
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted" style="width: 170px;">Tahun Anggaran</td><td>: <span class="badge badge-primary px-2 font-monospace fs-12"><?= $selectedYear ?></span></td></tr>
                        <tr><td class="text-muted">Judul Penetapan</td><td>: <strong><?= htmlspecialchars($currentYearMeta['title']) ?></strong></td></tr>
                        <tr><td class="text-muted">Dasar Hukum / Dokumen</td><td>: <strong class="text-primary"><?= htmlspecialchars($currentYearMeta['legal_basis']) ?></strong></td></tr>
                        <tr><td class="text-muted">Status Siklus PK</td><td>: 
                            <?php if ($currentYearMeta['status'] === 'active'): ?>
                                <span class="badge badge-success light font-w600"><i class="fa fa-circle-check me-1"></i> Aktif Berjalan (Current Active TA)</span>
                            <?php elseif ($currentYearMeta['status'] === 'archived'): ?>
                                <span class="badge badge-secondary light font-w600"><i class="fa fa-box-archive me-1"></i> Arsip Tertutup (Archived Historical)</span>
                            <?php else: ?>
                                <span class="badge badge-info light font-w600"><i class="fa fa-compass me-1"></i> Tahap Perencanaan (Planning / Projection)</span>
                            <?php endif; ?>
                        </td></tr>
                        <?php if (!empty($currentYearMeta['description'])): ?>
                            <tr><td class="text-muted">Keterangan / Catatan</td><td>: <?= htmlspecialchars($currentYearMeta['description']) ?></td></tr>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-primary mb-0"><i class="fa-solid fa-bullseye me-1"></i> Rincian Indikator Kinerja Sasaran Kegiatan TA <?= $selectedYear ?> (<?= count($perjanjianKinerja) ?> Indikator)</h6>
                </div>
                <p class="text-muted fs-12 mb-3">Sasaran Kegiatan: <strong>Meningkatnya Kepatuhan dan Kualitas Pengendalian Spektrum Frekuensi Radio, Perangkat Telekomunikasi dan Layanan Telekomunikasi</strong></p>

                <div class="table-responsive border rounded-3 mb-3">
                    <table class="table table-hover table-sm align-middle mb-0 fs-12">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th style="width: 70px;">Kode</th>
                                <th>Indikator Sasaran Kinerja</th>
                                <th style="width: 130px;">Target Resmi</th>
                                <th style="width: 120px;">Realisasi</th>
                                <th>Rumus / Formula</th>
                                <th>Aplikasi Rujukan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($perjanjianKinerja)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada indikator terdaftar pada tahun <?= $selectedYear ?>.</td></tr>
                            <?php else: ?>
                                <?php foreach ($perjanjianKinerja as $pk): ?>
                                    <tr>
                                        <td><span class="badge badge-primary font-monospace"><?= htmlspecialchars($pk['code']) ?></span></td>
                                        <td>
                                            <strong class="d-block text-dark"><?= htmlspecialchars($pk['title']) ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($pk['indicator']) ?></small>
                                        </td>
                                        <td><span class="badge badge-light border fw-bold"><?= $pk['target_annual'] ?> <?= htmlspecialchars($pk['unit']) ?></span></td>
                                        <td><strong class="text-<?= $pk['status_class'] ?>"><?= $pk['realization'] ?> <?= htmlspecialchars($pk['unit']) ?></strong></td>
                                        <td><code class="text-dark bg-light px-1 rounded"><?= htmlspecialchars($pk['formula'] ?: '-') ?></code></td>
                                        <td><span class="badge badge-xs badge-info light"><?= htmlspecialchars($pk['app_reference'] ?: '-') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($selectedYear == 2026): ?>
                    <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-flag me-1"></i> Penugasan Tambahan TA 2026 (Event Khusus Sesuai Nota Dinas)</h6>
                    <ul class="text-muted mb-0 ps-3 fs-12">
                        <li><strong>Perayaan Hari Raya Besar:</strong> Monitoring frekuensi krusial (penerbangan, marabahaya, radar cuaca, maritim, keamanan, seluler) pada Idul Fitri, Natal, dan Tahun Baru.</li>
                        <li><strong>Event Kenegaraan / Olahraga:</strong> Pengamanan spektrum radio VVIP, event olahraga nasional/internasional, dan monitoring kawasan Ibu Kota Nusantara (IKN).</li>
                        <li><strong>Uji Coba Teknologi Baru:</strong> Monitoring pita frekuensi lelang (700 MHz, 1.4 GHz, 2.6 GHz, 3.3 GHz, 26 GHz) serta kebutuhan merger operator seluler.</li>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-0 bg-light py-2 px-4">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Salin / Buka Perjanjian Kinerja Tahun Baru -->
<div class="modal fade" id="modalCopyYear" tabindex="-1" aria-labelledby="modalCopyYearLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-copy fa-lg me-2"></i>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="modalCopyYearLabel">Salin / Buka Perjanjian Kinerja Tahun Baru</h5>
                        <small class="text-white text-opacity-75 fs-11">Manajemen Multi-Tahun Sasaran Kinerja UPT</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCopyYear" onsubmit="submitCopyYear(event)">
                <div class="modal-body p-4 fs-13">
                    <div class="alert alert-info border-0 py-2 px-3 mb-3 d-flex align-items-center fs-12">
                        <i class="fa-solid fa-circle-info fa-lg me-2 text-info"></i>
                        <div>
                            Fitur ini menyalin seluruh indikator sasaran, target, formula, dan manual pengukuran dari tahun acuan ke tahun anggaran baru secara otomatis.
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Salin Dari Tahun Acuan <span class="text-danger">*</span></label>
                            <select class="form-select" id="copySourceYear" name="source_year" required>
                                <?php foreach ($availableYears as $ay): ?>
                                    <option value="<?= $ay['year'] ?>" <?= $ay['year'] == $selectedYear ? 'selected' : '' ?>>
                                        Tahun <?= $ay['year'] ?> (<?= htmlspecialchars($ay['title']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted fs-11">Pilih tahun yang indikatornya ingin dijadikan template acuan.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tahun Anggaran Baru / Tujuan <span class="text-danger">*</span></label>
                            <?php 
                                $allYearsList = array_column($availableYears, 'year');
                                $nextSuggestedYear = !empty($allYearsList) ? max($allYearsList) + 1 : 2027;
                            ?>
                            <input type="number" class="form-control fw-bold font-monospace" id="copyTargetYear" name="target_year" min="2020" max="2035" value="<?= $nextSuggestedYear ?>" required oninput="updateCopyTitles(this.value)">
                            <small class="text-muted fs-11">Masukkan tahun anggaran baru (misal: <?= $nextSuggestedYear ?>).</small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Judul Dokumen PK Tahun Baru <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="copyTargetTitle" name="target_title" value="Perjanjian Kinerja Tahun <?= $nextSuggestedYear ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Dasar Hukum / Nomor Nota Dinas Penetapan</label>
                            <input type="text" class="form-control" id="copyTargetLegal" name="target_legal_basis" value="Nota Dinas Penetapan PK TA <?= $nextSuggestedYear ?>" placeholder="Contoh: Nota Dinas No: .../DJID.6/PR.04.01/.../<?= $nextSuggestedYear ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Status Siklus Tahun Baru</label>
                            <select class="form-select" id="copyTargetStatus" name="target_status">
                                <option value="planning" selected>Perencanaan (Planning)</option>
                                <option value="active">Aktif Berjalan (Active)</option>
                                <option value="archived">Arsip (Archived)</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check p-2 bg-light rounded-3 border ps-4">
                                <input class="form-check-input ms-0 me-2" type="checkbox" id="copyResetRealization" name="reset_realization" value="1" checked>
                                <label class="form-check-label fw-bold text-dark fs-13" for="copyResetRealization">
                                    Reset seluruh nilai capaian/realisasi menjadi 0% (Mulai dari awal)
                                </label>
                                <small class="text-muted d-block ms-4">Centang opsi ini jika tahun anggaran baru belum berjalan agar seluruh realisasi dihitung ulang dari 0%.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light py-2 px-4">
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold" id="btnSubmitCopyYear">
                        <i class="fa fa-copy me-1"></i> Buka Tahun Baru & Salin Indikator
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah / Edit Lengkap Indikator Sasaran -->
<div class="modal fade" id="modalAddIndicator" tabindex="-1" aria-labelledby="modalAddIndicatorLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-3 px-4" id="modalAddIndicatorHeader">
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-bullseye fa-lg me-2"></i>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="modalAddIndicatorLabel">Kelola Indikator Sasaran PK</h5>
                        <small class="text-white text-opacity-75 fs-11">Perjanjian Kinerja Tahun <span id="indicatorYearLabel"><?= $selectedYear ?></span></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formIndicator" onsubmit="submitIndicator(event)">
                <input type="hidden" id="indicatorId" name="id" value="">
                <input type="hidden" id="indicatorYear" name="year" value="<?= $selectedYear ?>">
                <div class="modal-body p-4 fs-13">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Kode Indikator <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace fw-bold" id="indicatorCode" name="code" placeholder="Misal: PK.1" required>
                            <small class="text-muted fs-11">Contoh: PK.1, PK.8, IK.1</small>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-bold">Judul Sasaran Kinerja <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="indicatorTitle" name="title" placeholder="Contoh: Monitoring SFR di Kab/Kota" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Deskripsi Lengkap Indikator Sasaran</label>
                            <textarea class="form-control" id="indicatorDesc" name="indicator" rows="2" placeholder="Penjelasan rincian indikator kinerja sasaran kegiatan..."></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Target Tahunan <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" class="form-control fw-bold" id="indicatorTarget" name="target_annual" value="100" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Satuan Target <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="indicatorUnit" name="unit" value="%" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Realisasi Saat Ini</label>
                            <input type="number" step="0.1" class="form-control fw-bold text-primary" id="indicatorRealization" name="realization" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Aplikasi Rujukan</label>
                            <input type="text" class="form-control" id="indicatorAppRef" name="app_reference" list="appRefList" placeholder="SIMF / SISMON">
                            <datalist id="appRefList">
                                <option value="SIMF / SISMON">
                                <option value="SPEKTRAL">
                                <option value="SMART / SPRINT">
                                <option value="SI-BIMA">
                                <option value="SIPERDINA">
                                <option value="MONITORING-QOS">
                            </datalist>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Formula / Rumus Perhitungan</label>
                            <input type="text" class="form-control font-monospace fs-12" id="indicatorFormula" name="formula" placeholder="Contoh: (Jumlah KabKota Termonitor / Total KabKota) * 100%">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Manual Pengukuran & Parameter Teknis</label>
                            <textarea class="form-control fs-12" id="indicatorManual" name="manual_measurement" rows="2" placeholder="Ketentuan teknis, sumber data acuan, dan cara verifikasi lapangan..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Catatan Khusus UPT</label>
                            <input type="text" class="form-control fs-12" id="indicatorNotes" name="notes" placeholder="Catatan atau progres khusus UPT...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light py-2 px-4 d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" id="btnDeleteIndicator" style="display: none;" onclick="confirmDeleteCurrentIndicator()">
                            <i class="fa fa-trash me-1"></i> Hapus Indikator
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm rounded-pill px-4 fw-bold" id="btnSaveIndicator">
                            <i class="fa fa-save me-1"></i> Simpan Indikator
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Realisasi Single PK -->
<div class="modal fade" id="modalEditSinglePK" tabindex="-1" aria-labelledby="modalEditSinglePKLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold text-white mb-0" id="modalEditSinglePKLabel">Perbarui Realisasi Indikator PK</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditSinglePk" onsubmit="submitSinglePk(event)">
                <input type="hidden" id="editPkId" name="id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fs-12 fw-semibold text-uppercase">Kode & Indikator</label>
                        <h6 class="fw-bold text-dark mb-1" id="editPkCodeTitle">-</h6>
                        <small class="text-muted d-block" id="editPkTargetText">-</small>
                    </div>
                    <div class="mb-3">
                        <label for="editPkRealization" class="form-label fw-bold fs-13">Nilai Realisasi Capaian Saat Ini (<span id="editPkUnitLabel">%</span>)</label>
                        <div class="input-group">
                            <input type="number" step="0.1" min="0" max="1000" class="form-control" id="editPkRealization" name="realization" required>
                            <span class="input-group-text bg-light fw-bold" id="editPkUnitAddon">%</span>
                        </div>
                        <small class="text-muted fs-11">Masukkan angka persentase / nilai realisasi riil saat ini.</small>
                    </div>
                    <div class="mb-0">
                        <label for="editPkNotes" class="form-label fw-semibold fs-12 text-muted">Catatan Khusus / Progres UPT</label>
                        <textarea class="form-control" id="editPkNotes" name="notes" rows="2" placeholder="Catatan progress capaian..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light py-2 px-4">
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold" id="btnSaveSinglePk">
                        <i class="fa fa-save me-1"></i> Simpan Realisasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Semua PK (Bulk Edit) untuk Tahun Terpilih -->
<div class="modal fade" id="modalEditAllPK" tabindex="-1" aria-labelledby="modalEditAllPKLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-list-check fa-lg me-2"></i>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="modalEditAllPKLabel">Kelola Realisasi Indikator Perjanjian Kinerja Tahun <?= $selectedYear ?></h5>
                        <small class="text-white text-opacity-75 fs-11">Balmon Kelas I Samarinda</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditAllPk" onsubmit="submitAllPk(event)">
                <div class="modal-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-12">
                            <thead class="bg-light text-uppercase text-muted">
                                <tr>
                                    <th style="width: 70px;">Kode</th>
                                    <th>Indikator Sasaran Kinerja</th>
                                    <th style="width: 140px;">Target Resmi</th>
                                    <th style="width: 170px;">Realisasi Saat Ini</th>
                                    <th>Aplikasi Rujukan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($perjanjianKinerja)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada indikator untuk tahun <?= $selectedYear ?>.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($perjanjianKinerja as $pk): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-primary fw-bold font-monospace"><?= htmlspecialchars($pk['code']) ?></span>
                                            </td>
                                            <td>
                                                <strong class="text-dark d-block"><?= htmlspecialchars($pk['title']) ?></strong>
                                                <small class="text-muted d-block text-truncate" style="max-width: 380px;" title="<?= htmlspecialchars($pk['indicator']) ?>"><?= htmlspecialchars($pk['indicator']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-light border fw-bold text-dark fs-12">
                                                    <?= $pk['target_annual'] ?> <?= htmlspecialchars($pk['unit']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" step="0.1" min="0" max="1000" class="form-control fw-bold text-primary bulk-pk-input" data-id="<?= $pk['id'] ?>" value="<?= $pk['realization'] ?>" required>
                                                    <span class="input-group-text bg-light text-muted fs-11"><?= htmlspecialchars($pk['unit']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-xs badge-info light"><?= htmlspecialchars($pk['app_reference']) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light py-2 px-4">
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
                    <?php if (!empty($perjanjianKinerja)): ?>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold" id="btnSaveAllPk">
                            <i class="fa fa-save me-1"></i> Simpan Semua Capaian
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- ApexCharts Script & Interactive Handlers for Dashboard -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Monthly Trend Bar/Area Chart
    const monthlyData = <?= json_encode(array_values($monthlyCounts)) ?>;
    const optionsMonthly = {
        series: [{
            name: 'Realisasi Kegiatan Monitoring',
            data: monthlyData
        }],
        chart: {
            height: 290,
            type: 'area',
            toolbar: { show: false },
            zoom: { enabled: false }
        },
        colors: ['#0070E0'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.5,
                opacityTo: 0.05,
                stops: [0, 95, 100]
            }
        },
        xaxis: {
            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            labels: { style: { colors: '#888888', fontSize: '12px' } }
        },
        yaxis: {
            labels: { style: { colors: '#888888', fontSize: '12px' } }
        },
        grid: { borderColor: '#f1f1f1' },
        tooltip: {
            theme: 'dark',
            y: { formatter: function(val) { return val + " Kegiatan Monitoring"; } }
        }
    };
    if (document.querySelector("#chart-monthly-trend")) {
        new ApexCharts(document.querySelector("#chart-monthly-trend"), optionsMonthly).render();
    }

    // 2. Verification Radial Chart
    const verified = <?= (int)$totalVerified ?>;
    const pending = <?= (int)$totalPending ?>;
    const revision = <?= (int)$totalRevision ?>;
    const total = verified + pending + revision || 1;

    const optionsRadial = {
        series: [
            Math.round((verified/total)*100),
            Math.round((pending/total)*100),
            Math.round((revision/total)*100)
        ],
        chart: {
            height: 230,
            type: 'radialBar',
        },
        plotOptions: {
            radialBar: {
                dataLabels: {
                    name: { fontSize: '12px' },
                    value: { fontSize: '14px', formatter: function(val) { return val + '%'; } },
                    total: {
                        show: true,
                        label: 'Total Laporan',
                        formatter: function() { return <?= (int)$totalRealized ?>; }
                    }
                }
            }
        },
        labels: ['Terverifikasi', 'Menunggu', 'Revisi'],
        colors: ['#28a745', '#ffc107', '#dc3545']
    };
    if (document.querySelector("#chart-verification-radial")) {
        new ApexCharts(document.querySelector("#chart-verification-radial"), optionsRadial).render();
    }
});

// Helper functions for Perjanjian Kinerja (PK) Multi-Year
function showPkDetail(pk) {
    if (!pk) return;
    document.getElementById('pkDetailCode').textContent = pk.code || 'PK';
    document.getElementById('pkDetailTitle').textContent = (pk.code || '') + ' - ' + (pk.title || '');
    document.getElementById('pkDetailIndicator').textContent = pk.indicator || '-';
    document.getElementById('pkDetailTarget').textContent = (pk.target_annual || '0') + ' ' + (pk.unit || '%');
    document.getElementById('pkDetailRealization').textContent = (pk.realization || '0') + ' ' + (pk.unit || '%');
    document.getElementById('pkDetailPercentOfTarget').textContent = (pk.percent_of_target || '0') + '% terhadap target (' + (pk.status_label || '') + ')';
    document.getElementById('pkDetailFormula').textContent = pk.formula || '-';
    document.getElementById('pkDetailManual').textContent = pk.manual_measurement || '-';
    document.getElementById('pkDetailApp').textContent = pk.app_reference || '-';
    document.getElementById('pkDetailNotes').textContent = pk.notes || '-';

    const modal = new bootstrap.Modal(document.getElementById('modalDetailPK'));
    modal.show();
}

function editSinglePk(pk) {
    if (!pk) return;
    document.getElementById('editPkId').value = pk.id;
    document.getElementById('editPkCodeTitle').textContent = pk.code + ': ' + pk.title;
    document.getElementById('editPkTargetText').textContent = 'Target: ' + pk.target_annual + ' ' + pk.unit + ' | Rujukan: ' + pk.app_reference;
    document.getElementById('editPkRealization').value = pk.realization;
    document.getElementById('editPkUnitLabel').textContent = pk.unit;
    document.getElementById('editPkUnitAddon').textContent = pk.unit;
    document.getElementById('editPkNotes').value = pk.notes || '';

    const modal = new bootstrap.Modal(document.getElementById('modalEditSinglePK'));
    modal.show();
}

function openEditAllPkModal() {
    const modal = new bootstrap.Modal(document.getElementById('modalEditAllPK'));
    modal.show();
}

function submitSinglePk(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveSinglePk');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...';

    const id = document.getElementById('editPkId').value;
    const realization = parseFloat(document.getElementById('editPkRealization').value);
    const notes = document.getElementById('editPkNotes').value;

    fetch('api/update_pk.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, realization, notes, action: 'update_realization'})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Gagal menyimpan realisasi.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-save me-1"></i> Simpan Realisasi';
        }
    })
    .catch(err => {
        alert('Terjadi kesalahan koneksi.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-save me-1"></i> Simpan Realisasi';
    });
}

function submitAllPk(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveAllPk');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan Semua...';

    const inputs = document.querySelectorAll('.bulk-pk-input');
    const promises = [];

    inputs.forEach(input => {
        const id = input.dataset.id;
        const realization = parseFloat(input.value);
        promises.push(
            fetch('api/update_pk.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id, realization, action: 'update_realization'})
            }).then(r => r.json())
        );
    });

    Promise.all(promises)
        .then(() => {
            window.location.reload();
        })
        .catch(() => {
            alert('Sebagian data gagal disimpan. Silakan refresh halaman.');
            window.location.reload();
        });
}

// Multi-Year Management JS Handlers
function openCopyYearModal() {
    const modal = new bootstrap.Modal(document.getElementById('modalCopyYear'));
    modal.show();
}

function updateCopyTitles(val) {
    const y = parseInt(val);
    if (y && y >= 2020) {
        document.getElementById('copyTargetTitle').value = 'Perjanjian Kinerja Tahun ' + y;
        document.getElementById('copyTargetLegal').value = 'Nota Dinas Penetapan PK TA ' + y;
    }
}

function submitCopyYear(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitCopyYear');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Menyalin Indikator...';

    const sourceYear = parseInt(document.getElementById('copySourceYear').value);
    const targetYear = parseInt(document.getElementById('copyTargetYear').value);
    const targetTitle = document.getElementById('copyTargetTitle').value;
    const targetLegal = document.getElementById('copyTargetLegal').value;
    const targetStatus = document.getElementById('copyTargetStatus').value;
    const resetRealization = document.getElementById('copyResetRealization').checked;

    fetch('api/update_pk.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'copy_year',
            source_year: sourceYear,
            target_year: targetYear,
            target_title: targetTitle,
            target_legal_basis: targetLegal,
            target_status: targetStatus,
            reset_realization: resetRealization
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Tahun baru berhasil dibuat!');
            window.location.href = 'index.php?year=' + (data.target_year || targetYear);
        } else {
            alert(data.message || 'Gagal menyalin tahun.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-copy me-1"></i> Buka Tahun Baru & Salin Indikator';
        }
    })
    .catch(err => {
        alert('Terjadi kesalahan koneksi.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-copy me-1"></i> Buka Tahun Baru & Salin Indikator';
    });
}

function openAddIndicatorModal(year) {
    document.getElementById('formIndicator').reset();
    document.getElementById('indicatorId').value = '';
    document.getElementById('indicatorYear').value = year || <?= $selectedYear ?>;
    document.getElementById('indicatorYearLabel').textContent = year || <?= $selectedYear ?>;
    document.getElementById('modalAddIndicatorLabel').textContent = 'Tambah Indikator Sasaran PK Baru';
    document.getElementById('modalAddIndicatorHeader').className = 'modal-header bg-success text-white py-3 px-4';
    document.getElementById('btnDeleteIndicator').style.display = 'none';

    const modal = new bootstrap.Modal(document.getElementById('modalAddIndicator'));
    modal.show();
}

function editIndicatorFull(pk) {
    if (!pk) return;
    document.getElementById('indicatorId').value = pk.id || '';
    document.getElementById('indicatorYear').value = pk.year || <?= $selectedYear ?>;
    document.getElementById('indicatorYearLabel').textContent = pk.year || <?= $selectedYear ?>;
    document.getElementById('indicatorCode').value = pk.code || '';
    document.getElementById('indicatorTitle').value = pk.title || '';
    document.getElementById('indicatorDesc').value = pk.indicator || '';
    document.getElementById('indicatorTarget').value = pk.target_annual || 100;
    document.getElementById('indicatorUnit').value = pk.unit || '%';
    document.getElementById('indicatorRealization').value = pk.realization || 0;
    document.getElementById('indicatorAppRef').value = pk.app_reference || '';
    document.getElementById('indicatorFormula').value = pk.formula || '';
    document.getElementById('indicatorManual').value = pk.manual_measurement || '';
    document.getElementById('indicatorNotes').value = pk.notes || '';

    document.getElementById('modalAddIndicatorLabel').textContent = 'Edit Indikator ' + (pk.code || '');
    document.getElementById('modalAddIndicatorHeader').className = 'modal-header bg-primary text-white py-3 px-4';
    document.getElementById('btnDeleteIndicator').style.display = 'inline-block';

    const modal = new bootstrap.Modal(document.getElementById('modalAddIndicator'));
    modal.show();
}

function confirmDeleteCurrentIndicator() {
    const id = document.getElementById('indicatorId').value;
    const code = document.getElementById('indicatorCode').value;
    if (!id) return;

    if (confirm('Apakah Anda yakin ingin menghapus indikator ' + code + ' ini dari daftar PK?')) {
        const btn = document.getElementById('btnDeleteIndicator');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Menghapus...';

        fetch('api/update_pk.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'delete_indicator', id: id})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Gagal menghapus indikator.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-trash me-1"></i> Hapus Indikator';
            }
        })
        .catch(err => {
            alert('Terjadi kesalahan koneksi.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-trash me-1"></i> Hapus Indikator';
        });
    }
}

function submitIndicator(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveIndicator');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...';

    const id = document.getElementById('indicatorId').value;
    const year = parseInt(document.getElementById('indicatorYear').value);
    const code = document.getElementById('indicatorCode').value;
    const title = document.getElementById('indicatorTitle').value;
    const indicator = document.getElementById('indicatorDesc').value;
    const targetAnnual = parseFloat(document.getElementById('indicatorTarget').value);
    const unit = document.getElementById('indicatorUnit').value;
    const realization = parseFloat(document.getElementById('indicatorRealization').value);
    const appRef = document.getElementById('indicatorAppRef').value;
    const formula = document.getElementById('indicatorFormula').value;
    const manual = document.getElementById('indicatorManual').value;
    const notes = document.getElementById('indicatorNotes').value;

    fetch('api/update_pk.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'save_indicator',
            id: id,
            year: year,
            code: code,
            title: title,
            indicator: indicator,
            target_annual: targetAnnual,
            unit: unit,
            realization: realization,
            app_reference: appRef,
            formula: formula,
            manual_measurement: manual,
            notes: notes
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Gagal menyimpan indikator.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-save me-1"></i> Simpan Indikator';
        }
    })
    .catch(err => {
        alert('Terjadi kesalahan koneksi.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-save me-1"></i> Simpan Indikator';
    });
}
</script>

