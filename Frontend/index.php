<?php
require_once __DIR__ . '/includes/functions.php';

// Redirect jika sudah login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['sso_token'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Akses SSO | RSJD Amino Hospital</title>

    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="container">

        <!-- ============ BAGIAN KIRI: Branding & Info ============ -->
        <div class="left-panel">
            <div class="overlay"></div>
            <div class="content-wrapper">

                <div class="brand">
                    <div class="logo-icon">
                        <i class="fa-solid fa-heart-pulse"></i>
                    </div>
                    <div class="brand-text">
                        <h1>RSJD AMINO HOSPITAL</h1>
                        <p>Portal Akses Terpadu</p>
                    </div>
                </div>

                <div class="main-text">
                    <h2>Satu login untuk semua sistem rumah sakit.</h2>
                    <p>SSO Authentication Web Service memvalidasi kredensial Anda sekali, lalu membuka akses ke SIMRS, AMINO Mobile, LAPOR AMINO, dan WBS sesuai hak akses masing-masing pengguna.</p>
                </div>

                <!-- Alur SSO -->
                <div class="flow">
                    <div class="flow-step active">
                        <span class="flow-dot"></span>
                        <div>
                            <div class="flow-label">Login SSO Page</div>
                            <div class="flow-detail">Permintaan autentikasi dikirim dari halaman login.</div>
                        </div>
                    </div>
                    <div class="flow-step">
                        <span class="flow-dot"></span>
                        <div>
                            <div class="flow-label">SSO Authentication Web Service</div>
                            <div class="flow-detail">Query kredensial ke database SIK, lalu token &amp; sesi global dibuat.</div>
                        </div>
                    </div>
                    <div class="flow-step">
                        <span class="flow-dot"></span>
                        <div>
                            <div class="flow-label">Sesi Aktif</div>
                            <div class="flow-detail">Token tervalidasi, expiration session diset, hak akses menu dibaca.</div>
                        </div>
                    </div>
                </div>

                <div class="footer-left">
                    <p>SIK · SIMRS · AMINO MOBILE · LAPOR AMINO · WBS</p>
                </div>
            </div>
        </div>

        <!-- ============ BAGIAN KANAN: Form Login ============ -->
        <div class="right-panel">
            <div class="login-wrapper">

                <div class="login-header">
                    <div class="login-icon">
                        <i class="fa-solid fa-user-lock"></i>
                    </div>
                    <h2>Masuk ke SSO</h2>
                    <p>Gunakan akun tunggal Anda untuk mengakses seluruh layanan.</p>
                </div>

                <?php if (!empty($_GET['error'])): ?>
                    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> Username atau password tidak dikenali. Silakan coba lagi.</div>
                <?php elseif (!empty($_GET['expired'])): ?>
                    <div class="alert alert-info"><i class="fa-solid fa-clock"></i> Sesi Anda telah berakhir. Silakan login kembali.</div>
                <?php elseif (!empty($_GET['logout'])): ?>
                    <div class="alert alert-info"><i class="fa-solid fa-right-from-bracket"></i> Anda telah keluar dari sesi SSO.</div>
                <?php endif; ?>

                <form action="auth.php" method="POST" class="login-form">
                    <div class="form-group">
                        <label for="username">NIP / Username</label>
                        <div class="input-icon">
                            <i class="fa-regular fa-user icon-left"></i>
                            <input type="text" id="username" name="username" placeholder="nip.namaanda" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Kata sandi</label>
                        <div class="input-icon">
                            <i class="fa-solid fa-lock icon-left"></i>
                            <input type="password" id="password" name="password" placeholder="••••••••" required>
                            <i class="fa-solid fa-eye icon-right" id="togglePassword" onclick="togglePassword()" title="Tampilkan sandi"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fa-solid fa-right-to-bracket"></i> Request Authentication
                    </button>
                </form>

                <div class="security-info">
                    <i class="fa-solid fa-shield-halved"></i>
                    <p>Token yang tervalidasi berlaku selama <?= SESSION_LIFETIME_MINUTES ?> menit. Anda tidak perlu login ulang saat berpindah antar layanan dalam masa berlaku sesi.</p>
                </div>

                <div class="support-info">
                    <p>Kendala login? Hubungi <strong>IT Support ext. 1123</strong></p>
                </div>
            </div>
        </div>

    </div>

    <script>
        // ---- Toggle Password Visibility ----
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('togglePassword');
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
