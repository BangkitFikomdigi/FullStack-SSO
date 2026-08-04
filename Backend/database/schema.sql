-- Skema database SIK untuk Portal Akses SSO
-- Import lewat HeidiSQL/phpMyAdmin bawaan Laragon, atau: mysql -u root < schema.sql

CREATE DATABASE IF NOT EXISTS sso_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sso_portal;

-- Table User: sumber kredensial & hak akses menu.
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,          -- disimpan dengan password_hash()
    nama       VARCHAR(150) NOT NULL,
    hak_akses  VARCHAR(255) NOT NULL,          -- CSV kode aplikasi, mis: SIMRS,AMINO_MOBILE,WBS
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Menyimpan token & global session yang diterbitkan SSO Authentication Web Service.
CREATE TABLE IF NOT EXISTS sso_sessions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token      CHAR(64) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Contoh akun untuk uji coba.
-- username: admin.rs   | password: rahasia123  | akses ke semua modul
-- username: staf.lapor | password: rahasia123  | akses terbatas
INSERT INTO users (username, password, nama, hak_akses) VALUES
('admin.rs',   '$2b$10$mW30n6XYMOIR2eh4Bwo0tuDGiMfX5L6Nt./k2SoGRhR2eDUXbYS26', 'Admin Rumah Sakit', 'SIMRS,AMINO_MOBILE,LAPOR_AMINO,WBS'),
('staf.lapor', '$2b$10$mW30n6XYMOIR2eh4Bwo0tuDGiMfX5L6Nt./k2SoGRhR2eDUXbYS26', 'Staf Pelaporan',    'LAPOR_AMINO,WBS');
