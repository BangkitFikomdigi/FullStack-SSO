<?php
session_start();

$apiBase = 'http://localhost:3000';
$sessionId = $_SESSION['sso_session_id'] ?? null;
$token = $_SESSION['sso_token'] ?? null;

if ($sessionId) {
    @file_get_contents($apiBase . '/auth/logout', false, stream_context_create([
        'http' => [
            'method' => 'DELETE',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode(['session_id' => $sessionId])
        ]
    ]));
}

session_destroy();
header('Location: login.php?logout=1');
exit;
