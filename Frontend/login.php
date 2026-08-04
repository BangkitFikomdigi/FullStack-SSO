<?php
session_start();

if (!empty($_SESSION['sso_token'])) {
    header('Location: dashboard.php');
    exit;
}

$sessionId = $_SESSION['sso_pending_session'] ?? '';
$activationCode = $_SESSION['sso_activation_code'] ?? '';
$captchaId = $_SESSION['sso_captcha_id'] ?? '';
$captchaSvg = $_SESSION['sso_captcha_svg'] ?? '';
$username = $_SESSION['sso_login_username'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SSO Portal - RSJD Amino Hospital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="overlay"></div>
            <div class="content-wrapper">
                <div class="brand">
                    <div class="logo-icon"><i class="fa-solid fa-heart-pulse"></i></div>
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
                    <div class="feature-card"><i class="fa-solid fa-stethoscope"></i><h3>SIMRS</h3><p>Sistem informasi manajemen rumah sakit.</p></div>
                    <div class="feature-card"><i class="fa-solid fa-heart-circle-check"></i><h3>AMINO_MOBILE</h3><p>Layanan mobile untuk staf dan pasien.</p></div>
                    <div class="feature-card"><i class="fa-solid fa-capsules"></i><h3>LAPOR_AMINO</h3><p>Kanal pelaporan dan pengaduan internal.</p></div>
                    <div class="feature-card"><i class="fa-regular fa-user"></i><h3>WBS</h3><p>Whistleblowing system untuk pelaporan pelanggaran.</p></div>
                </div>

                <div class="footer-left">
                    <p>&copy; 2026 RSJD Amino Hospital - Divisi Teknologi Informasi</p>
                </div>
            </div>
        </div>

        <div class="right-panel">
            <div class="login-wrapper">
                <div class="login-header">
                    <div class="login-icon"><i class="fa-solid fa-lock"></i></div>
                    <h2>Selamat datang</h2>
                    <p>Masuk dengan akun pegawai untuk melanjutkan ke portal aplikasi.</p>
                </div>

                <?php if (!empty($_GET['error'])): ?>
                    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> Username, password, captcha, atau kode aktivasi tidak valid.</div>
                <?php elseif (!empty($_GET['expired'])): ?>
                    <div class="alert alert-info"><i class="fa-solid fa-clock"></i> Sesi Anda telah berakhir. Silakan login kembali.</div>
                <?php elseif (!empty($_GET['logout'])): ?>
                    <div class="alert alert-info"><i class="fa-solid fa-right-from-bracket"></i> Anda telah keluar dari sesi SSO.</div>
                <?php endif; ?>

                <form action="auth.php" method="POST" class="login-form">
                    <?php if ($sessionId && $activationCode && $captchaId): ?>
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($sessionId); ?>">
                        <input type="hidden" name="captcha_id" value="<?php echo htmlspecialchars($captchaId); ?>">
                        <input type="hidden" name="activation_code" value="<?php echo htmlspecialchars($activationCode); ?>">
                    <?php else: ?>
                        <input type="hidden" name="action" value="login">
                    <?php endif; ?>

                    <?php if (!($sessionId && $activationCode && $captchaId)): ?>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <div class="input-icon">
                                <i class="fa-regular fa-user icon-left"></i>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" placeholder="Masukkan username" required autofocus>
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
                    <?php else: ?>
                        <div class="form-group">
                            <label>Kode Aktivasi</label>
                            <div class="captcha-container">
                                <div class="captcha-box" style="min-height: 54px; font-size: 2rem; letter-spacing: 0.30rem; text-align:center; display:flex; align-items:center; justify-content:center;">
                                    <?php echo htmlspecialchars($activationCode); ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Captcha</label>
                            <div class="captcha-container">
                                <div class="captcha-box" style="display:flex; align-items:center; justify-content:center; background:#fff;">
                                    <?php echo $captchaSvg; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="captcha_answer">Jawaban Captcha</label>
                            <div class="input-icon">
                                <i class="fa-solid fa-key icon-left"></i>
                                <input type="text" id="captcha_answer" name="captcha_answer" placeholder="Masukkan jawaban captcha" required>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn-login" id="submitButton">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <?php echo ($sessionId && $activationCode && $captchaId) ? 'Verifikasi & Masuk' : 'Request Authentication'; ?>
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
