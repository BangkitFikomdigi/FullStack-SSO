# SSO Authentication Backend - Migrasi SQLite ke PostgreSQL

## Steps
- [x] 1. Analisis backend & frontend yang ada
- [x] 2. Verifikasi koneksi PostgreSQL (pg, .env, database sso_auth)
- [x] 3. Rewrite `Backend/server.js` ke PostgreSQL (pg Pool, dialect SQL PostgreSQL)
- [x] 4. Rewrite `Backend/reset-db.js` untuk PostgreSQL
- [x] 5. Jalankan server & uji endpoint (/health, /auth/captcha, /auth/login)
- [x] 6. Verifikasi table & seed users di PostgreSQL

## Fitur Tambahan: Activity Login (untuk maintenance admin)
- [x] 7. Tambah tabel `login_activities` di PostgreSQL (schema server.js)
- [x] 8. Tambah helper `logLoginActivity` & catat aktivitas di /auth/login (sukses/gagal)
- [x] 9. Tambah endpoint admin GET /admin/login-activities
- [x] 10. Update reset-db.js (drop login_activities)
- [x] 11. Restart server & uji penyimpanan activity login
