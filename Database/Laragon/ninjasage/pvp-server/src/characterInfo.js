'use strict';

const StatsCalc = require('./battle/StatsCalc');

/**
 * Build the `Client.characterInfo` payload the Flash client expects.
 *
 * PvPLobby.as listens for this event and feeds the data to CharacterManager,
 * which populates:
 *   - pvp.character (used as auth check before entering a room)
 *   - nameTxt, txt_lvl, txt_hp, txt_cp, rankMC, elements, talents, outfit
 *
 * CharacterManager.fillCharacterData() maps the object's fields into the
 * internal format. The structure mirrors what the game servers emit.
 */
function buildCharacterInfoPayload(character) {
  const lvl    = parseInt(character.level,  10) || 1;
  const earth  = parseInt(character.point_earth,      10) || 0;
  const water  = parseInt(character.point_water,      10) || 0;
  const wind   = parseInt(character.point_wind,       10) || 0;
  const light  = parseInt(character.point_lightning,  10) || 0;
  const fire   = parseInt(character.point_fire,       10) || 0;
  const free   = parseInt(character.point_free,       10) || 0;

  // Stat formulas mirror StatManager.as exactly
  const maxHp    = 60 + lvl * 40 + earth * 30;
  const maxCp    = 60 + lvl * 40 + water * 30;
  const agility  = 9 + lvl + wind;
  const maxSp    = Math.max(0, 1000 + (lvl - 80) * 40);  // sage points, 0 below level 80

  // Equipment IDs
  const weapon    = character.equipment_weapon     || 'wpn_01';
  const backItem  = character.equipment_back       || 'back_01';
  const accessory = character.equipment_accessory  || 'accessory_01';
  const clothing  = character.equipment_clothing   || `set_01_${character.gender == 0 ? '0' : '1'}`;
  const hairstyle = formatHair(character);
  const face      = formatFace(character);
  const hairColor = character.hair_color  || '0|0';
  const skinColor = character.skin_color  || 'null|null';

  // Skills: equipment_skills is a comma-separated string (e.g. "skill_13,skill_39")
  const skills = character.equipment_skills || '';

  const charObj = {
    id:           character.id,
    name:         character.name,
    level:        lvl,
    rank:         parseInt(character.rank, 10) || 1,
    xp:           0,
    trophy:       character.pvp_trophy || 0,
    scrolls:      0,

    // Elements (integers: 1=fire 2=water 3=wind 4=lightning 5=earth)
    element_1:    parseInt(character.element_1, 10) || 0,
    element_2:    parseInt(character.element_2, 10) || 0,
    element_3:    parseInt(character.element_3, 10) || 0,

    // Talents (string IDs or null)
    talent_1:     character.talent_1 || null,
    talent_2:     character.talent_2 || null,
    talent_3:     character.talent_3 || null,

    // Class / senjutsu
    special_class: character.class    || null,
    senjutsu:      null,

    // Stats — mirrors what CharacterManager.getMaxHP/CP/SP read from character.stats
    stat: {
      hp:    maxHp,
      cp:    maxCp,
      maxHp,
      maxCp,
      sp:    maxSp,
      maxSp,
      agility,
    },

    // Elemental attribute points
    point: {
      wind:      wind,
      fire:      fire,
      lightning: light,
      water:     water,
      earth:     earth,
      free:      free,
    },

    // Equipment / cosmetic set
    set: {
      weapon,
      back_item:  backItem,
      accessory,
      clothing,
      hairstyle,
      face,
      hair_color: hairColor,
      skin_color: skinColor,
      skills,
      animations: [],
      talents:    '',
      senjutsus:  '',
    },
  };

  return { character: charObj };
}

function formatHair(char) {
  const suffix = char.gender == 0 ? '_0' : '_1';
  const h = char.hair_style;
  if (!h) return `hair_01${suffix}`;
  if (!isNaN(Number(h))) return `hair_${String(h).padStart(2, '0')}${suffix}`;
  return String(h);
}

function formatFace(char) {
  const suffix = char.gender == 0 ? '_0' : '_1';
  return `face_01${suffix}`;
}

module.exports = { buildCharacterInfoPayload };
