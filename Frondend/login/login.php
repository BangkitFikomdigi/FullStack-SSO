<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSO Portal - RSJD Amino Hospital</title>
    <!-- Menghubungkan ke file CSS -->
    <link rel="stylesheet" href="../assets/styel/login.css">
    <!-- FontAwesome untuk Ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        
        <!-- BAGIAN KIRI: Informasi & Branding -->
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
                        <p>SKanal pelaporan dan pengaduan internal.</p>
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

        <!-- BAGIAN KANAN: Form Login -->
        <div class="right-panel">
            <div class="login-wrapper">
                <div class="login-header">
                    <h2>Selamat datang</h2>
                    <p>Masuk dengan akun pegawai untuk melanjutkan ke portal aplikasi.</p>
                </div>

                <!-- Form Login -->
                <form action="" method="POST" class="login-form">
                    <div class="form-group">
                        <label for="username">NIP / Username</label>
                        <div class="input-icon">
                            <i class="fa-regular fa-user icon-left"></i>
                            <input type="text" id="username" name="username" value="Username" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Kata sandi</label>
                        <div class="input-icon">
                            <i class="fa-solid fa-lock icon-left"></i>
                            <input type="password" id="password" name="password"  placeholder="Masukkan Password"required>
                          
                        </div>
                    </div>
                    
                    <!-- BAGIAN TOKEN / CAPTCHA -->
                    <div class="form-group">
                        <label for="token">Kode Token / Captcha</label>
                        <div class="captcha-container">
                            <div class="captcha-box" id="captchaDisplay">487291</div>
                            <button type="button" class="btn-refresh" onclick="generateCaptcha()" title="Ganti Token">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                        </div>

                    <!-- TAMBAHAN INPUT TOKEN DI SINI -->
                    <div class="form-group">
                        <label for="token">Token / Kode Verifikasi</label>
                        <div class="input-icon">
                            <i class="fa-solid fa-key icon-left"></i>
                            <input type="text" id="token" name="token" placeholder="Masukkan token keamanan" required>
                        </div>
                    </div>

                    <div class="form-actions">
                        <label class="remember-me">
                            <input type="checkbox" checked>
                            Ingat saya
                        </label>
                        <a href="#" class="forgot-password">Lupa sandi?</a>
                    </div>

                    <button type="submit" class="btn-login">Masuk ke Portal</button>
                </form>

                <div class="security-info">
                    <i class="fa-solid fa-shield-halved"></i>
                    <p>Akses dilindungi Single Sign-On. Jangan bagikan kredensial anda kepada siapa pun.</p>
                </div>

                <div class="support-info">
                    <p>Kendala login? Hubungi <strong>IT Support ext. 1123</strong></p>
                </div>
            </div>
        </div>

    </div>
</body>
</html>