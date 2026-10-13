<?php
// includes/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);

// 11 Kategori Kegiatan Monitoring Lapangan
$monitoringPages = [
    'monitoring_spektrum.php',
    'monitoring_microwavelink.php',
    'monitoring_radio_fm.php',
    'monitoring_tv_digital.php',
    'monitoring_hf.php',
    'monitoring_alat.php',
    'monitoring_penertiban_frekuensi.php',
    'monitoring_penertiban_alat.php',
    'monitoring_penugasan_lainnya.php',
    'monitoring_penanganan_gangguan.php',
    'monitoring_kualitas_layanan.php'
];
$isMonitoringActive = in_array($currentPage, $monitoringPages);

// Multi-Year App State
$activeAppYear = function_exists('getActiveAppYear') ? getActiveAppYear() : (int)($_GET['year'] ?? ($_SESSION['app_year'] ?? 2026));
$sidebarYears = function_exists('getAvailableAppYears') ? getAvailableAppYears() : [];
if (empty($sidebarYears)) {
    try {
        $pdoSb = getDbConnection();
        $sidebarYears = $pdoSb->query("SELECT * FROM pk_years_meta ORDER BY year DESC")->fetchAll();
    } catch (Exception $e) {
        $sidebarYears = [
            ['year' => 2026, 'title' => 'Perjanjian Kinerja Tahun 2026', 'status' => 'active']
        ];
    }
}

// Fetch PK indicators for the active year
$sidebarPkList = [];
try {
    $pdoSb = getDbConnection();
    $stmtSbPk = $pdoSb->prepare("SELECT code, title, target_annual, realization, unit FROM perjanjian_kinerja WHERE year = ? ORDER BY order_num ASC");
    $stmtSbPk->execute([$activeAppYear]);
    $sidebarPkList = $stmtSbPk->fetchAll();
} catch (Exception $e) {
    $sidebarPkList = [];
}

// Metadata visual untuk indikator PK (Tema Midnight Navy & Glowing Badges sesuai acuan)
$pkMetaMap = [
    2026 => [
        'PK.1' => [
            'num' => '1',
            'title' => 'Monitoring SFR',
            'sub' => 'Observasi 10 Kab/Kota Kaltim',
            'icon' => 'radar',
            'icon_color' => '#38bdf8',
            'icon_bg' => 'rgba(56, 189, 248, 0.12)',
            'icon_border' => 'rgba(56, 189, 248, 0.25)',
        ],
        'PK.2' => [
            'num' => '2',
            'title' => 'Pemeriksaan Stasiun',
            'sub' => 'Sampling Siaran & Hop Link',
            'icon' => 'settings_input_antenna',
            'icon_color' => '#2dd4bf',
            'icon_bg' => 'rgba(45, 212, 191, 0.12)',
            'icon_border' => 'rgba(45, 212, 191, 0.25)',
        ],
        'PK.3' => [
            'num' => '3',
            'title' => 'Gangguan SFR',
            'sub' => 'Trouble Ticket & Kasus',
            'icon' => 'warning_amber',
            'icon_color' => '#fbbf24',
            'icon_bg' => 'rgba(251, 191, 36, 0.12)',
            'icon_border' => 'rgba(251, 191, 36, 0.25)',
        ],
        'PK.4' => [
            'num' => '4',
            'title' => 'Monitoring APT',
            'sub' => '1.052 Perangkat SMART',
            'icon' => 'devices_other',
            'icon_color' => '#60a5fa',
            'icon_bg' => 'rgba(96, 165, 250, 0.12)',
            'icon_border' => 'rgba(96, 165, 250, 0.25)',
        ],
        'PK.5' => [
            'num' => '5',
            'title' => 'Tertib APT',
            'sub' => 'Standar Teknis & Sanksi',
            'icon' => 'gavel',
            'icon_color' => '#34d399',
            'icon_bg' => 'rgba(52, 211, 153, 0.12)',
            'icon_border' => 'rgba(52, 211, 153, 0.25)',
        ],
        'PK.6' => [
            'num' => '6',
            'title' => 'SLA SMFR',
            'sub' => 'Stasiun Tetap & Transportable',
            'icon' => 'speed',
            'icon_color' => '#a78bfa',
            'icon_bg' => 'rgba(167, 139, 250, 0.12)',
            'icon_border' => 'rgba(167, 139, 250, 0.25)',
        ],
        'PK.7' => [
            'num' => '7',
            'title' => 'Sanksi Denda',
            'sub' => 'SPP Denda & Kepatuhan',
            'icon' => 'account_balance_wallet',
            'icon_color' => '#f472b6',
            'icon_bg' => 'rgba(244, 114, 182, 0.12)',
            'icon_border' => 'rgba(244, 114, 182, 0.25)',
        ],
        'PK.8' => [
            'num' => '8',
            'title' => 'QoS Seluler',
            'sub' => 'Kualitas Layanan Kaltim',
            'icon' => 'signal_cellular_alt',
            'icon_color' => '#38bdf8',
            'icon_bg' => 'rgba(56, 189, 248, 0.12)',
            'icon_border' => 'rgba(56, 189, 248, 0.25)',
        ],
    ],
    2025 => [
        'PK.1' => [
            'num' => '1',
            'title' => 'Monitoring SFR',
            'sub' => 'Observasi Spektrum',
            'icon' => 'radar',
            'icon_color' => '#38bdf8',
            'icon_bg' => 'rgba(56, 189, 248, 0.12)',
            'icon_border' => 'rgba(56, 189, 248, 0.25)',
        ],
        'PK.2' => [
            'num' => '2',
            'title' => 'Pemeriksaan Stasiun',
            'sub' => 'Stasiun Radio Balmon',
            'icon' => 'settings_input_antenna',
            'icon_color' => '#2dd4bf',
            'icon_bg' => 'rgba(45, 212, 191, 0.12)',
            'icon_border' => 'rgba(45, 212, 191, 0.25)',
        ],
        'PK.3' => [
            'num' => '3',
            'title' => 'Gangguan SFR',
            'sub' => 'Penyelesaian Gangguan',
            'icon' => 'warning_amber',
            'icon_color' => '#fbbf24',
            'icon_bg' => 'rgba(251, 191, 36, 0.12)',
            'icon_border' => 'rgba(251, 191, 36, 0.25)',
        ],
        'PK.4' => [
            'num' => '4',
            'title' => 'Kepatuhan APT',
            'sub' => 'Standar Teknis APT',
            'icon' => 'devices_other',
            'icon_color' => '#60a5fa',
            'icon_bg' => 'rgba(96, 165, 250, 0.12)',
            'icon_border' => 'rgba(96, 165, 250, 0.25)',
        ],
        'PK.5' => [
            'num' => '5',
            'title' => 'SLA SMFR',
            'sub' => 'Operasional Stasiun',
            'icon' => 'speed',
            'icon_color' => '#a78bfa',
            'icon_bg' => 'rgba(167, 139, 250, 0.12)',
            'icon_border' => 'rgba(167, 139, 250, 0.25)',
        ],
        'PK.6' => [
            'num' => '6',
            'title' => 'Sanksi Denda',
            'sub' => 'Denda Administratif',
            'icon' => 'account_balance_wallet',
            'icon_color' => '#f472b6',
            'icon_bg' => 'rgba(244, 114, 182, 0.12)',
            'icon_border' => 'rgba(244, 114, 182, 0.25)',
        ],
        'PK.7' => [
            'num' => '7',
            'title' => 'QoS Layanan',
            'sub' => 'Data Pengukuran QoS',
            'icon' => 'signal_cellular_alt',
            'icon_color' => '#38bdf8',
            'icon_bg' => 'rgba(56, 189, 248, 0.12)',
            'icon_border' => 'rgba(56, 189, 248, 0.25)',
        ],
    ]
];
?>
<style>
/* ========================================================
   THEME MIDNIGHT OCEANIC NAVY SIDEBAR (ACCORDING TO REFERENCE)
   ======================================================== */
.dlabnav,
.dlabnav .dlabnav-scroll {
    background-color: #031730 !important;
}

/* Custom Scrollbar */
.dlabnav-scroll::-webkit-scrollbar {
    width: 4px;
}
.dlabnav-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.dlabnav-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.12);
    border-radius: 10px;
}
.dlabnav-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(56, 189, 248, 0.3);
}

/* Multi-Year Switcher di Sidebar */
.sidebar-year-header {
    margin: 8px 10px 4px 10px !important;
    padding: 0 !important;
    display: block !important;
    position: relative !important;
}
.sidebar-year-header .bootstrap-select {
    display: none !important;
}
.sidebar-year-box {
    display: flex !important;
    align-items: center !important;
    position: relative !important;
    width: 100% !important;
    height: 28px !important;
    min-height: 28px !important;
    border-radius: 6px !important;
    padding: 0 6px 0 8px !important;
    background-color: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2) !important;
    transition: all 0.2s ease-in-out !important;
}
.sidebar-year-box:hover,
.sidebar-year-box:focus-within {
    border-color: #38bdf8 !important;
    background-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2) !important;
}
.sidebar-year-icon {
    font-size: 11px !important;
    color: #38bdf8 !important;
    margin-right: 6px !important;
    flex-shrink: 0 !important;
    pointer-events: none !important;
}
.sidebar-year-select {
    flex: 1 1 auto !important;
    width: 100% !important;
    height: 100% !important;
    border: none !important;
    background: transparent !important;
    font-size: 0.7rem !important; /* ~11px */
    font-weight: 600 !important;
    line-height: 28px !important;
    color: #f1f5f9 !important;
    padding: 0 18px 0 0 !important;
    margin: 0 !important;
    cursor: pointer !important;
    outline: none !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    display: block !important;
}
.sidebar-year-caret {
    position: absolute !important;
    right: 8px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    font-size: 9px !important;
    color: #7ea4cc !important;
    pointer-events: none !important;
}
.sidebar-year-opt {
    background-color: #031730 !important;
    color: #f1f5f9 !important;
    padding: 5px 8px;
    font-weight: 500;
}

/* Label Seksi Header Terpadu (IKHTISAR DASHBOARD, CAPAIAN KINERJA, KEGIATAN MONITORING) */
.sidebar-sec-label,
.pk-header-title,
.dlabnav .metismenu .nav-label {
    font-size: 0.625rem !important; /* 10px */
    font-weight: 800 !important;
    letter-spacing: 0.8px !important;
    text-transform: uppercase !important;
    color: #5d85ab !important;
    line-height: normal !important;
}

.sidebar-sec-label {
    padding: 10px 12px 4px 12px;
}

/* Tombol Ikhtisar Utama (Gradient Pill Button) */
.sidebar-overview-wrap {
    padding: 0 10px 4px 10px;
}
.sidebar-overview-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(90deg, #1d6fe9 0%, #009ceb 55%, #00c0f8 100%) !important;
    padding: 8px 12px;
    border-radius: 10px;
    color: #ffffff !important;
    text-decoration: none !important;
    box-shadow: 0 4px 16px rgba(0, 156, 235, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.sidebar-overview-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(0, 156, 235, 0.45);
    filter: brightness(1.04);
    color: #ffffff !important;
}
.sidebar-overview-btn.active {
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.4), 0 6px 20px rgba(0, 156, 235, 0.5) !important;
}
.overview-icon {
    font-size: 18px !important;
    color: #ffffff !important;
    margin-right: 8px;
    font-variation-settings: 'FILL' 1;
}
.overview-title {
    font-size: 12px;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: -0.1px;
}
.overview-badge {
    font-size: 9.5px;
    font-weight: 600;
    color: #ffffff;
    background: rgba(255, 255, 255, 0.22);
    border: 1px solid rgba(255, 255, 255, 0.45);
    border-radius: 999px;
    padding: 2px 8px;
    letter-spacing: 0.2px;
    line-height: 1.2;
}

/* Header Capaian Kinerja */
.sidebar-pk-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4px 12px 6px 12px;
}
.pk-sasaran-badge {
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: #38bdf8;
    background: #082245;
    border: 1px solid rgba(56, 189, 248, 0.35);
    border-radius: 5px;
    padding: 2px 6.5px;
    line-height: 1.2;
}

/* Header Collapsible Styles (Capaian Kinerja & Kegiatan Monitoring) */
.sidebar-collapsible-header,
.sidebar-nav-header-toggle {
    cursor: pointer !important;
    user-select: none !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
    border-radius: 6px !important;
}
.sidebar-collapsible-header:hover,
.sidebar-nav-header-toggle:hover {
    background: rgba(255, 255, 255, 0.05) !important;
}
.sidebar-collapsible-header:hover .pk-header-title,
.sidebar-nav-header-toggle:hover span {
    color: #38bdf8 !important;
}
.sidebar-collapsible-header:hover .sidebar-toggle-caret,
.sidebar-nav-header-toggle:hover .sidebar-toggle-caret {
    color: #38bdf8 !important;
}

.sidebar-nav-header-toggle {
    padding: 4px 12px 6px 12px !important;
    margin: 2px 0 !important;
}

/* Caret Arrow Rotation */
.sidebar-toggle-caret {
    font-size: 8px !important;
    color: #5d85ab !important;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
    margin-left: 6px !important;
    display: inline-block !important;
}
.sidebar-collapsible-header.is-collapsed .sidebar-toggle-caret,
.sidebar-nav-header-toggle.is-collapsed .sidebar-toggle-caret {
    transform: rotate(-90deg) !important;
}

/* Badge Kegiatan Monitoring */
.pk-monitoring-badge {
    font-size: 9px !important;
    font-weight: 800 !important;
    letter-spacing: 0.5px !important;
    text-transform: uppercase !important;
    color: #2dd4bf !important;
    background: #022a3a !important;
    border: 1px solid rgba(45, 212, 191, 0.35) !important;
    border-radius: 5px !important;
    padding: 2px 6.5px !important;
    line-height: 1.2 !important;
}

/* Collapsed Hidden State */
.sidebar-pk-list.is-collapsed {
    display: none !important;
}
.monitoring-nav-item.is-collapsed {
    display: none !important;
}

/* List Indikator Kinerja */
.sidebar-pk-list {
    padding: 0 8px;
    display: flex;
    flex-direction: column;
    gap: 2.5px;
}
.sidebar-pk-item {
    display: flex;
    align-items: center;
    padding: 5px 7px;
    border-radius: 8px;
    text-decoration: none !important;
    background: transparent;
    border: 1px solid transparent;
    transition: all 0.18s ease;
}
.sidebar-pk-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.08);
}
.sidebar-pk-item.active {
    background: rgba(56, 189, 248, 0.12) !important;
    border-color: rgba(56, 189, 248, 0.35) !important;
    box-shadow: 0 2px 10px rgba(0, 168, 232, 0.12);
}
.pk-icon-box {
    width: 32px;
    height: 32px;
    min-width: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid;
    flex-shrink: 0;
    transition: transform 0.18s ease;
}
.sidebar-pk-item:hover .pk-icon-box {
    transform: scale(1.04);
}
.pk-text-wrap {
    flex: 1 1 auto;
    min-width: 0;
    margin: 0 8px;
}
.pk-title {
    font-size: 11px;
    font-weight: 600;
    color: #ffffff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.25;
}
.pk-sub {
    font-size: 9.5px;
    font-weight: 500;
    color: #769cc3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
    margin-top: 1px;
}
.pk-badge {
    font-size: 9.5px;
    font-weight: 700;
    border-radius: 5px;
    padding: 2px 6px;
    flex-shrink: 0;
    line-height: 1.2;
    text-align: center;
}
.pk-badge-green {
    background: rgba(16, 185, 129, 0.16);
    border: 1px solid rgba(16, 185, 129, 0.35);
    color: #34d399;
}
.pk-badge-teal {
    background: rgba(20, 184, 166, 0.16);
    border: 1px solid rgba(20, 184, 166, 0.35);
    color: #2dd4bf;
}
.pk-badge-cyan {
    background: rgba(56, 189, 248, 0.16);
    border: 1px solid rgba(56, 189, 248, 0.35);
    color: #38bdf8;
}
.pk-badge-amber {
    background: rgba(245, 158, 11, 0.16);
    border: 1px solid rgba(245, 158, 11, 0.35);
    color: #fbbf24;
}
.pk-badge-muted {
    background: rgba(148, 163, 184, 0.14);
    border: 1px solid rgba(148, 163, 184, 0.25);
    color: #94a3b8;
}

/* Garis Pemisah Sidebar */
.sidebar-divider {
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    margin: 10px 12px 8px 12px;
}

/* Navigasi Standard Metismenu - Card Style Sesuai Indikator Kinerja */
.dlabnav .metismenu {
    padding: 0 8px !important;
}
.dlabnav .metismenu .nav-label {
    padding: 10px 4px 4px 4px !important;
    border-top: none !important;
}
.dlabnav .metismenu > li {
    margin: 2.5px 0 !important;
}
.dlabnav .metismenu > li > a {
    display: flex !important;
    align-items: center !important;
    padding: 5px 7px !important;
    margin: 0 !important;
    border-radius: 8px !important;
    border: 1px solid transparent !important;
    background: transparent !important;
    text-decoration: none !important;
    position: relative !important;
    transition: all 0.18s ease !important;
}
.dlabnav .metismenu > li:hover > a {
    background: rgba(255, 255, 255, 0.05) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    color: #ffffff !important;
}
.dlabnav .metismenu > li:hover > a .pk-icon-box {
    transform: scale(1.04);
}
.dlabnav .metismenu > li.mm-active > a,
.dlabnav .metismenu > li > a.mm-active {
    background: rgba(56, 189, 248, 0.12) !important;
    border: 1px solid rgba(56, 189, 248, 0.35) !important;
    box-shadow: 0 2px 10px rgba(0, 168, 232, 0.12) !important;
    color: #ffffff !important;
}
.dlabnav .metismenu .pk-icon-box .material-symbols-outlined,
.pk-icon-box .material-symbols-outlined {
    font-size: 16px !important;
    width: auto !important;
    min-width: auto !important;
    height: auto !important;
    margin: 0 !important;
    line-height: 1 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.dlabnav .metismenu > li > a.has-arrow {
    padding-right: 28px !important;
}
.dlabnav .metismenu .has-arrow:after {
    border-color: #7ea4cc transparent transparent #7ea4cc !important;
    border-width: 1.5px 0 0 1.5px !important;
    width: 6px !important;
    height: 6px !important;
    right: 11px !important;
    top: 50% !important;
    margin-top: -3px !important;
    transition: all 0.2s ease !important;
}
.dlabnav .metismenu > li:hover > a.has-arrow:after {
    border-color: #ffffff transparent transparent #ffffff !important;
}
.dlabnav .metismenu > li.mm-active > a.has-arrow:after,
.dlabnav .metismenu .has-arrow[aria-expanded=true]:after {
    border-color: #38bdf8 transparent transparent #38bdf8 !important;
}
.dlabnav .metismenu ul {
    background-color: #020f20 !important;
    border-radius: 6px !important;
    margin: 3px 0 5px 0 !important;
    padding: 4px 0 !important;
    border: 1px solid rgba(255, 255, 255, 0.05) !important;
}
.dlabnav .metismenu ul a {
    font-size: 0.6875rem !important; /* 11px */
    padding: 0.35rem 0.65rem 0.35rem 1.25rem !important;
    color: #8da4be !important;
    border-radius: 5px !important;
    margin: 1px 4px !important;
    transition: all 0.18s ease;
    display: flex !important;
    align-items: center !important;
}
.dlabnav .metismenu ul a:hover {
    color: #ffffff !important;
    background-color: rgba(56, 189, 248, 0.08) !important;
}
.dlabnav .metismenu ul a.mm-active,
.dlabnav .metismenu ul li.mm-active > a {
    color: #38bdf8 !important;
    background-color: rgba(56, 189, 248, 0.14) !important;
    font-weight: 600 !important;
}
.dlabnav .metismenu ul a .sub-menu-icon {
    font-size: 14px !important;
    margin-right: 6px !important;
    color: #5d85ab !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: auto !important;
    height: auto !important;
}
.dlabnav .metismenu ul a:hover .sub-menu-icon,
.dlabnav .metismenu ul a.mm-active .sub-menu-icon {
    color: #38bdf8 !important;
}

/* Penyesuaian Mini Sidebar (Menu Toggle) */
#main-wrapper.menu-toggle .pk-text-wrap,
#main-wrapper.menu-toggle .pk-badge,
#main-wrapper.menu-toggle .pk-sasaran-badge,
#main-wrapper.menu-toggle .pk-monitoring-badge,
#main-wrapper.menu-toggle .sidebar-toggle-caret,
#main-wrapper.menu-toggle .sidebar-sec-label,
#main-wrapper.menu-toggle .sidebar-pk-header,
#main-wrapper.menu-toggle .sidebar-nav-header-toggle,
#main-wrapper.menu-toggle .overview-title,
#main-wrapper.menu-toggle .overview-badge,
#main-wrapper.menu-toggle .sidebar-year-header,
[data-sidebar-style="mini"] .pk-text-wrap,
[data-sidebar-style="mini"] .pk-badge,
[data-sidebar-style="mini"] .pk-sasaran-badge,
[data-sidebar-style="mini"] .pk-monitoring-badge,
[data-sidebar-style="mini"] .sidebar-toggle-caret,
[data-sidebar-style="mini"] .sidebar-sec-label,
[data-sidebar-style="mini"] .sidebar-pk-header,
[data-sidebar-style="mini"] .sidebar-nav-header-toggle,
[data-sidebar-style="mini"] .overview-title,
[data-sidebar-style="mini"] .overview-badge,
[data-sidebar-style="mini"] .sidebar-year-header {
    display: none !important;
}
#main-wrapper.menu-toggle .sidebar-overview-btn,
[data-sidebar-style="mini"] .sidebar-overview-btn {
    justify-content: center !important;
    padding: 8px !important;
}
#main-wrapper.menu-toggle .overview-icon,
[data-sidebar-style="mini"] .overview-icon {
    margin-right: 0 !important;
}
#main-wrapper.menu-toggle .sidebar-pk-item,
[data-sidebar-style="mini"] .sidebar-pk-item {
    justify-content: center !important;
    padding: 5px 0 !important;
}
#main-wrapper.menu-toggle .dlabnav .metismenu > li > a,
[data-sidebar-style="mini"] .dlabnav .metismenu > li > a {
    justify-content: center !important;
    padding: 5px 0 !important;
}
#main-wrapper.menu-toggle .dlabnav .metismenu > li > a.has-arrow:after,
[data-sidebar-style="mini"] .dlabnav .metismenu > li > a.has-arrow:after,
#main-wrapper.menu-toggle .online-dot,
[data-sidebar-style="mini"] .online-dot {
    display: none !important;
}
</style>
<div class="dlabnav">
    <div class="dlabnav-scroll">
        <!-- Multi-Year Switcher di Sidebar -->
        <div class="sidebar-year-header">
            <div class="sidebar-year-box">
                <i class="fa-solid fa-calendar-days sidebar-year-icon"></i>
                <select id="sidebarPkYearSelect" 
                        class="sidebar-year-select no-bootstrap-select" 
                        onchange="if(this.value) window.location.href=this.value;"
                        title="Pilih Tahun Anggaran Capaian Kinerja">
                    <?php foreach ($sidebarYears as $sy): 
                        $yNum = (int)$sy['year'];
                        $isYearActive = ($activeAppYear === $yNum);
                        $targetUrl = ($currentPage === 'pk_view.php') ? "pk_view.php?code=PK.1&year={$yNum}" : "index.php?year={$yNum}";
                        $statusLabel = $sy['status'] === 'active' ? 'Aktif' : ($sy['status'] === 'archived' ? 'Arsip' : 'Rencana');
                    ?>
                        <option value="<?= $targetUrl ?>" <?= $isYearActive ? 'selected' : '' ?> class="sidebar-year-opt">
                            Tahun <?= $yNum ?> (<?= $statusLabel ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <i class="fa-solid fa-chevron-down sidebar-year-caret"></i>
            </div>
        </div>

        <!-- SEKSI 1: IKHTISAR DASHBOARD -->
        <div class="sidebar-sec-label">IKHTISAR DASHBOARD</div>
        <div class="sidebar-overview-wrap">
            <a href="index.php?year=<?= $activeAppYear ?>" class="sidebar-overview-btn <?= ($currentPage === 'index.php') ? 'active' : '' ?>">
                <div class="d-flex align-items-center">
                    <span class="material-symbols-outlined overview-icon">grid_view</span>
                    <span class="overview-title">Ikhtisar Utama</span>
                </div>
                <span class="overview-badge">Overview</span>
            </a>
        </div>

        <!-- DIVIDER -->
        <div class="sidebar-divider"></div>

        <!-- SEKSI 2: CAPAIAN KINERJA -->
        <div class="sidebar-pk-header sidebar-collapsible-header" id="headingPk" role="button" tabindex="0" onclick="toggleSidebarSection('pk', event)" title="Klik untuk melipat / membuka Capaian Kinerja">
            <div class="d-flex align-items-center">
                <span class="pk-header-title">Capaian Kinerja</span>
                <i class="fa-solid fa-chevron-down sidebar-toggle-caret" id="caretPk"></i>
            </div>
            <span class="pk-sasaran-badge"><?= count($sidebarPkList) ?> SASARAN</span>
        </div>
        <div class="sidebar-pk-list" id="sidebarPkList">
            <?php foreach ($sidebarPkList as $idx => $p): 
                $pCode = $p['code'];
                $isPkActive = ($currentPage === 'pk_view.php' && ($_GET['code'] ?? 'PK.1') === $pCode);
                
                $meta = $pkMetaMap[$activeAppYear][$pCode] ?? null;
                if (!$meta) {
                    $num = preg_match('/PK\.(\d+)/i', $pCode, $m) ? $m[1] : (string)($idx + 1);
                    $title = function_exists('getPkShortTitle') ? getPkShortTitle($activeAppYear, $pCode, $p['title']) : $pCode;
                    $sub = $p['unit'] ? ('Target ' . $p['target_annual'] . ' ' . $p['unit']) : 'Indikator Kinerja';
                    $icon = 'insights';
                    $iconColor = '#38bdf8';
                    $iconBg = 'rgba(56, 189, 248, 0.12)';
                    $iconBorder = 'rgba(56, 189, 248, 0.25)';
                } else {
                    $num = $meta['num'];
                    $title = $meta['title'];
                    $sub = $meta['sub'];
                    $icon = $meta['icon'];
                    $iconColor = $meta['icon_color'];
                    $iconBg = $meta['icon_bg'];
                    $iconBorder = $meta['icon_border'];
                }
                
                $target = (float)$p['target_annual'];
                $real = (float)$p['realization'];
                
                if ($activeAppYear > 2026) {
                    $badgeText = "Tgt " . ($target > 0 ? (floor($target) == $target ? (int)$target : $target) . '%' : '-');
                    $badgeClass = "pk-badge-cyan";
                } else {
                    $badgeText = (floor($real) == $real) ? number_format($real, 0) . '%' : str_replace('.', ',', (string)$real) . '%';
                    if ($real >= 80) {
                        $badgeClass = "pk-badge-green";
                    } elseif ($real >= 60) {
                        $badgeClass = "pk-badge-cyan";
                    } elseif ($real > 0) {
                        $badgeClass = "pk-badge-amber";
                    } else {
                        $badgeClass = "pk-badge-muted";
                    }
                }
            ?>
                <a href="pk_view.php?code=<?= urlencode($pCode) ?>&year=<?= $activeAppYear ?>" 
                   class="sidebar-pk-item <?= $isPkActive ? 'active' : '' ?>"
                   title="<?= htmlspecialchars($p['title']) ?>">
                    <div class="pk-icon-box" style="background: <?= $iconBg ?>; border-color: <?= $iconBorder ?>;">
                        <span class="material-symbols-outlined" style="color: <?= $iconColor ?>; font-size: 16px;"><?= $icon ?></span>
                    </div>
                    <div class="pk-text-wrap">
                        <div class="pk-title"><?= $num ?>. <?= htmlspecialchars($title) ?></div>
                        <div class="pk-sub"><?= htmlspecialchars($sub) ?></div>
                    </div>
                    <div class="pk-badge <?= $badgeClass ?>"><?= $badgeText ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- DIVIDER -->
        <div class="sidebar-divider"></div>

        <!-- SEKSI 3: KEGIATAN MONITORING -->
        <div class="sidebar-pk-header sidebar-collapsible-header" id="headingMonitoring" role="button" tabindex="0" onclick="toggleSidebarSection('mon', event)" title="Klik untuk melipat / membuka Kegiatan Monitoring">
            <div class="d-flex align-items-center">
                <span class="pk-header-title">Kegiatan Monitoring</span>
                <i class="fa-solid fa-chevron-down sidebar-toggle-caret" id="caretMon"></i>
            </div>
            <span class="pk-monitoring-badge">11 KEGIATAN</span>
        </div>
        <div class="sidebar-pk-list" id="sidebarMonitoringList">
            <!-- 1. Monitoring SFR -->
            <a href="monitoring_spektrum.php" class="sidebar-pk-item <?= $currentPage === 'monitoring_spektrum.php' ? 'active' : '' ?>" title="Monitoring Spektrum Frekuensi Radio">
                <div class="pk-icon-box" style="background: rgba(56, 189, 248, 0.12); border-color: rgba(56, 189, 248, 0.25);">
                    <span class="material-symbols-outlined" style="color: #38bdf8; font-size: 16px;">cell_tower</span>
                </div>
                <div class="pk-text-wrap">
                    <div class="pk-title">Monitoring SFR</div>
                    <div class="pk-sub">Spektrum Frekuensi Radio</div>
                </div>
            </a>

            <!-- 2. Microwavelink -->
            <a href="monitoring_microwavelink.php" class="sidebar-pk-item <?= $currentPage === 'monitoring_microwavelink.php' ? 'active' : '' ?>" title="Monitoring Microwavelink">
                <div class="pk-icon-box" style="background: rgba(45, 212, 191, 0.12); border-color: rgba(45, 212, 191, 0.25);">
                    <span class="material-symbols-outlined" style="color: #2dd4bf; font-size: 16px;">settings_input_antenna</span>
                </div>
                <div class="pk-text-wrap">
                    <div class="pk-title">Microwavelink</div>
                    <div class="pk-sub">Hop Link & Titik Stasiun</div>
                </div>
            </a>

            <!-- 3. Radio Siaran FM -->
            <a href="monitoring_radio_fm.php" class="sidebar-pk-item <?= $currentPage === 'monitoring_radio_fm.php' ? 'active' : '' ?>" title="Monitoring Radio Siaran FM">
                <div class="pk-icon-box" style="background: rgba(251, 191, 36, 0.12); border-color: rgba(251, 191, 36, 0.25);">
                    <span class="material-symbols-outlined" style="color: #fbbf24; font-size: 16px;">radio</span>
                </div>
                <div class="pk-text-wrap">
                    <div class="pk-title">Radio Siaran FM</div>
                    <div class="pk-sub">Observasi Pemancar FM</div>
                </div>
            </a>

            <!-- 4. TV Digital -->
            <a href="monitoring_tv_digital.php" class="sidebar-pk-item <?= $currentPage === 'monitoring_tv_digital.php' ? 'active' : '' ?>" title="Monitoring TV Digital">
                <div class="pk-icon-box" style="background: rgba(96, 165, 250, 0.12); border-color: rgba(96, 165, 250, 0.25);">
                    <span class="material-symbols-outlined" style="color: #60a5fa; font-size: 16px;">live_tv</span>
                </div>
                <div class="pk-text-wrap">
                    <div class="pk-title">TV Digital</div>
                    <div class="pk-sub">Kanal DVB-T2 Kaltim</div>
                </div>
            </a>

            <!-- 5. Monitoring HF -->
            <a href="monitoring_hf.php" class="sidebar-pk-item <?= $currentPage === 'monitoring_hf.php' ? 'active' : '' ?>" title="Monitoring High Frequency">
                <div class="pk-icon-box" style="background: rgba(167, 139, 250, 0.12); border-color: rgba(167, 139, 250, 0.25);">
                    <span class="material-symbols-outlined" style="color: #a78bfa; font-size: 16px;">waves</span>
                </div>
                <div class="pk-text-wrap">
                    <div class="pk-title">Monitoring HF</div>
                    <div class="pk-sub">High Frequency 3-30 MHz</div>
                </div>
            </a>

            <!-- 6. Monitoring APT -->
            <a href="monitoring_alat.php" class="sidebar-pk-item <?= $currentPage === 'monitoring_alat.php' ? 'active' : '' ?>" title="Monitoring Alat & Perangkat Telekomunikasi">
                <div class="pk-icon-box" style="background: rgba(52, 211, 153, 0.12); border-color: rgba(52, 211, 153, 0.25);">
                    <span class="material-symbols-outlined" style="color: #34d399; font-size: 16px;">devices</span>
                </div>
                <div class="pk-text-wrap">
                    <div class="pk-title">Monitoring APT</div>
                    <div class="pk-sub">Alat & Perangkat SMART</div>
                </div>
            </a>

            <!-- 7. Penertiban SFR -->
            <a href="monitoring_penertiban_frekuensi.php" class="sidebar-pk-item <?= $currentPage === 'monitoring_penertiban_frekuensi.php' ? 'active' : '' ?>" title="Operasi Penertiban Spektrum Frekuensi">
                <div class="pk-icon-box" style="background: rgba(248, 113, 113, 0.12); border-color: rgba(248, 113, 113, 0.25);">
                    <span class="material-symbols-outlined" style="color: #f87171; font-size: 16px;">gavel</span>
                </div>
                <div class="pk-text-wrap">
                    <div class="pk-title">Penertiban SFR</div>
                    <div class="pk-sub">Operasi Tertib Frekuensi</div>
                </div>
            </a>

            <!-- 8. Penertiban APT -->
            <a href="monitoring_penertiban_alat.php" class="sidebar-pk-item <?= $currentPage === 'monitoring_penertiban_alat.php' ? 'active' : '' ?>" title="Penertiban Alat & Perangkat Telekomunikasi">
                <div class="pk-icon-box" style="background: rgba(251, 146, 60, 0.12); border-color: rgba(251, 146, 60, 0.25);">
                    <span class="material-symbols-outlined" style="color: #fb923c; font-size: 16px;">security</span>
                </div>
                <div class="pk-text-wrap">
                    <div class="pk-title">Penertiban APT</div>
                    <div class="pk-sub">Sertifikasi & Standar Alat</div>
                </div>
            </a>

            <!-- 9. Monitoring Lainnya -->
            <a href="monitoring_penugasan_lainnya.php" class="sidebar-pk-item <?= $currentPage === 'monitoring_penugasan_lainnya.php' ? 'active' : '' ?>" title="Monitoring Penugasan Lainnya">
                <div class="pk-icon-box" style="background: rgba(244, 114, 182, 0.12); border-color: rgba(244, 114, 182, 0.25);">
                    <span class="material-symbols-outlined" style="color: #f472b6; font-size: 16px;">stars</span>
                </div>
                <div class="pk-text-wrap">
                    <div class="pk-title">Monitoring Lainnya</div>
                    <div class="pk-sub">Penugasan Khusus Balmon</div>
                </div>
            </a>

            <!-- 10. Penanganan Gangguan -->
            <a href="monitoring_penanganan_gangguan.php" class="sidebar-pk-item <?= $currentPage === 'monitoring_penanganan_gangguan.php' ? 'active' : '' ?>" title="Penanganan Gangguan Frekuensi Radio">
                <div class="pk-icon-box" style="background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.25);">
                    <span class="material-symbols-outlined" style="color: #f59e0b; font-size: 16px;">report_problem</span>
                </div>
                <div class="pk-text-wrap">
                    <div class="pk-title">Penanganan Gangguan</div>
                    <div class="pk-sub">Interferensi Spektrum Radio</div>
                </div>
            </a>

            <!-- 11. Kualitas Layanan (QoS) -->
            <a href="monitoring_kualitas_layanan.php" class="sidebar-pk-item <?= $currentPage === 'monitoring_kualitas_layanan.php' ? 'active' : '' ?>" title="Pengukuran Kualitas Layanan (QoS)">
                <div class="pk-icon-box" style="background: rgba(14, 165, 233, 0.12); border-color: rgba(14, 165, 233, 0.25);">
                    <span class="material-symbols-outlined" style="color: #0ea5e9; font-size: 16px;">speed</span>
                </div>
                <div class="pk-text-wrap">
                    <div class="pk-title">Kualitas Layanan (QoS)</div>
                    <div class="pk-sub">Pengukuran Seluler & Internet</div>
                </div>
            </a>
        </div>

        <!-- DIVIDER -->
        <div class="sidebar-divider"></div>

        <!-- MENU SISTEM (METISMENU) -->
        <ul class="metismenu" id="menu">
            <!-- SEKSI 4: FITUR SISTEM -->
            <li class="nav-label">Fitur Sistem</li>

            <!-- Fitur 1: DATABASE -->
            <li class="<?= in_array($currentPage, ['reports.php', 'report_detail.php', 'export.php']) ? 'mm-active' : '' ?>">
                <a class="has-arrow" href="javascript:void(0);" aria-expanded="<?= in_array($currentPage, ['reports.php', 'report_detail.php', 'export.php']) ? 'true' : 'false' ?>" title="Database Hasil Monitoring">
                    <div class="pk-icon-box" style="background: rgba(56, 189, 248, 0.12); border-color: rgba(56, 189, 248, 0.25);">
                        <span class="material-symbols-outlined" style="color: #38bdf8; font-size: 16px;">database</span>
                    </div>
                    <div class="pk-text-wrap">
                        <div class="pk-title">Database</div>
                        <div class="pk-sub">Data & Rekapitulasi</div>
                    </div>
                </a>
                <ul aria-expanded="<?= in_array($currentPage, ['reports.php', 'report_detail.php', 'export.php']) ? 'true' : 'false' ?>">
                    <li class="<?= in_array($currentPage, ['reports.php', 'report_detail.php']) ? 'mm-active' : '' ?>">
                        <a href="reports.php" class="<?= in_array($currentPage, ['reports.php', 'report_detail.php']) ? 'mm-active' : '' ?>">
                            <i class="material-symbols-outlined sub-menu-icon">folder_open</i>
                            <span>Semua Data Monitoring</span>
                        </a>
                    </li>
                    <li class="<?= $currentPage === 'export.php' ? 'mm-active' : '' ?>">
                        <a href="export.php" class="<?= $currentPage === 'export.php' ? 'mm-active' : '' ?>">
                            <i class="material-symbols-outlined sub-menu-icon">print</i>
                            <span>Cetak & Ekspor Data</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Fitur 2: PELAPORAN -->
            <li class="<?= $currentPage === 'verification.php' ? 'mm-active' : '' ?>">
                <a href="verification.php" class="<?= $currentPage === 'verification.php' ? 'mm-active' : '' ?>" title="Monitoring Pelaporan & Verifikasi">
                    <div class="pk-icon-box" style="background: rgba(45, 212, 191, 0.12); border-color: rgba(45, 212, 191, 0.25);">
                        <span class="material-symbols-outlined" style="color: #2dd4bf; font-size: 16px;">fact_check</span>
                    </div>
                    <div class="pk-text-wrap">
                        <div class="pk-title">Pelaporan</div>
                        <div class="pk-sub">Verifikasi & Persetujuan</div>
                    </div>
                </a>
            </li>

            <!-- Fitur 3: JADWAL MONITORING -->
            <li class="<?= in_array($currentPage, ['schedules.php', 'schedule_add.php']) ? 'mm-active' : '' ?>">
                <a href="schedules.php" class="<?= in_array($currentPage, ['schedules.php', 'schedule_add.php']) ? 'mm-active' : '' ?>" title="Jadwal Penugasan & SPT Monitoring">
                    <div class="pk-icon-box" style="background: rgba(96, 165, 250, 0.12); border-color: rgba(96, 165, 250, 0.25);">
                        <span class="material-symbols-outlined" style="color: #60a5fa; font-size: 16px;">calendar_month</span>
                    </div>
                    <div class="pk-text-wrap">
                        <div class="pk-title">Jadwal Monitoring</div>
                        <div class="pk-sub">Agenda & SPT Tim</div>
                    </div>
                </a>
            </li>

            <!-- Fitur 4: DISKUSI TIM -->
            <li class="<?= in_array($currentPage, ['chat.php']) ? 'mm-active' : '' ?>">
                <a class="has-arrow" href="javascript:void(0);" aria-expanded="<?= in_array($currentPage, ['chat.php']) ? 'true' : 'false' ?>" title="Forum Komunikasi & Chat Tim">
                    <div class="pk-icon-box" style="background: rgba(244, 114, 182, 0.12); border-color: rgba(244, 114, 182, 0.25);">
                        <span class="material-symbols-outlined" style="color: #f472b6; font-size: 16px;">forum</span>
                    </div>
                    <div class="pk-text-wrap">
                        <div class="pk-title">Diskusi Tim</div>
                        <div class="pk-sub">Semua Tim & Per Tim Kerja</div>
                    </div>
                </a>
                <ul aria-expanded="<?= in_array($currentPage, ['chat.php']) ? 'true' : 'false' ?>">
                    <?php
                    $activeChatRoom = $_GET['room'] ?? '';
                    $hasUserDirect = isset($_GET['user_id']);
                    ?>
                    <li class="<?= ($currentPage === 'chat.php' && (in_array($activeChatRoom, ['all', 'team']) || (empty($activeChatRoom) && !$hasUserDirect))) ? 'mm-active' : '' ?>">
                        <a href="chat.php?room=all">
                            <i class="material-symbols-outlined sub-menu-icon" style="color: #38bdf8;">groups</i>
                            <span>Semua Tim Kerja</span>
                        </a>
                    </li>
                    <li class="<?= ($currentPage === 'chat.php' && $activeChatRoom === 'sarpras') ? 'mm-active' : '' ?>">
                        <a href="chat.php?room=sarpras">
                            <i class="material-symbols-outlined sub-menu-icon" style="color: #f59e0b;">construction</i>
                            <span>Tim Kerja Sarpras</span>
                        </a>
                    </li>
                    <li class="<?= ($currentPage === 'chat.php' && $activeChatRoom === 'montib') ? 'mm-active' : '' ?>">
                        <a href="chat.php?room=montib">
                            <i class="material-symbols-outlined sub-menu-icon" style="color: #10b981;">radar</i>
                            <span>Tim Kerja Montib</span>
                        </a>
                    </li>
                    <li class="<?= ($currentPage === 'chat.php' && $activeChatRoom === 'kualid') ? 'mm-active' : '' ?>">
                        <a href="chat.php?room=kualid">
                            <i class="material-symbols-outlined sub-menu-icon" style="color: #a855f7;">verified</i>
                            <span>Tim Kerja Kualid</span>
                        </a>
                    </li>
                    <li class="<?= ($currentPage === 'chat.php' && ($activeChatRoom === 'direct' || $hasUserDirect)) ? 'mm-active' : '' ?>">
                        <a href="chat.php?room=direct">
                            <i class="material-symbols-outlined sub-menu-icon" style="color: #38bdf8;">chat</i>
                            <span>Pesan Langsung</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Fitur 5: PERSONIL BALMON SAMARINDA -->
            <li class="<?= in_array($currentPage, ['users.php', 'active_officers.php']) ? 'mm-active' : '' ?>">
                <a class="has-arrow" href="javascript:void(0);" aria-expanded="<?= in_array($currentPage, ['users.php', 'active_officers.php']) ? 'true' : 'false' ?>" title="Daftar Personel Balmon Samarinda">
                    <div class="pk-icon-box" style="background: rgba(52, 211, 153, 0.12); border-color: rgba(52, 211, 153, 0.25);">
                        <span class="material-symbols-outlined" style="color: #34d399; font-size: 16px;">badge</span>
                    </div>
                    <div class="pk-text-wrap">
                        <div class="pk-title">Personil Balmon Samarinda</div>
                        <div class="pk-sub">Daftar Tim & Petugas</div>
                    </div>
                    <span class="online-dot ms-auto me-1" title="Indikator Online"></span>
                </a>
                <ul aria-expanded="<?= in_array($currentPage, ['users.php', 'active_officers.php']) ? 'true' : 'false' ?>">
                    <li class="<?= $currentPage === 'users.php' ? 'mm-active' : '' ?>">
                        <a href="users.php" class="<?= $currentPage === 'users.php' ? 'mm-active' : '' ?>">
                            <i class="material-symbols-outlined sub-menu-icon">group</i>
                            <span>Daftar Personel Balmon</span>
                        </a>
                    </li>
                    <li>
                        <a href="javascript:void(0);" onclick="openOffcanvasOfficers()">
                            <i class="material-symbols-outlined sub-menu-icon">sensors</i>
                            <span>Panel Petugas Online</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Fitur 6: AKUN -->
            <li class="<?= $currentPage === 'profile.php' ? 'mm-active' : '' ?>">
                <a href="profile.php" class="<?= $currentPage === 'profile.php' ? 'mm-active' : '' ?>" title="Pengaturan Akun & Profil">
                    <div class="pk-icon-box" style="background: rgba(129, 140, 248, 0.12); border-color: rgba(129, 140, 248, 0.25);">
                        <span class="material-symbols-outlined" style="color: #818cf8; font-size: 16px;">manage_accounts</span>
                    </div>
                    <div class="pk-text-wrap">
                        <div class="pk-title">Akun</div>
                        <div class="pk-sub">Profil & Pengaturan Akun</div>
                    </div>
                </a>
            </li>
        </ul>

        <div class="copyright text-center py-3">
            <p class="fs-12 mb-0" style="color: #7ea4cc;"><strong>Balmon Kelas I Samarinda</strong></p>
            <p class="fs-11 mb-0" style="color: #5d85ab;">© 2026 Ditjend Infrastruktur Digital - Komdigi</p>
        </div>
    </div>
</div>

<script>
// Fungsi Toggle Collapsible Sidebar (Global, tidak bisa di-unbind oleh script template lain)
window.toggleSidebarSection = function(section, ev) {
    if (ev) {
        ev.preventDefault();
        ev.stopPropagation();
    }
    var isPk = (section === 'pk');
    var headerEl = document.getElementById(isPk ? 'headingPk' : 'headingMonitoring');
    var listEl = document.getElementById(isPk ? 'sidebarPkList' : 'sidebarMonitoringList');
    var storageKey = isPk ? 'monreport_sidebar_pk_collapsed' : 'monreport_sidebar_mon_collapsed';

    if (!headerEl || !listEl) return;

    var willCollapse = !headerEl.classList.contains('is-collapsed');
    headerEl.classList.toggle('is-collapsed', willCollapse);

    if (typeof jQuery !== 'undefined') {
        if (willCollapse) {
            jQuery(listEl).slideUp(180, function() {
                listEl.classList.add('is-collapsed');
                listEl.style.display = '';
            });
        } else {
            listEl.classList.remove('is-collapsed');
            listEl.style.display = 'none';
            jQuery(listEl).slideDown(180, function() {
                listEl.style.display = '';
            });
        }
    } else {
        listEl.classList.toggle('is-collapsed', willCollapse);
    }

    try {
        localStorage.setItem(storageKey, willCollapse ? 'true' : 'false');
    } catch(e) {}
};

// Terapkan status awal saat DOM selesai di-parse
(function() {
    function cleanSidebarYearSelect() {
        var $select = jQuery('#sidebarPkYearSelect');
        if ($select.length) {
            if (jQuery.fn.selectpicker) {
                try {
                    $select.selectpicker('destroy');
                } catch(e) {}
            }
            var $wrapper = $select.closest('.bootstrap-select');
            if ($wrapper.length) {
                $select.unwrap();
                $wrapper.remove();
            }
        }
    }

    function applyInitialSidebarStates() {
        var isPkActive = <?= ($currentPage === 'pk_view.php') ? 'true' : 'false' ?>;
        var isMonActive = <?= $isMonitoringActive ? 'true' : 'false' ?>;

        try {
            var pkSaved = localStorage.getItem('monreport_sidebar_pk_collapsed');
            if (pkSaved === 'true' && !isPkActive) {
                var hPk = document.getElementById('headingPk');
                var lPk = document.getElementById('sidebarPkList');
                if (hPk) hPk.classList.add('is-collapsed');
                if (lPk) {
                    lPk.classList.add('is-collapsed');
                    lPk.style.display = 'none';
                }
            }
        } catch(e) {}

        try {
            var monSaved = localStorage.getItem('monreport_sidebar_mon_collapsed');
            if (monSaved === 'true' && !isMonActive) {
                var hMon = document.getElementById('headingMonitoring');
                var lMon = document.getElementById('sidebarMonitoringList');
                if (hMon) hMon.classList.add('is-collapsed');
                if (lMon) {
                    lMon.classList.add('is-collapsed');
                    lMon.style.display = 'none';
                }
            }
        } catch(e) {}
    }

    applyInitialSidebarStates();

    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function() {
            cleanSidebarYearSelect();
            applyInitialSidebarStates();
        });
        jQuery(window).on('load', function() {
            cleanSidebarYearSelect();
            applyInitialSidebarStates();
        });
        setTimeout(cleanSidebarYearSelect, 250);
        setTimeout(cleanSidebarYearSelect, 800);
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            applyInitialSidebarStates();
        });
    }
})();
</script>
