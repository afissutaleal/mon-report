<?php
// includes/footer.php
?>
        <!-- Footer start -->
        <div class="footer footer-outer">
            <div class="copyright">
                <p>Copyright © 2026 <strong>Balai Monitor Spektrum Frekuensi Radio dan Infrastruktur Digital Kelas I Samarinda</strong>. All rights reserved.</p>
            </div>
        </div>
        <!-- Footer end -->
    </div>
    <!-- Main wrapper end -->

    <!-- Core Scripts from Akademi Template -->
    <script>var asset_url = 'assets/';</script>
    <script src="assets/vendor/global/global.min.js"></script>
    <script src="assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js"></script>
    <script src="assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
    <script src="assets/vendor/apexchart/apexchart.js"></script>
    <script src="assets/vendor/bootstrap-datetimepicker/js/moment.js"></script>
    <script src="assets/vendor/datepicker/js/bootstrap-datepicker.min.js"></script>
    <script src="assets/js/custom.js?v=<?= filemtime(__DIR__ . '/../assets/js/custom.js') ?>"></script>
    <script src="assets/js/dlabnav-init.js?v=<?= filemtime(__DIR__ . '/../assets/js/dlabnav-init.js') ?>"></script>

    <!-- Global Application Scripts for Balmon Samarinda -->
    <script>
    // Theme toggle function with backend persistence and anti-bounce lock
    let isThemeSwitching = false;
    window.toggleTheme = function() {
        if (isThemeSwitching) return;
        isThemeSwitching = true;
        setTimeout(function() { isThemeSwitching = false; }, 300);

        const body = document.querySelector('body');
        const currentTheme = body.getAttribute('data-theme-version') || 'light';
        const newTheme = (currentTheme === 'dark') ? 'light' : 'dark';
        
        body.setAttribute('data-theme-version', newTheme);
        document.documentElement.setAttribute('data-theme-version', newTheme);
        if (typeof jQuery !== 'undefined') {
            jQuery('body').attr('data-theme-version', newTheme);
            jQuery('html').attr('data-theme-version', newTheme);
        }
        document.cookie = "version=" + newTheme + "; path=/; max-age=" + (365*24*60*60);
        
        // Sync dlabSettingsOptions so window resize won't revert theme
        if (typeof dlabSettingsOptions !== 'undefined') {
            dlabSettingsOptions.version = newTheme;
        }

        // Toggle .active class on all theme mode buttons
        const modeBtns = document.querySelectorAll('.dz-theme-mode');
        modeBtns.forEach(btn => {
            if (newTheme === 'dark') {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        if (typeof jQuery !== 'undefined') {
            jQuery('#theme_version').val(newTheme);
            if (jQuery.fn.selectpicker) {
                jQuery('.default-select').selectpicker('refresh');
            }
        }
        
        // Sync with backend API
        fetch('api/toggle_theme.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({theme: newTheme})
        }).catch(err => console.log('Theme sync error:', err));
    };

    document.addEventListener('DOMContentLoaded', function() {

        // Initialize DataTable if present
        if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable && jQuery('.data-table-balmon').length > 0) {
            jQuery('.data-table-balmon').DataTable({
                language: {
                    search: "Cari Data:",
                    lengthMenu: "Tampilkan _MENU_ baris",
                    info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                    paginate: {
                        next: '<i class="fa fa-angle-right"></i>',
                        previous: '<i class="fa fa-angle-left"></i>'
                    }
                }
            });
        }

        // Offcanvas Chat Lifecycle & Polling
        const offcanvasChatEl = document.getElementById('offcanvasChat');
        let quickChatPollTimer = null;

        if (offcanvasChatEl) {
            offcanvasChatEl.addEventListener('shown.bs.offcanvas', function() {
                loadQuickTeamChat();
                refreshOffcanvasOfficers();
                if (!quickChatPollTimer) {
                    quickChatPollTimer = setInterval(loadQuickTeamChat, 4000);
                }
            });

            offcanvasChatEl.addEventListener('hidden.bs.offcanvas', function() {
                if (quickChatPollTimer) {
                    clearInterval(quickChatPollTimer);
                    quickChatPollTimer = null;
                }
            });
        }

        // Tab switch auto-scroll
        const teamTabBtn = document.getElementById('tab-offcanvas-team-btn');
        if (teamTabBtn) {
            teamTabBtn.addEventListener('shown.bs.tab', function() {
                const chatBox = document.getElementById('quickTeamChatBody');
                if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
            });
        }

        // Load initial quick team chat
        loadQuickTeamChat();
    });

    // Global functions to open offcanvas with specific tab
    window.openOffcanvasOfficers = function() {
        const tabBtn = document.getElementById('tab-offcanvas-officers-btn');
        if (tabBtn) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            } else if (typeof jQuery !== 'undefined') {
                jQuery(tabBtn).tab('show');
            }
        }
        const offcanvasEl = document.getElementById('offcanvasChat');
        if (offcanvasEl) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
                bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).show();
            } else if (typeof jQuery !== 'undefined') {
                jQuery(offcanvasEl).offcanvas('show');
            }
        }
    };

    window.openOffcanvasTeamChat = function() {
        const tabBtn = document.getElementById('tab-offcanvas-team-btn');
        if (tabBtn) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            } else if (typeof jQuery !== 'undefined') {
                jQuery(tabBtn).tab('show');
            }
        }
        const offcanvasEl = document.getElementById('offcanvasChat');
        if (offcanvasEl) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
                bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).show();
            } else if (typeof jQuery !== 'undefined') {
                jQuery(offcanvasEl).offcanvas('show');
            }
        }
    };

    // Heartbeat for online status (runs every 60s)
    setInterval(function() {
        fetch('api/heartbeat.php', {method: 'POST'}).catch(() => {});
    }, 60000);

    // Filter Pegawai Aktif in Offcanvas Sidebar
    function filterOffcanvasOfficers(query) {
        query = (query || '').toLowerCase().trim();
        const items = document.querySelectorAll('#offcanvasOfficersUl .officer-item');
        let visibleCount = 0;
        
        items.forEach(item => {
            const name = (item.getAttribute('data-name') || '').toLowerCase();
            const nip = (item.getAttribute('data-nip') || '').toLowerCase();
            if (!query || name.includes(query) || nip.includes(query)) {
                item.style.setProperty('display', 'flex', 'important');
                visibleCount++;
            } else {
                item.style.setProperty('display', 'none', 'important');
            }
        });

        let noResultEl = document.getElementById('offcanvasOfficerNoResult');
        if (!noResultEl) {
            noResultEl = document.createElement('li');
            noResultEl.id = 'offcanvasOfficerNoResult';
            noResultEl.className = 'text-center text-muted fs-12 py-3';
            noResultEl.innerHTML = '<i class="fa fa-circle-exclamation me-1"></i> Tidak ada pegawai yang cocok.';
            const ul = document.getElementById('offcanvasOfficersUl');
            if (ul) ul.appendChild(noResultEl);
        }
        noResultEl.style.display = (visibleCount === 0) ? 'block' : 'none';
    }

    // Refresh online status in offcanvas
    function refreshOffcanvasOfficers() {
        fetch('api/active_users.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('offcanvasOnlineCount');
                    if (badge) badge.textContent = data.online_count;
                }
            })
            .catch(() => {});
    }

    // Quick team chat loader
    function loadQuickTeamChat() {
        const chatBox = document.getElementById('quickTeamChatBody');
        if (!chatBox) return;

        fetch('api/chat.php?room=team&limit=15')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.messages) {
                    const wasScrolledToBottom = (chatBox.scrollHeight - chatBox.clientHeight) <= (chatBox.scrollTop + 50);
                    let html = '';
                    data.messages.forEach(msg => {
                        const isMe = msg.is_me;
                        html += `
                            <div class="d-flex justify-content-${isMe ? 'end' : 'start'} mb-3">
                                ${!isMe ? `<div class="img_cont_msg me-2"><img src="${msg.avatar || 'assets/images/avatar/1.jpg'}" class="rounded-circle user_img_msg" width="30" height="30" alt=""></div>` : ''}
                                <div class="${isMe ? 'msg_cotainer_send bg-primary text-white' : 'msg_cotainer bg-light'}" style="padding: 8px 12px; border-radius: 12px; max-width: 78%; word-break: break-word;">
                                    <div class="fw-bold fs-11 ${isMe ? 'text-white text-opacity-75' : 'text-primary'}">${escapeHtml(msg.sender_name)}</div>
                                    <div class="fs-12">${escapeHtml(msg.message)}</div>
                                    <span class="fs-10 text-muted d-block text-end mt-1">${escapeHtml(msg.time_formatted || '')}</span>
                                </div>
                            </div>
                        `;
                    });
                    chatBox.innerHTML = html || '<p class="text-center text-muted fs-12 my-3"><i class="fa fa-info-circle me-1"></i> Belum ada percakapan tim.</p>';
                    if (wasScrolledToBottom || !chatBox.dataset.loaded) {
                        chatBox.scrollTop = chatBox.scrollHeight;
                        chatBox.dataset.loaded = 'true';
                    }
                }
            })
            .catch(() => {});
    }

    function sendQuickTeamMessage(e) {
        e.preventDefault();
        const input = document.getElementById('quickTeamInput');
        const text = input.value.trim();
        if (!text) return;

        const btn = e.target.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;

        fetch('api/chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                is_team: 1,
                message: text
            })
        })
        .then(res => res.json())
        .then(res => {
            if (btn) btn.disabled = false;
            if (res.success) {
                input.value = '';
                loadQuickTeamChat();
                const chatBox = document.getElementById('quickTeamChatBody');
                if (chatBox) {
                    setTimeout(() => { chatBox.scrollTop = chatBox.scrollHeight; }, 100);
                }
            } else {
                alert(res.message || 'Gagal mengirim pesan.');
            }
        })
        .catch(() => {
            if (btn) btn.disabled = false;
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, function(m) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
        });
    }
    </script>
</body>
</html>
