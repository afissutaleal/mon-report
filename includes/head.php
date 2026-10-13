<?php
// includes/head.php
if (!isset($pageTitle)) {
    $pageTitle = "Sistem Monitoring & Pelaporan - Balmon Samarinda";
}

$currentUser = currentUser();
$themeVersion = $currentUser['theme_mode'] ?? ($_COOKIE['version'] ?? 'light');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, minimal-ui, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle) ?> | Balmon Spektrum Frekuensi Radio Kelas I Samarinda</title>
    
    <!-- FAVICONS ICON -->
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon.png">
    
    <!-- CSS Plugins from Akademi Template -->
    <link href="assets/vendor/wow-master/css/libs/animate.css" rel="stylesheet" type="text/css"/>
    <link href="assets/vendor/datatables/css/jquery.dataTables.min.css" rel="stylesheet" type="text/css"/>
    <link href="assets/vendor/datepicker/css/bootstrap-datepicker.min.css" rel="stylesheet" type="text/css"/>
    <link href="assets/vendor/bootstrap-select/dist/css/bootstrap-select.min.css" rel="stylesheet" type="text/css"/>
    <link href="assets/vendor/swiper/css/swiper-bundle.min.css" rel="stylesheet" type="text/css" onerror="this.remove()"/>
    
    <!-- Font Awesome & Google Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Material+Icons&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    
    <!-- Main Akademi Template Style -->
    <link href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet" type="text/css"/>

    <!-- Custom Styling for Balmon Samarinda Branding & Polish -->
    <style>
        :root {
            --primary: #0070E0 !important;
            --primary-hover: #005FB8 !important;
            --primary-dark: #003C8C !important;
            --secondary: #FFB400 !important;
            --info: #00C8FF !important;
            --danger: #A01428 !important;
            --balmon-primary: #0070E0 !important;
            --balmon-blue: #0070E0 !important;
            --balmon-cyan: #00C8FF !important;
            --balmon-gold: #FFB400 !important;
            --balmon-crimson: #A01428 !important;
            --balmon-dark: #111A29 !important;
            --bs-primary: #0070E0 !important;
            --bs-primary-rgb: 0, 112, 224 !important;
            --bs-secondary: #FFB400 !important;
            --bs-secondary-rgb: 255, 180, 0 !important;
            --rgba-primary-1: rgba(0, 112, 224, 0.1) !important;
            --rgba-primary-2: rgba(0, 112, 224, 0.2) !important;
            --rgba-primary-3: rgba(0, 112, 224, 0.3) !important;
            --rgba-primary-4: rgba(0, 112, 224, 0.4) !important;
            --rgba-primary-5: rgba(0, 112, 224, 0.5) !important;
            --rgba-primary-6: rgba(0, 112, 224, 0.6) !important;
            --rgba-primary-7: rgba(0, 112, 224, 0.7) !important;
            --rgba-primary-8: rgba(0, 112, 224, 0.8) !important;
            --rgba-primary-9: rgba(0, 112, 224, 0.9) !important;
        }
        /* ========================================================
           RESPONSIVE LOGO SWITCHING (FULL VS MINI SIDEBAR)
           - Full Logo (dengan tulisan MonReport) saat sidebar lebar
           - Icon Logo saja saat sidebar dikecilkan / mini
           ======================================================== */
        .brand-logo-balmon {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            text-decoration: none;
            padding: 8px 16px;
            background: transparent !important;
            height: 100%;
            transition: all 0.25s ease;
        }

        /* Default (Expanded Sidebar): Tampilkan Logo Full, Sembunyikan Icon */
        .brand-logo-balmon .logo-full {
            height: 44px;
            max-height: 46px;
            width: auto;
            max-width: 185px;
            object-fit: contain;
            display: block !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            filter: drop-shadow(0 2px 8px rgba(0, 112, 224, 0.35));
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .brand-logo-balmon .logo-icon-only {
            height: 42px;
            max-height: 44px;
            width: auto;
            max-width: 48px;
            object-fit: contain;
            display: none !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            filter: drop-shadow(0 2px 8px rgba(0, 112, 224, 0.28));
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .brand-logo-balmon:hover .logo-full,
        .brand-logo-balmon:hover .logo-icon-only {
            transform: scale(1.04);
        }

        /* Saat Sidebar Dikecilkan (Menu Toggle / Mini / Compact): Sembunyikan Logo Full, Munculkan Icon Saja */
        #main-wrapper.menu-toggle .brand-logo-balmon,
        [data-sidebar-style="mini"] .brand-logo-balmon,
        [data-sidebar-style="compact"] .brand-logo-balmon {
            justify-content: center !important;
            padding: 6px 0 !important;
            width: 100% !important;
        }
        #main-wrapper.menu-toggle .brand-logo-balmon .logo-full,
        [data-sidebar-style="mini"] .brand-logo-balmon .logo-full,
        [data-sidebar-style="compact"] .brand-logo-balmon .logo-full {
            display: none !important;
        }
        #main-wrapper.menu-toggle .brand-logo-balmon .logo-icon-only,
        [data-sidebar-style="mini"] .brand-logo-balmon .logo-icon-only,
        [data-sidebar-style="compact"] .brand-logo-balmon .logo-icon-only {
            display: block !important;
        }

        /* Responsif Layar Kecil / Tablet (Lebar Layar <= 1024px) */
        @media only screen and (max-width: 63.9375rem) {
            .brand-logo-balmon {
                justify-content: center !important;
                padding: 6px 0 !important;
            }
            .brand-logo-balmon .logo-full {
                display: none !important;
            }
            .brand-logo-balmon .logo-icon-only {
                display: block !important;
            }
        }
        .status-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .online-dot {
            width: 9px;
            height: 9px;
            background-color: #00C8FF;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 2px rgba(0, 200, 255, 0.25);
            animation: pulse-cyan 2s infinite;
        }
        .offline-dot {
            width: 9px;
            height: 9px;
            background-color: #adb5bd;
            border-radius: 50%;
            display: inline-block;
        }
        @keyframes pulse-cyan {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 200, 255, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(0, 200, 255, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 200, 255, 0); }
        }
        .kpi-card {
            transition: transform 0.15s ease, box-shadow 0.15s ease !important;
            border-radius: 14px !important;
            border: 1px solid rgba(10, 22, 40, 0.07) !important;
            box-shadow: 0 1px 4px rgba(10, 22, 40, 0.03) !important;
        }
        .kpi-card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 10px rgba(10, 22, 40, 0.06) !important;
        }
        .category-nav-badge {
            margin-left: auto;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
            white-space: nowrap;
        }

        /* ========================================================
           SIDEBAR ICONS POLISH & OVERRIDE PREVENTION
           ======================================================== */
        .dlabnav .metismenu > li > a i.material-symbols-outlined,
        .dlabnav .metismenu > li a > i.material-symbols-outlined,
        .dlabnav-scroll .metismenu i.material-symbols-outlined {
            font-family: 'Material Symbols Outlined', 'Material Icons' !important;
            font-weight: normal !important;
            font-style: normal !important;
            font-size: 22px !important;
            line-height: 1 !important;
            letter-spacing: normal !important;
            text-transform: none !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            vertical-align: middle !important;
            width: 28px !important;
            min-width: 28px !important;
            height: 28px !important;
            margin-right: 12px !important;
            -webkit-font-feature-settings: 'liga' !important;
            -webkit-font-smoothing: antialiased !important;
        }

        .dlabnav .metismenu > li > a i.fa,
        .dlabnav .metismenu > li > a i.fas,
        .dlabnav .metismenu > li > a i.fa-solid,
        .dlabnav .metismenu > li > a i.fa-regular {
            font-family: 'Font Awesome 6 Free' !important;
            font-weight: 900 !important;
            font-style: normal !important;
            font-size: 18px !important;
            line-height: 1 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            vertical-align: middle !important;
            width: 28px !important;
            min-width: 28px !important;
            height: 28px !important;
            margin-right: 12px !important;
        }

        /* Dark mode compatibility for dark colored icons */
        body[data-theme-version="dark"] .dlabnav .metismenu > li > a i.text-dark {
            color: #cbd5e1 !important;
        }

        /* --- ATURAN TAMPILAN SIMPEL & SATU BARIS (NO WRAPPING / TIDAK TER-ENTER) --- */
        .table-nowrap th, 
        .table-nowrap td,
        .data-table-balmon th,
        .data-table-balmon td {
            white-space: nowrap !important;
            vertical-align: middle;
        }
        .table-responsive {
            scrollbar-width: thin;
            scrollbar-color: #0070E0 #f1f1f1;
        }
        .table-responsive::-webkit-scrollbar {
            height: 6px;
        }
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background: #0070E0;
            border-radius: 10px;
        }
        body[data-theme-version="dark"] .table-responsive::-webkit-scrollbar-track {
            background: #111A29;
        }
        body[data-theme-version="dark"] .table-responsive::-webkit-scrollbar-thumb {
            background: #0050B4;
        }

        /* ========================================================
           OPTIMASI UKURAN FONT SIDEBAR & PERSENTASE TIDAK TERPOTONG
           ======================================================== */
        /* Label Kategori / Seksi Menu */
        .dlabnav .metismenu .nav-label {
            font-size: 0.6875rem !important; /* 11px */
            letter-spacing: 0.5px;
            padding: 0.5rem 1rem 0.25rem 1.15rem !important;
            text-transform: uppercase;
            font-weight: 700;
        }

        /* Navigasi & Sidebar Menu Level 1 */
        .dlabnav .metismenu > li > a {
            font-size: 0.8125rem !important; /* 13px */
            padding: 0.6rem 1rem 0.6rem 1.15rem !important;
            font-weight: 500;
        }
        .dlabnav .metismenu a {
            white-space: nowrap !important;
            display: flex !important;
            align-items: center !important;
        }
        .dlabnav .metismenu .nav-text {
            font-size: 0.8125rem !important;
            white-space: nowrap !important;
            overflow: visible !important;
            text-overflow: clip !important;
        }

        /* Submenu Level 2 (Capaian Kinerja, Kegiatan Monitoring, dll) */
        .dlabnav .metismenu ul {
            padding: 0.2rem 0 !important;
        }
        .dlabnav .metismenu ul a {
            font-size: 0.735rem !important; /* ~11.75px: ukuran lebih kecil agar teks panjang & persentase pas */
            padding-top: 0.35rem !important;
            padding-bottom: 0.35rem !important;
            padding-left: 2.2rem !important; /* Kurangi indentasi dari 3.8rem ke 2.2rem agar ruang konten jauh lebih lebar */
            padding-right: 0.75rem !important; /* Ruang padding kanan yang pas */
            letter-spacing: -0.15px;
            white-space: nowrap !important;
            line-height: 1.35 !important;
        }
        .dlabnav .metismenu ul a span {
            font-size: 0.735rem !important;
            line-height: 1.35 !important;
        }
        .dlabnav .metismenu ul a .sub-menu-icon {
            font-size: 15px !important;
            margin-right: 6px !important;
        }

        /* Badge Persentase Kinerja & Indikator di Sidebar */
        .dlabnav .metismenu ul a .badge,
        .dlabnav .metismenu .badge {
            font-size: 0.625rem !important; /* 10px */
            padding: 1.5px 5px !important;
            line-height: 1.2 !important;
            border-radius: 4px !important;
            font-weight: 700 !important;
            flex-shrink: 0 !important;
            margin-left: 6px !important;
            white-space: nowrap !important;
            letter-spacing: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* Panah Accordion Dropdown Submenu */
        .dlabnav .metismenu .has-arrow:after {
            right: 1.15rem !important;
        }

        /* Header Bar & Profile Navbar */
        .header-left {
            flex-shrink: 1 !important;
            min-width: 0 !important;
        }
        .dashboard_bar {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            font-size: 18px !important;
            max-width: 460px;
        }
        .header-right {
            margin-left: auto !important;
            flex-shrink: 0 !important;
            display: flex !important;
            align-items: center !important;
        }
        .header-right .header-profile2 {
            position: relative !important;
            display: flex !important;
            align-items: center !important;
            flex-shrink: 0 !important;
        }
        .header-profile2 > a.nav-link {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 3px !important;
            border-radius: 50% !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease !important;
        }
        .header-profile2 > a.nav-link:hover {
            transform: scale(1.06);
        }
        .header-profile2 img {
            min-width: 42px !important;
            width: 42px !important;
            height: 42px !important;
            flex-shrink: 0 !important;
            object-fit: cover !important;
            cursor: pointer;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }

        /* Truncate Helpers dengan Tooltip */
        .truncate-sm {
            max-width: 130px;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            display: inline-block;
            vertical-align: middle;
        }
        .truncate-md {
            max-width: 200px;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            display: inline-block;
            vertical-align: middle;
        }
        .truncate-lg {
            max-width: 300px;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            display: inline-block;
            vertical-align: middle;
        }
        .truncate-title {
            max-width: 260px;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            display: block;
        }

        /* Badge & Tombol Rapi Tanpa Wrap */
        .badge {
            white-space: nowrap !important;
        }
        .btn {
            white-space: nowrap !important;
        }
        .card-header h5, 
        .card-header h6 {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        /* ========================================================
           FULL SCREEN / FULL LAYAR (NAVBAR & CONTENT AREA)
           ======================================================== */
        :root {
            --right-space: 0rem !important;
        }
        #main-wrapper {
            width: 100% !important;
            overflow-x: hidden !important;
        }
        #main-wrapper .header {
            width: 100% !important;
            left: 0 !important;
            right: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            box-sizing: border-box !important;
            transition: all 0.3s ease;
        }
        #main-wrapper .header .header-content,
        .wallet-open .header .header-content,
        .wallet-open.active .header .header-content {
            width: calc(100% - var(--width)) !important;
            max-width: calc(100% - var(--width)) !important;
            margin-left: var(--width) !important;
            padding-left: 5.5rem !important;
            padding-right: 1.875rem !important;
            box-sizing: border-box !important;
            transition: all 0.3s ease;
        }
        .menu-toggle .header .header-content {
            width: calc(100% - 5.5rem) !important;
            max-width: calc(100% - 5.5rem) !important;
            margin-left: 5.5rem !important;
            padding-left: 6.5rem !important;
        }
        @media only screen and (max-width: 47.9375rem) {
            #main-wrapper .header .header-content,
            .wallet-open .header .header-content,
            .wallet-open.active .header .header-content,
            .menu-toggle .header .header-content {
                width: 100% !important;
                max-width: 100% !important;
                margin-left: 0 !important;
                padding-left: 4.8rem !important;
                padding-right: 1rem !important;
            }
        }
        .header-left {
            display: flex !important;
            align-items: center !important;
        }
        .header-left .dashboard_bar {
            margin-left: 0 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            font-size: 1.45rem !important;
            font-weight: 700 !important;
        }
        .nav-control {
            z-index: 9999 !important;
            transition: all 0.2s ease;
        }
        body[data-theme-version="dark"] .hamburger svg rect {
            fill: #ffffff !important;
        }
        #main-wrapper .content-body,
        .wallet-open .content-body,
        .wallet-open.active .content-body {
            padding-right: 0 !important;
            margin-right: 0 !important;
            width: auto !important;
            transition: all 0.3s ease;
        }
        .content-body .container-fluid {
            width: 100% !important;
            max-width: 100% !important;
            padding-left: 1.875rem !important;
            padding-right: 1.875rem !important;
        }
        .wallet-bar,
        .wallet-open .wallet-bar,
        .chatbox {
            display: none !important;
        }

        /* ========================================================
           FULL SCREEN / FULL LAYAR: FOOTER BALMON SAMARINDA
           ======================================================== */
        #main-wrapper .footer,
        .footer,
        .footer.footer-outer,
        .wallet-open .footer,
        .wallet-open.active .footer {
            width: calc(100% - var(--width)) !important;
            max-width: calc(100% - var(--width)) !important;
            margin-left: var(--width) !important;
            margin-right: 0 !important;
            padding: 1.25rem 1.875rem !important;
            box-sizing: border-box !important;
            border-radius: 0 !important;
            border-top: 1px solid rgba(0, 0, 0, 0.06) !important;
            background-color: #ffffff !important;
            transition: all 0.3s ease;
            display: block !important;
        }
        .menu-toggle .footer,
        .menu-toggle .footer.footer-outer,
        [data-sidebar-style="mini"] .footer,
        [data-sidebar-style="mini"] .footer.footer-outer {
            width: calc(100% - 5.5rem) !important;
            max-width: calc(100% - 5.5rem) !important;
            margin-left: 5.5rem !important;
            margin-right: 0 !important;
        }
        @media only screen and (max-width: 47.9375rem) {
            #main-wrapper .footer,
            .footer,
            .footer.footer-outer,
            .wallet-open .footer,
            .wallet-open.active .footer,
            .menu-toggle .footer,
            .menu-toggle .footer.footer-outer {
                width: 100% !important;
                max-width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
        }
        .footer .copyright {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }
        .footer .copyright p {
            margin: 0 !important;
            text-align: center !important;
            font-size: 13px !important;
            color: #6c757d;
            white-space: normal !important;
        }
        body[data-theme-version="dark"] .footer,
        body[data-theme-version="dark"] .footer.footer-outer {
            background-color: #242B35 !important;
            border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
        }
        body[data-theme-version="dark"] .footer .copyright p {
            color: #a0a8b4 !important;
        }
        body[data-theme-version="dark"] .footer .copyright strong {
            color: #ffffff !important;
        }

        /* Profile Dropdown Popup Positioning & Styling */
        .header-profile2 .dropdown-menu {
            min-width: 290px !important;
            width: 290px !important;
            max-width: 90vw !important;
            right: 0 !important;
            left: auto !important;
            margin-top: 8px !important;
            border-radius: 14px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
            border: 1px solid rgba(0, 0, 0, 0.08) !important;
            padding: 0 !important;
            overflow: hidden !important;
            transform: none !important;
        }
        body[data-theme-version="dark"] .header-profile2 .dropdown-menu {
            background-color: #242B35 !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        body[data-theme-version="dark"] .header-profile2 .dropdown-menu .card {
            background-color: #242B35 !important;
        }
        body[data-theme-version="dark"] .header-profile2 .dropdown-menu .card-header {
            background-color: #1e242d !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        body[data-theme-version="dark"] .header-profile2 .dropdown-menu .card-footer {
            background-color: #1e242d !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        .header-profile2 .dropdown-menu .card-header h6 {
            white-space: normal !important;
            word-break: break-word !important;
            font-size: 13.5px !important;
            line-height: 1.3 !important;
            overflow: visible !important;
            text-overflow: clip !important;
            color: #2A353A !important;
        }
        body[data-theme-version="dark"] .header-profile2 .dropdown-menu .card-header h6 {
            color: #ffffff !important;
        }
        .header-profile2 .dropdown-menu .card-header small {
            white-space: nowrap !important;
            display: block !important;
            font-size: 11px !important;
        }

        /* ========================================================
           OFFCANVAS SIDEBAR: PESAN & PEGAWAI AKTIF
           ======================================================== */
        #offcanvasChat {
            width: 420px !important;
            max-width: 92vw !important;
            box-shadow: -5px 0 30px rgba(0, 0, 0, 0.18);
            border-left: 1px solid rgba(0, 0, 0, 0.08);
            z-index: 1060 !important;
        }
        body[data-theme-version="dark"] #offcanvasChat {
            background-color: #111A29 !important;
            color: #e2e8f0 !important;
            border-left-color: rgba(255, 255, 255, 0.1);
        }
        body[data-theme-version="dark"] #offcanvasChat .bg-light {
            background-color: #0B111E !important;
            color: #e2e8f0 !important;
        }
        body[data-theme-version="dark"] #offcanvasChat .bg-white {
            background-color: #162032 !important;
        }
        body[data-theme-version="dark"] #offcanvasChat .border-bottom,
        body[data-theme-version="dark"] #offcanvasChat .border-top {
            border-color: rgba(255, 255, 255, 0.08) !important;
        }
        body[data-theme-version="dark"] #offcanvasChat .hover-bg:hover {
            background-color: rgba(255, 255, 255, 0.05) !important;
        }
        body[data-theme-version="dark"] #offcanvasChat .text-dark {
            color: #f1f5f9 !important;
        }
        #offcanvasChat .nav-pills .nav-link {
            border-radius: 10px;
            color: #64748b;
            transition: all 0.2s ease;
        }
        #offcanvasChat .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #008CF0 0%, #0050B4 100%) !important;
            color: #fff !important;
            box-shadow: 0 4px 12px rgba(0, 112, 224, 0.3) !important;
        }
        .floating-chat-btn button {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: linear-gradient(135deg, #008CF0 0%, #0050B4 100%) !important;
            border: none;
            box-shadow: 0 6px 18px rgba(0, 112, 224, 0.35) !important;
        }
        .floating-chat-btn button:hover {
            transform: scale(1.08);
            box-shadow: 0 8px 24px rgba(0, 112, 224, 0.5) !important;
        }
        <?php if (basename($_SERVER['PHP_SELF'] ?? '') === 'chat.php'): ?>
        .floating-chat-btn { display: none !important; }
        <?php endif; ?>

        /* ========================================================
           GLOBAL LOGO-THEME HARMONIZATION SYSTEM
           Colors: Wave Blue (#0070E0), Cyan (#00C8FF), Amber (#FFB400), Crimson (#A01428)
           ======================================================== */
        .btn-primary {
            background: linear-gradient(135deg, #008CF0 0%, #0050B4 100%) !important;
            border-color: #0050B4 !important;
            box-shadow: 0 2px 6px rgba(0, 112, 224, 0.18) !important;
            color: #ffffff !important;
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background: linear-gradient(135deg, #0070E0 0%, #003C8C 100%) !important;
            border-color: #003C8C !important;
            box-shadow: 0 3px 8px rgba(0, 112, 224, 0.22) !important;
            color: #ffffff !important;
        }
        .btn-secondary {
            background: linear-gradient(135deg, #FFB400 0%, #E69D00 100%) !important;
            border-color: #E69D00 !important;
            color: #111A29 !important;
            font-weight: 600 !important;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, #E69D00 0%, #CC8800 100%) !important;
            color: #111A29 !important;
        }
        .text-primary {
            color: #0070E0 !important;
        }
        .bg-primary {
            background-color: #0070E0 !important;
        }
        .badge-primary, .badge.badge-primary {
            background-color: #0070E0 !important;
            color: #ffffff !important;
        }
        .badge-primary.light, .badge.badge-primary.light {
            background-color: rgba(0, 112, 224, 0.12) !important;
            color: #0070E0 !important;
        }
        .badge-secondary.light, .badge.badge-secondary.light {
            background-color: rgba(255, 180, 0, 0.15) !important;
            color: #B47800 !important;
        }
        .badge-info.light, .badge.badge-info.light {
            background-color: rgba(0, 200, 255, 0.15) !important;
            color: #0088B8 !important;
        }
        .badge-danger.light, .badge.badge-danger.light {
            background-color: rgba(160, 20, 40, 0.12) !important;
            color: #A01428 !important;
        }
        .dlabnav .metismenu > li.mm-active > a {
            background: rgba(0, 112, 224, 0.08) !important;
            color: #0070E0 !important;
            border-left: 4px solid #0070E0 !important;
            font-weight: 600 !important;
        }
        /* ========================================================
           COMMAND CENTER PALETTE: DEEP NAVY SIDEBAR + WAVE BLUE NAVBAR
           ======================================================== */
        /* SIDEBAR & NAV-HEADER (DEEP MIDNIGHT NAVY - MAKES LOGO POP VIVIDLY) */
        .nav-header {
            background-color: #031730 !important;
            border-right: 1px solid rgba(255, 255, 255, 0.06) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06) !important;
        }

        .dlabnav {
            background-color: #031730 !important;
            border-right: 1px solid rgba(255, 255, 255, 0.06) !important;
        }
        .dlabnav .dlabnav-scroll {
            background-color: #031730 !important;
        }

        .dlabnav .metismenu .nav-label {
            color: #728299 !important;
            font-size: 10px !important;
            font-weight: 700 !important;
            letter-spacing: 0.9px !important;
            text-transform: uppercase !important;
            padding: 14px 22px 6px 22px !important;
            margin-top: 6px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .dlabnav .metismenu li:first-child.nav-label,
        .dlabnav .metismenu .nav-label:first-of-type {
            border-top: none !important;
            margin-top: 0 !important;
            padding-top: 8px !important;
        }

        /* Variabel Lebar Sidebar Lebih Luas (21.5rem ~344px) - Teks Tidak Terpotong */
        :root {
            --width: 21.5rem !important;
        }
        [data-sidebar-style="mini"] {
            --width: 6.25rem !important;
        }
        .nav-header {
            width: var(--width) !important;
        }
        .dlabnav {
            width: var(--width) !important;
        }
        .content-body {
            margin-left: var(--width) !important;
        }
        .header .header-content {
            margin-left: var(--width) !important;
            width: calc(100% - var(--width)) !important;
        }

        /* Override template media query (max-width: 87.5rem) agar sidebar tetap 21.5rem di layar laptop/desktop */
        @media only screen and (min-width: 48rem) {
            .nav-header {
                width: var(--width) !important;
            }
            .dlabnav {
                width: var(--width) !important;
            }
            .content-body {
                margin-left: var(--width) !important;
            }
            .header .header-content {
                margin-left: var(--width) !important;
                width: calc(100% - var(--width)) !important;
            }
        }

        /* Penyesuaian saat sidebar mini / toggle */
        #main-wrapper.menu-toggle .dlabnav,
        [data-sidebar-style="mini"] .dlabnav,
        #main-wrapper.menu-toggle .nav-header,
        [data-sidebar-style="mini"] .nav-header {
            width: 6.25rem !important;
        }
        #main-wrapper.menu-toggle .content-body,
        [data-sidebar-style="mini"] .content-body {
            margin-left: 6.25rem !important;
        }
        #main-wrapper.menu-toggle .header .header-content,
        [data-sidebar-style="mini"] .header .header-content {
            margin-left: 6.25rem !important;
            width: calc(100% - 6.25rem) !important;
        }

        /* Posisi Panah Dropdown Digeser Rapi ke Kanan & Aman dari Teks */
        .dlabnav .metismenu .has-arrow:after,
        .dlabnav .metismenu > li .has-arrow:after,
        .dlabnav .metismenu > li > a.has-arrow:after,
        .dlabnav .metismenu .has-arrow[aria-expanded="true"]:after,
        .dlabnav .metismenu .mm-active > a.has-arrow:after,
        [data-sidebar-style="full"][data-layout="vertical"] .dlabnav .metismenu > li .has-arrow:after,
        [data-sidebar-style="full"][data-layout="vertical"] .dlabnav .metismenu > li > a.has-arrow:after,
        [data-sidebarbg] .dlabnav .metismenu .has-arrow:after,
        [data-sidebarbg] .dlabnav .metismenu > li > a.has-arrow:after {
            right: 0.85rem !important;
        }

        .dlabnav .metismenu > li > a.has-arrow {
            padding-right: 2.2rem !important;
        }

        .dlabnav .metismenu > li > a {
            color: #cbd5e1 !important;
            transition: all 0.2s ease;
        }

        .dlabnav .metismenu > li:hover > a {
            color: #ffffff !important;
            background-color: rgba(0, 112, 224, 0.16) !important;
        }

        .dlabnav .metismenu > li.mm-active > a {
            background: linear-gradient(90deg, rgba(0, 140, 240, 0.22) 0%, rgba(0, 112, 224, 0.08) 100%) !important;
            color: #38bdf8 !important;
            border-left: 4px solid #00C8FF !important;
            font-weight: 600 !important;
        }

        .dlabnav .metismenu > li.mm-active > a i {
            color: #00C8FF !important;
        }

        .dlabnav .metismenu ul {
            background-color: #060e1a !important;
        }

        /* Sub-menu Item Lapang Tanpa Terpotong / Titik-Titik */
        .dlabnav .metismenu ul a {
            color: #94a3b8 !important;
            padding-left: 2rem !important;
            padding-top: 0.45rem !important;
            padding-bottom: 0.45rem !important;
            padding-right: 0.8rem !important;
            font-size: 0.8125rem !important;
            white-space: nowrap !important;
            overflow: visible !important;
            text-overflow: clip !important;
            display: flex !important;
            align-items: center !important;
        }

        /* Ikon Sub-menu Mengikuti Ukuran Tulisan (~15px) */
        .dlabnav .metismenu ul a i,
        .dlabnav .metismenu ul a .sub-menu-icon {
            font-size: 0.95rem !important; /* Proporsional & serasi mengikuti ukuran teks */
            width: 1.15rem !important;
            height: 1.15rem !important;
            line-height: 1.15rem !important;
            margin-right: 0.55rem !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            vertical-align: middle !important;
            color: #94a3b8 !important;
            transition: all 0.2s ease !important;
        }

        .dlabnav .metismenu ul a:hover,
        .dlabnav .metismenu ul a:focus {
            color: #ffffff !important;
            background-color: rgba(0, 112, 224, 0.14) !important;
        }

        .dlabnav .metismenu ul a:hover i,
        .dlabnav .metismenu ul a:hover .sub-menu-icon,
        .dlabnav .metismenu ul a.mm-active i,
        .dlabnav .metismenu ul a.mm-active .sub-menu-icon,
        .dlabnav .metismenu ul li.mm-active > a i,
        .dlabnav .metismenu ul li.mm-active > a .sub-menu-icon {
            color: #00C8FF !important; /* Cyan terang khas logo saat aktif / hover */
        }

        .dlabnav .metismenu ul a.mm-active,
        .dlabnav .metismenu ul li.mm-active > a {
            color: #38bdf8 !important;
            font-weight: 600 !important;
        }

        /* NAVBAR ATAS (WAVE BLUE - IDENTITAS RESMI LOGO) */
        #main-wrapper .header {
            background: linear-gradient(90deg, #0050B4 0%, #0066D6 45%, #007CE8 100%) !important;
            box-shadow: 0 4px 20px rgba(0, 70, 160, 0.25) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
        }

        .header .dashboard_bar {
            color: #ffffff !important;
            font-weight: 700 !important;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
        }

        /* Tombol Icon Navigasi Kanan (Theme Toggle, Chat, Notifikasi) */
        .header-right .nav-link,
        .header-right .notification_dropdown .nav-link,
        [data-headerbg] .header-right .notification_dropdown .nav-link,
        [data-headerbg="color_3"] .header-right .notification_dropdown .nav-link {
            background: rgba(255, 255, 255, 0.15) !important;
            border: 1px solid rgba(255, 255, 255, 0.25) !important;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15) !important;
            border-radius: 10px !important;
            transition: all 0.2s ease-in-out !important;
        }

        .header-right .nav-link:hover,
        .header-right .notification_dropdown .nav-link:hover,
        [data-headerbg] .header-right .notification_dropdown .nav-link:hover,
        [data-headerbg="color_3"] .header-right .notification_dropdown .nav-link:hover {
            background: rgba(255, 255, 255, 0.28) !important;
            border-color: rgba(255, 255, 255, 0.45) !important;
            color: #ffffff !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.22) !important;
        }

        .header-right .nav-link svg,
        .header-right .notification_dropdown .nav-link svg,
        .header-right .notification_dropdown .nav-link i {
            color: #ffffff !important;
            stroke: #ffffff !important;
        }

        /* Mode Light/Dark Icon Switcher */
        .dz-theme-mode #icon-light-1 {
            display: none !important;
        }
        .dz-theme-mode #icon-dark-1 {
            display: inline-flex !important;
        }
        .dz-theme-mode #icon-dark-1 svg {
            stroke: #ffffff !important;
            color: #ffffff !important;
        }

        .dz-theme-mode.active #icon-light-1 {
            display: inline-flex !important;
        }
        .dz-theme-mode.active #icon-light-1 svg {
            stroke: #ffffff !important;
            color: #ffffff !important;
        }
        .dz-theme-mode.active #icon-dark-1 {
            display: none !important;
        }

        .header-right .bell-icon svg path {
            fill: #ffffff !important;
        }

        .header-right .bell-icon .badge-primary {
            background-color: #FFB400 !important; /* Amber Yellow dari logo */
            color: #002244 !important;
            font-weight: 700 !important;
            box-shadow: 0 0 6px rgba(255, 180, 0, 0.6) !important;
        }

        .header-right .bell-link .badge-danger {
            background-color: #A01428 !important; /* Crimson Red dari logo */
            color: #ffffff !important;
            box-shadow: 0 0 6px rgba(160, 20, 40, 0.6) !important;
        }

        /* Tombol 9 Titik Hamburger */
        .nav-control {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
        }

        .nav-control:hover {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            opacity: 0.8 !important;
        }

        .hamburger,
        .hamburger svg {
            filter: none !important;
            box-shadow: none !important;
        }

        .hamburger svg rect {
            fill: #ffffff !important;
            filter: none !important;
        }

        /* Foto Profil Akun & Indikator Online di Navbar */
        .header-profile2 > a.nav-link {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 2px !important;
        }
        .header-profile2 > a.nav-link:hover {
            background: transparent !important;
            transform: translateY(-1px) !important;
        }
        .header-profile2 > a.nav-link img {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }
        .header-profile2 .online_icon,
        .header-profile2 span.online_icon,
        .chatbox .online_icon {
            background: #00C8FF !important; /* Electric Cyan khas logo Monreport */
            border: 2px solid #ffffff !important;
            box-shadow: 0 0 8px rgba(0, 200, 255, 0.9) !important;
        }

        /* Fast Animated Dots Preloader (Bulat-Bulat Seperti Sebelumnya) */
        #preloader {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background-color: #ffffff !important;
            z-index: 999999 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            opacity: 1 !important;
            visibility: visible !important;
            pointer-events: all !important;
            transition: opacity 0.18s ease-out, visibility 0.18s ease-out !important;
        }
        body[data-theme-version="dark"] #preloader,
        [data-theme-version="dark"] #preloader {
            background-color: #0B111E !important;
        }
        #preloader.loaded {
            opacity: 0 !important;
            visibility: hidden !important;
            pointer-events: none !important;
        }
        #preloader .dot.mainDot {
            background: #0070E0 !important;
        }
        #preloader .dot {
            background: #00C8FF !important;
        }
        /* Animasi bulat-bulat jauh lebih cepat & lincah (1.1s, sebelumnya 3s) */
        #preloader .dot:first-child {
            animation: FirstDot 1.1s infinite !important;
        }
        #preloader .dot:nth-child(2) {
            animation: SecondDot 1.1s infinite !important;
        }
        #preloader .dot:nth-child(3) {
            animation: ThirdDot 1.1s infinite !important;
        }
        #preloader .dot:nth-child(4) {
            animation: FourthDot 1.1s infinite !important;
        }
        #preloader .dot:nth-child(5) {
            animation: FifthDot 1.1s infinite !important;
        }
        #main-wrapper {
            opacity: 1 !important;
            transition: none !important;
        }
            transition: none !important;
        }

        body[data-theme-version="dark"] {
            background-color: #0B111E !important;
            color: #E2E8F0 !important;
        }
        body[data-theme-version="dark"] .card {
            background-color: #162032 !important;
            border-color: rgba(255, 255, 255, 0.06) !important;
        }
        body[data-theme-version="dark"] .footer {
            background-color: #0B111E !important;
            border-top-color: rgba(255, 255, 255, 0.06) !important;
        }

        /* Welcome Banner Capaian Kinerja (Tema Kuning Emas Logo MonReport) */
        .welcome-banner-kpi,
        body[data-theme-version="dark"] .welcome-banner-kpi,
        [data-theme-version="dark"] .welcome-banner-kpi {
            background: linear-gradient(118deg, #FDB813 0%, #F59E0B 55%, #E68A00 100%) !important;
            border: 1px solid rgba(245, 158, 11, 0.35) !important;
        }

        .welcome-banner-kpi h3,
        body[data-theme-version="dark"] .welcome-banner-kpi h3,
        [data-theme-version="dark"] .welcome-banner-kpi h3 {
            color: #0A1628 !important;
        }

        .welcome-banner-kpi p,
        body[data-theme-version="dark"] .welcome-banner-kpi p,
        [data-theme-version="dark"] .welcome-banner-kpi p {
            color: #1e293b !important;
        }

        .welcome-banner-kpi strong,
        body[data-theme-version="dark"] .welcome-banner-kpi strong,
        [data-theme-version="dark"] .welcome-banner-kpi strong {
            color: #0A1628 !important;
        }

        /* Floating Box & Angka Persentase Capaian Kinerja (1.7%) Tetap Warna Gelap pada Mode Dark */
        .kpi-percentage-box,
        body[data-theme-version="dark"] .kpi-percentage-box,
        [data-theme-version="dark"] .kpi-percentage-box {
            background: rgba(255, 255, 255, 0.92) !important;
            border: 2px solid rgba(245, 158, 11, 0.45) !important;
        }

        .kpi-percentage-number,
        body[data-theme-version="dark"] .kpi-percentage-number,
        [data-theme-version="dark"] .kpi-percentage-number,
        body[data-theme-version="dark"] h1.kpi-percentage-number,
        [data-theme-version="dark"] h1.kpi-percentage-number {
            color: #0A1628 !important; /* Deep Midnight Navy */
        }

        .text-kpi-sub,
        body[data-theme-version="dark"] .text-kpi-sub,
        [data-theme-version="dark"] .text-kpi-sub {
            color: #0A1628 !important;
        }

        /* Hapus / Sembunyikan Tombol Floating Support & Buy Now */
        .DZ-bt-support-now,
        .DZ-bt-buy-now,
        .DZBuyNowBtn,
        .DZSupportBtn,
        .DZBuyNowRegularBtn,
        .DZBuyNowExtendedBtn,
        .DZ-theme-btn,
        #DZSubscriptionModal,
        [class*="DZ-bt-"],
        [class*="DZBuyNow"],
        [class*="DZSupport"],
        #DZScript {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
    </style>
</head>
<body data-theme-version="<?= htmlspecialchars($themeVersion) ?>">
    <!-- Preloader Start (Bulat-Bulat Seperti Sebelumnya - Versi Cepat) -->
    <div id="preloader">
        <div class="loader">
            <div class="dots">
                <div class="dot mainDot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var p = document.getElementById('preloader');
        if (!p) return;
        var hidePreloader = function() {
            if (!p || p.classList.contains('loaded')) return;
            p.classList.add('loaded');
            setTimeout(function(){
                if (p && p.parentNode) {
                    p.style.display = 'none';
                }
            }, 180);
        };
        // Cepat & mulus: tampil sangat singkat (~150ms) setelah DOM siap agar transisi terasa rapi tanpa jeda lama
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(hidePreloader, 150);
        } else {
            document.addEventListener('DOMContentLoaded', function(){
                setTimeout(hidePreloader, 150);
            });
        }
        // Failsafe maksimal 320ms agar perpindahan halaman tetap sangat cepat
        setTimeout(hidePreloader, 320);
    })();
    </script>
    <!-- Preloader End -->

    <div id="main-wrapper" class="show">
