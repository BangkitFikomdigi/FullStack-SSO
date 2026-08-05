<?php
// Proxy untuk refresh captcha di halaman login.
// Memanggil backend GET /auth/captcha lalu mengembalikan JSON
// dalam format yang diharapkan JavaScript login.php:
//   { success: true, svg: "<svg...>", id: "<captcha uuid>" }

$apiBase = 'http://localhost:3000';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Accept: application/json\r\n",
        'ignore_errors' => true,
        'timeout' => 20,
    ]
]);

$response = @file_get_contents($apiBase . '/auth/captcha', false, $context);

if ($response === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal terhubung ke backend captcha.'
    ]);
    exit;
}

$decoded = json_decode($response, true);

if (!($decoded['success'] ?? false) || empty($decoded['data']['captcha']['id']) || empty($decoded['data']['captcha']['svg'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memuat captcha baru.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'svg' => $decoded['data']['captcha']['svg'],
    'id' => $decoded['data']['captcha']['id']
]);
