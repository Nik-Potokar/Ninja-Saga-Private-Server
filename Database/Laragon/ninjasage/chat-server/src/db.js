'use strict';

const mysql  = require('mysql2/promise');
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
      connectionLimit:    10,
      queueLimit:         0,
    });
  }
  return pool;
}

async function query(sql, params = []) {
  const [rows] = await getPool().execute(sql, params);
  return rows;
}

async function queryOne(sql, params = []) {
  const rows = await query(sql, params);
  return rows[0] || null;
}

module.exports = { query, queryOne };
