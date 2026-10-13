<?php
// includes/header.php
$currentUser = currentUser();
$unreadCount = $currentUser ? getUnreadMessagesCount($currentUser['id']) : 0;
$currentThemeMode = $themeVersion ?? ($currentUser['theme_mode'] ?? ($_COOKIE['version'] ?? 'light'));
?>
<div class="header">
    <div class="header-content">
        <nav class="navbar navbar-expand">
            <div class="collapse navbar-collapse justify-content-between">
                <div class="header-left d-flex align-items-center flex-wrap gap-2">
                    <div class="dashboard_bar">
                        <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
                    </div>
                </div>

                <ul class="navbar-nav header-right">
                    <!-- Light / Dark Mode Toggle from Akademi Template -->
                    <li class="nav-item dropdown notification_dropdown">
                        <a class="nav-link bell dz-theme-mode <?= ($currentThemeMode === 'dark') ? 'active' : '' ?>" href="javascript:void(0);" id="themeToggleBtn" onclick="toggleTheme()" title="Ganti Mode Light / Dark">
                            <i id="icon-light-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-sun">
                                    <circle cx="12" cy="12" r="5"></circle>
                                    <line x1="12" y1="1" x2="12" y2="3"></line>
                                    <line x1="12" y1="21" x2="12" y2="23"></line>
                                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                    <line x1="1" y1="12" x2="3" y2="12"></line>
                                    <line x1="21" y1="12" x2="23" y2="12"></line>
                                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                                </svg>
                            </i>
                            <i id="icon-dark-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-moon">
                                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                                </svg>
                            </i>
                        </a>
                    </li>


                    <!-- Slideout Chat & Pegawai Aktif Offcanvas Toggle -->
                    <li class="nav-item dropdown notification_dropdown">
                        <a class="nav-link bell-link position-relative" href="javascript:void(0);" data-bs-toggle="offcanvas" data-bs-target="#offcanvasChat" aria-controls="offcanvasChat" title="Buka Pesan & Pegawai Aktif">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-message-square">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge badge-circle badge-danger position-absolute top-0 end-0" style="font-size: 10px;"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- Notifications -->
                    <li class="nav-item bell-icon dropdown notification_dropdown">
                        <a class="nav-link" href="javascript:void(0);" role="button" data-bs-toggle="dropdown">
                            <svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M25.3677 18.9391V9.86768C25.3677 4.70215 21.1655 0.5 16 0.5C10.8345 0.5 6.63232 4.70215 6.63232 9.86768V18.9397C4.96704 19.4224 3.73828 20.9544 3.73828 22.8374C3.73828 25.0386 5.5293 26.8296 7.73096 26.8296H11.377V26.877C11.377 29.4263 13.4507 31.5 16 31.5C18.5493 31.5 20.6231 29.4263 20.6231 26.8769V26.8296H24.2691C26.4707 26.8296 28.2617 25.0386 28.2617 22.7583C28.2617 20.9406 27.033 19.4198 25.3677 18.9391Z" fill="currentColor" />
                            </svg>
                            <span class="badge badge-circle badge-primary position-absolute top-0 end-0" style="font-size: 9px; padding: 4px;"></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end of-visible">
                            <div class="dropdown-header">
                                <h5 class="title mb-0">Notifikasi Balmon</h5>
                            </div>
                            <div class="widget-media dlab-scroll p-3" style="max-height: 300px; overflow-y: auto;">
                                <ul class="timeline">
                                    <li>
                                        <div class="timeline-panel">
                                            <div class="media me-2 media-primary">
                                                <i class="fa fa-check-circle"></i>
                                            </div>
                                            <div class="media-body">
                                                <h6 class="mb-1">Laporan Frekuensi Terverifikasi</h6>
                                                <small class="d-block text-muted">MON-SPF-2026-001 oleh Koordinator</small>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="timeline-panel">
                                            <div class="media me-2 media-info">
                                                <i class="fa fa-calendar-alt"></i>
                                            </div>
                                            <div class="media-body">
                                                <h6 class="mb-1">Jadwal Monitoring Mendatang</h6>
                                                <small class="d-block text-muted">SPT No. 370/SP.03.03/10/2026 (Kota Balikpapan)</small>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </li>

                    <!-- Officer Profile Dropdown (Hanya Logo Akun, Detail Muncul Saat Diklik) -->
                    <li class="nav-item">
                        <div class="dropdown header-profile2">
                            <a class="nav-link ms-0 p-1 rounded-circle" href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Profil Akun: <?= htmlspecialchars($currentUser['name'] ?? 'Petugas Balmon') ?>">
                                <div class="position-relative d-inline-block">
                                    <img src="<?= htmlspecialchars($currentUser['avatar'] ?? 'assets/images/avatar/1.jpg') ?>" class="rounded-circle flex-shrink-0" width="42" height="42" alt="<?= htmlspecialchars($currentUser['name'] ?? '') ?>">
                                    <span class="online_icon" style="width: 10px; height: 10px; border-width: 2px;"></span>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end pb-0">
                                <div class="card mb-0 shadow-sm border-0">
                                    <div class="card-header p-3 border-bottom">
                                        <div class="d-flex align-items-center">
                                            <img src="<?= htmlspecialchars($currentUser['avatar'] ?? 'assets/images/avatar/1.jpg') ?>" class="rounded-circle me-2 flex-shrink-0 border" width="48" height="48" alt="">
                                            <div class="overflow-hidden">
                                                <h6 class="mb-0 fw-bold fs-14 text-dark"><?= htmlspecialchars($currentUser['name'] ?? '') ?></h6>
                                                <small class="text-primary font-w600 d-block fs-12">NIP: <?= htmlspecialchars($currentUser['nip'] ?? '') ?></small>
                                                <small class="text-muted d-block fs-11 text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($currentUser['jabatan'] ?? '') ?>"><?= htmlspecialchars($currentUser['jabatan'] ?? '') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body p-2">
                                        <a href="profile.php" class="dropdown-item ai-icon py-2">
                                            <i class="fa-solid fa-user me-2 text-primary"></i> Profil Saya
                                        </a>
                                        <a href="chat.php" class="dropdown-item ai-icon py-2">
                                            <i class="fa-solid fa-comments me-2 text-info"></i> Pesan & Chat
                                        </a>
                                        <a href="javascript:void(0);" data-bs-toggle="offcanvas" data-bs-target="#offcanvasChat" class="dropdown-item ai-icon py-2">
                                            <i class="fa-solid fa-users me-2 text-info"></i> Panel Interaksi Tim
                                        </a>
                                        <a href="javascript:void(0);" onclick="toggleTheme()" class="dropdown-item ai-icon py-2">
                                            <i class="fa-solid fa-circle-half-stroke me-2 text-warning"></i> Ganti Tema Light / Dark
                                        </a>
                                    </div>
                                    <div class="card-footer text-center p-2 bg-light">
                                        <a href="logout.php" class="btn btn-danger btn-xs w-100">
                                            <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</div>
