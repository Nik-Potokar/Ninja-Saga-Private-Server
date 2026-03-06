'use strict';

const mysql = require('mysql2/promise');
const config = require('./config');

let pool = null;

function getPool() {
  if (!pool) {
    pool = mysql.createPool({
      host:               config.db.host,
      port:               config.db.port,
      database:           config.db.database,
      user:               config.db.user,
      password:           config.db.password,
      waitForConnections: true,
      connectionLimit:    20,
      queueLimit:         0,
    });
  }
  return pool;
}

/**
 * Run a query and return rows.
 * @param {string} sql
 * @param {any[]} params
 * @returns {Promise<any[]>}
 */
async function query(sql, params = []) {
  const [rows] = await getPool().execute(sql, params);
  return rows;
}

/**
 * Run a query and return the first row (or null).
 */
async function queryOne(sql, params = []) {
  const rows = await query(sql, params);
  return rows[0] || null;
}

// ─── Game config cache ─────────────────────────────────────────────────────────
let _settingsCache = null;
let _settingsCacheAt = 0;
const SETTINGS_TTL_MS = 60_000; // refresh every 60 s

/**
 * Read a JSON config value from the game_configs table with a 60-second cache.
 * Falls back to `defaultValue` if the key is absent or the DB is unreachable.
 */
async function getGameConfig(key, defaultValue = null) {
  const now = Date.now();

  if (!_settingsCache || (now - _settingsCacheAt) > SETTINGS_TTL_MS) {
    try {
      const rows = await query('SELECT `key`, `value` FROM game_configs');
      const map = {};
      for (const row of rows) {
        try { map[row.key] = JSON.parse(row.value); } catch { map[row.key] = row.value; }
      }
      _settingsCache = map;
      _settingsCacheAt = now;
    } catch (err) {
      console.error('[DB] getGameConfig cache refresh failed:', err.message);
      // Keep stale cache (or empty) rather than crashing
      if (!_settingsCache) _settingsCache = {};
    }
  }

  return key in _settingsCache ? _settingsCache[key] : defaultValue;
}

module.exports = { query, queryOne, getPool, getGameConfig };
