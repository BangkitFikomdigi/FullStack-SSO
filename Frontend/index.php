<?php
require_once __DIR__ . '/Frontend/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sesi masih aktif, langsung ke dashboard tanpa login ulang.
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
<title>Portal Akses SSO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="Frontend/assets/style.css">
</head>
<body>

<div class="login-screen">
  <div class="brand-pane">
    <div>
      <div class="brand-eyebrow">Portal Akses Terpadu</div>
      <h1 class="brand-title">Satu login untuk semua sistem rumah sakit.</h1>
      <p class="brand-sub">SSO Authentication Web Service memvalidasi kredensial Anda sekali, lalu membuka akses ke SIMRS, AMINO Mobile, LAPOR AMINO, dan WBS sesuai hak akses masing-masing pengguna.</p>

      <div class="flow">
        <div class="flow-step active">
          <span class="flow-dot"></span>
          <div class="flow-label">Login SSO Page</div>
          <div class="flow-detail">Permintaan autentikasi dikirim dari halaman login.</div>
        </div>
        <div class="flow-step">
          <span class="flow-dot"></span>
          <div class="flow-label">SSO Authentication Web Service</div>
          <div class="flow-detail">Query kredensial ke database SIK, lalu token &amp; sesi global dibuat.</div>
        </div>
        <div class="flow-step">
          <span class="flow-dot"></span>
          <div class="flow-label">Sesi Aktif</div>
          <div class="flow-detail">Token tervalidasi, expiration session diset, hak akses menu dibaca.</div>
        </div>
      </div>
    </div>
    <div class="brand-foot mono">SIK · SIMRS · AMINO MOBILE · LAPOR AMINO · WBS</div>
  </div>

  <div class="form-pane">
    <div class="form-card">
      <h2>Masuk ke SSO</h2>
      <p class="lead">Gunakan akun tunggal Anda untuk mengakses seluruh layanan.</p>

      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">Username atau password tidak dikenali. Silakan coba lagi.</div>
      <?php elseif (isset($_GET['expired'])): ?>
        <div class="alert alert-info">Sesi Anda telah berakhir. Silakan login kembali.</div>
      <?php elseif (isset($_GET['logout'])): ?>
        <div class="alert alert-info">Anda telah keluar dari sesi SSO.</div>
      <?php endif; ?>

      <form action="auth.php" method="post">
        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" placeholder="nip.namaanda" required autofocus>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" placeholder="••••••••" required>
        </div>
        <button class="btn-primary" type="submit">Request Authentication</button>
      </form>

      <div class="hint">Token yang tervalidasi berlaku selama <?= SESSION_LIFETIME_MINUTES ?> menit. Anda tidak perlu login ulang saat berpindah antar layanan dalam masa berlaku sesi.</div>
    </div>
  </div>
</div>

</body>
</html>