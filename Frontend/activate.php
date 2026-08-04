<?php
session_start();

if (empty($_SESSION['sso_pending_session'])) {
    header('Location: login.php');
    exit;
}

$apiBase = 'http://localhost:3000';
$sessionId = $_SESSION['sso_pending_session'];
$activationCode = $_POST['activation_code'] ?? $_SESSION['sso_activation_code'] ?? '';
$captchaAnswer = $_POST['captcha_answer'] ?? '';
$captchaId = $_SESSION['sso_captcha_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activateRes = @file_get_contents($apiBase . '/auth/activate', false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode([
                'session_id' => $sessionId,
                'activation_code' => $activationCode,
                'captcha_id' => $captchaId,
                'captcha_answer' => $captchaAnswer
            ])
        ]
    ]));

    if ($activateRes === false) {
        header('Location: login.php?error=1');
        exit;
    }

    $activateData = json_decode($activateRes, true);
    if (!($activateData['success'] ?? false)) {
        $_SESSION['sso_error'] = $activateData['message'] ?? 'Aktivasi gagal.';
        header('Location: login.php?error=1');
        exit;
    }

    $_SESSION['sso_token'] = $activateData['data']['refresh_token'] ?? '';
    $_SESSION['sso_session_id'] = $activateData['data']['session_id'] ?? '';
    $_SESSION['sso_user'] = $activateData['data']['user']['username'] ?? '';
    $_SESSION['sso_modules'] = $activateData['data']['user']['modul_akses'] ?? [];
    unset($_SESSION['sso_pending_session'], $_SESSION['sso_activation_code'], $_SESSION['sso_captcha_id'], $_SESSION['sso_captcha_svg']);

    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aktivasi SSO</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="login.css">
</head>
<body>
  <div class="container">
    <div class="right-panel" style="width: 100%; max-width: 520px; margin: 80px auto;">
      <div class="login-wrapper">
        <div class="login-header">
          <div class="login-icon"><i class="fa-solid fa-shield-check"></i></div>
          <h2>Aktivasi SSO</h2>
          <p>Masukkan kode aktivasi dan captcha untuk melanjutkan.</p>
        </div>

        <form method="POST" class="login-form">
          <div class="form-group">
            <label for="activation_code">Kode Aktivasi</label>
            <div class="input-icon">
              <i class="fa-solid fa-key icon-left"></i>
              <input type="text" id="activation_code" name="activation_code" placeholder="Masukkan kode 6 digit" required>
            </div>
          </div>

          <div class="form-group">
            <label>Captcha</label>
            <div class="captcha-container">
              <div class="captcha-box"><?php echo $_SESSION['sso_captcha_svg'] ?? ''; ?></div>
            </div>
          </div>

          <div class="form-group">
            <label for="captcha_answer">Jawaban Captcha</label>
            <div class="input-icon">
              <i class="fa-solid fa-clipboard-check icon-left"></i>
              <input type="text" id="captcha_answer" name="captcha_answer" placeholder="Masukkan jawaban captcha" required>
            </div>
          </div>

          <button type="submit" class="btn-login">
            <i class="fa-solid fa-right-to-bracket"></i> Verifikasi
          </button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
