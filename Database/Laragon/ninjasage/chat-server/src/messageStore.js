'use strict';

const db     = require('./db');
const config = require('./config');

const CREATE_TABLE = `
CREATE TABLE IF NOT EXISTS chat_messages (
  id                BIGINT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  channel           VARCHAR(32)       NOT NULL,
  character_id      INT UNSIGNED      NOT NULL,
  character_name    VARCHAR(64)       NOT NULL,
  character_level   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  character_rank    TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  character_premium TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  message           TEXT              NOT NULL,
  created_at        TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_channel_id (channel, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`;

/**
 * Create the chat_messages table if it doesn't exist yet.
 * Must be called once at startup before load/save are used.
 */
async function init() {
  await db.query(CREATE_TABLE);
  console.log('[MessageStore] chat_messages table ready');
}

/**
 * Load the most recent maxHistory messages for a channel.
 * Returns them in chronological order (oldest first).
 */
async function load(channel) {
  try {
    const rows = await db.query(
      `SELECT character_id, character_name, character_level, character_rank, character_premium, message
         FROM chat_messages
        WHERE channel = ?
        ORDER BY id DESC
        LIMIT ${config.maxHistory}`,
      [channel]
    );
    return rows.reverse().map(r => ({
      character: {
        id:      r.character_id,
        name:    r.character_name,
        level:   r.character_level,
        rank:    r.character_rank,
        premium: r.character_premium,
      },
      message: r.message,
    }));
  } catch (err) {
    console.error(`[MessageStore] Failed to load channel "${channel}":`, err.message);
    return [];
  }
}

/**
 * Persist one message. Errors are logged, not thrown.
 */
async function save(channel, msg) {
  try {
    await db.query(
      `INSERT INTO chat_messages
         (channel, character_id, character_name, character_level, character_rank, character_premium, message)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [
        channel,
        msg.character.id,
        msg.character.name,
        msg.character.level,
        msg.character.rank,
        msg.character.premium,
        msg.message,
      ]
    );
  } catch (err) {
    console.error(`[MessageStore] Failed to save to channel "${channel}":`, err.message);
  }
}

module.exports = { init, load, save };
