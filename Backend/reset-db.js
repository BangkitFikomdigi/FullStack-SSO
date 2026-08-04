const fs = require('fs');
const path = require('path');
const sqlite3 = require('sqlite3').verbose();

const dbPath = path.join(__dirname, 'database.sqlite');
if (fs.existsSync(dbPath)) fs.unlinkSync(dbPath);

const db = new sqlite3.Database(dbPath);

db.serialize(() => {
  db.run(`CREATE TABLE users (
    id TEXT PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
  )`);

  db.run(`CREATE TABLE modules (
    id TEXT PRIMARY KEY,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    url TEXT NOT NULL
  )`);

  db.run(`CREATE TABLE user_modules (
    user_id TEXT,
    module_id TEXT,
    PRIMARY KEY (user_id, module_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (module_id) REFERENCES modules(id)
  )`);

  db.run(`CREATE TABLE sessions (
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
  )`);

  db.close(() => {
    console.log('Database reset OK:', dbPath);
  });
});
