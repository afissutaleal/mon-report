<?php
// profile.php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo = getDbConnection();
$currentUser = currentUser();
$pageTitle = "Profil & Pengaturan Petugas";

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $theme = trim($_POST['theme_mode'] ?? 'light');
    $oldPass = $_POST['old_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';

    try {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format email dinas tidak valid.");
        }

        // Check if email already used by another user
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $currentUser['id']]);
        if ($check->fetch()) {
            throw new Exception("Email '$email' sudah digunakan oleh personel lain.");
        }

        if (!empty($newPass)) {
            if (empty($oldPass)) {
                $err = "Kata sandi lama wajib diisi jika ingin mengubah kata sandi.";
            } elseif (!password_verify($oldPass, $currentUser['password'])) {
                $err = "Kata sandi lama salah.";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ?, theme_mode = ?, password = ? WHERE id = ?");
                $stmt->execute([$email, $phone, $theme, password_hash($newPass, PASSWORD_BCRYPT), $currentUser['id']]);
                setcookie('version', $theme, time() + (365*24*60*60), '/');
                $msg = "Profil dan kata sandi berhasil diperbarui!";
            }
        } else {
            $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ?, theme_mode = ? WHERE id = ?");
            $stmt->execute([$email, $phone, $theme, $currentUser['id']]);
            setcookie('version', $theme, time() + (365*24*60*60), '/');
            $msg = "Profil dan preferensi tema berhasil diperbarui!";
        }
        $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmtUser->execute([$currentUser['id']]);
        $currentUser = $stmtUser->fetch();
    } catch (Exception $e) {
        $err = "Gagal memperbarui data: " . $e->getMessage();
    }
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
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active"><a href="javascript:void(0)">Profil Petugas</a></li>
            </ol>
        </div>

        <div class="row">
            <div class="col-xl-4 col-lg-5">
                <div class="card border-0 shadow-sm text-center p-4 mb-4" style="border-radius: 18px;">
                    <div class="card-body">
                        <img src="<?= htmlspecialchars($currentUser['avatar'] ?: 'assets/images/avatar/1.jpg') ?>" class="rounded-circle border mb-3 shadow-sm" width="110" height="110" alt="">
                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($currentUser['name']) ?></h4>
                        <span class="fs-13 text-muted d-block mb-2"><?= htmlspecialchars($currentUser['jabatan']) ?></span>
                        <span class="badge badge-primary light px-3 py-1 fs-12 mb-3">NIP: <?= htmlspecialchars($currentUser['nip']) ?></span>
                        
                        <div class="p-3 bg-light rounded-3 text-start fs-12 text-muted">
                            <div class="mb-2"><i class="fa fa-envelope text-primary me-2"></i> <?= htmlspecialchars($currentUser['email']) ?></div>
                            <div class="mb-2"><i class="fa fa-phone text-success me-2"></i> <?= htmlspecialchars($currentUser['phone'] ?: 'Belum diisi') ?></div>
                            <div class="mb-2"><i class="fa fa-shield-alt text-warning me-2"></i> Peran: <strong class="text-uppercase text-dark"><?= htmlspecialchars($currentUser['role']) ?></strong></div>
                            <div><i class="fa fa-circle-half-stroke text-info me-2"></i> Tema Pilihan: <strong class="text-capitalize text-dark"><?= htmlspecialchars($currentUser['theme_mode']) ?> Mode</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8 col-lg-7">
                <div class="card border-0 shadow-sm" style="border-radius: 18px;">
                    <div class="card-header border-0 pb-0">
                        <h5 class="fw-bold mb-0"><i class="fa fa-user-pen text-primary me-2"></i> Pengaturan Data & Tampilan</h5>
                        <small class="text-muted">Perbarui kontak dan pilihan mode Light atau Dark</small>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($msg): ?>
                            <div class="alert alert-success py-2 fs-13 mb-3"><?= htmlspecialchars($msg) ?></div>
                        <?php endif; ?>
                        <?php if ($err): ?>
                            <div class="alert alert-danger py-2 fs-13 mb-3"><?= htmlspecialchars($err) ?></div>
                        <?php endif; ?>

                        <form action="" method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-w600 fs-13">Nama Lengkap</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['name']) ?>" disabled>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-w600 fs-13">NIP Dinas</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['nip']) ?>" disabled>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-w600 fs-13">Email Dinas (Komdigi)</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" placeholder="nama@komdigi.go.id" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-w600 fs-13">Nomor Telepon / WhatsApp (Opsional)</label>
                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($currentUser['phone']) ?>" placeholder="08xxxxxxxxxx (opsional)">
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label font-w600 fs-13">Pilihan Mode Tampilan (Sesuai Preferensi Petugas)</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="theme_mode" id="themeLight" value="light" <?= ($currentUser['theme_mode'] === 'light') ? 'checked' : '' ?>>
                                            <label class="form-check-label fs-13 fw-semibold" for="themeLight">
                                                <i class="fa fa-sun text-warning me-1"></i> Mode Terang (Light Mode)
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="theme_mode" id="themeDark" value="dark" <?= ($currentUser['theme_mode'] === 'dark') ? 'checked' : '' ?>>
                                            <label class="form-check-label fs-13 fw-semibold" for="themeDark">
                                                <i class="fa fa-moon text-info me-1"></i> Mode Gelap (Dark Mode)
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="fw-bold text-primary mt-4 mb-3 border-top pt-3">
                                    <i class="fa fa-lock me-1"></i> Ganti Kata Sandi (Opsional)
                                </h6>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-w600 fs-13">Kata Sandi Saat Ini</label>
                                    <input type="password" name="old_password" class="form-control" placeholder="Isi hanya jika ingin ganti password">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-w600 fs-13">Kata Sandi Baru</label>
                                    <input type="password" name="new_password" class="form-control" placeholder="Kata sandi baru">
                                </div>

                                <div class="col-12 text-end mt-3">
                                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                                        <i class="fa fa-save me-1"></i> Simpan Perubahan Profil
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
