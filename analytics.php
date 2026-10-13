<?php
// analytics.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();
$pageTitle = "Grafik & Analitik KPI Multi-Tahun";

// 1. Data Multi-Tahun Perjanjian Kinerja (PK)
$availableYears = $pdo->query("SELECT * FROM pk_years_meta ORDER BY year DESC")->fetchAll();
if (empty($availableYears)) {
    $availableYears = [
        ['year' => 2026, 'title' => 'Perjanjian Kinerja Tahun 2026', 'legal_basis' => 'Nota Dinas No: 224/DJID.6/PR.04.01/02/2026', 'status' => 'active']
    ];
}
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : 2026;

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
        'status' => 'active'
    ];
}

// Fetch PK items for selected year
$stmtPK = $pdo->prepare("SELECT * FROM perjanjian_kinerja WHERE year = ? ORDER BY order_num ASC, id ASC");
$stmtPK->execute([$selectedYear]);
$perjanjianKinerja = $stmtPK->fetchAll();

foreach ($perjanjianKinerja as &$pk) {
    $target = (float)$pk['target_annual'];
    $real = (float)$pk['realization'];
    $pk['percent_of_target'] = $target > 0 ? min(100, round(($real / $target) * 100, 1)) : 0;
}
unset($pk);

// Multi-Year Average Scores for Trend Chart
$multiYearTrend = [];
$allYearsSorted = $pdo->query("SELECT * FROM pk_years_meta ORDER BY year ASC")->fetchAll();
foreach ($allYearsSorted as $ay) {
    $y = (int)$ay['year'];
    $stY = $pdo->prepare("SELECT target_annual, realization FROM perjanjian_kinerja WHERE year = ?");
    $stY->execute([$y]);
    $itemsY = $stY->fetchAll();
    $cntY = count($itemsY);
    $sumScoreY = 0;
    foreach ($itemsY as $iy) {
        $tarY = (float)$iy['target_annual'];
        $relY = (float)$iy['realization'];
        $pctY = $tarY > 0 ? min(100, round(($relY / $tarY) * 100, 1)) : 0;
        $sumScoreY += $pctY;
    }
    $avgScoreY = $cntY > 0 ? round($sumScoreY / $cntY, 1) : 0;
    $multiYearTrend[] = [
        'year' => $y,
        'score' => $avgScoreY,
        'count' => $cntY,
        'status' => $ay['status']
    ];
}

// 2. Data 9 Kategori Kegiatan Lapangan (RKT)
$categories = $pdo->query("SELECT * FROM monitoring_categories ORDER BY id ASC")->fetchAll();
foreach ($categories as &$cat) {
    $stmtC = $pdo->prepare("
        SELECT 
            COUNT(*) as total_count,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_count
        FROM monitoring_reports
        WHERE category_id = ?
    ");
    $stmtC->execute([$cat['id']]);
    $counts = $stmtC->fetch();
    $cat['realized'] = (int)$counts['total_count'];
    $cat['verified'] = (int)$counts['verified_count'];
    $cat['pct'] = $cat['target_annual'] > 0 ? min(100, round(($cat['realized'] / $cat['target_annual']) * 100, 1)) : 0;
}
unset($cat);

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav_header.php';
require_once __DIR__ . '/includes/chatbox.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="content-body">
    <div class="container-fluid">
        <!-- Breadcrumb & Multi-Year Year Switcher -->
        <div class="row page-titles mb-3">
            <div class="col-sm-6 p-md-0">
                <div class="welcome-text">
                    <h4 class="mb-1 text-primary fw-bold">Grafik & Analitik Capaian Kinerja</h4>
                    <p class="mb-0 text-muted fs-12">Visualisasi Analitis Perjanjian Kinerja (PK) Multi-Tahun & Target RKT Lapangan</p>
                </div>
            </div>
            <div class="col-sm-6 p-md-0 justify-content-sm-end mt-2 mt-sm-0 d-flex align-items-center gap-2">
                <span class="fs-12 text-muted fw-bold d-none d-md-inline"><i class="fa fa-calendar me-1"></i> Tahun PK:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <?php foreach ($availableYears as $ay): ?>
                        <a href="analytics.php?year=<?= $ay['year'] ?>" class="btn <?= $selectedYear == $ay['year'] ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                            <?= $ay['year'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- SECTION 1: VISUALISASI PERJANJIAN KINERJA (PK) TAHUN TERPILIH -->
        <div class="row mb-4">
            <!-- Grafik Target vs Realisasi PK Tahun Terpilih -->
            <div class="col-xl-8 col-lg-7 mb-3">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 18px;">
                    <div class="card-header border-0 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="fa-solid fa-chart-column text-primary me-2"></i> Capaian Indikator Perjanjian Kinerja (TA <?= $selectedYear ?>)
                            </h5>
                            <small class="text-muted"><?= htmlspecialchars($currentYearMeta['title']) ?> &bull; <?= htmlspecialchars($currentYearMeta['legal_basis']) ?></small>
                        </div>
                        <a href="index.php?year=<?= $selectedYear ?>" class="btn btn-xs btn-outline-primary rounded-pill px-3">
                            <i class="fa fa-arrow-up-right-from-square me-1"></i> Dashboard PK
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($perjanjianKinerja)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fa fa-folder-open fa-3x mb-2 text-muted opacity-50"></i>
                                <p class="mb-0">Belum ada indikator sasaran kinerja pada tahun <?= $selectedYear ?>.</p>
                            </div>
                        <?php else: ?>
                            <div id="chart-pk-indicators" style="min-height: 330px;"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Grafik Tren Year-on-Year PK -->
            <div class="col-xl-4 col-lg-5 mb-3">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 18px;">
                    <div class="card-header border-0 pb-0">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-timeline text-success me-2"></i> Tren Rata-rata Skor PK
                        </h5>
                        <small class="text-muted">Perkembangan Capaian Antar-Tahun Anggaran</small>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div id="chart-yoy-trend" style="min-height: 240px;"></div>
                        <div class="p-3 bg-light rounded-3 mt-2 border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fs-12 text-muted">Tahun Terpilih (<?= $selectedYear ?>):</span>
                                <strong class="fs-14 text-primary">
                                    <?php 
                                        $curScore = 0;
                                        foreach ($multiYearTrend as $myt) {
                                            if ($myt['year'] == $selectedYear) { $curScore = $myt['score']; break; }
                                        }
                                        echo $curScore . '%';
                                    ?>
                                </strong>
                            </div>
                            <small class="text-muted fs-11 d-block">
                                Status: <strong><?= strtoupper($currentYearMeta['status']) ?></strong> (<?= count($perjanjianKinerja) ?> Indikator Terdaftar)
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: CAPAIAN KINERJA PER KELOMPOK KEGIATAN LAPANGAN (RKT) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius: 18px;">
                    <div class="card-header border-0 pb-0">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-list-check text-primary me-2"></i> Capaian Kinerja per Kelompok Kegiatan Lapangan (RKT)</h5>
                        <small class="text-muted">Target Rencana Kinerja Tahunan (RKT) Balmon Kelas I Samarinda</small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle table-nowrap data-table-balmon mb-0">
                                <thead class="bg-light fs-12 text-uppercase text-muted">
                                    <tr>
                                        <th>Kategori Kegiatan</th>
                                        <th class="text-center">Satuan</th>
                                        <th class="text-center">Target RKT</th>
                                        <th class="text-center">Realisasi</th>
                                        <th class="text-center">Terverifikasi</th>
                                        <th style="width: 25%;">Persentase Capaian</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13">
                                    <?php foreach ($categories as $c): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center text-nowrap">
                                                    <div class="rounded-3 d-flex align-items-center justify-content-center bg-<?= $c['badge_color'] ?> bg-opacity-10 text-<?= $c['badge_color'] ?> me-2 flex-shrink-0" style="width: 32px; height: 32px;">
                                                        <i class="fa-solid <?= $c['icon'] ?> fs-14"></i>
                                                    </div>
                                                    <div class="overflow-hidden">
                                                        <strong class="d-block truncate-md" title="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></strong>
                                                        <small class="text-muted d-block truncate-md" title="<?= htmlspecialchars($c['short_title']) ?>"><?= htmlspecialchars($c['short_title']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center text-nowrap"><?= htmlspecialchars($c['unit']) ?></td>
                                            <td class="text-center fw-bold text-dark text-nowrap"><?= $c['target_annual'] ?></td>
                                            <td class="text-center fw-bold text-primary text-nowrap"><?= $c['realized'] ?></td>
                                            <td class="text-center fw-bold text-success text-nowrap"><?= $c['verified'] ?></td>
                                            <td>
                                                <div class="d-flex align-items-center justify-content-between fs-12 mb-1 text-nowrap">
                                                    <span>Progress:</span>
                                                    <strong class="text-<?= $c['badge_color'] ?>"><?= $c['pct'] ?>%</strong>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-<?= $c['badge_color'] ?>" role="progressbar" style="width: <?= $c['pct'] ?>%"></div>
                                                </div>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <?php if ($c['pct'] >= 100): ?>
                                                    <span class="badge badge-success light text-nowrap">TERCAPAI</span>
                                                <?php elseif ($c['pct'] >= 50): ?>
                                                    <span class="badge badge-primary light text-nowrap">ON TRACK</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning light text-nowrap">PERLU AKSELERASI</span>
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
        </div>

        <!-- SECTION 3: KOMPARASI RKT LAPANGAN BAR CHART -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius: 18px;">
                    <div class="card-header border-0 pb-0">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-chart-simple text-primary me-2"></i> Komparasi Realisasi vs Target Kegiatan Lapangan (RKT)</h5>
                        <small class="text-muted">Perbandingan jumlah objek/kegiatan lapangan yang terealisasi terhadap target</small>
                    </div>
                    <div class="card-body">
                        <div id="chart-comparison" style="min-height: 350px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Chart Indikator PK Tahun Terpilih
    <?php if (!empty($perjanjianKinerja)): ?>
        const pkCodes = <?= json_encode(array_column($perjanjianKinerja, 'code')) ?>;
        const pkTargets = <?= json_encode(array_map('floatval', array_column($perjanjianKinerja, 'target_annual'))) ?>;
        const pkRealizations = <?= json_encode(array_map('floatval', array_column($perjanjianKinerja, 'realization'))) ?>;

        const optionsPK = {
            series: [{
                name: 'Target Resmi (%)',
                data: pkTargets
            }, {
                name: 'Realisasi Capaian (%)',
                data: pkRealizations
            }],
            chart: {
                type: 'bar',
                height: 330,
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '50%',
                    borderRadius: 4
                }
            },
            dataLabels: { enabled: false },
            stroke: { show: true, width: 2, colors: ['transparent'] },
            colors: ['#003C8C', '#28a745'],
            xaxis: {
                categories: pkCodes,
                labels: { style: { colors: '#666666', fontSize: '12px', fontWeight: 600 } }
            },
            yaxis: {
                max: 100,
                title: { text: 'Persentase (%)' }
            },
            fill: { opacity: 1 },
            tooltip: {
                theme: 'dark',
                y: { formatter: function (val) { return val + "%"; } }
            }
        };
        if (document.querySelector("#chart-pk-indicators")) {
            new ApexCharts(document.querySelector("#chart-pk-indicators"), optionsPK).render();
        }
    <?php endif; ?>

    // 2. Chart YoY Trend Skor PK
    const trendYears = <?= json_encode(array_column($multiYearTrend, 'year')) ?>;
    const trendScores = <?= json_encode(array_column($multiYearTrend, 'score')) ?>;

    const optionsTrend = {
        series: [{
            name: 'Rata-rata Capaian PK',
            data: trendScores
        }],
        chart: {
            height: 240,
            type: 'line',
            toolbar: { show: false }
        },
        stroke: { curve: 'smooth', width: 4 },
        colors: ['#0070E0'],
        markers: { size: 6, colors: ['#0070E0'], strokeColors: '#fff', strokeWidth: 2 },
        xaxis: {
            categories: trendYears.map(y => 'TA ' + y),
            labels: { style: { colors: '#888888', fontSize: '11px' } }
        },
        yaxis: {
            min: 0,
            max: 100,
            labels: { formatter: function(val) { return val + '%'; } }
        },
        grid: { borderColor: '#f1f1f1' },
        tooltip: {
            theme: 'dark',
            y: { formatter: function(val) { return val + "%"; } }
        }
    };
    if (document.querySelector("#chart-yoy-trend")) {
        new ApexCharts(document.querySelector("#chart-yoy-trend"), optionsTrend).render();
    }

    // 3. Chart Komparasi 9 Kategori RKT Lapangan
    const catLabels = <?= json_encode(array_column($categories, 'short_title')) ?>;
    const catTargets = <?= json_encode(array_column($categories, 'target_annual')) ?>;
    const catRealized = <?= json_encode(array_column($categories, 'realized')) ?>;

    const optionsRKT = {
        series: [{
            name: 'Target RKT Tahunan',
            data: catTargets
        }, {
            name: 'Realisasi Berjalan',
            data: catRealized
        }],
        chart: {
            type: 'bar',
            height: 350,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                borderRadius: 4
            },
        },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        colors: ['#003C8C', '#008CF0'],
        xaxis: {
            categories: catLabels,
            labels: { style: { colors: '#888888', fontSize: '11px' } }
        },
        yaxis: {
            title: { text: 'Jumlah Kegiatan / Objek' }
        },
        fill: { opacity: 1 },
        tooltip: {
            theme: 'dark',
            y: { formatter: function (val) { return val + " Kegiatan"; } }
        }
    };

    if (document.querySelector("#chart-comparison")) {
        new ApexCharts(document.querySelector("#chart-comparison"), optionsRKT).render();
    }
});
</script>
