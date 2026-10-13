<?php
// login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to index.php
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        $error = "Silakan isi NIP/Email dan kata sandi.";
    } else {
        $pdo = getDbConnection();
        $cleanId = str_replace(' ', '', $identifier);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR nip = ? OR replace(nip, ' ', '') = ?) AND status_active = 1");
        $stmt->execute([$identifier, $identifier, $cleanId]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nip'] = $user['nip'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // Update heartbeat & device
            updateOfficerHeartbeat($user['id']);

            // Set cookie theme from user preference
            if (!empty($user['theme_mode'])) {
                setcookie('version', $user['theme_mode'], time() + (365*24*60*60), '/');
            }

            header("Location: index.php");
            exit;
        } else {
            $error = "NIP/Email atau kata sandi tidak sesuai.";
        }
    }
}

$themeVersion = $_COOKIE['version'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="id" class="h-100">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Sistem Monitoring Balmon Samarinda</title>
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0070E0;
            --bs-primary: #0070E0;
        }
        .authincation {
            background: linear-gradient(135deg, #0b1526 0%, #152238 50%, #0c2b4e 100%);
            min-height: 100vh;
        }
        body[data-theme-version="light"] .authincation {
            background: linear-gradient(135deg, #edf4fc 0%, #e2ecf9 50%, #d0e3fc 100%);
        }
        .login-card {
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 112, 224, 0.18);
            overflow: hidden;
        }
        .btn-primary {
            background: linear-gradient(135deg, #008CF0 0%, #0050B4 100%) !important;
            border: none !important;
            box-shadow: 0 4px 14px rgba(0, 112, 224, 0.35) !important;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0078DC 0%, #003C8C 100%) !important;
            box-shadow: 0 6px 20px rgba(0, 112, 224, 0.45) !important;
        }
        .badge-primary.light {
            background-color: rgba(0, 112, 224, 0.12) !important;
            color: #0070E0 !important;
        }
        .demo-btn {
            font-size: 11px;
            padding: 6px 10px;
            margin: 3px;
            border-radius: 8px;
            text-align: left;
            transition: all 0.2s;
        }
    </style>
</head>
<body class="h-100" data-theme-version="<?= htmlspecialchars($themeVersion) ?>">
    <div class="authincation h-100">
        <div class="container h-100">
            <div class="row justify-content-center h-100 align-items-center">
                <div class="col-md-7 col-lg-5 col-xl-5">
                    <div class="card login-card border-0">
                        <div class="card-body p-4 p-sm-5">
                            <div class="text-center mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center mb-3">
                                    <img src="assets/images/logo-monreport.png" alt="Logo Monreport" style="width: 80px; height: 80px; object-fit: contain; filter: drop-shadow(0 4px 14px rgba(0, 112, 224, 0.28));">
                                </div>
                                <h3 class="fw-bold mb-1" style="color: #003C8C;">MONREPORT</h3>
                                <p class="text-muted fs-13 mb-0">Balai Monitor Spektrum Frekuensi Radio dan Infrastruktur Digital Kelas I Samarinda</p>
                                <span class="badge badge-primary light mt-2 px-3 py-1">Kementerian Komunikasi dan Digital RI</span>
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show fs-13 py-2">
                                    <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form action="login.php" method="POST">
                                <div class="mb-3">
                                    <label class="form-label font-w600 fs-13">NIP atau Email Dinas</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fa fa-user text-primary"></i></span>
                                        <input type="text" name="identifier" id="loginIdentifier" class="form-control border-start-0" placeholder="Masukkan NIP atau Email" required autofocus>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label font-w600 fs-13">Kata Sandi</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fa fa-lock text-primary"></i></span>
                                        <input type="password" name="password" id="loginPassword" class="form-control border-start-0" placeholder="Masukkan kata sandi" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-2 fs-14 fw-bold shadow-sm mb-3">
                                    <i class="fa-solid fa-right-to-bracket me-2"></i> Masuk ke Sistem
                                </button>
                            </form>

                            <!-- Demo Accounts One-Click Helper -->
                            <div class="border-top pt-3 mt-3">
                                <p class="fs-12 text-muted fw-bold mb-2"><i class="fa-solid fa-key me-1"></i> Quick Demo Login (Pilih Akun Petugas):</p>
                                <div class="d-grid gap-1">
                                    <button type="button" class="btn btn-outline-primary demo-btn text-start py-1" onclick="fillLogin('19711129 199103 1 000', 'password123')">
                                        <strong>Kepala Balai:</strong> Edi Mulyono, S.Kom, M.M
                                    </button>
                                    <button type="button" class="btn btn-outline-info demo-btn text-start py-1" onclick="fillLogin('19770506 200803 1 001', 'password123')">
                                        <strong>Ketua Tim Montib:</strong> Andreo Yustiantoro Anjaya, ST
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary demo-btn text-start py-1" onclick="fillLogin('19960310 201902 2 006', 'password123')">
                                        <strong>Admin Tim:</strong> Ade Ulfah Laelatussifa,Amd
                                    </button>
                                    <button type="button" class="btn btn-outline-warning demo-btn text-start py-1" onclick="fillLogin('19880924 200803 1 001', 'password123')">
                                        <strong>Ketua Tim Sarpras:</strong> Rifyal, ST
                                    </button>
                                    <button type="button" class="btn btn-outline-dark demo-btn text-start py-1" onclick="fillLogin('19950215 201902 1 002', 'password123')">
                                        <strong>Ketua Tim Layanan:</strong> Muhammad Alif A.F, ST
                                    </button>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <a href="javascript:void(0);" onclick="toggleLoginTheme()" class="text-muted fs-12">
                                    <i class="fa-solid fa-circle-half-stroke me-1"></i> Ganti Tampilan Mode (Light / Dark)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/vendor/global/global.min.js"></script>
    <script src="assets/js/custom.js"></script>
    <script>
    function fillLogin(nip, pass) {
        document.getElementById('loginIdentifier').value = nip;
        document.getElementById('loginPassword').value = pass;
    }
    function toggleLoginTheme() {
        const body = document.body;
        const current = body.getAttribute('data-theme-version') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        body.setAttribute('data-theme-version', next);
        document.cookie = "version=" + next + "; path=/; max-age=" + (365*24*60*60);
    }
    </script>
</body>
</html>
