<?php
// -- Redirect jika sudah login ----------------------------------------
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
    <title>Login | SSO Portal - RSJD Amino Hospital</title>

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
                        <p>Single Sign-On Portal</p>
                    </div>
                </div>

                <div class="main-text">
                    <h2>Satu akun,<br>empat aplikasi rumah sakit.</h2>
                    <p>Cukup masuk sekali untuk mengakses semua layanan internal secara aman dan cepat.</p>
                </div>

                <div class="features-grid">
                    <div class="feature-card">
                        <i class="fa-solid fa-stethoscope"></i>
                        <h3>SIMRS</h3>
                        <p>Sistem informasi manajemen rumah sakit.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fa-solid fa-heart-circle-check"></i>
                        <h3>AMINO_MOBILE</h3>
                        <p>Layanan mobile untuk staf dan pasien.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fa-solid fa-capsules"></i>
                        <h3>LAPOR_AMINO</h3>
                        <p>Kanal pelaporan dan pengaduan internal.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fa-regular fa-user"></i>
                        <h3>WBS</h3>
                        <p>Whistleblowing system untuk pelaporan pelanggaran.</p>
                    </div>
                </div>

                <div class="footer-left">
                    <p>&copy; 2026 RSJD Amino Hospital - Divisi Teknologi Informasi</p>
                </div>
            </div>
        </div>

        <!-- ============ BAGIAN KANAN: Form Login ============ -->
        <div class="right-panel">
            <div class="login-wrapper">

                <div class="login-header">
                    <div class="login-icon">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <h2>Selamat datang</h2>
                    <p>Masuk dengan akun pegawai untuk melanjutkan ke portal aplikasi.</p>
                </div>

                <?php if (!empty($_GET['error'])): ?>
                    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> Username atau password tidak dikenali. Silakan coba lagi.</div>
                <?php elseif (!empty($_GET['expired'])): ?>
                    <div class="alert alert-info"><i class="fa-solid fa-clock"></i> Sesi Anda telah berakhir. Silakan login kembali.</div>
                <?php elseif (!empty($_GET['logout'])): ?>
                    <div class="alert alert-info"><i class="fa-solid fa-right-from-bracket"></i> Anda telah keluar dari sesi SSO.</div>
                <?php endif; ?>

                <!-- Form Login -->
                <form action="auth.php" method="POST" class="login-form" id="loginForm">
                    <div class="form-group">
                        <label for="username">NIP / Username</label>
                        <div class="input-icon">
                            <i class="fa-regular fa-user icon-left"></i>
                            <input type="text" id="username" name="username" placeholder="Masukkan NIP / Username" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Kata sandi</label>
                        <div class="input-icon">
                            <i class="fa-solid fa-lock icon-left"></i>
                            <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" required>
                            <i class="fa-solid fa-eye icon-right" id="togglePassword" onclick="togglePassword()" title="Tampilkan sandi"></i>
                        </div>
                    </div>

                    <!-- Captcha / Token -->
                    <div class="form-group">
                        <label for="token">Kode Keamanan (Captcha)</label>
                        <div class="captcha-container">
                            <div class="captcha-box" id="captchaDisplay" onclick="generateCaptcha()" title="Klik untuk mengubah">487291</div>
                            <button type="button" class="btn-refresh" onclick="generateCaptcha()" title="Ganti Token">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                        </div>
                        <div class="input-icon" style="margin-top:12px;">
                            <i class="fa-solid fa-key icon-left"></i>
                            <input type="text" id="token" name="token" placeholder="Masukkan kode verifikasi" required>
                        </div>
                    </div>

                    <div class="form-actions">
                        <label class="remember-me">
                            <input type="checkbox" checked>
                            Ingat saya
                        </label>
                        <a href="#" class="forgot-password">Lupa sandi?</a>
                    </div>

                    <button type="submit" class="btn-login" id="btnLogin">
                        <i class="fa-solid fa-right-to-bracket"></i> Masuk ke Portal
                    </button>
                </form>

                <div class="security-info">
                    <i class="fa-solid fa-shield-halved"></i>
                    <p>Akses dilindungi Single Sign-On. Jangan bagikan kredensial Anda kepada siapa pun.</p>
                </div>

                <div class="support-info">
                    <p>Kendala login? Hubungi <strong>IT Support ext. 1123</strong></p>
                </div>
            </div>
        </div>

    </div>

    <script>
        // ---- Generate Captcha ----
        function generateCaptcha() {
            const el = document.getElementById('captchaDisplay');
            const code = String(Math.floor(100000 + Math.random() * 900000));
            el.textContent = code;
            el.dataset.code = code;
        }

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

        // ---- Client-side captcha validation ----
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            const token = document.getElementById('token').value.trim();
            const captcha = document.getElementById('captchaDisplay').dataset.code ||
                           document.getElementById('captchaDisplay').textContent.trim();
            if (token !== captcha) {
                e.preventDefault();
                alert('Kode keamanan (captcha) tidak sesuai. Silakan coba lagi.');
                generateCaptcha();
                document.getElementById('token').value = '';
            }
        });
    </script>
</body>
</html>
