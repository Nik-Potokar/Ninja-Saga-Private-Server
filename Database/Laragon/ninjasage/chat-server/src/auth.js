'use strict';

const crypto = require('crypto');
const db     = require('./db');

/**
 * Validate a character session and return character data with clan info.
 */
async function validateSession(charId, sessionKey) {
  if (!charId || !sessionKey) {
    return { valid: false, error: 'Missing character_id or session_key' };
  }

  const id = parseInt(charId, 10);
  if (isNaN(id)) {
    return { valid: false, error: 'Invalid character_id' };
  }

  const row = await db.queryOne(
    `SELECT c.id, c.name, c.level, c.rank,
            u.account_type,
            u.session_key,
            cm.clan_id
     FROM   characters c
     JOIN   users u ON u.id = c.user_id
     LEFT JOIN clan_members cm ON cm.character_id = c.id
     WHERE  c.id = ?`,
    [id]
  );

  if (!row) {
    return { valid: false, error: 'Character not found' };
  }

  const provided = sessionKey.trim();
  const stored   = (row.session_key || '').trim();

  if (!matchesAnyKey(id, stored, provided)) {
    return { valid: false, error: 'Invalid session key' };
  }

  return {
    valid: true,
    character: {
      id:        row.id,
      name:      row.name,
      level:     row.level,
      rank:      row.rank,
      premium:   row.account_type || 0,
      clan_id:   row.clan_id || null,
    },
  };
}

function matchesAnyKey(ownerId, stored, provided) {
  if (timingSafeEqual(stored, provided)) return true;

  try {
    const decoded = Buffer.from(provided, 'base64').toString('utf8');
    if (timingSafeEqual(stored, decoded)) return true;
  } catch (_) {}

  const sha256 = crypto.createHash('sha256').update(String(ownerId) + stored).digest('hex');
  if (timingSafeEqual(sha256, provided)) return true;

  const md5 = crypto.createHash('md5').update(String(ownerId) + stored).digest('hex');
  if (timingSafeEqual(md5, provided)) return true;

  return false;
}

function timingSafeEqual(a, b) {
  if (a.length !== b.length) return false;
  try {
    return crypto.timingSafeEqual(Buffer.from(a), Buffer.from(b));
  } catch (_) {
    return false;
  }
}

module.exports = { validateSession };
