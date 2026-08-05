<?php
session_start();

// Jika sudah ada token SSO, langsung arahkan ke dashboard
if (!empty($_SESSION['sso_token'])) {
    header('Location: dashboard.php');
    exit;
}

$apiBase = 'http://localhost:3000';

function callApiGet(string $path) {
    global $apiBase;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n",
            'ignore_errors' => true,
            'timeout' => 20,
        ]
    ]);

    $response = @file_get_contents($apiBase . $path, false, $context);
    if ($response === false) {
        $err = error_get_last();
        error_log('[login.php] Gagal konek ke ' . $apiBase . $path . ': ' . ($err['message'] ?? 'unknown error'));
        return null;
    }

    $decoded = json_decode($response, true);
    if ($decoded === null) {
        error_log('[login.php] Response bukan JSON dari ' . $apiBase . $path . ': ' . substr($response, 0, 300));
    }

    return $decoded;
}

// Ambil captcha baru dari backend supaya bisa ditampilkan LANGSUNG di halaman
// login yang sama (satu form, satu kali submit bersama username & password).
$captchaData = callApiGet('/auth/captcha');
$captchaId = $captchaData['data']['captcha']['id'] ?? '';
$captchaSvg = $captchaData['data']['captcha']['svg'] ?? '';

if ($captchaSvg === '') {
    error_log('[login.php] Captcha kosong. Raw response: ' . json_encode($captchaData));
}

// Mengambil username jika sebelumnya sudah pernah diinput/gagal login
$username = $_SESSION['sso_login_username'] ?? '';
unset($_SESSION['sso_login_username']);
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
        <!-- BAGIAN KIRI -->
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

        <!-- BAGIAN KANAN -->
        <div class="right-panel">
            <div class="login-wrapper">
                <div class="login-header">
                    <div class="login-icon"><i class="fa-solid fa-lock" style="font-size: 24px; color: #16a385; margin-bottom: 15px;"></i></div>
                    <h2>Selamat datang</h2>
                    <p>Masuk dengan akun pegawai untuk melanjutkan ke portal aplikasi.</p>
                </div>

                <!-- Notifikasi Error/Info -->
                <?php if (!empty($_GET['error'])): ?>
                    <div class="alert alert-error" style="color: #dc2626; background: #fef2f2; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;">
                        <i class="fa-solid fa-circle-exclamation"></i> Username, password, atau captcha tidak valid.
                    </div>
                <?php elseif (!empty($_GET['expired'])): ?>
                    <div class="alert alert-info" style="color: #0284c7; background: #f0f9ff; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;">
                        <i class="fa-solid fa-clock"></i> Sesi Anda telah berakhir. Silakan login kembali.
                    </div>
                <?php elseif (!empty($_GET['logout'])): ?>
                    <div class="alert alert-info" style="color: #0284c7; background: #f0f9ff; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;">
                        <i class="fa-solid fa-right-from-bracket"></i> Anda telah keluar dari sesi SSO.
                    </div>
                <?php endif; ?>

                <!-- FORM LOGIN 1 HALAMAN (username, password, captcha sekaligus) -->
                <form action="auth.php" method="POST" class="login-form" id="loginForm">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="captcha_id" id="captcha_id" value="<?php echo htmlspecialchars($captchaId); ?>">

                    <div class="form-group">
                        <label for="username">Username / NIP</label>
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
                            <i class="fa-solid fa-eye icon-right" id="togglePassword" onclick="togglePassword()" title="Tampilkan sandi" style="cursor: pointer;"></i>
                        </div>
                    </div>

                    <!-- CAPTCHA ASLI DARI BACKEND, TAMPIL DI HALAMAN YANG SAMA -->
                    <div class="form-group">
                        <label for="captcha_answer">Kode Captcha</label>
                        <div class="captcha-wrapper" style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                            <div id="captchaBox" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:4px; display:flex; align-items:center; justify-content:center; min-width:160px; min-height:60px;">
                                <?php echo $captchaSvg ?: '<span style="font-size:12px;color:#dc2626;padding:8px;">Gagal memuat captcha</span>'; ?>
                            </div>
                            <button type="button" class="btn-refresh" id="refreshCaptchaBtn" title="Ganti Captcha">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                        </div>
                        <div class="input-icon">
                            <i class="fa-solid fa-shield-keyhole icon-left"></i>
                            <input type="text" id="captcha_answer" name="captcha_answer" placeholder="Masukkan kode captcha di atas" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-login" id="submitBtn">
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

    <!-- JAVASCRIPT UNTUK INTERAKSI FORM & CAPTCHA -->
    <script>
        // Fitur Toggle Show/Hide Password
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

        // Ambil captcha baru dari backend (lewat captcha.php sebagai proxy)
        // tanpa reload halaman, supaya tetap 1 layar.
        async function refreshCaptcha() {
            const box = document.getElementById('captchaBox');
            const idField = document.getElementById('captcha_id');
            const answerField = document.getElementById('captcha_answer');

            box.style.opacity = '0.5';
            try {
                const res = await fetch('captcha.php', { cache: 'no-store' });
                const json = await res.json();
                if (json.success) {
                    box.innerHTML = json.svg;
                    idField.value = json.id;
                    answerField.value = '';
                    answerField.focus();
                }
            } catch (e) {
                console.error('Gagal memuat captcha baru', e);
            } finally {
                box.style.opacity = '1';
            }
        }

        document.getElementById('refreshCaptchaBtn').addEventListener('click', refreshCaptcha);
        document.getElementById('captchaBox').addEventListener('click', refreshCaptcha);

        // Efek loading tombol saat submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
            btn.style.opacity = '0.8';
            btn.style.pointerEvents = 'none';
        });
    </script>
</body>
</html>
