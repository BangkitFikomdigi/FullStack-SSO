<?php
session_start();

// Jika sudah ada token SSO, langsung arahkan ke dashboard
if (!empty($_SESSION['sso_token'])) {
    header('Location: dashboard.php');
    exit;
}

// Mengambil username jika sebelumnya sudah pernah diinput/gagal login
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

                <!-- FORM LOGIN TERGABUNG -->
                <form action="auth.php" method="POST" class="login-form" id="loginForm">
                    <input type="hidden" name="action" value="login">

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

                    <!-- BAGIAN CAPTCHA DENGAN TOMBOL REFRESH -->
                    <div class="form-group">
                        <label for="captcha_answer">Kode Captcha</label>
                        <div class="captcha-wrapper" style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                            <canvas id="captchaCanvas" width="140" height="46" title="Klik untuk memperbarui token"></canvas>
                            <button type="button" class="btn-refresh" onclick="generateCaptcha()" title="Ganti Token">
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

        // Fitur Generate Canvas Captcha
        function generateCaptcha() {
            const canvas = document.getElementById('captchaCanvas');
            const ctx = canvas.getContext('2d');
            
            // Bersihkan dan set background kotak
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#f1f5f9';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Tambahkan noise (garis acak)
            for (let i = 0; i < 6; i++) {
                ctx.strokeStyle = `rgba(22, 163, 132, ${Math.random() * 0.5 + 0.2})`;
                ctx.beginPath();
                ctx.moveTo(Math.random() * canvas.width, Math.random() * canvas.height);
                ctx.lineTo(Math.random() * canvas.width, Math.random() * canvas.height);
                ctx.stroke();
            }

            // Buat 5 karakter acak untuk Captcha
            const chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            let captchaText = '';
            for (let i = 0; i < 5; i++) {
                captchaText += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            // Simpan teks di atribut data untuk validasi form
            canvas.setAttribute('data-code', captchaText);

            // Tulis teks di tengah Canvas
            ctx.font = 'bold 22px Inter, sans-serif';
            ctx.fillStyle = '#0f172a';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'center';
            
            // Efek posisi & rotasi acak pada teks
            let x = 25;
            for (let i = 0; i < captchaText.length; i++) {
                ctx.save();
                ctx.translate(x, canvas.height / 2);
                let angle = (Math.random() - 0.5) * 0.4;
                ctx.rotate(angle);
                ctx.fillText(captchaText[i], 0, 0);
                ctx.restore();
                x += 22;
            }
        }

        // Validasi form saat submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const inputCaptcha = document.getElementById('captcha_answer').value.toUpperCase();
            const actualCaptcha = document.getElementById('captchaCanvas').getAttribute('data-code');

            if (inputCaptcha !== actualCaptcha) {
                e.preventDefault(); // Hentikan form agar tidak terkirim
                alert('Kode Captcha yang Anda masukkan salah. Silakan coba lagi!');
                generateCaptcha(); // Refresh captcha
                document.getElementById('captcha_answer').value = ''; // Kosongkan input
                document.getElementById('captcha_answer').focus();
            } else {
                // Efek loading tombol jika captcha benar
                const btn = document.getElementById('submitBtn');
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
                btn.style.opacity = '0.8';
                btn.style.pointerEvents = 'none';
            }
        });

        // Jalankan captcha saat halaman pertama kali dimuat
        window.onload = function() {
            generateCaptcha();
            // Bisa juga di-refresh dengan mengklik area kotaknya langsung
            document.getElementById('captchaCanvas').addEventListener('click', generateCaptcha);
        };
    </script>
</body>
</html>