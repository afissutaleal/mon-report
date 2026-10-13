<?php
// import_data.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();
$pageTitle = "Impor Data & Tracing Capaian Kinerja";

// Ambil riwayat impor
$stmtHist = $pdo->query("
    SELECT h.*, u.avatar as user_avatar, u.role as user_role
    FROM import_history h
    LEFT JOIN users u ON h.user_id = u.id
    ORDER BY h.id DESC
");
$historyList = $stmtHist->fetchAll();

// Ambil daftar tahun PK yang tersedia
$availableYears = $pdo->query("SELECT * FROM pk_years_meta ORDER BY year DESC")->fetchAll();
if (empty($availableYears)) {
    $availableYears = [
        ['year' => 2026, 'title' => 'Perjanjian Kinerja Tahun 2026', 'legal_basis' => 'Nota Dinas No: 224/DJID.6/PR.04.01/02/2026', 'status' => 'active']
    ];
}
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : 2026;

// Ambil data PK.1 sesuai tahun terpilih
$stmtPK1 = $pdo->prepare("SELECT * FROM perjanjian_kinerja WHERE code = 'PK.1' AND year = ?");
$stmtPK1->execute([$selectedYear]);
$pk1 = $stmtPK1->fetch();
if (!$pk1) {
    $stmtPK1 = $pdo->prepare("SELECT * FROM perjanjian_kinerja WHERE code = 'PK.1' ORDER BY year DESC LIMIT 1");
    $stmtPK1->execute();
    $pk1 = $stmtPK1->fetch();
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
            <div class="col-sm-6 p-md-0">
                <div class="welcome-text">
                    <h4 class="mb-1 text-primary fw-bold">Impor Data & Tracing Capaian Kinerja</h4>
                    <p class="mb-0 text-muted fs-12">Pembaruan otomatis capaian berbasis berkas Excel/CSV & Jejak Audit Lengkap</p>
                </div>
            </div>
            <div class="col-sm-6 p-md-0 justify-content-sm-end mt-2 mt-sm-0 d-flex">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="javascript:void(0)">Capaian Kinerja</a></li>
                    <li class="breadcrumb-item active"><a href="javascript:void(0)">Impor & Tracing</a></li>
                </ol>
            </div>
        </div>

        <!-- Banner Header Info -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #0052cc 0%, #0070E0 50%, #0284c7 100%); border-radius: 18px; overflow: hidden;">
                    <div class="card-body p-4 position-relative">
                        <div class="row align-items-center">
                            <div class="col-lg-8">
                                <div class="d-inline-flex align-items-center bg-white bg-opacity-25 px-3 py-1 rounded-pill mb-2">
                                    <i class="material-symbols-outlined fs-16 me-2">verified</i>
                                    <span class="fs-12 fw-semibold">Nota Dinas No. 224/DJID.6/PR.04.01/02/2026</span>
                                </div>
                                <h3 class="fw-bold text-white mb-2">Pembaruan Capaian Kinerja Berbasis Bukti Nyata</h3>
                                <p class="text-white text-opacity-90 fs-13 mb-3" style="max-width: 680px;">
                                    Unggah berkas Excel/CSV hasil pemantauan spektrum atau capaian tahunan. Sistem akan melakukan validasi 4 kriteria kelulusan tiap Kabupaten/Kota, mengkalkulasi persentase capaian secara otomatis, dan mendokumentasikan jejak audit (audit trail) untuk transparansi dan pembuktian kinerja UPT.
                                </p>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="api/download_template.php?type=pk1_sfr_xls" class="btn btn-light btn-sm rounded-pill fw-semibold shadow-sm text-primary">
                                        <i class="material-symbols-outlined fs-15 align-middle me-1">download</i> Unduh Template PK.1 (Excel)
                                    </a>
                                    <a href="api/download_template.php?type=pk1_sfr_csv" class="btn btn-outline-light btn-sm rounded-pill fw-semibold">
                                        <i class="material-symbols-outlined fs-15 align-middle me-1">download</i> Unduh Template PK.1 (CSV)
                                    </a>
                                    <a href="api/download_template.php?type=bulk_pk_xls" class="btn btn-outline-light btn-sm rounded-pill fw-semibold">
                                        <i class="material-symbols-outlined fs-15 align-middle me-1">grid_on</i> Unduh Format 8 Indikator (Excel)
                                    </a>
                                </div>
                            </div>
                            <div class="col-lg-4 text-center text-lg-end mt-3 mt-lg-0">
                                <div class="bg-white bg-opacity-10 p-3 rounded-4 border border-white border-opacity-25 d-inline-block text-start w-100" style="max-width: 320px;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fs-12 text-white text-opacity-75">Status Capaian PK.1 Saat Ini</span>
                                        <span class="badge bg-success rounded-pill px-2 py-1 fs-11">Aktif</span>
                                    </div>
                                    <div class="d-flex align-items-baseline gap-2 mb-2">
                                        <h2 class="fw-bold text-white mb-0" id="currentPk1Val"><?= number_format($pk1['realization'] ?? 0, 1) ?>%</h2>
                                        <span class="fs-12 text-white text-opacity-75">Target: <?= number_format($pk1['target_annual'] ?? 80, 1) ?>%</span>
                                    </div>
                                    <div class="progress bg-white bg-opacity-25" style="height: 6px; border-radius: 4px;">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?= min(100, $pk1['realization'] ?? 0) ?>%"></div>
                                    </div>
                                    <small class="fs-11 text-white text-opacity-75 mt-2 d-block text-truncate" title="<?= htmlspecialchars($pk1['notes'] ?? '') ?>">
                                        <?= htmlspecialchars($pk1['notes'] ?? 'Belum ada catatan pembaruan.') ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigasi -->
        <div class="row mb-4">
            <div class="col-12">
                <ul class="nav nav-pills custom-pills" id="importTabs" role="tablist">
                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link active px-4 py-2 rounded-pill fw-semibold" id="upload-tab" data-bs-toggle="pill" data-bs-target="#tab-upload" type="button" role="tab">
                            <i class="material-symbols-outlined fs-18 align-middle me-1">upload_file</i> Unggah & Kalkulasi Data
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link px-4 py-2 rounded-pill fw-semibold position-relative" id="history-tab" data-bs-toggle="pill" data-bs-target="#tab-history" type="button" role="tab">
                            <i class="material-symbols-outlined fs-18 align-middle me-1">history</i> Riwayat Impor & Audit Tracing
                            <span class="badge bg-primary rounded-pill ms-2" id="historyBadgeCount"><?= count($historyList) ?></span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Tab Contents -->
        <div class="tab-content" id="importTabsContent">
            
            <!-- TAB 1: FORM UNGGAH & KALKULASI -->
            <div class="tab-pane fade show active" id="tab-upload" role="tabpanel">
                <div class="row">
                    <!-- Kolom Kiri: Form Upload -->
                    <div class="col-xl-7 col-lg-7">
                        <div class="card border-0 shadow-sm" style="border-radius: 18px;">
                            <div class="card-header border-0 bg-transparent pt-4 px-4 pb-0">
                                <h5 class="fw-bold mb-0 text-dark">
                                    <i class="material-symbols-outlined text-primary me-2 align-middle">cloud_upload</i>
                                    Formulir Unggah Berkas Kinerja
                                </h5>
                                <p class="text-muted fs-12 mb-0">Pilih berkas Excel (.xlsx / .xls) atau CSV (.csv) untuk diproses.</p>
                            </div>
                            <div class="card-body p-4">
                                    <!-- 0. Pilihan Tahun Sasaran PK -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-dark fs-13">Tahun Anggaran Sasaran PK <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm fw-bold text-primary" name="target_year" id="importTargetYear" onchange="window.location.href='import_data.php?year='+this.value">
                                            <?php foreach ($availableYears as $ay): ?>
                                                <option value="<?= $ay['year'] ?>" <?= $ay['year'] == $selectedYear ? 'selected' : '' ?>>
                                                    Tahun <?= $ay['year'] ?> - <?= htmlspecialchars($ay['title']) ?> (<?= ucfirst($ay['status']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted fs-11">Capaian indikator akan disinkronkan ke dokumen PK tahun yang dipilih di atas.</small>
                                    </div>

                                    <!-- 1. Pilihan Tipe Impor -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-dark fs-13">Tipe Impor Data <span class="text-danger">*</span></label>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="card border p-3 cursor-pointer h-100 import-type-card active" for="typePk1" id="labelTypePk1">
                                                    <div class="d-flex align-items-start">
                                                        <input class="form-check-input me-3 mt-1" type="radio" name="import_type" id="typePk1" value="pk1_monitoring_sfr" checked>
                                                        <div>
                                                            <strong class="d-block text-dark fs-13 mb-1">Monitoring SFR (PK.1)</strong>
                                                            <span class="text-muted fs-11">Evaluasi 10 Kab/Kota Kaltim & IKN sesuai 4 syarat Nota Dinas No. 224</span>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="card border p-3 cursor-pointer h-100 import-type-card" for="typeBulk" id="labelTypeBulk">
                                                    <div class="d-flex align-items-start">
                                                        <input class="form-check-input me-3 mt-1" type="radio" name="import_type" id="typeBulk" value="pk_all_targets">
                                                        <div>
                                                            <strong class="d-block text-dark fs-13 mb-1">Bulk 8 Indikator PK</strong>
                                                            <span class="text-muted fs-11">Pembaruan massal realisasi indikator PK.1 s/d PK.8 sekaligus</span>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 2. Area Drag & Drop Berkas -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-dark fs-13">Pilih / Seret Berkas Excel atau CSV <span class="text-danger">*</span></label>
                                        <div class="upload-dropzone p-4 text-center rounded-3 border-2 border-dashed position-relative" id="dropZone" style="border-color: #cbd5e1; background-color: #f8fafc; cursor: pointer;">
                                            <input type="file" name="import_file" id="fileInput" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" accept=".xlsx,.xls,.csv" required>
                                            <div id="dropZoneContent">
                                                <i class="material-symbols-outlined text-primary mb-2" style="font-size: 48px;">upload_file</i>
                                                <h6 class="fw-bold mb-1 text-dark" id="fileNameDisplay">Klik atau seret berkas ke sini</h6>
                                                <p class="text-muted fs-12 mb-2" id="fileInfoDisplay">Mendukung berkas Microsoft Excel (.xlsx, .xls) dan CSV (.csv) maks 10MB</p>
                                                <span class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1 fs-12">Pilih dari Komputer</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 3. Catatan Audit -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-dark fs-13">Catatan / Keterangan Audit (Opsional)</label>
                                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Contoh: Hasil verifikasi pemantauan lapangan TW I 2026 Tim Kerja SFR">
                                        <small class="text-muted fs-11">Catatan ini akan tersimpan permanen pada riwayat audit untuk keperluan pelacakan dan verifikasi BPK/Inspektorat.</small>
                                    </div>

                                    <!-- 4. Mode Simulasi vs Terapkan Langsung -->
                                    <div class="card bg-light border-0 p-3 mb-4 rounded-3">
                                        <div class="form-check form-switch d-flex align-items-center justify-content-between ps-0 mb-0">
                                            <div>
                                                <label class="form-check-label fw-bold text-dark fs-13 mb-0" for="switchSimulation">Mode Simulasi (Cek Saja)</label>
                                                <p class="text-muted fs-11 mb-0">Jika diaktifkan, berkas hanya divalidasi dan dihitung hasilnya tanpa memperbarui database capaian kinerja.</p>
                                            </div>
                                            <input class="form-check-input ms-3 fs-5" type="checkbox" id="switchSimulation" name="simulation" value="1">
                                        </div>
                                    </div>

                                    <!-- Tombol Submit -->
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary px-4 rounded-pill fw-semibold shadow-sm" id="btnSubmitImport">
                                            <i class="material-symbols-outlined fs-18 align-middle me-1">auto_mode</i> Proses & Hitung Capaian
                                        </button>
                                        <button type="reset" class="btn btn-light px-3 rounded-pill" id="btnResetImport">Reset</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kanan: Panduan Teknis & Hasil Kalkulasi Realtime -->
                    <div class="col-xl-5 col-lg-5">
                        <!-- Hasil Impor Realtime Card (Hidden initially) -->
                        <div class="card border-0 shadow-sm mb-4 d-none" id="resultCard" style="border-radius: 18px; border-left: 5px solid #10b981 !important;">
                            <div class="card-header border-0 bg-transparent pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0 text-success">
                                    <i class="material-symbols-outlined align-middle me-1">check_circle</i>
                                    Hasil Kalkulasi Capaian
                                </h5>
                                <span class="badge bg-success rounded-pill px-2 py-1 fs-11" id="resStatusBadge">Berhasil</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="alert alert-info py-2 px-3 fs-12 mb-3" id="resSummaryText"></div>
                                
                                <div class="row g-2 mb-3 text-center">
                                    <div class="col-6">
                                        <div class="p-2 rounded bg-light border">
                                            <span class="fs-11 text-muted d-block">Sebelum Impor</span>
                                            <strong class="fs-16 text-dark" id="resBeforeVal">0%</strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 rounded bg-success bg-opacity-10 border border-success border-opacity-25">
                                            <span class="fs-11 text-success d-block fw-semibold">Hasil Baru</span>
                                            <strong class="fs-16 text-success" id="resAfterVal">0%</strong>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="fw-bold text-dark fs-12 mb-2">Evaluasi Baris Data:</h6>
                                <div class="table-responsive" style="max-height: 240px; overflow-y: auto;">
                                    <table class="table table-sm table-bordered fs-11 mb-0" id="resDetailTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Objek / Kab-Kota</th>
                                                <th>Hasil Evaluasi</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="resDetailBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Card Panduan Standar Nota Dinas No. 224 -->
                        <div class="card border-0 shadow-sm" style="border-radius: 18px;">
                            <div class="card-header border-0 bg-transparent pt-4 px-4 pb-0">
                                <h5 class="fw-bold mb-0 text-dark">
                                    <i class="material-symbols-outlined text-info me-2 align-middle">menu_book</i>
                                    Standar Formula Kinerja
                                </h5>
                                <p class="text-muted fs-12 mb-0">Nota Dinas Dirjen ID No. 224/DJID.6/PR.04.01/02/2026</p>
                            </div>
                            <div class="card-body p-4">
                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark fs-13 mb-1">Kriteria Kab/Kota Dinyatakan Termonitor (PK.1):</h6>
                                    <ul class="list-unstyled fs-12 text-muted mb-0">
                                        <li class="mb-2 d-flex align-items-start">
                                            <i class="material-symbols-outlined text-success fs-16 me-2 mt-1">check</i>
                                            <span><strong>Min 15 Pita Frekuensi:</strong> Termonitor minimal 15 sub-pita (Lampiran I ND 224).</span>
                                        </li>
                                        <li class="mb-2 d-flex align-items-start">
                                            <i class="material-symbols-outlined text-success fs-16 me-2 mt-1">check</i>
                                            <span><strong>Min 60% ISR acuan ROL:</strong> Terverifikasi Report Online (ROL).</span>
                                        </li>
                                        <li class="mb-2 d-flex align-items-start">
                                            <i class="material-symbols-outlined text-success fs-16 me-2 mt-1">check</i>
                                            <span><strong>100% Teridentifikasi:</strong> Seluruh stasiun hasil monitoring teridentifikasi.</span>
                                        </li>
                                        <li class="mb-2 d-flex align-items-start">
                                            <i class="material-symbols-outlined text-success fs-16 me-2 mt-1">check</i>
                                            <span><strong>100% Tindak Lanjut Sanksi:</strong> Pelanggaran telah diterbitkan SP/Teguran.</span>
                                        </li>
                                    </ul>
                                </div>

                                <div class="p-3 rounded-3 bg-light border mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark fs-12">Formula Penghitungan Capaian PK.1:</span>
                                        <span class="badge bg-primary fs-10">Target: 80%</span>
                                    </div>
                                    <div class="text-center py-2 bg-white rounded border my-2 font-monospace fs-12 fw-bold text-primary">
                                        (Jml Kab/Kota Termonitor / 10 Kab-Kota) &times; 100%
                                    </div>
                                    <small class="text-muted fs-11 d-block">
                                        Wilayah Balmon Samarinda: Samarinda, Balikpapan, Kukar, Kutim, Kubar, Berau, Paser, PPU (IKN), Bontang, Mahulu.
                                    </small>
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="api/download_template.php?type=pk1_sfr_xls" class="btn btn-outline-primary btn-xs rounded-pill w-100">
                                        <i class="material-symbols-outlined fs-14 align-middle me-1">download</i> Unduh Template Excel
                                    </a>
                                    <a href="api/download_template.php?type=pk1_sfr_csv" class="btn btn-outline-secondary btn-xs rounded-pill w-100">
                                        <i class="material-symbols-outlined fs-14 align-middle me-1">description</i> Unduh CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: RIWAYAT IMPOR & AUDIT TRACING -->
            <div class="tab-pane fade" id="tab-history" role="tabpanel">
                <div class="card border-0 shadow-sm" style="border-radius: 18px;">
                    <div class="card-header border-0 bg-transparent pt-4 px-4 pb-2 d-flex flex-wrap justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">
                                <i class="material-symbols-outlined text-primary me-2 align-middle">receipt_long</i>
                                Jejak Audit Unggahan & Tracing Kinerja
                            </h5>
                            <p class="text-muted fs-12 mb-0">Seluruh riwayat impor terekam lengkap beserta nama petugas pengunggah, berkas asli, dan rincian evaluasi per baris.</p>
                        </div>
                        <div class="d-flex gap-2 mt-2 mt-sm-0">
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" onclick="location.reload()">
                                <i class="material-symbols-outlined fs-15 align-middle me-1">refresh</i> Segarkan
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle fs-13 mb-0" id="tableHistory">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th>Kode Batch</th>
                                        <th>Waktu Unggah</th>
                                        <th>Nama Berkas</th>
                                        <th>Tipe & Target</th>
                                        <th>Pengunggah</th>
                                        <th>Hasil Capaian</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 140px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($historyList)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4 text-muted">
                                                <i class="material-symbols-outlined fs-36 text-muted mb-2 d-block">inbox</i>
                                                Belum ada riwayat impor data.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $no = 1; foreach ($historyList as $item): ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td>
                                                    <span class="badge bg-light text-dark font-monospace fs-11 border">
                                                        <?= htmlspecialchars($item['batch_code']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?= date('d M Y', strtotime($item['created_at'])) ?></div>
                                                    <div class="text-muted fs-11"><?= date('H:i:s', strtotime($item['created_at'])) ?> WITA</div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="material-symbols-outlined text-success fs-20 me-2">
                                                            <?= in_array($item['file_extension'], ['xlsx', 'xls']) ? 'table_chart' : 'description' ?>
                                                        </i>
                                                        <div>
                                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($item['original_filename']) ?>">
                                                                <?= htmlspecialchars($item['original_filename']) ?>
                                                            </div>
                                                            <div class="text-muted fs-11">
                                                                <?= round($item['file_size'] / 1024, 1) ?> KB &bull; <?= $item['valid_rows'] ?> baris valid
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 fs-11">
                                                        <?= htmlspecialchars($item['indicator_code'] ?? 'PK.1') ?>
                                                    </span>
                                                    <div class="text-muted fs-11 mt-1">
                                                        <?= $item['import_type'] === 'pk1_monitoring_sfr' ? 'Monitoring SFR' : 'Bulk Indikator PK' ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= htmlspecialchars($item['user_avatar'] ?? 'assets/images/avatar/1.jpg') ?>" class="rounded-circle me-2 border" width="28" height="28" alt="">
                                                        <div>
                                                            <div class="fw-semibold text-dark fs-12"><?= htmlspecialchars($item['uploader_name']) ?></div>
                                                            <div class="text-muted fs-10">NIP: <?= htmlspecialchars($item['uploader_nip'] ?: '-') ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($item['realization_after'] !== null): ?>
                                                        <div class="d-flex align-items-center gap-1">
                                                            <?php if ($item['realization_before'] !== null): ?>
                                                                <span class="text-muted text-decoration-line-through fs-11"><?= number_format($item['realization_before'], 1) ?>%</span>
                                                                <i class="material-symbols-outlined fs-12 text-muted">arrow_forward</i>
                                                            <?php endif; ?>
                                                            <strong class="text-success fs-13"><?= number_format($item['realization_after'], 1) ?>%</strong>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted fs-11">-</span>
                                                    <?php endif; ?>
                                                    <div class="text-muted fs-10 text-truncate" style="max-width: 160px;" title="<?= htmlspecialchars($item['summary_result']) ?>">
                                                        <?= htmlspecialchars($item['summary_result']) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($item['status'] === 'success'): ?>
                                                        <span class="badge bg-success rounded-pill px-2 py-1 fs-11"><i class="fa fa-check me-1"></i> Sukses</span>
                                                    <?php elseif ($item['status'] === 'simulation'): ?>
                                                        <span class="badge bg-info rounded-pill px-2 py-1 fs-11"><i class="fa fa-flask me-1"></i> Simulasi</span>
                                                    <?php elseif ($item['status'] === 'warning'): ?>
                                                        <span class="badge bg-warning rounded-pill px-2 py-1 fs-11"><i class="fa fa-exclamation me-1"></i> Catatan</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger rounded-pill px-2 py-1 fs-11"><i class="fa fa-times me-1"></i> Gagal</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-info" title="Lihat Jejak Audit Tracing" onclick="openDetailTracing(<?= $item['id'] ?>)">
                                                            <i class="material-symbols-outlined fs-15 align-middle">visibility</i>
                                                        </button>
                                                        <?php if (!empty($item['file_path']) && file_exists(__DIR__ . '/' . $item['file_path'])): ?>
                                                            <a href="<?= htmlspecialchars($item['file_path']) ?>" download="<?= htmlspecialchars($item['original_filename']) ?>" class="btn btn-outline-primary" title="Unduh Berkas Asli">
                                                                <i class="material-symbols-outlined fs-15 align-middle">download</i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if (in_array($currentUser['role'], ['admin', 'koordinator'])): ?>
                                                            <button type="button" class="btn btn-outline-danger" title="Hapus Riwayat" onclick="deleteHistory(<?= $item['id'] ?>, '<?= htmlspecialchars($item['batch_code']) ?>')">
                                                                <i class="material-symbols-outlined fs-15 align-middle">delete</i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- MODAL DETAIL TRACING & AUDIT TRAIL -->
<div class="modal fade" id="modalDetailTracing" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow" style="border-radius: 18px;">
            <div class="modal-header bg-light border-0 py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-circle me-3">
                        <i class="material-symbols-outlined fs-24">fact_check</i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0">Rincian Jejak Audit & Tracing Berkas</h5>
                        <p class="text-muted fs-12 mb-0" id="modalBatchSubtitle">Memuat data...</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="modalDetailTracingBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted fs-13 mt-2">Mengambil riwayat data audit...</p>
                </div>
            </div>
            <div class="modal-footer border-0 py-3 px-4 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                <a href="javascript:void(0)" id="modalDownloadBtn" class="btn btn-primary rounded-pill px-4">
                    <i class="material-symbols-outlined fs-16 align-middle me-1">download</i> Unduh Berkas Asli
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.custom-pills .nav-link {
    background: #f1f5f9;
    color: #475569;
    transition: all 0.2s ease;
}
.custom-pills .nav-link.active {
    background: #0070E0;
    color: #fff;
    box-shadow: 0 4px 12px rgba(0, 112, 224, 0.25);
}
.import-type-card {
    transition: all 0.2s ease;
    border-radius: 12px;
}
.import-type-card.active {
    border-color: #0070E0 !important;
    background-color: #eff6ff !important;
}
.upload-dropzone.dragover {
    border-color: #0070E0 !important;
    background-color: #e0f2fe !important;
}
</style>

<!-- SweetAlert2 for rich dialogs -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Radio Card Switch
    const radioPk1 = document.getElementById('typePk1');
    const radioBulk = document.getElementById('typeBulk');
    const cardPk1 = document.getElementById('labelTypePk1');
    const cardBulk = document.getElementById('labelTypeBulk');

    function updateCardActive() {
        if (radioPk1.checked) {
            cardPk1.classList.add('active');
            cardBulk.classList.remove('active');
        } else {
            cardBulk.classList.add('active');
            cardPk1.classList.remove('active');
        }
    }
    radioPk1.addEventListener('change', updateCardActive);
    radioBulk.addEventListener('change', updateCardActive);

    // Drag and Drop Zone
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const fileInfoDisplay = document.getElementById('fileInfoDisplay');

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('dragover');
        });
    });

    fileInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const f = this.files[0];
            fileNameDisplay.innerText = f.name;
            fileInfoDisplay.innerHTML = `<span class="badge bg-primary">${(f.size / 1024).toFixed(1)} KB</span> &bull; Format siap diproses`;
        }
    });

    // Reset Form
    document.getElementById('btnResetImport').addEventListener('click', function() {
        fileNameDisplay.innerText = 'Klik atau seret berkas ke sini';
        fileInfoDisplay.innerText = 'Mendukung berkas Microsoft Excel (.xlsx, .xls) dan CSV (.csv) maks 10MB';
        document.getElementById('resultCard').classList.add('d-none');
    });

    // Form Submit via AJAX
    const form = document.getElementById('formImportData');
    const btnSubmit = document.getElementById('btnSubmitImport');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!fileInput.files || !fileInput.files[0]) {
            Swal.fire({
                icon: 'warning',
                title: 'Berkas Belum Dipilih',
                text: 'Silakan pilih berkas Excel atau CSV terlebih dahulu.'
            });
            return;
        }

        const formData = new FormData(form);
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Memproses & Menghitung...`;

        fetch('api/import_process.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = `<i class="material-symbols-outlined fs-18 align-middle me-1">auto_mode</i> Proses & Hitung Capaian`;

            if (data.success) {
                // Tampilkan Result Card
                const resultCard = document.getElementById('resultCard');
                resultCard.classList.remove('d-none');

                document.getElementById('resStatusBadge').innerText = data.is_simulation ? 'Simulasi Berhasil' : 'Berhasil Diperbarui';
                document.getElementById('resStatusBadge').className = data.is_simulation ? 'badge bg-info rounded-pill px-2 py-1 fs-11' : 'badge bg-success rounded-pill px-2 py-1 fs-11';
                document.getElementById('resSummaryText').innerText = data.summary_result;

                if (data.realization_before !== null) {
                    document.getElementById('resBeforeVal').innerText = parseFloat(data.realization_before).toFixed(1) + '%';
                }
                if (data.realization_after !== null) {
                    document.getElementById('resAfterVal').innerText = parseFloat(data.realization_after).toFixed(1) + '%';
                    if (!data.is_simulation) {
                        document.getElementById('currentPk1Val').innerText = parseFloat(data.realization_after).toFixed(1) + '%';
                    }
                }

                // Render Table Baris
                const tbody = document.getElementById('resDetailBody');
                tbody.innerHTML = '';
                if (data.logs && data.logs.length > 0) {
                    data.logs.forEach(item => {
                        const tr = document.createElement('tr');
                        const isOk = item.evaluation.indexOf('MEMENUHI 4 SYARAT') !== -1 || item.status === 'valid';
                        const badgeHtml = isOk && item.data.memenuhi_syarat === 'YA' 
                            ? `<span class="badge bg-success fs-10">MEMENUHI</span>` 
                            : (item.data.memenuhi_syarat === 'TIDAK' ? `<span class="badge bg-danger fs-10">BELUM MEMENUHI</span>` : `<span class="badge bg-info fs-10">VALID</span>`);

                        tr.innerHTML = `
                            <td><strong>${item.identifier}</strong></td>
                            <td>${item.evaluation}</td>
                            <td>${badgeHtml}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }

                Swal.fire({
                    icon: 'success',
                    title: data.is_simulation ? 'Simulasi Sukses' : 'Impor & Kalkulasi Berhasil!',
                    text: data.message + (data.is_simulation ? '' : ' Nilai capaian kinerja pada sistem telah otomatis disinkronkan.'),
                    confirmButtonText: 'Bagus!'
                });

            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Memproses',
                    text: data.message || 'Terjadi kesalahan saat memproses berkas.'
                });
            }
        })
        .catch(err => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = `<i class="material-symbols-outlined fs-18 align-middle me-1">auto_mode</i> Proses & Hitung Capaian`;
            Swal.fire({
                icon: 'error',
                title: 'Error Koneksi',
                text: 'Terjadi kegagalan komunikasi dengan server: ' + err.message
            });
        });
    });
});

// Buka Modal Rincian Tracing
function openDetailTracing(id) {
    const modalEl = document.getElementById('modalDetailTracing');
    const modal = new bootstrap.Modal(modalEl);
    const body = document.getElementById('modalDetailTracingBody');
    const subtitle = document.getElementById('modalBatchSubtitle');
    const downloadBtn = document.getElementById('modalDownloadBtn');

    body.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted fs-13 mt-2">Mengambil riwayat data audit...</p>
        </div>
    `;
    modal.show();

    fetch('api/get_import_detail.php?id=' + id)
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            body.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            return;
        }

        const h = data.history;
        subtitle.innerText = `Batch: ${h.batch_code} &bull; Diunggah oleh: ${h.uploader_name} (${h.uploader_nip || '-'})`;
        downloadBtn.href = h.file_path;
        downloadBtn.download = h.original_filename;

        let rowsHtml = '';
        if (data.logs && data.logs.length > 0) {
            data.logs.forEach(log => {
                const parsed = log.row_data_parsed || {};
                let statusBadge = `<span class="badge bg-light text-dark fs-10 border">${log.status}</span>`;
                if (parsed.memenuhi_syarat === 'YA') {
                    statusBadge = `<span class="badge bg-success fs-10">MEMENUHI 4 SYARAT</span>`;
                } else if (parsed.memenuhi_syarat === 'TIDAK') {
                    statusBadge = `<span class="badge bg-danger fs-10">BELUM MEMENUHI</span>`;
                }

                rowsHtml += `
                    <tr>
                        <td class="text-center">${log.row_number}</td>
                        <td><strong>${log.row_identifier}</strong></td>
                        <td>
                            <div class="fs-12">${log.evaluation_notes}</div>
                            ${parsed.keterangan ? `<div class="text-muted fs-11 fst-italic">Ket: ${parsed.keterangan}</div>` : ''}
                        </td>
                        <td class="text-center">${statusBadge}</td>
                    </tr>
                `;
            });
        } else {
            rowsHtml = `<tr><td colspan="4" class="text-center text-muted py-3">Tidak ada catatan baris individual</td></tr>`;
        }

        body.innerHTML = `
            <!-- Metadata Info Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="fs-11 text-muted d-block">Berkas Asli</span>
                        <strong class="fs-13 text-dark text-truncate d-block" title="${h.original_filename}">${h.original_filename}</strong>
                        <span class="fs-11 text-muted">${(h.file_size / 1024).toFixed(1)} KB &bull; .${h.file_extension}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="fs-11 text-muted d-block">Waktu & IP Pengunggah</span>
                        <strong class="fs-13 text-dark d-block">${h.created_at}</strong>
                        <span class="fs-11 text-muted">IP: ${h.ip_address || '127.0.0.1'}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="fs-11 text-muted d-block">Indikator & Tipe</span>
                        <strong class="fs-13 text-primary d-block">${h.indicator_code}</strong>
                        <span class="fs-11 text-muted">${h.import_type === 'pk1_monitoring_sfr' ? 'Monitoring SFR Kab/Kota' : 'Bulk Indikator PK'}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25">
                        <span class="fs-11 text-success d-block fw-semibold">Hasil Realisasi Baru</span>
                        <strong class="fs-16 text-success d-block">${h.realization_after !== null ? parseFloat(h.realization_after).toFixed(1) + '%' : '-'}</strong>
                        <span class="fs-11 text-muted">${h.valid_rows} dari ${h.total_rows} baris valid</span>
                    </div>
                </div>
            </div>

            <!-- Catatan Audit & Ringkasan -->
            <div class="alert alert-primary bg-opacity-10 border-primary border-opacity-25 py-2 px-3 fs-12 mb-3">
                <strong><i class="material-symbols-outlined fs-15 align-middle me-1">info</i> Ringkasan Evaluasi:</strong> ${h.summary_result}
                ${h.notes ? `<div class="mt-1 text-muted fst-italic">Catatan Pengunggah: "${h.notes}"</div>` : ''}
            </div>

            <!-- Tabel Baris Evaluasi Tracing -->
            <h6 class="fw-bold text-dark fs-13 mb-2">Jejak Evaluasi Baris per Baris:</h6>
            <div class="table-responsive border rounded-3" style="max-height: 320px; overflow-y: auto;">
                <table class="table table-hover table-striped fs-12 mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width: 50px;" class="text-center">No</th>
                            <th style="width: 220px;">Kabupaten / Kota / Indikator</th>
                            <th>Detail Evaluasi Kriteria</th>
                            <th style="width: 160px;" class="text-center">Status Kelulusan</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>
        `;
    })
    .catch(err => {
        body.innerHTML = `<div class="alert alert-danger">Gagal mengambil data: ${err.message}</div>`;
    });
}

// Hapus Riwayat
function deleteHistory(id, batchCode) {
    Swal.fire({
        title: 'Hapus Riwayat Impor?',
        text: `Apakah Anda yakin ingin menghapus jejak audit untuk batch ${batchCode}? Berkas terkait juga akan dihapus.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('id', id);

            fetch('api/delete_import.php', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terhapus!',
                        text: data.message
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: data.message
                    });
                }
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: err.message
                });
            });
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
