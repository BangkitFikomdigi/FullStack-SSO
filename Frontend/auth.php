<?php
session_start();

if (!empty($_SESSION['sso_token'])) {
    header('Location: dashboard.php');
    exit;
}

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
    $captchaId = trim($_POST['captcha_id'] ?? '');
    $captchaAnswer = trim($_POST['captcha_answer'] ?? '');

    if ($username === '' || $password === '' || $captchaId === '' || $captchaAnswer === '') {
        $_SESSION['sso_login_username'] = $username;
        header('Location: login.php?error=1');
        exit;
    }

    // Satu kali panggilan API: backend memvalidasi captcha + username + password
    // sekaligus dan langsung mengembalikan token (tidak ada tahap kedua).
    $loginData = callApi('/auth/login', [
        'username' => $username,
        'password' => $password,
        'captcha_id' => $captchaId,
        'captcha_answer' => $captchaAnswer,
    ]);

    if (!($loginData['success'] ?? false) || empty($loginData['data']['refresh_token'])) {
        $_SESSION['sso_login_username'] = $username;
        header('Location: login.php?error=1');
        exit;
    }

    $_SESSION['sso_token'] = $loginData['data']['refresh_token'];
    $_SESSION['sso_session_id'] = $loginData['data']['session_id'] ?? '';
    $_SESSION['sso_user'] = $loginData['data']['user']['username'] ?? $username;
    $_SESSION['sso_modules'] = $loginData['data']['user']['modul_akses'] ?? [];

    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;
