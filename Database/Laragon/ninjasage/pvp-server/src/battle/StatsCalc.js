'use strict';

const SkillData = require('../SkillData');

/**
 * Server-side stat calculator.
 * Mirrors the formulas in the client's StatManager.as / EffectsManager.as.
 *
 * HP        = 60 + level * 40  + point_earth     * 30
 * CP        = 60 + level * 40  + point_water      * 30
 * agility   = 9  + level       + point_wind
 * critical% = 5  + point_lightning * 0.4
 * dodge%    = 5  + point_wind  * 0.4
 *
 * ATK / DEF are derived server-side so that weapon attacks scale sensibly.
 * Skill damage uses the raw skill_damage value from SkillLibrary (skills.bin)
 * boosted by the attacker's elemental attributes matching the skill's type,
 * mirroring the client's EffectsManager.increaseDamage() logic.
 */
function buildStats(char) {
  const lvl        = parseInt(char.level,           10) || 1;
  const earth      = parseInt(char.point_earth,     10) || 0;
  const water      = parseInt(char.point_water,     10) || 0;
  const wind       = parseInt(char.point_wind,      10) || 0;
  const lightning  = parseInt(char.point_lightning, 10) || 0;
  const fire       = parseInt(char.point_fire,      10) || 0;

  const maxHp  = 60 + lvl * 40 + earth * 30;
  const maxCp  = 60 + lvl * 40 + water * 30;
  const agility  = 9 + lvl + wind;
  const critPct  = parseFloat((5 + lightning * 0.4).toFixed(1));   // %
  const dodgePct = parseFloat((5 + wind      * 0.4).toFixed(1));   // %

  // Attack power: fire and lightning are offensive elements
  const atk = Math.floor(lvl * 5 + (fire + lightning) * 2);

  // Defense: earth and water are defensive elements
  const def = Math.floor(lvl * 2 + (earth + water));

  return {
    maxHp,
    maxCp,
    hp:       maxHp,
    cp:       maxCp,
    agility,
    critPct,
    dodgePct,
    atk,
    def,
    // Raw elemental points – kept for skill-type damage bonuses
    fire,
    lightning,
    wind,
    earth,
    water,
  };
}

/**
 * Compute effective dodge% for a defender, factoring in active buff/debuff modifiers.
 *   - Dodge-increasing buffs on the defender add to dodge%.
 *   - Dodge-decreasing debuffs on the defender subtract from dodge%.
 *   - Accuracy-decreasing debuffs on the attacker effectively increase dodge%.
 * Capped at [0, 95] so a hit is always possible.
 */
function calcEffectiveDodge(defenderStats, attackerStats) {
  let dodge = defenderStats.dodgePct;

  const DODGE_UP = new Set([
    'reflexes', 'flexible', 'energize', 'pet_energize',
    'evade', 'overwhelm', 'meditation', 'peace', 'invincible',
  ]);
  const DODGE_DOWN = new Set([
    'darkness', 'blind', 'pet_blind', 'slow', 'muddy',
    'embrace', 'dark_curse',
  ]);
  // These debuffs on the attacker reduce accuracy (= defender dodges more)
  const ACCURACY_DOWN = new Set(['darkness', 'blind', 'pet_blind']);

  for (const b of (defenderStats.activeBuffs || [])) {
    if (b.duration <= 0) continue;
    if (b.type === 'Buff'   && DODGE_UP.has(b.effect)   && b.calc_type === 'percent') dodge += b.amount;
    if (b.type === 'Debuff' && DODGE_DOWN.has(b.effect) && b.calc_type === 'percent') dodge -= b.amount;
  }
  for (const b of (attackerStats.activeBuffs || [])) {
    if (b.duration <= 0) continue;
    if (b.type === 'Debuff' && ACCURACY_DOWN.has(b.effect) && b.calc_type === 'percent') dodge += b.amount;
  }

  return Math.max(0, Math.min(95, dodge));
}

/**
 * Calculate weapon-attack damage.
 * Returns { damage, crit, dodged }.
 */
function calcWeaponDamage(attackerStats, defenderStats) {
  // Check dodge first (factoring in active buff/debuff modifiers)
  if (Math.random() * 100 < calcEffectiveDodge(defenderStats, attackerStats)) {
    return { damage: 0, crit: false, dodged: true };
  }

  // Base damage with ±20% variance
  const variance = 0.8 + Math.random() * 0.4;
  let damage = Math.floor(attackerStats.atk * variance);

  // Defense reduction (diminishing returns)
  const defReduction = Math.floor(defenderStats.def * 0.5);
  damage = Math.max(1, damage - defReduction);

  // Apply active buff/debuff modifiers
  damage = applyDamageModifiers(damage, attackerStats, defenderStats);

  // Critical hit
  const crit = Math.random() * 100 < attackerStats.critPct;
  if (crit) {
    const critMult = 1 + (50 + attackerStats.lightning * 0.8) / 100;
    damage = Math.floor(damage * critMult);
  }

  return { damage, crit, dodged: false };
}

/**
 * Calculate skill damage.
 *
 * Uses the actual skill_damage value from skills.bin as the base, then applies
 * elemental multipliers that mirror the client's
 * EffectsManager.increaseFromPassiveEffects_Element_Multiplicative():
 *
 *   - Fire always adds (fire * 0.4)% multiplicatively.
 *   - The skill's type adds the matching element's points as an extra % bonus:
 *       type 1 → wind%, type 2 → fire%, type 3 → lightning%,
 *       type 4 → earth%, type 5 → water%
 *
 * Returns { damage, crit, dodged }.
 */
function calcSkillDamage(attackerStats, defenderStats, skillId) {
  const skill     = SkillData.getSkill(skillId);
  const skillType = skill.skill_type;

  // Skills are harder to dodge (50 % of normal dodge chance)
  if (Math.random() * 100 < calcEffectiveDodge(defenderStats, attackerStats) * 0.5) {
    return { damage: 0, crit: false, dodged: true };
  }

  // Base damage: use skill_damage from data; fall back to atk-based estimate
  const base = skill.skill_damage > 0 ? skill.skill_damage : Math.floor(attackerStats.atk * 0.5);

  // Elemental multiplier – mirrors client EffectsManager logic
  let elementMult = 1.0;
  elementMult += (attackerStats.fire * 0.4) / 100;   // fire passive always applies

  switch (skillType) {
    case 1: elementMult += attackerStats.wind      / 100; break;
    case 2: elementMult += attackerStats.fire      / 100; break;
    case 3: elementMult += attackerStats.lightning / 100; break;
    case 4: elementMult += attackerStats.earth     / 100; break;
    case 5: elementMult += attackerStats.water     / 100; break;
  }

  // ±15% variance
  const variance = 0.85 + Math.random() * 0.3;
  let damage = Math.floor(base * variance * elementMult);

  // Defense reduction (skills penetrate more than weapon attacks)
  const defReduction = Math.floor(defenderStats.def * 0.3);
  damage = Math.max(1, damage - defReduction);

  // Apply active buff/debuff modifiers
  damage = applyDamageModifiers(damage, attackerStats, defenderStats);

  // Critical hit (base 50 % bonus + extra from lightning, mirrors calculateCriticalDamage)
  const crit = Math.random() * 100 < attackerStats.critPct;
  if (crit) {
    const critMult = 1 + (50 + attackerStats.lightning * 0.8) / 100;
    damage = Math.floor(damage * critMult);
  }

  return { damage, crit, dodged: false };
}

/**
 * Apply damage-modifying buffs/debuffs stored in attackerStats.activeBuffs
 * and defenderStats.activeBuffs.
 *
 * Both stats objects carry an optional `activeBuffs` array that Battle.js
 * populates from participant.buffs before passing to the calc functions.
 *
 * Damage-increasing attacker buffs: strengthen, power_up, rage, rampage,
 *   solar_might, invincible, unyielding, sage_mode, stealth, lightning_armor,
 *   domain_expansion
 * Damage-reducing defender buffs: protection, defense_up, turtle
 * Damage-increasing defender debuffs (attackers deal more): weaken
 */
function applyDamageModifiers(damage, attackerStats, defenderStats) {
  const DAMAGE_UP_EFFECTS = new Set([
    'strengthen', 'power_up', 'rage', 'rampage', 'solar_might',
    'invincible', 'unyielding', 'sage_mode', 'stealth',
    'lightning_armor', 'domain_expansion', 'pet_strengthen',
    'taijutsu_strengthen', 'senjutsu_strengthen',
    'concentration',    // skill-damage increase buff (40%)
    'attention',        // accuracy/damage buff (30%)
  ]);
  const DAMAGE_DOWN_EFFECTS = new Set([
    'protection', 'defense_up', 'turtle', 'absorb_damage',
    'plus_protection', 'tolerance',
  ]);
  const WEAKEN_EFFECTS = new Set(['weaken', 'conduction', 'vulnerable', 'infection']);
  // Debuffs applied to the attacker that reduce their outgoing damage
  const ATTACKER_DEBUFF_DOWN = new Set([
    'numb', 'disorient', 'dark_curse', 'embrace', 'holdback', 'ecstasy',
  ]);

  let multiplier = 1.0;

  // Attacker's damage-up buffs and damage-down debuffs
  for (const b of (attackerStats.activeBuffs || [])) {
    if (b.duration > 0 && b.type === 'Buff' && DAMAGE_UP_EFFECTS.has(b.effect)) {
      if (b.calc_type === 'percent') multiplier += b.amount / 100;
    }
    if (b.duration > 0 && b.type === 'Debuff' && ATTACKER_DEBUFF_DOWN.has(b.effect)) {
      if (b.calc_type === 'percent') multiplier -= b.amount / 100;
    }
    // lock: when HP >= 50% acts as a weaken (damage-down debuff on the attacker)
    if (b.duration > 0 && b.effect === 'lock' && b.type === 'Debuff') {
      if (attackerStats.hp >= attackerStats.maxHp * 0.5 && b.calc_type === 'percent') {
        multiplier -= b.amount / 100;
      }
    }
  }

  // Defender's damage-reduction buffs and amplifying debuffs
  for (const b of (defenderStats.activeBuffs || [])) {
    if (b.duration > 0 && b.type === 'Buff' && DAMAGE_DOWN_EFFECTS.has(b.effect)) {
      if (b.calc_type === 'percent') multiplier -= b.amount / 100;
    }
    if (b.duration > 0 && b.type === 'Debuff' && WEAKEN_EFFECTS.has(b.effect)) {
      if (b.calc_type === 'percent') multiplier += b.amount / 100;
    }
  }

  return Math.max(1, Math.floor(damage * Math.max(0, multiplier)));
}

/**
 * Real CP cost for a skill, looked up from skills.bin.
 */
function skillCpCost(skillId) {
  return SkillData.getSkill(skillId).skill_cp_cost;
}

/**
 * Real cooldown (in turns) for a skill, looked up from skills.bin.
 */
function getSkillCooldown(skillId) {
  return SkillData.getSkill(skillId).skill_cooldown;
}

/**
 * CP gained per charge action.
 */
function chargeAmount(attackerStats) {
  return Math.floor(attackerStats.maxCp * 0.2);
}

module.exports = {
  buildStats,
  calcEffectiveDodge,
  calcWeaponDamage,
  calcSkillDamage,
  skillCpCost,
  getSkillCooldown,
  chargeAmount,
  applyDamageModifiers,
};
