const express = require('express');
const cors = require('cors');
const jwt = require('jsonwebtoken');
const bcrypt = require('bcrypt');
const { Sequelize, DataTypes } = require('sequelize');
const fs = require('fs');
const crypto = require('crypto');
require('dotenv').config();

const app = express();
app.use(cors());
app.use(express.json());

// KONEKSI DATABASE (SQLite)
const sequelize = new Sequelize({
    dialect: 'sqlite',
    storage: './database.sqlite',
    logging: false
});

// MODEL USER
const User = sequelize.define('User', {
    id: { type: DataTypes.INTEGER, autoIncrement: true, primaryKey: true },
    username: { type: DataTypes.STRING, unique: true, allowNull: false },
    email: { type: DataTypes.STRING, unique: true, allowNull: false },
    password_hash: { type: DataTypes.STRING, allowNull: false },
    role: { type: DataTypes.STRING, defaultValue: 'user' },
    is_active: { type: DataTypes.BOOLEAN, defaultValue: true },
    modul_akses: { type: DataTypes.JSON, defaultValue: [] }
});

// MODEL SESSION
const Session = sequelize.define('Session', {
    id: { type: DataTypes.INTEGER, autoIncrement: true, primaryKey: true },
    user_id: { type: DataTypes.INTEGER, allowNull: false },
    access_token: { type: DataTypes.TEXT, allowNull: false },
    refresh_token: { type: DataTypes.TEXT, allowNull: false },
    is_active: { type: DataTypes.BOOLEAN, defaultValue: true },
    expires_at: { type: DataTypes.DATE, allowNull: false }
});

User.hasMany(Session, { foreignKey: 'user_id' });
Session.belongsTo(User, { foreignKey: 'user_id' });

// FUNGSI JWT
const PRIVATE_KEY = fs.readFileSync(process.env.PRIVATE_KEY_PATH, 'utf8');
const PUBLIC_KEY = fs.readFileSync(process.env.PUBLIC_KEY_PATH, 'utf8');

function generateAccessToken(user) {
    return jwt.sign(
        { sub: user.id, username: user.username, email: user.email, role: user.role },
        PRIVATE_KEY,
        { algorithm: 'RS256', expiresIn: process.env.ACCESS_TOKEN_EXPIRY, issuer: process.env.JWT_ISSUER, audience: process.env.JWT_AUDIENCE }
    );
}
function generateRefreshToken() { return crypto.randomBytes(64).toString('hex'); }
function verifyAccessToken(token) {
    return jwt.verify(token, PUBLIC_KEY, { algorithms: ['RS256'], issuer: process.env.JWT_ISSUER, audience: process.env.JWT_AUDIENCE });
}

// ENDPOINT 1: LOGIN
app.post('/auth/login', async (req, res) => {
    try {
        const { username, password } = req.body;
        if (!username || !password) return res.status(400).json({ success: false, message: 'Username dan password wajib diisi' });

        const user = await User.findOne({ where: { username } });
        if (!user) return res.status(401).json({ success: false, message: 'Username atau password salah' });

        const isMatch = await bcrypt.compare(password, user.password_hash);
        if (!isMatch) return res.status(401).json({ success: false, message: 'Username atau password salah' });
        if (!user.is_active) return res.status(403).json({ success: false, message: 'Akun dinonaktifkan' });

        const accessToken = generateAccessToken(user);
        const refreshToken = generateRefreshToken();
        const expiresAt = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);

        const session = await Session.create({ user_id: user.id, access_token: accessToken, refresh_token: refreshToken, expires_at: expiresAt });

        res.json({
            success: true,
            data: {
                access_token: accessToken,
                refresh_token: refreshToken,
                expires_in: 900,
                session_id: session.id,
                user: { username: user.username, email: user.email, role: user.role, modul_akses: user.modul_akses }
            }
        });
    } catch (error) {
        console.error(error);
        res.status(500).json({ success: false, message: 'Terjadi kesalahan pada server' });
    }
});

// ENDPOINT 2: VALIDATE
app.post('/auth/validate', async (req, res) => {
    try {
        const token = req.headers.authorization?.split(' ')[1];
        if (!token) return res.status(401).json({ success: false, valid: false, message: 'Token tidak ditemukan' });

        let decoded;
        try { decoded = verifyAccessToken(token); } catch (err) { return res.status(401).json({ success: false, valid: false, message: err.message }); }

        const session = await Session.findOne({ where: { access_token: token, is_active: true } });
        if (!session) return res.status(401).json({ success: false, valid: false, message: 'Session tidak aktif' });

        const user = await User.findByPk(session.user_id);
        if (!user || !user.is_active) return res.status(403).json({ success: false, valid: false, message: 'Akun tidak aktif' });

        res.json({ success: true, valid: true, data: { user: { username: user.username, email: user.email, role: user.role }, session_id: session.id } });
    } catch (error) {
        console.error(error);
        res.status(500).json({ success: false, valid: false, message: 'Terjadi kesalahan pada server' });
    }
});

// ENDPOINT 3: REFRESH
app.post('/auth/refresh', async (req, res) => {
    try {
        const { refresh_token } = req.body;
        if (!refresh_token) return res.status(400).json({ success: false, message: 'Refresh token wajib diisi' });

        const session = await Session.findOne({ where: { refresh_token: refresh_token, is_active: true } });
        if (!session) return res.status(401).json({ success: false, message: 'Refresh token tidak valid' });
        if (new Date() > session.expires_at) { await session.update({ is_active: false }); return res.status(401).json({ success: false, message: 'Refresh token kadaluarsa' }); }

        const user = await User.findByPk(session.user_id);
        if (!user || !user.is_active) return res.status(403).json({ success: false, message: 'Akun tidak aktif' });

        const newAccessToken = generateAccessToken(user);
        await session.update({ access_token: newAccessToken });

        res.json({ success: true, data: { access_token: newAccessToken, refresh_token: refresh_token, expires_in: 900, session_id: session.id } });
    } catch (error) {
        console.error(error);
        res.status(500).json({ success: false, message: 'Terjadi kesalahan pada server' });
    }
});

// ENDPOINT 4: REVOKE (LOGOUT)
app.post('/auth/revoke', async (req, res) => {
    try {
        const token = req.headers.authorization?.split(' ')[1];
        if (!token) return res.status(400).json({ success: false, message: 'Token tidak ditemukan' });

        const result = await Session.update({ is_active: false }, { where: { access_token: token } });
        if (result[0] === 0) return res.status(404).json({ success: false, message: 'Session tidak ditemukan' });

        res.json({ success: true, message: 'Logout berhasil' });
    } catch (error) {
        console.error(error);
        res.status(500).json({ success: false, message: 'Terjadi kesalahan pada server' });
    }
});

// ENDPOINT 5: GET SESSION
app.get('/auth/session', async (req, res) => {
    try {
        const token = req.headers.authorization?.split(' ')[1];
        if (!token) return res.status(401).json({ success: false, message: 'Token tidak ditemukan' });

        const session = await Session.findOne({ where: { access_token: token, is_active: true }, include: [{ model: User, attributes: ['username', 'email', 'role', 'modul_akses'] }] });
        if (!session) return res.status(404).json({ success: false, message: 'Session tidak ditemukan' });

        res.json({ success: true, data: { session_id: session.id, user: session.User, created_at: session.createdAt, expires_at: session.expires_at } });
    } catch (error) {
        console.error(error);
        res.status(500).json({ success: false, message: 'Terjadi kesalahan pada server' });
    }
});

// SEEDER (BUAT USER ADMIN)
(async () => {
    try {
        await sequelize.sync({ force: true });
        console.log('✅ Database SQLite siap.');

        const adminExists = await User.findOne({ where: { username: 'admin' } });
        if (!adminExists) {
            const hashed = await bcrypt.hash('12#56*DS', parseInt(process.env.BCRYPT_SALT_ROUNDS) || 10);
            await User.create({ username: 'admin', email: 'admin@contoh.com', password_hash: hashed, role: 'admin', modul_akses: ['dashboard', 'users', 'laporan'] });
            console.log('✅ User ADMIN dibuat (username: admin, password: 12#56*DS)');
        }
        console.log('🚀 Server siap.');
    } catch (error) {
        console.error('❌ Gagal inisialisasi DB:', error);
        process.exit(1);
    }
})();

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`✅ SSO Backend berjalan di http://localhost:${PORT}`);
    console.log(`📋 Endpoint: /auth/login, /auth/validate, /auth/refresh, /auth/revoke, /auth/session`);
});