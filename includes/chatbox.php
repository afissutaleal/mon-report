<?php
// includes/chatbox.php
$officers = getActiveOfficersList();
$currentUser = currentUser();
$unreadCount = $currentUser ? getUnreadMessagesCount($currentUser['id']) : 0;
$onlineOfficersCount = count(array_filter($officers, fn($o) => $o['is_online']));
?>
<style>
.officer-item {
    transition: background-color 0.15s ease-in-out, transform 0.15s ease-in-out;
}
.officer-item:hover {
    background-color: rgba(77, 68, 181, 0.08) !important;
}
[data-theme-version="dark"] .officer-item:hover {
    background-color: rgba(255, 255, 255, 0.08) !important;
}
</style>
<!-- Offcanvas Sidebar: Pesan & Pegawai Aktif -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasChat" aria-labelledby="offcanvasChatLabel">
    <div class="offcanvas-header border-bottom bg-light py-3 px-3">
        <div class="d-flex align-items-center">
            <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-2 me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="fa-solid fa-comments fs-18"></i>
            </div>
            <div>
                <h6 class="offcanvas-title fw-bold mb-0 text-truncate" id="offcanvasChatLabel">Interaksi & Komunikasi</h6>
                <small class="text-muted fs-11">Balmon Kelas I Samarinda</small>
            </div>
        </div>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <!-- Navigation Tabs: Diskusi Tim vs Pegawai Aktif -->
    <div class="p-2 border-bottom bg-white">
        <ul class="nav nav-pills nav-fill" id="offcanvasChatTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-2 fs-12 fw-semibold" id="tab-offcanvas-team-btn" data-bs-toggle="pill" data-bs-target="#tab-offcanvas-team" type="button" role="tab" aria-controls="tab-offcanvas-team" aria-selected="true">
                    <i class="fa-solid fa-users me-1"></i> Diskusi Tim
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 fs-12 fw-semibold position-relative" id="tab-offcanvas-officers-btn" data-bs-toggle="pill" data-bs-target="#tab-offcanvas-officers" type="button" role="tab" aria-controls="tab-offcanvas-officers" aria-selected="false">
                    <i class="material-symbols-outlined fs-14 align-middle me-1">sensors</i> Pegawai Aktif
                    <span class="badge bg-success rounded-pill ms-1 fs-10" id="offcanvasOnlineCount"><?= $onlineOfficersCount ?></span>
                </button>
            </li>
        </ul>
    </div>

    <div class="offcanvas-body p-0 d-flex flex-column" style="height: calc(100vh - 120px);">
        <div class="tab-content flex-grow-1 d-flex flex-column h-100" style="overflow: hidden;">
            <!-- Tab 1: Diskusi Tim Balmon -->
            <div class="tab-pane fade show active h-100 d-flex flex-column" id="tab-offcanvas-team" role="tabpanel" aria-labelledby="tab-offcanvas-team-btn">
                <div class="p-2 px-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold fs-12 text-primary"><span class="online-dot me-1"></span> Forum Koordinasi Tim</span>
                        <small class="text-muted d-block fs-11">Semua petugas menerima pesan ini</small>
                    </div>
                    <a href="chat.php?room=all" class="btn btn-xs btn-outline-primary rounded-pill px-2" title="Buka Halaman Chat Lengkap">
                        <i class="fa-solid fa-expand me-1"></i> Layar Penuh
                    </a>
                </div>

                <!-- Chat Messages Scroll Area -->
                <div class="flex-grow-1 p-3 dlab-scroll" id="quickTeamChatBody" style="overflow-y: auto; height: 380px;">
                    <div class="text-center py-4 text-muted">
                        <i class="fa fa-spinner fa-spin me-1"></i> Memuat percakapan tim...
                    </div>
                </div>

                <!-- Chat Input Form -->
                <div class="p-3 border-top bg-light mt-auto">
                    <form id="quickTeamChatForm" onsubmit="sendQuickTeamMessage(event)">
                        <div class="input-group">
                            <input type="text" id="quickTeamInput" class="form-control form-control-sm" placeholder="Ketik pesan untuk tim..." required autocomplete="off">
                            <button type="submit" class="btn btn-primary btn-sm px-3 fw-bold">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tab 2: Pegawai Aktif (Real-time Online & Direct Chat) -->
            <div class="tab-pane fade h-100 d-flex flex-column" id="tab-offcanvas-officers" role="tabpanel" aria-labelledby="tab-offcanvas-officers-btn">
                <div class="p-2 px-3 bg-light border-bottom">
                    <div class="input-group input-group-sm mb-0">
                        <span class="input-group-text bg-white border-end-0"><i class="fa fa-search text-muted"></i></span>
                        <input type="text" id="offcanvasOfficerSearch" class="form-control border-start-0" placeholder="Cari nama pegawai atau NIP..." oninput="filterOffcanvasOfficers(this.value)">
                    </div>
                </div>

                <!-- Officer List -->
                <div class="flex-grow-1 p-2 dlab-scroll" id="offcanvasOfficersList" style="overflow-y: auto;">
                    <ul class="list-unstyled mb-0" id="offcanvasOfficersUl">
                        <?php foreach ($officers as $officer): ?>
                            <?php if ($currentUser && $officer['id'] == $currentUser['id']) continue; ?>
                            <li class="p-2 mb-1 rounded-3 hover-bg officer-item d-flex align-items-center justify-content-between" data-name="<?= strtolower(htmlspecialchars($officer['name'])) ?>" data-nip="<?= htmlspecialchars($officer['nip']) ?>">
                                <div class="d-flex align-items-center overflow-hidden me-2">
                                    <div class="position-relative me-2 flex-shrink-0">
                                        <img src="<?= htmlspecialchars($officer['avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle border" width="38" height="38" alt="">
                                        <span class="online_icon <?= $officer['is_online'] ? '' : 'offline' ?>" style="width: 10px; height: 10px; border-width: 2px;"></span>
                                    </div>
                                    <div class="overflow-hidden">
                                        <strong class="fs-12 d-block text-truncate" style="max-width: 170px;" title="<?= htmlspecialchars($officer['name']) ?>"><?= htmlspecialchars($officer['name']) ?></strong>
                                        <span class="fs-11 text-muted d-block text-truncate" style="max-width: 170px;" title="<?= htmlspecialchars($officer['jabatan']) ?>"><?= htmlspecialchars($officer['jabatan']) ?></span>
                                        <span class="badge badge-xs <?= $officer['is_online'] ? 'badge-info' : 'badge-light' ?> light text-nowrap">
                                            <?= $officer['is_online'] ? 'Aktif (' . strtoupper($officer['device_type'] ?? 'WEB') . ')' : 'Offline' ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-shrink-0">
                                    <a href="chat.php?user_id=<?= $officer['id'] ?>" class="btn btn-xs btn-outline-primary sharp rounded-circle" title="Kirim Pesan Langsung">
                                        <i class="fa fa-comment-dots"></i>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="p-2 text-center border-top bg-light mt-auto">
                    <a href="active_officers.php" class="fs-11 text-primary fw-semibold text-decoration-none">
                        <i class="fa fa-users me-1"></i> Buka Halaman Seluruh Personel Aktif
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Button: Shortcut Buka Pesan / Pegawai Aktif -->
<div class="floating-chat-btn" style="position: fixed; bottom: 25px; right: 25px; z-index: 1045;">
    <button type="button" class="btn btn-primary rounded-circle shadow-lg d-flex align-items-center justify-content-center position-relative" style="width: 52px; height: 52px;" data-bs-toggle="offcanvas" data-bs-target="#offcanvasChat" aria-controls="offcanvasChat" title="Buka Pesan & Pegawai Aktif">
        <i class="fa-solid fa-comment-dots fs-20 text-white"></i>
        <?php if ($unreadCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 10px;">
                <?= $unreadCount ?>
            </span>
        <?php endif; ?>
    </button>
</div>
