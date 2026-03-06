'use strict';

const crypto = require('crypto');
const db = require('./db');

/**
 * Validate a character session.
 * Mirrors Laravel's SessionValidator::validateCharacter logic.
 *
 * @param {number|string} charId
 * @param {string}        sessionKey
 * @returns {Promise<{valid: boolean, character?: object, error?: string}>}
 */
async function validateSession(charId, sessionKey) {
  if (!charId || !sessionKey) {
    return { valid: false, error: 'Missing character_id or session_key' };
  }

  const id = parseInt(charId, 10);
  if (isNaN(id)) {
    return { valid: false, error: 'Invalid character_id' };
  }

  // Load character + owning user's session_key
  const row = await db.queryOne(
    `SELECT c.id, c.name, c.level, c.rank, c.gender,
            c.element_1, c.element_2, c.element_3,
            c.point_earth, c.point_water, c.point_wind, c.point_lightning, c.point_fire, c.point_free,
            c.equipment_weapon, c.equipment_back, c.equipment_accessory,
            c.equipment_skills, c.equipment_pet,
            c.talent_1, c.talent_2, c.talent_3,
            c.pvp_trophy, c.pvp_played, c.pvp_won, c.pvp_lost,
            c.hair_style, c.hair_color, c.skin_color,
            c.equipment_clothing,
            u.session_key
     FROM   characters c
     JOIN   users u ON u.id = c.user_id
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

  // Strip the session_key before handing data to the socket layer
  const { session_key: _sk, ...character } = row;
  return { valid: true, character };
}

function matchesAnyKey(ownerId, stored, provided) {
  // Exact match
  if (timingSafeEqual(stored, provided)) return true;

  // Base64-decoded form
  try {
    const decoded = Buffer.from(provided, 'base64').toString('utf8');
    if (timingSafeEqual(stored, decoded)) return true;
  } catch (_) {}

  // sha256(id + storedKey)
  const sha256 = crypto.createHash('sha256').update(String(ownerId) + stored).digest('hex');
  if (timingSafeEqual(sha256, provided)) return true;

  // md5(id + storedKey)
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
