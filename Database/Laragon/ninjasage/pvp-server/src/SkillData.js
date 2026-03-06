'use strict';

const fs   = require('fs');
const path = require('path');
const zlib = require('zlib');

// Path to the compiled game-data directory (one level up from pvp-server)
const BIN_DIR = path.resolve(__dirname, '../../bindata');

// Indexed maps populated by load()
const _skills  = {};   // skillId  -> { skill_damage, skill_cp_cost, skill_cooldown, skill_type }
const _effects = {};   // skillId  -> Array<EffectObject>

/**
 * Parse a zlib-compressed JSON .bin file.
 */
function _loadBin(filename) {
  const buf = fs.readFileSync(path.join(BIN_DIR, filename));
  let raw;
  try {
    raw = zlib.inflateSync(buf);
  } catch {
    raw = buf;   // not compressed, treat as plain JSON
  }
  return JSON.parse(raw);
}

/**
 * Call once at server startup to populate the skill lookup tables.
 */
function load() {
  const skillsArr  = _loadBin('skills.bin');
  const effectsArr = _loadBin('skill-effect.bin');

  for (const s of skillsArr) {
    _skills[s.id] = {
      skill_id:       s.id,
      skill_damage:   parseInt(s.damage,    10) || 0,
      skill_cp_cost:  parseInt(s.cp_cost,   10) || 50,
      skill_cooldown: parseInt(s.cooldown,  10) || 3,
      skill_type:     parseInt(s.type,      10) || 0,
    };
  }

  for (const e of effectsArr) {
    // Normalise each effect entry, filling in defaults used by the server
    _effects[e.skill_id] = (e.skill_effect || []).map(eff => ({
      target:      eff.target      || 'enemy',
      type:        eff.type        || 'Debuff',
      effect:      eff.effect      || '',
      effect_name: eff.effect_name || '',
      duration:    parseInt(eff.duration, 10)  || 0,
      calc_type:   eff.calc_type   || 'number',
      reduce_type: eff.reduce_type || 'CURRENT',
      amount:      parseFloat(eff.amount)      || 0,
      amount_hp:   parseFloat(eff.amount_hp)   || 0,
      amount_cp:   parseFloat(eff.amount_cp)   || 0,
      chance:      parseInt(eff.chance, 10)    ?? 100,
      passive:     eff.passive                 || false,
      no_disperse: eff.no_disperse             || false,
    }));
  }

  console.log(`[SkillData] loaded ${Object.keys(_skills).length} skills, ` +
              `${Object.keys(_effects).length} skill-effect entries`);
}

/**
 * Return skill metadata for the given skillId.
 * Falls back to safe defaults so callers never have to null-check.
 */
function getSkill(skillId) {
  return _skills[skillId] || {
    skill_id:       skillId,
    skill_damage:   0,
    skill_cp_cost:  50,
    skill_cooldown: 3,
    skill_type:     0,
  };
}

/**
 * Return the array of effect objects for the given skillId (may be empty).
 */
function getSkillEffects(skillId) {
  return _effects[skillId] || [];
}

module.exports = { load, getSkill, getSkillEffects };
