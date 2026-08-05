const express = require('express');
const cors = require('cors');
const bcrypt = require('bcrypt');
const svgCaptcha = require('svg-captcha');
const crypto = require('crypto');
const sqlite3 = require('sqlite3').verbose();
const fs = require('fs');
const path = require('path');
require('dotenv').config();

const app = express();
app.use(cors({ origin: true, credentials: true }));
app.use(express.json());

const DB_PATH = path.join(__dirname, 'database.sqlite');
let db = new sqlite3.Database(DB_PATH);

// Captcha halaman login: dibuat lepas dari sesi login (belum tahu username/password),
// supaya bisa ditampilkan di satu halaman yang sama bersama form username & password.
// Disimpan sementara di memori (satu kali pakai, kadaluarsa singkat).
const LOGIN_CAPTCHA_TTL_MS = 5 * 60 * 1000; // 5 menit
const loginCaptchaStore = new Map(); // id -> { answer, expiresAt }

function cleanupLoginCaptchas() {
  const now = Date.now();
  for (const [id, entry] of loginCaptchaStore.entries()) {
    if (entry.expiresAt <= now) {
      loginCaptchaStore.delete(id);
    }
  }
}
setInterval(cleanupLoginCaptchas, 60 * 1000).unref();

function recreateDatabaseFile() {
  return new Promise((resolve, reject) => {
    db.close((closeErr) => {
      if (closeErr) {
        console.warn('Close warning:', closeErr.message);
      }

      if (fs.existsSync(DB_PATH)) {
        fs.unlinkSync(DB_PATH);
      }

      db = new sqlite3.Database(DB_PATH);
      resolve();
    });
  });
}

const SESSION_PENDING_MINUTES = parseInt(process.env.SESSION_PENDING_MINUTES) || 5;
const SESSION_ACTIVE_MINUTES = parseInt(process.env.SESSION_ACTIVE_MINUTES) || 15;
const REFRESH_TOKEN_DAYS = parseInt(process.env.REFRESH_TOKEN_DAYS) || 7;
const MAX_ACTIVATION_ATTEMPTS = 5;

function run(sql, params = []) {
  return new Promise((resolve, reject) => {
    db.run(sql, params, function (err) {
      if (err) return reject(err);
      resolve({ lastID: this.lastID, changes: this.changes });
    });
  });
}

function get(sql, params = []) {
  return new Promise((resolve, reject) => {
    db.get(sql, params, (err, row) => {
      if (err) return reject(err);
      resolve(row);
    });
  });
}

function all(sql, params = []) {
  return new Promise((resolve, reject) => {
    db.all(sql, params, (err, rows) => {
      if (err) return reject(err);
      resolve(rows);
    });
  });
}

function generateActivationCode() {
  return String(Math.floor(100000 + Math.random() * 900000));
}

function generateRefreshToken() {
  return crypto.randomBytes(64).toString('hex');
}

function generateCaptcha() {
  const captcha = svgCaptcha.create({
    size: 6,
    ignoreChars: '0oO1lI',
    noise: 2,
    color: true,
    background: '#f0fdf4',
    width: 160,
    height: 60
  });

  return {
    id: crypto.randomUUID(),
    svg: captcha.data,
    answer: captcha.text.toLowerCase()
  };
}

function toMinutes(ms) {
  const total = Math.round(ms / 60000);
  return total > 0 ? total : 0;
}

async function initDatabase() {
  const initializeSchema = async () => {
    await run(`
      CREATE TABLE IF NOT EXISTS users (
        id TEXT PRIMARY KEY,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
      )
    `);

    await run(`
      CREATE TABLE IF NOT EXISTS modules (
        id TEXT PRIMARY KEY,
        code TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        url TEXT NOT NULL
      )
    `);

    await run(`
      CREATE TABLE IF NOT EXISTS user_modules (
        user_id TEXT,
        module_id TEXT,
        PRIMARY KEY (user_id, module_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (module_id) REFERENCES modules(id)
      )
    `);

    await run(`
      CREATE TABLE IF NOT EXISTS sessions (
        id TEXT PRIMARY KEY,
        user_id TEXT,
        status TEXT NOT NULL DEFAULT 'pending',
        activation_code TEXT,
        activation_attempts INTEGER DEFAULT 0,
        captcha_id TEXT,
        captcha_answer TEXT,
        refresh_token TEXT,
        refresh_expires_at TEXT,
        expires_at TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
      )
    `);

    const modules = [
      { code: 'SIMRS', name: 'SIMRS', url: 'https://rs-amino.jatengprov.go.id/login/' },
      { code: 'AMINO_MOBILE', name: 'AMINO Mobile', url: 'https://rs-amino.jatengprov.go.id/inovasi-amino-mobile/' },
      { code: 'LAPOR_AMINO', name: 'LAPOR AMINO', url: 'https://rs-amino.jatengprov.go.id/pengaduaninformasi-pasien/' },
      { code: 'WBS', name: 'WBS', url: 'https://rs-amino.jatengprov.go.id/sistem-pelaporan-pelanggaran-wbs/' }
    ];

    for (const m of modules) {
      await run(
        `INSERT OR IGNORE INTO modules (id, code, name, url) VALUES (?, ?, ?, ?)`,
        [crypto.randomUUID(), m.code, m.name, m.url]
      );
    }

    const defaultUsers = [
      { username: 'admin_simrs', password: '12#56*DS', modules: ['SIMRS'] },
      { username: 'dokter_amino', password: '11#22*AA', modules: ['AMINO_MOBILE'] },
      { username: 'petugas_lapor', password: '33#44*PL', modules: ['LAPOR_AMINO'] },
      { username: 'manager_wbs', password: '55#66*MW', modules: ['WBS'] },
      { username: 'super_user', password: '77#88*SU', modules: ['SIMRS', 'AMINO_MOBILE', 'LAPOR_AMINO', 'WBS'] }
    ];

    for (const user of defaultUsers) {
      const existing = await get('SELECT id FROM users WHERE username = ?', [user.username]);
      if (!existing) {
        const passwordHash = await bcrypt.hash(user.password, 10);
        const userId = crypto.randomUUID();
        await run('INSERT INTO users (id, username, password_hash) VALUES (?, ?, ?)', [userId, user.username, passwordHash]);

        for (const code of user.modules) {
          const module = await get('SELECT id FROM modules WHERE code = ?', [code]);
          if (module) {
            await run('INSERT OR IGNORE INTO user_modules (user_id, module_id) VALUES (?, ?)', [userId, module.id]);
          }
        }
      }
    }
  };

  try {
    await initializeSchema();
    console.log('✅ Database SQLite siap & ter-seed.');
  } catch (error) {
    if (error && /mismatch|datatype/i.test(String(error.message))) {
      console.warn('⚠️ Deteksi schema lama. Menghapus database lama dan membuat ulang...');
      await recreateDatabaseFile();
      await initializeSchema();
      console.log('✅ Database SQLite baru dibuat & ter-seed.');
    } else {
      throw error;
    }
  }
}

async function getUserModules(userId) {
  return all(
    `SELECT m.code, m.name, m.url
     FROM user_modules um
     JOIN modules m ON m.id = um.module_id
     WHERE um.user_id = ?
     ORDER BY m.code`,
    [userId]
  );
}

app.get('/health', (req, res) => {
  res.json({ success: true, message: 'SSO backend is running' });
});

// Captcha berdiri sendiri untuk halaman login (tampil bersama username & password,
// tidak menunggu password diverifikasi dulu).
app.get('/auth/captcha', (req, res) => {
  cleanupLoginCaptchas();
  const captcha = generateCaptcha();
  loginCaptchaStore.set(captcha.id, {
    answer: captcha.answer,
    expiresAt: Date.now() + LOGIN_CAPTCHA_TTL_MS
  });

  return res.status(201).json({
    success: true,
    data: {
      captcha: {
        id: captcha.id,
        svg: captcha.svg
      },
      expires_in: LOGIN_CAPTCHA_TTL_MS / 1000
    }
  });
});

app.post('/auth/login', async (req, res) => {
  try {
    const { username, password, captcha_id, captcha_answer } = req.body || {};

    if (!username || !password || !captcha_id || !captcha_answer) {
      return res.status(400).json({ success: false, message: 'Username, password, dan captcha wajib diisi' });
    }

    // 1. Cek captcha lebih dulu (satu kali pakai) sebelum menyentuh data user.
    const captchaEntry = loginCaptchaStore.get(captcha_id);
    loginCaptchaStore.delete(captcha_id); // one-time use, baik cocok maupun tidak

    if (!captchaEntry || captchaEntry.expiresAt <= Date.now()) {
      return res.status(400).json({ success: false, message: 'Captcha kadaluarsa. Silakan muat ulang captcha.' });
    }

    if (String(captcha_answer).toLowerCase() !== String(captchaEntry.answer).toLowerCase()) {
      return res.status(400).json({ success: false, message: 'Username, password, atau captcha tidak valid' });
    }

    // 2. Baru cek username/password.
    const user = await get('SELECT * FROM users WHERE username = ?', [username]);
    if (!user) {
      return res.status(401).json({ success: false, message: 'Username, password, atau captcha tidak valid' });
    }

    const isMatch = await bcrypt.compare(password, user.password_hash);
    if (!isMatch) {
      return res.status(401).json({ success: false, message: 'Username, password, atau captcha tidak valid' });
    }

    // 3. Semua valid -> langsung buat sesi aktif (tanpa tahap aktivasi terpisah).
    const sessionId = crypto.randomUUID();
    const refreshToken = generateRefreshToken();
    const now = new Date();
    const activeExpiresAt = new Date(now.getTime() + SESSION_ACTIVE_MINUTES * 60 * 1000).toISOString();
    const refreshExpiresAt = new Date(now.getTime() + REFRESH_TOKEN_DAYS * 24 * 60 * 60 * 1000).toISOString();

    await run(
      `INSERT INTO sessions (id, user_id, status, refresh_token, refresh_expires_at, expires_at)
       VALUES (?, ?, 'active', ?, ?, ?)`,
      [sessionId, user.id, refreshToken, refreshExpiresAt, activeExpiresAt]
    );

    const modulAkses = await getUserModules(user.id);

    return res.status(201).json({
      success: true,
      data: {
        refresh_token: refreshToken,
        expires_in: SESSION_ACTIVE_MINUTES * 60,
        session_id: sessionId,
        status: 'active',
        user: {
          username: user.username,
          modul_akses: modulAkses
        }
      }
    });
  } catch (error) {
    console.error('[/auth/login] Error:', error);
    return res.status(500).json({ success: false, message: 'Terjadi kesalahan pada server' });
  }
});

app.post('/auth/activate', async (req, res) => {
  try {
    const { session_id, activation_code, captcha_id, captcha_answer } = req.body || {};
    if (!session_id || !activation_code || !captcha_id || !captcha_answer) {
      return res.status(400).json({ success: false, message: 'Semua field wajib diisi' });
    }

    const session = await get('SELECT * FROM sessions WHERE id = ?', [session_id]);
    if (!session) {
      return res.status(404).json({ success: false, message: 'Session tidak ditemukan' });
    }

    if (session.status !== 'pending') {
      return res.status(400).json({ success: false, message: `Session sudah berstatus ${session.status}` });
    }

    if (new Date() > new Date(session.expires_at)) {
      await run('UPDATE sessions SET status = ? WHERE id = ?', ['expired', session.id]);
      return res.status(401).json({ success: false, message: 'Session kadaluarsa. Silakan login ulang.' });
    }

    if (session.activation_attempts >= MAX_ACTIVATION_ATTEMPTS) {
      await run('UPDATE sessions SET status = ? WHERE id = ?', ['expired', session.id]);
      return res.status(429).json({ success: false, message: 'Terlalu banyak percobaan. Session dikunci.' });
    }

    const captchaMatch = session.captcha_id === captcha_id && String(captcha_answer).toLowerCase() === String(session.captcha_answer).toLowerCase();
    const codeMatch = String(activation_code) === String(session.activation_code);

    if (!captchaMatch || !codeMatch) {
      const newAttempts = Number(session.activation_attempts || 0) + 1;
      await run('UPDATE sessions SET activation_attempts = ? WHERE id = ?', [newAttempts, session.id]);
      return res.status(400).json({
        success: false,
        message: 'Kode aktivasi atau captcha tidak sesuai.',
        remaining_attempts: MAX_ACTIVATION_ATTEMPTS - newAttempts
      });
    }

    const refreshToken = generateRefreshToken();
    const now = new Date();
    const activeExpiresAt = new Date(now.getTime() + SESSION_ACTIVE_MINUTES * 60 * 1000).toISOString();
    const refreshExpiresAt = new Date(now.getTime() + REFRESH_TOKEN_DAYS * 24 * 60 * 60 * 1000).toISOString();

    await run(
      `UPDATE sessions
       SET status = 'active',
           activation_code = NULL,
           captcha_id = NULL,
           captcha_answer = NULL,
           refresh_token = ?,
           refresh_expires_at = ?,
           expires_at = ?,
           updated_at = CURRENT_TIMESTAMP
       WHERE id = ?`,
      [refreshToken, refreshExpiresAt, activeExpiresAt, session.id]
    );

    const user = await get('SELECT * FROM users WHERE id = ?', [session.user_id]);
    const modulAkses = await getUserModules(user.id);

    return res.json({
      success: true,
      data: {
        refresh_token: refreshToken,
        expires_in: SESSION_ACTIVE_MINUTES * 60,
        session_id: session.id,
        status: 'active',
        user: {
          username: user.username,
          modul_akses: modulAkses
        }
      }
    });
  } catch (error) {
    console.error('[/auth/activate] Error:', error);
    return res.status(500).json({ success: false, message: 'Terjadi kesalahan pada server', error: error.message });
  }
});

app.post('/auth/session', async (req, res) => {
  try {
    const { session_id } = req.body || {};
    if (!session_id) {
      return res.status(400).json({ success: false, message: 'session_id wajib diisi' });
    }

    const session = await get('SELECT * FROM sessions WHERE id = ?', [session_id]);
    if (!session) {
      return res.status(404).json({ success: false, message: 'Session tidak ditemukan' });
    }

    if (session.status === 'active' && new Date() > new Date(session.expires_at)) {
      await run('UPDATE sessions SET status = ? WHERE id = ?', ['expired', session.id]);
      session.status = 'expired';
    }

    const user = await get('SELECT * FROM users WHERE id = ?', [session.user_id]);
    const modulAkses = await getUserModules(user.id);

    return res.json({
      success: true,
      data: {
        session_id: session.id,
        status: session.status,
        user: {
          username: user.username,
          modul_akses: modulAkses
        },
        created_at: session.created_at,
        expires_at: session.expires_at,
        expires_in: session.expires_at ? toMinutes(new Date(session.expires_at) - new Date()) : null
      }
    });
  } catch (error) {
    console.error('[/auth/session] Error:', error);
    return res.status(500).json({ success: false, message: 'Terjadi kesalahan pada server' });
  }
});

app.post('/auth/validate', async (req, res) => {
  try {
    const authHeader = req.headers.authorization || '';
    let token = authHeader.startsWith('Bearer ') ? authHeader.split(' ')[1] : authHeader;

    if (!token && req.body && req.body.token) {
      token = req.body.token;
    }

    if (!token) {
      return res.status(401).json({ success: false, valid: false, message: 'Token tidak ditemukan' });
    }

    const session = await get('SELECT * FROM sessions WHERE refresh_token = ? AND status = ?', [token, 'active']);
    if (!session) {
      return res.status(401).json({ success: false, valid: false, message: 'Token tidak valid atau session tidak aktif' });
    }

    if (new Date() > new Date(session.expires_at)) {
      await run('UPDATE sessions SET status = ? WHERE id = ?', ['expired', session.id]);
      return res.status(401).json({ success: false, valid: false, message: 'Session expired' });
    }

    const user = await get('SELECT * FROM users WHERE id = ?', [session.user_id]);
    const modulAkses = await getUserModules(user.id);

    return res.json({
      success: true,
      valid: true,
      data: {
        session_id: session.id,
        time_remaining: toMinutes(new Date(session.expires_at) - new Date()),
        user: {
          username: user.username,
          modul_akses: modulAkses
        }
      }
    });
  } catch (error) {
    console.error('[/auth/validate] Error:', error);
    return res.status(500).json({ success: false, valid: false, message: 'Terjadi kesalahan pada server' });
  }
});

app.post('/auth/refresh', async (req, res) => {
  try {
    const { refresh_token } = req.body || {};
    if (!refresh_token) {
      return res.status(400).json({ success: false, message: 'refresh_token wajib diisi' });
    }

    const session = await get('SELECT * FROM sessions WHERE refresh_token = ?', [refresh_token]);
    if (!session) {
      return res.status(401).json({ success: false, message: 'Refresh token tidak valid' });
    }

    if (new Date() > new Date(session.refresh_expires_at)) {
      await run('UPDATE sessions SET status = ? WHERE id = ?', ['expired', session.id]);
      return res.status(401).json({ success: false, message: 'Refresh token kadaluarsa. Silakan login ulang.' });
    }

    const newActiveExpiresAt = new Date(Date.now() + SESSION_ACTIVE_MINUTES * 60 * 1000).toISOString();
    await run('UPDATE sessions SET status = ?, expires_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', ['active', newActiveExpiresAt, session.id]);

    const user = await get('SELECT * FROM users WHERE id = ?', [session.user_id]);
    const modulAkses = await getUserModules(user.id);

    return res.json({
      success: true,
      data: {
        refresh_token: refresh_token,
        expires_in: SESSION_ACTIVE_MINUTES * 60,
        session_id: session.id,
        status: 'active',
        user: {
          username: user.username,
          modul_akses: modulAkses
        }
      }
    });
  } catch (error) {
    console.error('[/auth/refresh] Error:', error);
    return res.status(500).json({ success: false, message: 'Terjadi kesalahan pada server' });
  }
});

app.delete('/auth/logout', async (req, res) => {
  try {
    const { session_id } = req.body || {};
    if (!session_id) {
      return res.status(400).json({ success: false, message: 'session_id wajib diisi' });
    }

    const result = await run('UPDATE sessions SET status = ?, refresh_token = NULL WHERE id = ?', ['expired', session_id]);
    if (result.changes === 0) {
      return res.status(404).json({ success: false, message: 'Session tidak ditemukan' });
    }

    return res.json({ success: true, message: 'Logout berhasil. Session dan token dinonaktifkan.' });
  } catch (error) {
    console.error('[/auth/logout] Error:', error);
    return res.status(500).json({ success: false, message: 'Terjadi kesalahan pada server' });
  }
});

const PORT = process.env.PORT || 3000;

initDatabase()
  .then(() => {
    app.listen(PORT, () => {
      console.log(`✅ SSO Backend berjalan di http://localhost:${PORT}`);
      console.log('📋 Endpoint:');
      console.log('   GET    /health');
      console.log('   GET    /auth/captcha');
      console.log('   POST   /auth/login');
      console.log('   POST   /auth/activate  (legacy, tidak dipakai alur login 1-halaman)');
      console.log('   POST   /auth/session');
      console.log('   POST   /auth/validate');
      console.log('   POST   /auth/refresh');
      console.log('   DELETE /auth/logout');
    });
  })
  .catch((err) => {
    console.error('❌ Gagal inisialisasi database:', err.message);
    process.exit(1);
  });

