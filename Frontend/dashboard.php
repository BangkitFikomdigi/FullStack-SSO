<?php
session_start();

if (empty($_SESSION['sso_token'])) {
    header('Location: login.php');
    exit;
}

$apiBase = 'http://localhost:3000';
$token = $_SESSION['sso_token'];

$sessionRes = @file_get_contents($apiBase . '/auth/validate', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\nAuthorization: Bearer {$token}\r\n",
        'content' => json_encode([])
    ]
]));

$sessionData = $sessionRes ? json_decode($sessionRes, true) : null;
$valid = ($sessionData['success'] ?? false) && ($sessionData['valid'] ?? false);
$modules = $sessionData['data']['user']['modul_akses'] ?? ($_SESSION['sso_modules'] ?? []);
$username = $_SESSION['sso_user'] ?? 'User';

if (!$valid) {
    session_destroy();
    header('Location: login.php?expired=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard SSO</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="login.css">
</head>
<body>
  <div class="container" style="max-width: 1200px; margin: 40px auto; padding: 20px;">
    <div class="login-wrapper">
      <div class="login-header">
        <div class="login-icon"><i class="fa-solid fa-user-check"></i></div>
        <h2>Halo, <?php echo htmlspecialchars($username); ?></h2>
        <p>Anda berhasil masuk ke Single Sign-On.</p>
      </div>

      <div class="security-info">
        <i class="fa-solid fa-shield-halved"></i>
        <p>Session aktif dan valid. Daftar aplikasi berikut yang dapat Anda akses sesuai hak izin.</p>
      </div>

      <div class="features-grid">
        <?php foreach ($modules as $module): ?>
          <div class="feature-card">
            <i class="fa-solid fa-circle-check"></i>
            <h3><?php echo htmlspecialchars($module['name'] ?? $module['code'] ?? 'Module'); ?></h3>
            <p><?php echo htmlspecialchars($module['code'] ?? ''); ?></p>
            <a href="<?php echo htmlspecialchars($module['url'] ?? '#'); ?>" target="_blank" rel="noopener noreferrer">Buka aplikasi</a>
          </div>
        <?php endforeach; ?>
      </div>

      <form method="POST" action="logout.php" style="margin-top: 20px;">
        <button type="submit" class="btn-login">
          <i class="fa-solid fa-right-from-bracket"></i> Logout
        </button>
      </form>
    </div>
  </div>
</body>
</html>
