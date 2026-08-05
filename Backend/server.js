const express = require('express');
const cors = require('cors');
const bcrypt = require('bcrypt');
const svgCaptcha = require('svg-captcha');
const crypto = require('crypto');
const { Pool } = require('pg');
require('dotenv').config();

const app = express();
app.use(cors({ origin: true, credentials: true }));
app.use(express.json());

const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: process.env.DB_SSL === 'true' ? { rejectUnauthorized: false } : false
});

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

const SESSION_PENDING_MINUTES = parseInt(process.env.SESSION_PENDING_MINUTES) || 5;
const SESSION_ACTIVE_MINUTES = parseInt(process.env.SESSION_ACTIVE_MINUTES) || 15;
const REFRESH_TOKEN_DAYS = parseInt(process.env.REFRESH_TOKEN_DAYS) || 7;
const MAX_ACTIVATION_ATTEMPTS = 5;

function run(sql, params = []) {
  return pool.query(sql, params).then((result) => ({
    lastID: null,
    changes: result.rowCount ?? 0
  }));
}

function get(sql, params = []) {
  return pool.query(sql, params).then((result) => result.rows[0] || null);
}

function all(sql, params = []) {
  return pool.query(sql, params).then((result) => result.rows);
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

// Mencatat aktivitas login (sukses/gagal) untuk maintenance admin.
async function logLoginActivity({ user_id = null, username = null, status, reason = null, req = null }) {
  try {
    const ipAddress = (req && req.ip) ? String(req.ip) : null;
    const userAgent = (req && req.headers && req.headers['user-agent']) ? String(req.headers['user-agent']).slice(0, 500) : null;

    await run(
      `INSERT INTO login_activities (user_id, username, status, reason, ip_address, user_agent)
       VALUES ($1, $2, $3, $4, $5, $6)`,
      [user_id, username, status, reason, ipAddress, userAgent]
    );
  } catch (error) {
    console.error('[logLoginActivity] Gagal mencatat aktivitas:', error.message);
  }
}

async function initDatabase() {
  const initializeSchema = async () => {
    await run(`
      CREATE TABLE IF NOT EXISTS users (
        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
        username VARCHAR UNIQUE NOT NULL,
        password_hash VARCHAR NOT NULL,
        created_at TIMESTAMPTZ DEFAULT NOW()
      )
    `);

    await run(`
      CREATE TABLE IF NOT EXISTS modules (
        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
        code VARCHAR UNIQUE NOT NULL,
        name VARCHAR NOT NULL,
        url VARCHAR NOT NULL
      )
    `);

    await run(`
      CREATE TABLE IF NOT EXISTS user_modules (
        user_id UUID NOT NULL,
        module_id UUID NOT NULL,
        PRIMARY KEY (user_id, module_id),
FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (module_id) REFERENCES modules(id)
      )
    `);

    await run(`
      CREATE TABLE IF NOT EXISTS sessions (
        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
        user_id UUID,
        status VARCHAR NOT NULL DEFAULT 'pending',
        activation_code VARCHAR,
        activation_attempts INTEGER DEFAULT 0,
        captcha_id UUID,
        captcha_answer VARCHAR,
        refresh_token VARCHAR,
        refresh_expires_at TIMESTAMPTZ,
        expires_at TIMESTAMPTZ,
        created_at TIMESTAMPTZ DEFAULT NOW(),
        updated_at TIMESTAMPTZ DEFAULT NOW(),
        FOREIGN KEY (user_id) REFERENCES users(id)
      )
    `);

    await run(`
      CREATE TABLE IF NOT EXISTS login_activities (
        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
        user_id UUID,
        username VARCHAR,
        status VARCHAR NOT NULL,
        reason VARCHAR,
        ip_address VARCHAR,
        user_agent VARCHAR,
        created_at TIMESTAMPTZ DEFAULT NOW(),
        FOREIGN KEY (user_id) REFERENCES users(id)
      )
    `);

    // Indeks untuk mempercepat pencarian riwayat by username & waktu.
    await run(`CREATE INDEX IF NOT EXISTS idx_login_activities_username ON login_activities (username)`);
    await run(`CREATE INDEX IF NOT EXISTS idx_login_activities_created_at ON login_activities (created_at)`);

    const modules = [
      { code: 'SIMRS', name: 'SIMRS', url: 'https://rs-amino.jatengprov.go.id/login/' },
      { code: 'AMINO_MOBILE', name: 'AMINO Mobile', url: 'https://rs-amino.jatengprov.go.id/inovasi-amino-mobile/' },
      { code: 'LAPOR_AMINO', name: 'LAPOR AMINO', url: 'https://rs-amino.jatengprov.go.id/pengaduaninformasi-pasien/' },
      { code: 'WBS', name: 'WBS', url: 'https://rs-amino.jatengprov.go.id/sistem-pelaporan-pelanggaran-wbs/' }
    ];

    for (const m of modules) {
      await run(
        `INSERT INTO modules (id, code, name, url) VALUES ($1, $2, $3, $4)
         ON CONFLICT (code) DO NOTHING`,
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
      const existing = await get('SELECT id FROM users WHERE username = $1', [user.username]);
      if (!existing) {
        const passwordHash = await bcrypt.hash(user.password, 10);
        const userId = crypto.randomUUID();
        await run('INSERT INTO users (id, username, password_hash) VALUES ($1, $2, $3)', [userId, user.username, passwordHash]);

        for (const code of user.modules) {
          const module = await get('SELECT id FROM modules WHERE code = $1', [code]);
          if (module) {
            await run('INSERT INTO user_modules (user_id, module_id) VALUES ($1, $2) ON CONFLICT DO NOTHING', [userId, module.id]);
          }
        }
      }
    }
  };

  await initializeSchema();
  console.log('✅ Database PostgreSQL siap & ter-seed.');
}

async function getUserModules(userId) {
  return all(
    `SELECT m.code, m.name, m.url
     FROM user_modules um
     JOIN modules m ON m.id = um.module_id
     WHERE um.user_id = $1
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
      await logLoginActivity({ username, status: 'failed', reason: 'captcha_expired', req });
      return res.status(400).json({ success: false, message: 'Captcha kadaluarsa. Silakan muat ulang captcha.' });
    }

    if (String(captcha_answer).toLowerCase() !== String(captchaEntry.answer).toLowerCase()) {
      await logLoginActivity({ username, status: 'failed', reason: 'captcha_failed', req });
      return res.status(400).json({ success: false, message: 'Username, password, atau captcha tidak valid' });
    }

    // 2. Baru cek username/password.
    const user = await get('SELECT * FROM users WHERE username = $1', [username]);
    if (!user) {
      await logLoginActivity({ username, status: 'failed', reason: 'user_not_found', req });
      return res.status(401).json({ success: false, message: 'Username, password, atau captcha tidak valid' });
    }

    const isMatch = await bcrypt.compare(password, user.password_hash);
    if (!isMatch) {
      await logLoginActivity({ user_id: user.id, username, status: 'failed', reason: 'wrong_password', req });
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
       VALUES ($1, $2, 'active', $3, $4, $5)`,
      [sessionId, user.id, refreshToken, refreshExpiresAt, activeExpiresAt]
    );

const modulAkses = await getUserModules(user.id);

    await logLoginActivity({ user_id: user.id, username: user.username, status: 'success', req });

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

    const session = await get('SELECT * FROM sessions WHERE id = $1', [session_id]);
    if (!session) {
      return res.status(404).json({ success: false, message: 'Session tidak ditemukan' });
    }

    if (session.status !== 'pending') {
      return res.status(400).json({ success: false, message: `Session sudah berstatus ${session.status}` });
    }

    if (new Date() > new Date(session.expires_at)) {
      await run('UPDATE sessions SET status = $1 WHERE id = $2', ['expired', session.id]);
      return res.status(401).json({ success: false, message: 'Session kadaluarsa. Silakan login ulang.' });
    }

    if (session.activation_attempts >= MAX_ACTIVATION_ATTEMPTS) {
      await run('UPDATE sessions SET status = $1 WHERE id = $2', ['expired', session.id]);
      return res.status(429).json({ success: false, message: 'Terlalu banyak percobaan. Session dikunci.' });
    }

    const captchaMatch = session.captcha_id === captcha_id && String(captcha_answer).toLowerCase() === String(session.captcha_answer).toLowerCase();
    const codeMatch = String(activation_code) === String(session.activation_code);

    if (!captchaMatch || !codeMatch) {
      const newAttempts = Number(session.activation_attempts || 0) + 1;
      await run('UPDATE sessions SET activation_attempts = $1 WHERE id = $2', [newAttempts, session.id]);
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
           refresh_token = $1,
           refresh_expires_at = $2,
           expires_at = $3,
           updated_at = NOW()
       WHERE id = $4`,
      [refreshToken, refreshExpiresAt, activeExpiresAt, session.id]
    );

    const user = await get('SELECT * FROM users WHERE id = $1', [session.user_id]);
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

    const session = await get('SELECT * FROM sessions WHERE id = $1', [session_id]);
    if (!session) {
      return res.status(404).json({ success: false, message: 'Session tidak ditemukan' });
    }

    if (session.status === 'active' && new Date() > new Date(session.expires_at)) {
      await run('UPDATE sessions SET status = $1 WHERE id = $2', ['expired', session.id]);
      session.status = 'expired';
    }

    const user = await get('SELECT * FROM users WHERE id = $1', [session.user_id]);
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

    const session = await get('SELECT * FROM sessions WHERE refresh_token = $1 AND status = $2', [token, 'active']);
    if (!session) {
      return res.status(401).json({ success: false, valid: false, message: 'Token tidak valid atau session tidak aktif' });
    }

    if (new Date() > new Date(session.expires_at)) {
      await run('UPDATE sessions SET status = $1 WHERE id = $2', ['expired', session.id]);
      return res.status(401).json({ success: false, valid: false, message: 'Session expired' });
    }

    const user = await get('SELECT * FROM users WHERE id = $1', [session.user_id]);
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

    const session = await get('SELECT * FROM sessions WHERE refresh_token = $1', [refresh_token]);
    if (!session) {
      return res.status(401).json({ success: false, message: 'Refresh token tidak valid' });
    }

    if (new Date() > new Date(session.refresh_expires_at)) {
      await run('UPDATE sessions SET status = $1 WHERE id = $2', ['expired', session.id]);
      return res.status(401).json({ success: false, message: 'Refresh token kadaluarsa. Silakan login ulang.' });
    }

    const newActiveExpiresAt = new Date(Date.now() + SESSION_ACTIVE_MINUTES * 60 * 1000).toISOString();
    await run('UPDATE sessions SET status = $1, expires_at = $2, updated_at = NOW() WHERE id = $3', ['active', newActiveExpiresAt, session.id]);

    const user = await get('SELECT * FROM users WHERE id = $1', [session.user_id]);
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

    const result = await run('UPDATE sessions SET status = $1, refresh_token = NULL WHERE id = $2', ['expired', session_id]);
    if (result.changes === 0) {
      return res.status(404).json({ success: false, message: 'Session tidak ditemukan' });
    }

    return res.json({ success: true, message: 'Logout berhasil. Session dan token dinonaktifkan.' });
  } catch (error) {
    console.error('[/auth/logout] Error:', error);
    return res.status(500).json({ success: false, message: 'Terjadi kesalahan pada server' });
  }
});

// Endpoint admin untuk melihat riwayat activity login (maintenance).
// Query param opsional: ?limit=50 & ?username=admin_simrs & ?status=success
app.get('/admin/login-activities', async (req, res) => {
  try {
    const limit = Math.min(parseInt(req.query.limit) || 50, 500);
    const username = req.query.username ? String(req.query.username) : null;
    const status = req.query.status ? String(req.query.status) : null;

    const conditions = [];
    const params = [];

    if (username) {
      params.push(username);
      conditions.push(`username = $${params.length}`);
    }
    if (status) {
      params.push(status);
      conditions.push(`status = $${params.length}`);
    }

    const whereClause = conditions.length ? `WHERE ${conditions.join(' AND ')}` : '';
    params.push(limit);

    const rows = await all(
      `SELECT id, user_id, username, status, reason, ip_address, user_agent, created_at
       FROM login_activities
       ${whereClause}
       ORDER BY created_at DESC
       LIMIT $${params.length}`,
      params
    );

    const total = await get('SELECT COUNT(*)::int AS count FROM login_activities');

    return res.json({
      success: true,
      data: {
        total: total ? total.count : 0,
        limit,
        activities: rows
      }
    });
  } catch (error) {
    console.error('[/admin/login-activities] Error:', error);
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
      console.log('   POST   /auth/activate');
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
