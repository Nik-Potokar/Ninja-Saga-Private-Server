'use strict';

module.exports = {
  port: parseInt(process.env.PORT || '3002', 10),

  db: {
    host:     process.env.DB_HOST || '127.0.0.1',
    port:     parseInt(process.env.DB_PORT || '3306', 10),
    database: process.env.DB_NAME || 'ninjasage',
    user:     process.env.DB_USER || 'root',
    password: process.env.DB_PASS || '',
  },

  // Shared secret for the /admin/announce HTTP endpoint.
  // Must match CHAT_ADMIN_SECRET in the Laravel .env.
  adminSecret: process.env.CHAT_ADMIN_SECRET || '',

  // Maximum messages retained per channel
  maxHistory: 200,

  // Minimum seconds between messages from one user
  messageCooldownMs: 3000,

  // Maximum message length in characters
  maxMessageLength: 500,
};