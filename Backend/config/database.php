<?php
/**
 * Konfigurasi koneksi ke database SIK.
 * Default disesuaikan untuk MySQL bawaan Laragon (host: localhost, user: root, tanpa password).
 * Sesuaikan jika environment Anda berbeda.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'sso_portal');
define('DB_USER', 'root');
define('DB_PASS', '');

function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Koneksi ke database SIK gagal: ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}
