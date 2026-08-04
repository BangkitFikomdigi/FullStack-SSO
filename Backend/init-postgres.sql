-- Jalankan di psql sebagai user postgres
CREATE DATABASE fullstack_sso;

-- Setelah masuk ke database fullstack_sso
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Catatan:
-- Aplikasi ini otomatis membuat tabel saat server dijalankan
-- jika database dan koneksi sudah benar.
