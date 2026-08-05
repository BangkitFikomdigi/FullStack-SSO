const { Pool } = require('pg');
require('dotenv').config();

const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: process.env.DB_SSL === 'true' ? { rejectUnauthorized: false } : false
});

async function reset() {
// Hapus tabel berurutan (memperhatikan foreign key).
  await pool.query('DROP TABLE IF EXISTS login_activities');
  await pool.query('DROP TABLE IF EXISTS sessions');
  await pool.query('DROP TABLE IF EXISTS user_modules');
  await pool.query('DROP TABLE IF EXISTS modules');
  await pool.query('DROP TABLE IF EXISTS users');

  console.log('Database PostgreSQL di-reset (semua tabel dihapus).');
  console.log('Jalankan server untuk membuat ulang schema & seed data.');
  await pool.end();
}

reset().catch((err) => {
  console.error('Gagal reset database:', err.message);
  process.exit(1);
});
