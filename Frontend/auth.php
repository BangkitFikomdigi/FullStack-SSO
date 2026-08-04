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
    $action = $_POST['action'] ?? 'login';

    if ($action === 'activate') {
        $sessionId = trim($_POST['session_id'] ?? '');
        $activationCode = trim($_POST['activation_code'] ?? '');
        $captchaId = trim($_POST['captcha_id'] ?? '');
        $captchaAnswer = trim($_POST['captcha_answer'] ?? '');

        if ($sessionId === '' || $activationCode === '' || $captchaId === '' || $captchaAnswer === '') {
            header('Location: login.php?error=1');
            exit;
        }

        $activateData = callApi('/auth/activate', [
            'session_id' => $sessionId,
            'activation_code' => $activationCode,
            'captcha_id' => $captchaId,
            'captcha_answer' => $captchaAnswer,
        ]);

        if (!($activateData['success'] ?? false)) {
            unset($_SESSION['sso_pending_session'], $_SESSION['sso_activation_code'], $_SESSION['sso_captcha_id'], $_SESSION['sso_captcha_svg'], $_SESSION['sso_login_username']);
            $_SESSION['sso_error'] = $activateData['message'] ?? 'Kode aktivasi atau captcha tidak valid.';
            header('Location: login.php?error=1');
            exit;
        }

        $_SESSION['sso_token'] = $activateData['data']['refresh_token'] ?? '';
        $_SESSION['sso_session_id'] = $activateData['data']['session_id'] ?? '';
        $_SESSION['sso_user'] = $activateData['data']['user']['username'] ?? ($_SESSION['sso_login_username'] ?? 'user');
        $_SESSION['sso_modules'] = $activateData['data']['user']['modul_akses'] ?? [];

        unset($_SESSION['sso_pending_session'], $_SESSION['sso_activation_code'], $_SESSION['sso_captcha_id'], $_SESSION['sso_captcha_svg'], $_SESSION['sso_login_username']);

        header('Location: dashboard.php');
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        header('Location: login.php?error=1');
        exit;
    }

    $loginData = callApi('/auth/login', [
        'username' => $username,
        'password' => $password,
    ]);

    if (!($loginData['success'] ?? false)) {
        header('Location: login.php?error=1');
        exit;
    }

    $_SESSION['sso_login_username'] = $username;
    $_SESSION['sso_pending_session'] = $loginData['data']['session_id'] ?? '';
    $_SESSION['sso_activation_code'] = $loginData['data']['activation_code'] ?? '';
    $_SESSION['sso_captcha_id'] = $loginData['data']['captcha']['id'] ?? '';
    $_SESSION['sso_captcha_svg'] = $loginData['data']['captcha']['svg'] ?? '';

    header('Location: login.php');
    exit;
}

header('Location: login.php');
exit;
