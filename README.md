# FullStack-SSO

A simple SSO authentication project using PostgreSQL for the backend and PHP for the frontend.

## Setup untuk Kontributor

### 1. Clone repository

```bash
git clone https://github.com/<your-repo>/FullStack-SSO.git
cd FullStack-SSO
```

### 2. Install backend dependencies

```bash
cd Backend
npm install
```

### 3. Buat database PostgreSQL lokal

Database yang dipakai adalah `sso_auth` pada PostgreSQL lokal.

#### Opsi A: Gunakan `psql`

```bash
set PGPASSWORD=postgres
"c:\laragon\bin\postgresql\postgresql\bin\psql.exe" -U postgres -h localhost -p 5432 -c "CREATE DATABASE sso_auth;"
set PGPASSWORD=postgres
"c:\laragon\bin\postgresql\postgresql\bin\psql.exe" -U postgres -h localhost -p 5432 -d sso_auth -c "CREATE EXTENSION IF NOT EXISTS pgcrypto;"
```

#### Opsi B: Pakai skrip SQL dari repo

Setelah database `sso_auth` dibuat, impor `Backend/init-postgres.sql` secara manual melalui `psql` atau pgAdmin.

### 4. Siapkan file environment lokal

Salin `Backend/.env.example` menjadi `Backend/.env` dan sesuaikan jika perlu.

```bash
cd Backend
copy .env.example .env
```

> `Backend/.env` tidak boleh di-commit. File ini sudah diabaikan oleh `.gitignore`.

### 5. Jalankan backend

```bash
npm start
```

Server akan membaca `Backend/.env`, lalu membuat tabel dan seed data secara otomatis jika belum ada.

## Cara cek database

Masuk ke PostgreSQL dan lihat daftar database:

```bash
set PGPASSWORD=postgres
"c:\laragon\bin\postgresql\postgresql\bin\psql.exe" -U postgres -h localhost -p 5432 -l
```

Lalu sambungkan ke `sso_auth`:

```sql
\c sso_auth
\dt
SELECT * FROM users;
```

## Catatan untuk kolaborasi

- Jangan commit `Backend/.env`
- Jangan membagikan folder data PostgreSQL lokal (`c:\laragon\data\postgresql`)
- Jika ingin berbagi data database, gunakan dump SQL (`pg_dump`) dan jangan sertakan file data cluster
- Konfigurasi yang dapat dibagikan adalah `Backend/.env.example` dan skrip SQL/schema

## File penting

- `Backend/.env.example` — contoh konfigurasi environment
- `Backend/.env` — konfigurasi lokal pribadi (tidak ada di repo)
- `Backend/init-postgres.sql` — skrip inisialisasi database PostgreSQL
- `Backend/reset-db.js` — skrip hapus tabel untuk reset local database

