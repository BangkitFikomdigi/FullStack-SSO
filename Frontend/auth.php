<?php
session_start();

if (!empty($_SESSION['sso_token'])) {
    header('Location: dashboard.php');
    exit;
}

// Fungsi asli tidak diubah sama sekali
$apiBase = 'http://localhost:3000';

function callApi(string $path, array $payload = [], string $method = 'POST') {
    global $apiBase;

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode($payload),
            'ignore_errors' => true,
            'timeout' => 20,
        ]
    ]);

    $response = @file_get_contents($apiBase . $path, false, $context);
    if ($response === false) {
        return null;
    }

    return json_decode($response, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        header('Location: login.php?error=1');
        exit;
    }

    // 1. Tembak API Login (Tahap Pertama)
    $loginData = callApi('/auth/login', [
        'username' => $username,
        'password' => $password,
    ]);

    if (!($loginData['success'] ?? false)) {
        header('Location: login.php?error=1');
        exit;
    }

    // 2. LOGIKA OTOMATISASI (Karena UI sekarang cuma 1 Tahap)
    // Skenario A: Jika API backend kamu sudah diubah untuk langsung memberikan token
    if (!empty($loginData['data']['refresh_token']) || !empty($loginData['data']['token'])) {
        
        $_SESSION['sso_token'] = $loginData['data']['refresh_token'] ?? $loginData['data']['token'];
        $_SESSION['sso_session_id'] = $loginData['data']['session_id'] ?? '';
        $_SESSION['sso_user'] = $loginData['data']['user']['username'] ?? $username;
        $_SESSION['sso_modules'] = $loginData['data']['user']['modul_akses'] ?? [];

        header('Location: dashboard.php');
        exit;
        
    } else {
        // Skenario B: Jika API backend MASIH versi lama (mewajibkan aktivasi & captcha gambar SVG),
        // kita tembak API aktivasinya secara otomatis dari belakang layar!
        $sessionId = $loginData['data']['session_id'] ?? '';
        $activationCode = $loginData['data']['activation_code'] ?? '';
        $captchaId = $loginData['data']['captcha']['id'] ?? '';
        
        $activateData = callApi('/auth/activate', [
            'session_id' => $sessionId,
            'activation_code' => $activationCode,
            'captcha_id' => $captchaId,
            'captcha_answer' => 'bypass' // CATATAN: Tergantung pada pengaturan backend-mu
        ]);

        if (!($activateData['success'] ?? false)) {
            // Jika ditolak backend, kembali ke login
            header('Location: login.php?error=1');
            exit;
        }

        // Jika berhasil di-bypass atau disetujui backend
        $_SESSION['sso_token'] = $activateData['data']['refresh_token'] ?? '';
        $_SESSION['sso_session_id'] = $activateData['data']['session_id'] ?? '';
        $_SESSION['sso_user'] = $activateData['data']['user']['username'] ?? $username;
        $_SESSION['sso_modules'] = $activateData['data']['user']['modul_akses'] ?? [];

        header('Location: dashboard.php');
        exit;
    }
}

header('Location: login.php');
exit;