'use strict';

const { v4: uuidv4 } = require('uuid');
const config    = require('../config');
const StatsCalc = require('./StatsCalc');
const TrophyCalc = require('./TrophyCalc');
const SkillData = require('../SkillData');
const { buildCharacterInfoPayload } = require('../characterInfo');
const db        = require('../db');

const TURN_DURATION_MS = config.turnDuration * 1000;

// Effects that cause the affected participant to lose their entire turn
// NOTE: 'restriction' is intentionally NOT here — it only blocks skills, not weapon/charge.
// NOTE: 'lock' is handled dynamically in _hasSkipEffect (conditional on HP%).
const SKIP_TURN_EFFECTS = new Set([
  'stun', 'sleep', 'frozen', 'paralysis', 'petrify', 'prison',
]);

// Effects that block skill usage (weapon and charge still allowed)
const SKILL_BLOCK_EFFECTS = new Set(['restriction', 'meridian_seal']);

// Effects that block charge usage (skill and weapon still allowed)
// Note: meridian_seal blocks both skills AND charge (weapon only)
const CHARGE_BLOCK_EFFECTS = new Set(['meridian_seal']);

// Effects that block weapon attacks
const WEAPON_BLOCK_EFFECTS = new Set(['dismantle']);

// Debuffs that cannot be resisted by debuff_resist
const DEBUFF_RESIST_EXEMPT = new Set(['disperse', 'drain_cp', 'drain_hp', 'oblivion']);

// Effects that deal damage each turn the debuff is active
const DOT_EFFECTS = new Set([
  'burn', 'burning', 'burningX', 'blaze', 'flaming',
  'poison', 'bleed', 'bleeding', 'combustion', 'conduction',
  'reduce_hp', 'reduce_hp_cp',
  'theft',   // drain per turn; stolen HP transferred to opponent in _applyPerTurnEffects
]);

// Effects that restore HP each turn
const HOT_EFFECTS = new Set(['heal', 'regenHP', 'regenerate', 'restoration', 'peace']);

// Effects that restore CP each turn
const COT_EFFECTS = new Set(['restoration', 'peace']);

// Effects that block ALL healing (HoT and instant heals)
const HEAL_BLOCK_EFFECTS = new Set(['internal_injury', 'hanyaoni']);

// Effects that blind (reduce dodge of the affected player)
const BLIND_EFFECTS = new Set(['blind']);

/**
 * Represents a live PvP battle between two characters.
 *
 * Participants are stored in `this.host` / `this.enemy` objects:
 *   { socket, character, stats, skills, cooldowns, buffs }
 *
 * Turn flow:
 *   1. ambush() -> emits Battle.action.ambush to active participant
 *   2. Client emits Battle.action.{weapon|skill|dodge|charge|scroll|run|timeout}
 *   3. processAction() handles it, broadcasts result to both + spectators
 *   4. Server waits for Battle.action.finished from acting client
 *   5. Repeat until battle ends
 */
class Battle {
  /**
   * @param {object} opts
   * @param {object} opts.host    - { socket, character }
   * @param {object} opts.enemy   - { socket, character }
   * @param {object} opts.room    - room info { mode, stage, allowScrolls }
   * @param {Function} opts.onEnd - called with (battle) when battle finishes
   */
  constructor({ host, enemy, room, onEnd }) {
    this.id = uuidv4();
    this.mode  = room.mode  || 'ranked';
    this.stage = room.stage || 'mission_1011';
    this.allowScrolls = !!room.allowScrolls;
    this.onEnd = onEnd;

    this.spectators = new Set();   // socket ids
    this._spectatorSockets = new Map(); // socketId -> socket

    this.round   = 0;
    this.running = false;
    this._turnTimer = null;
    this._awaitingFinished = false;  // waiting for Battle.action.finished
    this._animationTimer   = null;   // watchdog: fires if finished never arrives
    this._actionLog = [];            // replay log

    this.host  = this._buildParticipant(host.socket,  host.character);
    this.enemy = this._buildParticipant(enemy.socket, enemy.character);

    // Determine who goes first (highest agility; tie -> host)
    this._activeParticipant = this.host.stats.agility >= this.enemy.stats.agility
      ? this.host
      : this.enemy;

    this._bindSocketListeners(this.host);
    this._bindSocketListeners(this.enemy);
  }

  // ─────────────────────────────────────────────────────
  //  Setup helpers
  // ─────────────────────────────────────────────────────

  _buildParticipant(socket, character) {
    const stats   = StatsCalc.buildStats(character);
    const skills  = this._parseSkills(character.equipment_skills);
    const scrolls = [];

    return {
      socket,
      character,
      stats,
      skills,          // array of skill_id strings
      cooldowns: {},   // skillId -> turns remaining
      buffs: [],       // active buff/debuff effects  { effect, type, duration, amount, calc_type, effect_name }
      scrolls,
      isDodging: false,
      isCharged: false,
    };
  }

  _parseSkills(equipmentSkills) {
    if (!equipmentSkills) return [];
    return equipmentSkills
      .split(',')
      .map(s => s.trim())
      .filter(Boolean);
  }

  _bindSocketListeners(participant) {
    const { socket } = participant;
    const battleId = this.id;

    socket.on('Battle.action.weapon',  (data) => {
      if (!this.running || data.battle_id !== battleId) return;
      if (this._activeParticipant !== participant) return;
      this._handleWeapon(participant);
    });

    socket.on('Battle.action.skill', (data) => {
      if (!this.running || data.battle_id !== battleId) return;
      if (this._activeParticipant !== participant) return;
      this._handleSkill(participant, data.skillId || data.skill_id);
    });

    socket.on('Battle.action.dodge', (data) => {
      if (!this.running || data.battle_id !== battleId) return;
      if (this._activeParticipant !== participant) return;
      this._handleDodge(participant);
    });

    socket.on('Battle.action.charge', (data) => {
      if (!this.running || data.battle_id !== battleId) return;
      if (this._activeParticipant !== participant) return;
      this._handleCharge(participant);
    });

    socket.on('Battle.action.scroll', (data) => {
      if (!this.running || data.battle_id !== battleId) return;
      if (this._activeParticipant !== participant) return;
      this._handleScroll(participant, data.scroll_id);
    });

    socket.on('Battle.action.run', (data) => {
      if (!this.running || data.battle_id !== battleId) return;
      if (this._activeParticipant !== participant) return;
      this._handleRun(participant);
    });

    socket.on('Battle.action.timeout', (data) => {
      if (!this.running || data.battle_id !== battleId) return;
      if (this._activeParticipant !== participant) return;
      this._handleTimeout(participant);
    });

    socket.on('Battle.action.finished', (data) => {
      if (!this.running || data.battle_id !== battleId) return;
      if (!this._awaitingFinished) return;
      this._awaitingFinished = false;
      this._stopAnimationTimer();
      this._nextTurn();
    });

    socket.on('Battle.getPlayerInfo', (data) => {
      if (data.battle_id !== battleId) return;
      const charId = parseInt(data.char_id, 10);
      const participant = this.host.character.id == charId ? this.host
                        : this.enemy.character.id == charId ? this.enemy
                        : null;
      if (!participant) return;
      socket.emit('Battle.getPlayerInfo', this._buildPlayerInfoPayload(participant.character));
    });

    socket.on('Conversation.battle.sendMessage', (data) => {
      if (data.battle_id !== battleId) return;
      this._broadcastBattleChat(socket, data.message);
    });
  }

  // ─────────────────────────────────────────────────────
  //  Public interface
  // ─────────────────────────────────────────────────────

  start() {
    this.running = true;
    this.round   = 0;

    const payload = {
      battleId:   this.id,
      background: this.stage,
      hostId:     this.host.character.id,
      enemyId:    this.enemy.character.id,
    };

    this.host.socket.emit('Battle.started',  { ...payload, isHost: true  });
    this.enemy.socket.emit('Battle.started', { ...payload, isHost: false });

    this._broadcastSpectators('Battle.started', payload);

    // Send initial skill info so tooltips and cooldown display work from turn 1
    this._sendUpdateInfo(this.host);
    this._sendUpdateInfo(this.enemy);

    // Slight delay before first turn so clients have time to load
    setTimeout(() => this._nextTurn(), 1000);
  }

  addSpectator(socket) {
    this.spectators.add(socket.id);
    this._spectatorSockets.set(socket.id, socket);
    socket.emit('Battle.spectator.count', this.spectators.size);
    this._broadcastParticipants('Battle.spectator.count', this.spectators.size);
  }

  removeSpectator(socketId) {
    this.spectators.delete(socketId);
    this._spectatorSockets.delete(socketId);
    this._broadcastParticipants('Battle.spectator.count', this.spectators.size);
  }

  handleDisconnect(socket) {
    if (!this.running) return;

    const participant = this._getParticipant(socket.charId);
    if (participant) {
      // Disconnected participant loses
      const winner = participant === this.host ? this.enemy : this.host;
      this._endBattle(winner, 'disconnect');
    } else {
      // Spectator disconnected
      this.removeSpectator(socket.id);
    }
  }

  // ─────────────────────────────────────────────────────
  //  Turn management
  // ─────────────────────────────────────────────────────

  _nextTurn() {
    if (!this.running) return;
    this._stopTurnTimer();

    this.round++;
    if (this.round > config.maxRounds) {
      // Draw — whoever has more HP wins
      const winner = this.host.stats.hp >= this.enemy.stats.hp
        ? this.host
        : this.enemy;
      this._endBattle(winner, 'timeout');
      return;
    }

    // Tick cooldowns for the active participant then notify their client
    this._tickCooldowns(this._activeParticipant);
    this._sendUpdateInfo(this._activeParticipant);

    const opponent = this._getOpponent(this._activeParticipant);

    // Apply per-turn buff/debuff effects (DoT, HoT, CoT) for the ACTIVE participant only.
    // Effects tick once per the affected player's own turn — ticking both players every
    // half-turn would halve all durations (a 3-turn stun would last only 1 stunned turn).
    const turnOverlays = this._applyPerTurnEffects(this._activeParticipant, opponent);

    // Capture skip-effect state BEFORE decrementing durations so that a buff
    // with duration=1 still causes one full forced-skip before being removed.
    const shouldSkip    = this._hasSkipEffect(this._activeParticipant);
    const skipEffectName = shouldSkip
      ? this._getActiveSkipEffectName(this._activeParticipant)
      : null;

    // Tick buff durations for the active participant only
    this._tickBuffs(this._activeParticipant);

    // Broadcast per-turn stat changes if any
    if (turnOverlays.length) {
      this._broadcastAll('Battle.updateInfo', {
        stats: [
          { id: this._activeParticipant.character.id, stat: { hp: this._activeParticipant.stats.hp, cp: this._activeParticipant.stats.cp } },
          { id: opponent.character.id,                stat: { hp: opponent.stats.hp,                cp: opponent.stats.cp                } },
        ],
        overlays: turnOverlays,
      });
    }

    // Check for deaths caused by DoT/HoT
    if (this._activeParticipant.stats.hp <= 0 || opponent.stats.hp <= 0) {
      const winner = this._activeParticipant.stats.hp > 0
        ? this._activeParticipant
        : opponent;
      this._scheduleEndAfterAnimation(winner);
      return;
    }

    // Check if the active participant is stunned/sleeping → forced skip
    if (shouldSkip) {
      const skipPayload = {
        id:          this._activeParticipant.character.id,
        action:      'skip',
        effect_name: skipEffectName,
      };
      this._broadcastAll('Battle.action.skip', skipPayload);
      this._awaitingFinished = false;
      this._switchTurn();
      // Proceed directly to the next turn (no timer needed since no player input)
      setImmediate(() => this._nextTurn());
      return;
    }

    // Emit ambush to tell clients whose turn it is
    const ambushPayload = { id: this._activeParticipant.character.id };
    this._broadcastAll('Battle.action.ambush', ambushPayload);

    // chaos: participant cannot choose freely — server forces weapon or charge at random
    if (this._hasActiveEffect(this._activeParticipant, 'chaos')) {
      const forcedActor = this._activeParticipant;
      const forcedAction = Math.random() < 0.5 ? 'weapon' : 'charge';
      setImmediate(() => {
        if (!this.running || this._activeParticipant !== forcedActor) return;
        if (forcedAction === 'weapon') this._handleWeapon(forcedActor);
        else                           this._handleCharge(forcedActor);
      });
      return; // don't start the player-input timer
    }

    this._startTurnTimer(this._activeParticipant);
  }

  _startTurnTimer(participant) {
    this._turnTimer = setTimeout(() => {
      if (!this.running) return;
      if (this._activeParticipant === participant) {
        this._handleTimeout(participant);
      }
    }, TURN_DURATION_MS + 2000);   // +2s grace for network latency
  }

  _stopTurnTimer() {
    if (this._turnTimer) {
      clearTimeout(this._turnTimer);
      this._turnTimer = null;
    }
  }

  /**
   * Start a watchdog timer for the animation phase.
   * If Battle.action.finished never arrives (e.g. client crash / stall),
   * this fires and forces _nextTurn() so the game is never permanently stuck.
   * Timeout = full turn duration + generous 10 s buffer for slow animations.
   */
  _startAnimationTimer() {
    this._stopAnimationTimer();
    this._animationTimer = setTimeout(() => {
      if (!this.running || !this._awaitingFinished) return;
      console.warn(`[Battle ${this.id}] animation watchdog fired – forcing next turn`);
      this._awaitingFinished = false;
      this._nextTurn();
    }, TURN_DURATION_MS + 10000);
  }

  _stopAnimationTimer() {
    if (this._animationTimer) {
      clearTimeout(this._animationTimer);
      this._animationTimer = null;
    }
  }

  // ─────────────────────────────────────────────────────
  //  Action handlers
  // ─────────────────────────────────────────────────────

  _handleWeapon(attacker) {
    this._stopTurnTimer();

    // dismantle: cannot use weapon attack this turn
    if (this._hasActiveEffect(attacker, 'dismantle')) {
      attacker.socket.emit('Battle.action.weapon', {
        id: attacker.character.id, error: 'Weapon sealed by dismantle',
      });
      attacker.socket.emit('Battle.action.ambush', { id: attacker.character.id });
      this._startTurnTimer(attacker);
      return;
    }

    const defender = this._getOpponent(attacker);

    // Attach active buffs so StatsCalc can apply modifiers
    attacker.stats.activeBuffs = attacker.buffs;
    defender.stats.activeBuffs = defender.buffs;

    const { damage, crit, dodged } = StatsCalc.calcWeaponDamage(attacker.stats, defender.stats);

    const extraOverlays = [];

    if (!dodged && damage > 0) {
      if (this._hasActiveEffect(defender, 'serene_mind')) {
        // Reflect all damage back to the attacker; defender takes nothing
        attacker.stats.hp = Math.max(0, attacker.stats.hp - damage);
        extraOverlays.push({ id: attacker.character.id, icon: 'd', txt: String(damage), color: 16711680 });
      } else {
        const cpShieldBuff = defender.buffs.find(b => b.duration > 0 && b.effect === 'cp_shield');
        if (cpShieldBuff) {
          // CP shield: convert HP damage to a smaller CP drain (formula: floor(damage/amount))
          const cpDrain = Math.floor(damage / Math.max(1, cpShieldBuff.amount));
          defender.stats.cp = Math.max(0, defender.stats.cp - cpDrain);
          // HP is not reduced; show CP drain overlay on defender
          if (cpDrain > 0) {
            extraOverlays.push({ id: defender.character.id, icon: 'd', txt: `-${cpDrain}CP`, color: 255 });
          }
        } else {
          // Normal HP damage
          defender.stats.hp = Math.max(0, defender.stats.hp - damage);

          // damage_absorption: recover x% of damage taken as healing
          const absorbBuff = defender.buffs.find(b => b.duration > 0 && b.effect === 'damage_absorption');
          if (absorbBuff && absorbBuff.calc_type === 'percent') {
            const absorbed = Math.floor(damage * absorbBuff.amount / 100);
            if (absorbed > 0) {
              defender.stats.hp = Math.min(defender.stats.maxHp, defender.stats.hp + absorbed);
            }
          }
        }

        // bloodfeed: attacker life steal (applies to any non-reflected damage)
        const bloodfeedBuff = attacker.buffs.find(b => b.duration > 0 && b.effect === 'bloodfeed');
        if (bloodfeedBuff && bloodfeedBuff.calc_type === 'percent') {
          const lifeSteal = Math.floor(damage * bloodfeedBuff.amount / 100);
          if (lifeSteal > 0) {
            attacker.stats.hp = Math.min(attacker.stats.maxHp, attacker.stats.hp + lifeSteal);
          }
        }

        // Counter-effects triggered on the attacker by the defender's buffs
        const counterOvls = this._applyCounterEffects(attacker, defender, damage);
        extraOverlays.push(...counterOvls);
      }
    }

    // Build damage number overlay shown when the weapon animation hits
    const dmgOverlays = (!dodged && damage > 0)
      ? [{ id: defender.character.id, icon: 'd', txt: String(damage), color: crit ? 16776960 : 16711680 }]
      : [];

    const payload = {
      id:      attacker.character.id,
      action:  'weapon',
      stat:    { hp: attacker.stats.hp, cp: attacker.stats.cp },
      targets: [defender.character.id],
      dodged,
      crit,
      overlays: [...dmgOverlays, ...extraOverlays],
      stats: [
        { id: attacker.character.id, stat: { hp: attacker.stats.hp, cp: attacker.stats.cp } },
        { id: defender.character.id, stat: { hp: defender.stats.hp, cp: defender.stats.cp } },
      ],
    };

    this._logAction('weapon', payload);
    this._broadcastAll('Battle.action.weapon', payload);
    this._awaitingFinished = true;
    this._startAnimationTimer();

    // Check deaths (attacker can die from serene_mind or counter-effects like kekkai)
    if (attacker.stats.hp <= 0) {
      this._scheduleEndAfterAnimation(defender);
      return;
    }
    if (!dodged && defender.stats.hp <= 0) {
      this._scheduleEndAfterAnimation(attacker);
      return;
    }

    this._switchTurn();
  }

  _handleSkill(attacker, skillId) {
    this._stopTurnTimer();

    // restriction / meridian_seal: cannot use Ninjutsu this turn
    if (SKILL_BLOCK_EFFECTS.has('restriction') && this._hasActiveEffect(attacker, 'restriction')) {
      attacker.socket.emit('Battle.action.skill', {
        id: attacker.character.id, error: 'Skills sealed by restriction',
      });
      attacker.socket.emit('Battle.action.ambush', { id: attacker.character.id });
      this._startTurnTimer(attacker);
      return;
    }
    if (this._hasActiveEffect(attacker, 'meridian_seal')) {
      attacker.socket.emit('Battle.action.skill', {
        id: attacker.character.id, error: 'Skills sealed by meridian seal',
      });
      attacker.socket.emit('Battle.action.ambush', { id: attacker.character.id });
      this._startTurnTimer(attacker);
      return;
    }

    // Validate skill is equipped and not on cooldown
    if (!skillId || !attacker.skills.includes(skillId)) {
      attacker.socket.emit('Battle.action.skill', {
        id: attacker.character.id, error: 'Skill not available',
      });
      attacker.socket.emit('Battle.action.ambush', { id: attacker.character.id });
      this._startTurnTimer(attacker);
      return;
    }

    if ((attacker.cooldowns[skillId] || 0) > 0) {
      attacker.socket.emit('Battle.action.skill', {
        id: attacker.character.id, error: 'Skill is on cooldown',
      });
      attacker.socket.emit('Battle.action.ambush', { id: attacker.character.id });
      this._startTurnTimer(attacker);
      return;
    }

    // cp_cost debuff: inflates the chakra cost of the skill
    let cpCost = StatsCalc.skillCpCost(skillId);
    for (const b of attacker.buffs) {
      if (b.duration > 0 && b.effect === 'cp_cost' && b.calc_type === 'percent') {
        cpCost = Math.floor(cpCost * (1 + b.amount / 100));
      }
    }

    if (attacker.stats.cp < cpCost) {
      attacker.socket.emit('Battle.action.skill', {
        id: attacker.character.id, error: 'Not enough chakra',
      });
      attacker.socket.emit('Battle.action.ambush', { id: attacker.character.id });
      this._startTurnTimer(attacker);
      return;
    }

    // Deduct CP and set real cooldown from skill data
    attacker.stats.cp = Math.max(0, attacker.stats.cp - cpCost);
    attacker.cooldowns[skillId] = StatsCalc.getSkillCooldown(skillId);

    const defender = this._getOpponent(attacker);

    // Attach active buffs so StatsCalc can apply modifiers
    attacker.stats.activeBuffs = attacker.buffs;
    defender.stats.activeBuffs = defender.buffs;

    const { damage, crit, dodged } = StatsCalc.calcSkillDamage(attacker.stats, defender.stats, skillId);

    const extraOverlays = [];

    if (!dodged && damage > 0) {
      if (this._hasActiveEffect(defender, 'serene_mind')) {
        // Reflect all damage back to the attacker; defender takes nothing
        attacker.stats.hp = Math.max(0, attacker.stats.hp - damage);
        extraOverlays.push({ id: attacker.character.id, icon: 'd', txt: String(damage), color: 16711680 });
      } else {
        const cpShieldBuff = defender.buffs.find(b => b.duration > 0 && b.effect === 'cp_shield');
        if (cpShieldBuff) {
          // CP shield: convert HP damage to a smaller CP drain (formula: floor(damage/amount))
          const cpDrain = Math.floor(damage / Math.max(1, cpShieldBuff.amount));
          defender.stats.cp = Math.max(0, defender.stats.cp - cpDrain);
          if (cpDrain > 0) {
            extraOverlays.push({ id: defender.character.id, icon: 'd', txt: `-${cpDrain}CP`, color: 255 });
          }
        } else {
          // Normal HP damage
          defender.stats.hp = Math.max(0, defender.stats.hp - damage);

          // damage_absorption: recover x% of damage taken as healing
          const absorbBuff = defender.buffs.find(b => b.duration > 0 && b.effect === 'damage_absorption');
          if (absorbBuff && absorbBuff.calc_type === 'percent') {
            const absorbed = Math.floor(damage * absorbBuff.amount / 100);
            if (absorbed > 0) {
              defender.stats.hp = Math.min(defender.stats.maxHp, defender.stats.hp + absorbed);
            }
          }
        }

        // bloodfeed: attacker life steal
        const bloodfeedBuff = attacker.buffs.find(b => b.duration > 0 && b.effect === 'bloodfeed');
        if (bloodfeedBuff && bloodfeedBuff.calc_type === 'percent') {
          const lifeSteal = Math.floor(damage * bloodfeedBuff.amount / 100);
          if (lifeSteal > 0) {
            attacker.stats.hp = Math.min(attacker.stats.maxHp, attacker.stats.hp + lifeSteal);
          }
        }

        // Counter-effects triggered on the attacker by the defender's buffs
        const counterOvls = this._applyCounterEffects(attacker, defender, damage);
        extraOverlays.push(...counterOvls);
      }
    }

    // ── Apply skill effects (buffs / debuffs) ──────────────────────────────
    const effectOverlays = this._applySkillEffects(skillId, attacker, defender, dodged);

    // Damage number overlay shown at the animation hit point
    const dmgOverlays = (!dodged && damage > 0)
      ? [{ id: defender.character.id, icon: 'd', txt: String(damage), color: crit ? 16776960 : 16711680 }]
      : [];

    const payload = {
      id:       attacker.character.id,
      skillId,
      action:   'skill',
      stat:     { hp: attacker.stats.hp, cp: attacker.stats.cp },
      targets:  [defender.character.id],
      dodged,
      crit,
      // Damage number first, then buff/debuff name labels, then counter-effect labels
      overlays: [
        ...dmgOverlays,
        ...effectOverlays.map(o => ({
          id:    o.recipientId,
          icon:  o.buffType === 'Buff' ? 'h' : 'd',
          txt:   o.effectName,
          color: o.buffType === 'Buff' ? 65280 : 16711680,
        })),
        ...extraOverlays,
      ],
      stats: [
        { id: attacker.character.id, stat: { hp: attacker.stats.hp, cp: attacker.stats.cp } },
        { id: defender.character.id, stat: { hp: defender.stats.hp, cp: defender.stats.cp } },
      ],
    };

    this._logAction('skill', payload);
    this._broadcastAll('Battle.action.skill', payload);
    this._awaitingFinished = true;
    this._startAnimationTimer();

    // Send cooldown update to attacker
    this._sendUpdateInfo(attacker);

    // Check deaths (attacker can die from serene_mind or counter-effects)
    if (attacker.stats.hp <= 0) {
      this._scheduleEndAfterAnimation(defender);
      return;
    }
    if (!dodged && defender.stats.hp <= 0) {
      this._scheduleEndAfterAnimation(attacker);
      return;
    }

    this._switchTurn();
  }

  _handleDodge(participant) {
    this._stopTurnTimer();

    participant.isDodging = true;

    const payload = {
      id:     participant.character.id,
      action: 'dodge',
    };

    this._logAction('dodge', payload);
    this._broadcastAll('Battle.action.dodge', payload);
    this._awaitingFinished = true;
    this._startAnimationTimer();
    this._switchTurn();
  }

  _handleCharge(participant) {
    this._stopTurnTimer();

    // Respect charge_disable debuff
    if (this._hasActiveEffect(participant, 'charge_disable')) {
      participant.socket.emit('Battle.action.charge', {
        id: participant.character.id, error: 'Charge disabled',
      });
      participant.socket.emit('Battle.action.ambush', { id: participant.character.id });
      this._startTurnTimer(participant);
      return;
    }

    // meridian_seal: cannot charge this turn (weapon only)
    if (this._hasActiveEffect(participant, 'meridian_seal')) {
      participant.socket.emit('Battle.action.charge', {
        id: participant.character.id, error: 'Charge sealed by meridian seal',
      });
      participant.socket.emit('Battle.action.ambush', { id: participant.character.id });
      this._startTurnTimer(participant);
      return;
    }

    const gain = StatsCalc.chargeAmount(participant.stats);
    participant.stats.cp = Math.min(participant.stats.maxCp, participant.stats.cp + gain);

    const payload = {
      id:     participant.character.id,
      action: 'charge',
      stat:   { hp: participant.stats.hp, cp: participant.stats.cp },
      overlays: [],
    };

    this._logAction('charge', payload);
    this._broadcastAll('Battle.action.charge', payload);
    this._awaitingFinished = true;
    this._startAnimationTimer();
    this._switchTurn();
  }

  _handleScroll(participant, scrollId) {
    this._stopTurnTimer();

    if (!this.allowScrolls || !scrollId) {
      participant.socket.emit('Battle.action.scroll', {
        id: participant.character.id, error: 'Scrolls not allowed',
      });
      participant.socket.emit('Battle.action.ambush', { id: participant.character.id });
      this._startTurnTimer(participant);
      return;
    }

    // Simplified: scroll restores 20% HP
    const heal = Math.floor(participant.stats.maxHp * 0.2);
    participant.stats.hp = Math.min(participant.stats.maxHp, participant.stats.hp + heal);

    const payload = {
      id:      participant.character.id,
      action:  'scroll',
      overlays: [],
      stats: [
        { id: participant.character.id, stat: { hp: participant.stats.hp, cp: participant.stats.cp } },
      ],
    };

    this._logAction('scroll', payload);
    this._broadcastAll('Battle.action.scroll', payload);
    this._awaitingFinished = true;
    this._startAnimationTimer();
    this._switchTurn();
  }

  _handleRun(participant) {
    this._stopTurnTimer();

    const payload = {
      id:     participant.character.id,
      action: 'run',
    };

    this._logAction('run', payload);
    this._broadcastAll('Battle.action.run', payload);

    // The runner loses
    const winner = this._getOpponent(participant);
    this._endBattle(winner, 'run');
  }

  _handleTimeout(participant) {
    this._stopTurnTimer();

    // Forced skip — player did not act in time
    const payload = {
      id:          participant.character.id,
      action:      'skip',
      effect_name: 'Turn skipped (timeout)',
    };

    this._broadcastAll('Battle.action.skip', payload);
    this._awaitingFinished = false;
    this._switchTurn();
    // Advance to the next turn so the game never stalls after a timeout
    setImmediate(() => this._nextTurn());
  }

  // ─────────────────────────────────────────────────────
  //  Buff / debuff application
  // ─────────────────────────────────────────────────────

  /**
   * Apply all non-passive effects from a skill's skill-effect list.
   * Returns an overlays array of objects { recipientId, buffType, effectName }
   * so the caller can build the correct client payload.
   *
   * @param {string}  skillId
   * @param {object}  attacker  - participant who used the skill
   * @param {object}  defender  - opponent
   * @param {boolean} dodged    - whether the attack was dodged
   * @returns {Array<{recipientId, buffType, effectName}>}
   */
  _applySkillEffects(skillId, attacker, defender, dodged) {
    const effects  = SkillData.getSkillEffects(skillId);
    const overlays = [];

    for (const eff of effects) {
      // Skip passive entries (they are handled by the client's PassiveManager)
      if (eff.passive) continue;

      // Probability roll
      const roll = Math.random() * 100;
      if (roll >= eff.chance) continue;

      // Determine the recipient
      let recipient;
      if (eff.target === 'self') {
        recipient = attacker;
      } else {
        // "enemy" / default
        // Debuffs skip if attack was dodged
        if (dodged && eff.type === 'Debuff') continue;
        recipient = defender;
      }

      // purify and disperse have duration=1 in the data file but must fire as
      // instant effects — execute them immediately regardless of duration value.
      const isInstantByName = (eff.effect === 'purify' || eff.effect === 'disperse');

      // Handle instant (duration === 0) one-off effects, or purify/disperse
      if (eff.duration === 0 || isInstantByName) {
        this._applyInstantEffect(eff, attacker, defender, recipient);
        overlays.push({ recipientId: recipient.character.id, buffType: eff.type, effectName: eff.effect_name });
        continue;
      }

      // debuff_resist: block incoming debuffs (except a small exempt set)
      if (eff.type === 'Debuff'
          && this._hasActiveEffect(recipient, 'debuff_resist')
          && !DEBUFF_RESIST_EXEMPT.has(eff.effect)) {
        continue; // debuff was resisted — no overlay, no application
      }

      // Ongoing buff / debuff — add to recipient's buffs array
      // Merge with an existing entry of the same effect (refresh duration)
      const existing = recipient.buffs.find(b => b.effect === eff.effect);
      if (existing) {
        existing.duration  = Math.max(existing.duration, eff.duration);
        existing.amount    = eff.amount;
        existing.calc_type = eff.calc_type;
      } else {
        recipient.buffs.push({
          effect:      eff.effect,
          effect_name: eff.effect_name,
          type:        eff.type,
          duration:    eff.duration,
          amount:      eff.amount,
          amount_hp:   eff.amount_hp,
          amount_cp:   eff.amount_cp,
          calc_type:   eff.calc_type,
          no_disperse: eff.no_disperse,
        });
      }

      overlays.push({ recipientId: recipient.character.id, buffType: eff.type, effectName: eff.effect_name });
    }

    return overlays;
  }

  /**
   * Handle instant (duration=0) skill effects.
   * Covers: instant heals, instant CP drain, instant HP reduction, purify.
   */
  _applyInstantEffect(eff, attacker, defender, recipient) {
    switch (eff.effect) {
      case 'heal':
      case 'instant_heal': {
        // internal_injury / hanyaoni: block all healing
        if (HEAL_BLOCK_EFFECTS.has('internal_injury') && this._hasActiveEffect(recipient, 'internal_injury')) break;
        if (this._hasActiveEffect(recipient, 'hanyaoni')) break;
        let amount;
        if (eff.calc_type === 'percent') {
          amount = Math.floor(recipient.stats.maxHp * eff.amount / 100);
        } else {
          amount = Math.floor(eff.amount);
        }
        recipient.stats.hp = Math.min(recipient.stats.maxHp, recipient.stats.hp + amount);
        break;
      }

      case 'current_cp_drain':
      case 'cp_drain': {
        // Matches Flash client drainCP(enemy, attacker, effect):
        // drains CP from recipient and transfers it to the attacker (caster).
        // current_cp_drain uses enemy's current CP as the percentage base.
        // cp_drain uses enemy's max CP as the base (all cp_drain data has reduce_type=MAX).
        let amount;
        if (eff.calc_type === 'percent') {
          const base = eff.effect === 'cp_drain'
            ? recipient.stats.maxCp   // cp_drain: percent of max CP
            : recipient.stats.cp;     // current_cp_drain: percent of current CP
          amount = Math.floor(base * eff.amount / 100);
        } else {
          amount = Math.floor(eff.amount);
        }
        recipient.stats.cp = Math.max(0, recipient.stats.cp - amount);
        // Transfer drained CP to the attacker (caster)
        if (amount > 0 && recipient !== attacker) {
          attacker.stats.cp = Math.min(attacker.stats.maxCp, attacker.stats.cp + amount);
        }
        break;
      }

      case 'max_cp_drain': {
        // Drain from maxCp base; no transfer to attacker (pure drain)
        let amount;
        if (eff.calc_type === 'percent') {
          amount = Math.floor(recipient.stats.maxCp * eff.amount / 100);
        } else {
          amount = Math.floor(eff.amount);
        }
        recipient.stats.cp = Math.max(0, recipient.stats.cp - amount);
        break;
      }

      case 'current_hp_drain': {
        // Drains a % of target's current HP and gives it to the attacker
        let hpDrain;
        if (eff.calc_type === 'percent') {
          hpDrain = Math.floor(recipient.stats.hp * eff.amount / 100);
        } else {
          hpDrain = Math.floor(eff.amount);
        }
        recipient.stats.hp = Math.max(0, recipient.stats.hp - hpDrain);
        // Heal the attacker (who is the caster, not necessarily recipient)
        const hpDrainHealer = recipient === attacker ? defender : attacker;
        hpDrainHealer.stats.hp = Math.min(hpDrainHealer.stats.maxHp, hpDrainHealer.stats.hp + hpDrain);
        break;
      }

      case 'drain_HpCp': {
        // Drains % of target's HP and CP; transfers both to caster.
        // When reduce_type=MAX (Flash client drainHP behaviour), use maxHp/maxCp
        // as the percentage base; otherwise use current hp/cp.
        let hpDrain, cpDrain;
        if (eff.calc_type === 'percent') {
          const useMax = eff.reduce_type === 'MAX';
          const hpBase = useMax ? recipient.stats.maxHp : recipient.stats.hp;
          const cpBase = useMax ? recipient.stats.maxCp : recipient.stats.cp;
          hpDrain = Math.floor(hpBase * eff.amount / 100);
          cpDrain = Math.floor(cpBase * eff.amount / 100);
        } else {
          hpDrain = Math.floor(eff.amount);
          cpDrain = Math.floor(eff.amount);
        }
        recipient.stats.hp = Math.max(0, recipient.stats.hp - hpDrain);
        recipient.stats.cp = Math.max(0, recipient.stats.cp - cpDrain);
        const drainHealer = recipient === attacker ? defender : attacker;
        drainHealer.stats.hp = Math.min(drainHealer.stats.maxHp, drainHealer.stats.hp + hpDrain);
        drainHealer.stats.cp = Math.min(drainHealer.stats.maxCp, drainHealer.stats.cp + cpDrain);
        break;
      }

      case 'instant_reduce_hp':
      case 'reduce_hp_as_damage':
      case 'insta_reduce_curr_hp': {
        let amount;
        if (eff.calc_type === 'percent') {
          amount = Math.floor(recipient.stats.maxHp * eff.amount / 100);
        } else {
          amount = Math.floor(eff.amount);
        }
        recipient.stats.hp = Math.max(0, recipient.stats.hp - amount);
        break;
      }

      case 'purify': {
        // Remove all non-no_disperse debuffs from the recipient (self-cast)
        recipient.buffs = recipient.buffs.filter(
          b => b.type !== 'Debuff' || b.no_disperse
        );
        break;
      }

      case 'disperse': {
        // Remove all dispersible buffs (Buffs only) from the recipient (enemy-cast)
        recipient.buffs = recipient.buffs.filter(
          b => b.type !== 'Buff' || b.no_disperse
        );
        break;
      }

      case 'rapid_cooldown': {
        // Reduce all of the attacker's skill cooldowns
        const reduction = Math.max(1, Math.floor(eff.amount));
        for (const skillId of Object.keys(attacker.cooldowns)) {
          attacker.cooldowns[skillId] = Math.max(0, (attacker.cooldowns[skillId] || 0) - reduction);
        }
        break;
      }

      case 'add_cooldown': {
        // Add cooldown turns to all of the recipient's equipped skills
        const extra = Math.max(1, Math.floor(eff.amount));
        for (const skillId of recipient.skills) {
          recipient.cooldowns[skillId] = (recipient.cooldowns[skillId] || 0) + extra;
        }
        break;
      }

      case 'insta_reduce_max_hp': {
        let amount;
        if (eff.calc_type === 'percent') {
          amount = Math.floor(recipient.stats.maxHp * eff.amount / 100);
        } else {
          amount = Math.floor(eff.amount);
        }
        recipient.stats.maxHp = Math.max(1, recipient.stats.maxHp - amount);
        recipient.stats.hp    = Math.min(recipient.stats.hp, recipient.stats.maxHp);
        break;
      }

      case 'insta_reduce_max_cp': {
        // Matches Flash client reduceCPFromDebuff(effect, reduce_type="MAX"):
        // drains current CP by X% of maxCp but does NOT permanently lower maxCp.
        // Permanently modifying maxCp caused cp == maxCp immediately after the
        // skill, making every subsequent charge give 0 CP gain.
        let amount;
        if (eff.calc_type === 'percent') {
          amount = Math.floor(recipient.stats.maxCp * eff.amount / 100);
        } else {
          amount = Math.floor(eff.amount);
        }
        recipient.stats.cp = Math.max(0, recipient.stats.cp - amount);
        break;
      }

      case 'insta_consume_all_cp': {
        recipient.stats.cp = 0;
        break;
      }

      // increase_<element>_cd: add cooldown turns to all of recipient's skills
      // (element-filtering would require per-skill type lookup; adding to all is a safe approximation)
      case 'increase_earth_cd':
      case 'increase_fire_cd':
      case 'increase_water_cd':
      case 'increase_wind_cd':
      case 'increase_lightning_cd': {
        const extra = Math.max(1, Math.floor(eff.amount));
        for (const sid of recipient.skills) {
          recipient.cooldowns[sid] = (recipient.cooldowns[sid] || 0) + extra;
        }
        break;
      }

      default:
        // Other instant effects (e.g. stat swaps, special mechanics) are
        // client-side visual only at this tier; nothing extra to do.
        break;
    }
  }

  // ─────────────────────────────────────────────────────
  //  Per-turn buff processing
  // ─────────────────────────────────────────────────────

  /**
   * Apply per-turn effects (DoT, HoT, CoT, theft drain) for a participant.
   * Called at the start of each round before durations are ticked.
   * Returns client-ready overlay objects { id, icon, txt/amount, color }
   * for both `participant` and `opponent` (e.g. theft heal).
   * The caller broadcasts these via Battle.updateInfo.
   *
   * @param {object} participant - the participant whose buffs are processed
   * @param {object} opponent    - the other participant (for drain-to-self effects)
   * @returns {Array<{id,icon,txt?,amount?,color}>}
   */
  _applyPerTurnEffects(participant, opponent) {
    const overlays = [];

    for (const buff of participant.buffs) {
      if (buff.duration <= 0) continue;

      // ── Damage over time ──────────────────────────────────────────────────
      if (DOT_EFFECTS.has(buff.effect)) {
        let dmg;
        if (buff.calc_type === 'percent') {
          dmg = Math.floor(participant.stats.maxHp * buff.amount / 100);
        } else {
          dmg = Math.max(1, Math.floor(buff.amount));
        }
        participant.stats.hp = Math.max(0, participant.stats.hp - dmg);
        overlays.push({ id: participant.character.id, icon: 'd', txt: String(dmg), color: 16711680 });

        // theft: the drained HP is transferred to the opponent
        if (buff.effect === 'theft' && opponent) {
          opponent.stats.hp = Math.min(opponent.stats.maxHp, opponent.stats.hp + dmg);
          overlays.push({ id: opponent.character.id, icon: 'h', amount: dmg, color: 65280 });
        }

        // reduce_hp_cp also drains CP by the same percentage each turn
        if (buff.effect === 'reduce_hp_cp' && buff.calc_type === 'percent') {
          const cpDmg = Math.floor(participant.stats.maxCp * buff.amount / 100);
          if (cpDmg > 0) participant.stats.cp = Math.max(0, participant.stats.cp - cpDmg);
        }
      }

      // ── prison: stun + per-turn max HP/CP reduction ───────────────────────
      if (buff.effect === 'prison') {
        const reduction = buff.calc_type === 'percent'
          ? Math.floor(participant.stats.maxHp * buff.amount / 100)
          : Math.max(1, Math.floor(buff.amount));
        participant.stats.maxHp = Math.max(1, participant.stats.maxHp - reduction);
        participant.stats.maxCp = Math.max(1, participant.stats.maxCp - reduction);
        participant.stats.hp    = Math.min(participant.stats.hp,  participant.stats.maxHp);
        participant.stats.cp    = Math.min(participant.stats.cp,  participant.stats.maxCp);
      }

      // ── Heal over time ────────────────────────────────────────────────────
      if (HOT_EFFECTS.has(buff.effect)) {
        // internal_injury / hanyaoni: block all healing
        const healBlocked = participant.buffs.some(
          b => b !== buff && b.duration > 0 && HEAL_BLOCK_EFFECTS.has(b.effect)
        );
        if (!healBlocked) {
          const hotAmount = buff.amount_hp > 0 ? buff.amount_hp : buff.amount;
          let heal;
          if (buff.calc_type === 'percent') {
            heal = Math.floor(participant.stats.maxHp * hotAmount / 100);
          } else {
            heal = Math.max(1, Math.floor(hotAmount));
          }
          if (heal > 0) {
            participant.stats.hp = Math.min(participant.stats.maxHp, participant.stats.hp + heal);
            overlays.push({ id: participant.character.id, icon: 'h', amount: heal, color: 65280 });
          }
        }
      }

      // ── CP restore over time ──────────────────────────────────────────────
      if (COT_EFFECTS.has(buff.effect)) {
        // Prefer amount_cp; fall back to amount (some effects store it there)
        const cotAmount = buff.amount_cp > 0 ? buff.amount_cp : buff.amount;
        if (cotAmount > 0) {
          let cpGain;
          if (buff.calc_type === 'percent') {
            cpGain = Math.floor(participant.stats.maxCp * cotAmount / 100);
          } else {
            cpGain = Math.max(1, Math.floor(cotAmount));
          }
          if (cpGain > 0) {
            participant.stats.cp = Math.min(participant.stats.maxCp, participant.stats.cp + cpGain);
            // No separate overlay for CP restore (not shown in original game)
          }
        }
      }
    }

    return overlays;
  }

  // ─────────────────────────────────────────────────────
  //  Buff/cooldown helpers
  // ─────────────────────────────────────────────────────

  _tickCooldowns(participant) {
    for (const skillId of Object.keys(participant.cooldowns)) {
      if (participant.cooldowns[skillId] > 0) {
        participant.cooldowns[skillId]--;
      }
    }
  }

  _tickBuffs(participant) {
    participant.buffs = participant.buffs.filter(b => {
      b.duration--;
      return b.duration > 0;
    });
  }

  /**
   * Returns true if the participant has any active turn-skipping effect.
   * Includes `lock` when the participant's HP is below 50% of max.
   */
  _hasSkipEffect(participant) {
    return participant.buffs.some(b => {
      if (b.duration <= 0) return false;
      if (SKIP_TURN_EFFECTS.has(b.effect)) return true;
      // lock: acts as stun when HP < 50 %, as weaken when HP >= 50 %
      if (b.effect === 'lock' && participant.stats.hp < participant.stats.maxHp * 0.5) return true;
      return false;
    });
  }

  /**
   * Returns the effect_name of the first active skip effect, for the UI.
   */
  _getActiveSkipEffectName(participant) {
    const found = participant.buffs.find(b => {
      if (b.duration <= 0) return false;
      if (SKIP_TURN_EFFECTS.has(b.effect)) return true;
      if (b.effect === 'lock' && participant.stats.hp < participant.stats.maxHp * 0.5) return true;
      return false;
    });
    return found ? found.effect_name : 'Stunned';
  }

  /**
   * Returns true if the participant has an active buff/debuff with the given effect name.
   */
  _hasActiveEffect(participant, effectName) {
    return participant.buffs.some(b => b.duration > 0 && b.effect === effectName);
  }

  /**
   * Apply counter-effects that trigger on the ATTACKER when they land a hit on DEFENDER.
   * These are buffs on the defender that react to being struck:
   *   fire_wall         → apply burn to attacker
   *   attacker_bleeding → apply bleed to attacker
   *   slow_attacker     → apply slow to attacker
   *   dec_cp_attacker   → drain attacker's CP
   *   kekkai            → drain attacker's HP and CP
   *   instant_reduce_hp_attacker → deal instant HP damage to attacker
   *
   * Returns an array of overlay objects for the client.
   */
  _applyCounterEffects(attacker, defender, damage) {
    const overlays = [];

    for (const b of defender.buffs) {
      if (b.duration <= 0) continue;

      switch (b.effect) {
        case 'fire_wall': {
          // Apply burn to attacker if not already burning
          if (!attacker.buffs.find(x => x.effect === 'burn')) {
            attacker.buffs.push({
              effect: 'burn', effect_name: 'Burn',
              type: 'Debuff', duration: b.duration,
              amount: b.amount, calc_type: b.calc_type,
              no_disperse: false,
            });
          }
          overlays.push({ id: attacker.character.id, icon: 'd', txt: 'Burn', color: 16711680 });
          break;
        }

        case 'attacker_bleeding': {
          // Apply bleed to attacker if not already bleeding
          if (!attacker.buffs.find(x => x.effect === 'bleed')) {
            attacker.buffs.push({
              effect: 'bleed', effect_name: 'Bleed',
              type: 'Debuff', duration: b.duration,
              amount: b.amount, calc_type: b.calc_type,
              no_disperse: false,
            });
          }
          overlays.push({ id: attacker.character.id, icon: 'd', txt: 'Bleed', color: 16711680 });
          break;
        }

        case 'slow_attacker': {
          // Apply slow to attacker if not already slowed
          if (!attacker.buffs.find(x => x.effect === 'slow')) {
            attacker.buffs.push({
              effect: 'slow', effect_name: 'Slow',
              type: 'Debuff', duration: b.duration,
              amount: b.amount, calc_type: b.calc_type,
              no_disperse: false,
            });
          }
          overlays.push({ id: attacker.character.id, icon: 'd', txt: 'Slow', color: 16711680 });
          break;
        }

        case 'dec_cp_attacker': {
          // Drain attacker's CP when they land a hit
          const cpDrain = b.calc_type === 'percent'
            ? Math.floor(attacker.stats.maxCp * b.amount / 100)
            : Math.max(1, Math.floor(b.amount));
          attacker.stats.cp = Math.max(0, attacker.stats.cp - cpDrain);
          if (cpDrain > 0) {
            overlays.push({ id: attacker.character.id, icon: 'd', txt: `-${cpDrain}CP`, color: 255 });
          }
          break;
        }

        case 'kekkai': {
          // Drain attacker's own HP + CP when they use a weapon attack
          const kekDrain = b.calc_type === 'percent'
            ? Math.floor(attacker.stats.maxHp * b.amount / 100)
            : Math.max(1, Math.floor(b.amount));
          attacker.stats.hp = Math.max(0, attacker.stats.hp - kekDrain);
          attacker.stats.cp = Math.max(0, attacker.stats.cp - kekDrain);
          if (kekDrain > 0) {
            overlays.push({ id: attacker.character.id, icon: 'd', txt: String(kekDrain), color: 16711680 });
          }
          break;
        }

        case 'instant_reduce_hp_attacker': {
          // Deal instant HP damage to attacker when they hit the buff holder
          const hpDmg = b.calc_type === 'percent'
            ? Math.floor(attacker.stats.maxHp * b.amount / 100)
            : Math.max(1, Math.floor(b.amount));
          attacker.stats.hp = Math.max(0, attacker.stats.hp - hpDmg);
          if (hpDmg > 0) {
            overlays.push({ id: attacker.character.id, icon: 'd', txt: String(hpDmg), color: 16711680 });
          }
          break;
        }

        default:
          break;
      }
    }

    return overlays;
  }

  /**
   * Send a Battle.updateInfo packet to one participant.
   * Always includes ALL equipped skills so the tooltip can show CP cost
   * even before the first skill is used.
   * Cooldowns are formatted as { cd, cost } objects as the client expects.
   */
  _sendUpdateInfo(participant) {
    const cooldowns = {};
    for (const skillId of participant.skills) {
      cooldowns[skillId] = {
        cd:   participant.cooldowns[skillId] || 0,
        cost: SkillData.getSkill(skillId).skill_cp_cost,
      };
    }
    participant.socket.emit('Battle.updateInfo', {
      id:             participant.character.id,
      skillCooldowns: cooldowns,
      stat:           { hp: participant.stats.hp, cp: participant.stats.cp },
    });
  }

  // ─────────────────────────────────────────────────────
  //  Turn / end helpers
  // ─────────────────────────────────────────────────────

  _switchTurn() {
    this._activeParticipant =
      this._activeParticipant === this.host ? this.enemy : this.host;
  }

  _scheduleEndAfterAnimation(winner) {
    // Give clients ~2 s to finish the death animation before ending
    setTimeout(() => {
      if (this.running) this._endBattle(winner, 'battle');
    }, 2000);
  }

  async _endBattle(winner, reason) {
    if (!this.running) return;
    this.running = false;
    this._stopTurnTimer();
    this._stopAnimationTimer();

    const loser = winner === this.host ? this.enemy : this.host;

    // ── Load live PvP settings from DB (60-second cached) ──
    const pvpCfg = await db.getGameConfig('pvp_settings', {});
    this._pvpSettings = {
      pvpPointsWin:   pvpCfg.pvp_points_win   != null ? pvpCfg.pvp_points_win   : config.pvpPointsWin,
      pvpPointsLose:  pvpCfg.pvp_points_lose  != null ? pvpCfg.pvp_points_lose  : config.pvpPointsLose,
      pvpPrestigeWin: pvpCfg.pvp_prestige_win != null ? pvpCfg.pvp_prestige_win : config.pvpPrestigeWin,
      trophyKFactor:  pvpCfg.trophy_k_factor  != null ? pvpCfg.trophy_k_factor  : config.trophyKFactor,
      trophyFloor:    pvpCfg.trophy_floor      != null ? pvpCfg.trophy_floor     : config.trophyFloor,
    };

    // ── Trophy calculation ──
    let trophyInfo = null;
    if (this.mode === 'ranked') {
      trophyInfo = TrophyCalc.applyTrophies(
        winner.character.pvp_trophy || 0,
        loser.character.pvp_trophy  || 0,
        this._pvpSettings.trophyKFactor,
        this._pvpSettings.trophyFloor,
      );
    }

    const winnerTrophyAfter = trophyInfo ? trophyInfo.winnerAfter : (winner.character.pvp_trophy || 0);
    const loserTrophyAfter  = trophyInfo ? trophyInfo.loserAfter  : (loser.character.pvp_trophy  || 0);

    // ── Persist to DB ──
    try {
      await this._saveBattle(winner, loser, trophyInfo, winnerTrophyAfter, loserTrophyAfter);
    } catch (err) {
      console.error('[Battle] DB save error:', err.message);
    }

    // ── Build result payloads ──
    const winnerResult = this._buildResultPayload(winner, true,  trophyInfo, winnerTrophyAfter, loserTrophyAfter);
    const loserResult  = this._buildResultPayload(loser,  false, trophyInfo, winnerTrophyAfter, loserTrophyAfter);

    winner.socket.emit('Battle.ended', winnerResult);
    loser.socket.emit('Battle.ended',  loserResult);
    this._broadcastSpectators('Battle.ended', winnerResult);

    if (this.onEnd) this.onEnd(this);
  }

  _buildResultPayload(participant, won, trophyInfo, winnerTrophyAfter, loserTrophyAfter) {
    const isWinner = won;
    const char = participant.character;

    const trophyDelta = trophyInfo
      ? (isWinner ? trophyInfo.winnerDeltaText : trophyInfo.loserDeltaText)
      : '0';

    const trophyAfter = isWinner ? winnerTrophyAfter : loserTrophyAfter;

    return {
      id:      char.id,
      won,
      trophy:  trophyDelta,
      trophyAfter,
      rewards: {
        gold:   won ? Math.floor(50 + (parseInt(char.level, 10) || 1) * 2) : 0,
        xp:     won ? Math.floor(100 + (parseInt(char.level, 10) || 1) * 5) : 0,
        points: won ? (this._pvpSettings ? this._pvpSettings.pvpPointsWin : config.pvpPointsWin) : (this._pvpSettings ? this._pvpSettings.pvpPointsLose : config.pvpPointsLose),
        etc:    [],
      },
    };
  }

  async _saveBattle(winner, loser, trophyInfo, winnerTrophyAfter, loserTrophyAfter) {
    const hostWon     = winner === this.host;
    const trophyDelta = trophyInfo ? trophyInfo.delta : 0;

    const hostTrophyBefore  = this.host.character.pvp_trophy  || 0;
    const enemyTrophyBefore = this.enemy.character.pvp_trophy || 0;

    const hostTrophyAfter  = hostWon  ? winnerTrophyAfter : loserTrophyAfter;
    const enemyTrophyAfter = !hostWon ? winnerTrophyAfter : loserTrophyAfter;

    const hostSnap  = this._buildSnapshot(this.host);
    const enemySnap = this._buildSnapshot(this.enemy);

    // Insert battle record
    const [result] = await db.getPool().execute(
      `INSERT INTO pvp_battles
         (host_id, enemy_id, mode, host_won, trophy_delta,
          host_trophy_before, host_trophy_after,
          enemy_trophy_before, enemy_trophy_after,
          host_level, enemy_level, host_rank, enemy_rank,
          host_snapshot, enemy_snapshot, battle_data,
          created_at, updated_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())`,
      [
        this.host.character.id,
        this.enemy.character.id,
        this.mode,
        hostWon ? 1 : 0,
        trophyDelta,
        hostTrophyBefore,  hostTrophyAfter,
        enemyTrophyBefore, enemyTrophyAfter,
        this.host.character.level  || 1,
        this.enemy.character.level || 1,
        this.host.character.rank   || 1,
        this.enemy.character.rank  || 1,
        JSON.stringify(hostSnap),
        JSON.stringify(enemySnap),
        JSON.stringify({ rounds: this.round, log: this._actionLog.slice(-100) }),
      ]
    );

    // Update character stats
    const prestigePerWin = this._pvpSettings ? this._pvpSettings.pvpPrestigeWin : config.pvpPrestigeWin;
    const pointsWin  = this._pvpSettings ? this._pvpSettings.pvpPointsWin  : config.pvpPointsWin;
    const pointsLose = this._pvpSettings ? this._pvpSettings.pvpPointsLose : config.pvpPointsLose;

    await db.getPool().execute(
      `UPDATE characters
       SET pvp_played  = pvp_played  + 1,
           pvp_won     = pvp_won     + ?,
           pvp_lost    = pvp_lost    + ?,
           pvp_trophy  = ?,
           pvp_points  = GREATEST(0, pvp_points + ?),
           prestige    = prestige    + ?
       WHERE id = ?`,
      [hostWon ? 1 : 0, hostWon ? 0 : 1, hostTrophyAfter,
       hostWon ? pointsWin : -pointsLose,
       hostWon ? prestigePerWin : 0,
       this.host.character.id]
    );
    await db.getPool().execute(
      `UPDATE characters
       SET pvp_played  = pvp_played  + 1,
           pvp_won     = pvp_won     + ?,
           pvp_lost    = pvp_lost    + ?,
           pvp_trophy  = ?,
           pvp_points  = GREATEST(0, pvp_points + ?),
           prestige    = prestige    + ?
       WHERE id = ?`,
      [!hostWon ? 1 : 0, !hostWon ? 0 : 1, enemyTrophyAfter,
       !hostWon ? pointsWin : -pointsLose,
       !hostWon ? prestigePerWin : 0,
       this.enemy.character.id]
    );

    return result.insertId;
  }

  _buildSnapshot(participant) {
    const char = participant.character;
    return {
      id:     char.id,
      name:   char.name,
      rank:   char.rank,
      level:  char.level,
      trophy: char.pvp_trophy || 0,
      skills: participant.skills,
      talents: [char.talent_1, char.talent_2, char.talent_3],
      set: {
        clothing: char.equipment_clothing || 'set_01_0',
        weapon:   char.equipment_weapon   || 'wpn_01',
        back_item: char.equipment_back    || 'back_01',
        hairstyle:  this._formatHair(char),
        face:       this._formatFace(char),
        hair_color: char.hair_color  || '0|0',
        skin_color: char.skin_color  || 'null|null',
      },
    };
  }

  _formatHair(char) {
    const suffix = char.gender == 0 ? '_0' : '_1';
    const h = char.hair_style;
    if (!h) return `hair_01${suffix}`;
    if (isNaN(Number(h))) return h;
    return `hair_${String(h).padStart(2, '0')}${suffix}`;
  }

  _formatFace(char) {
    const suffix = char.gender == 0 ? '_0' : '_1';
    return `face_01${suffix}`;
  }

  // ─────────────────────────────────────────────────────
  //  Broadcast helpers
  // ─────────────────────────────────────────────────────

  _broadcastAll(event, data) {
    this.host.socket.emit(event, data);
    this.enemy.socket.emit(event, data);
    this._broadcastSpectators(event, data);
  }

  _broadcastParticipants(event, data) {
    this.host.socket.emit(event, data);
    this.enemy.socket.emit(event, data);
  }

  _broadcastSpectators(event, data) {
    for (const sock of this._spectatorSockets.values()) {
      sock.emit(event, data);
    }
  }

  _broadcastBattleChat(senderSocket, message) {
    if (!message) return;
    const sender = this._getParticipant(senderSocket.charId);
    if (!sender) return;
    const msg = {
      character: { id: sender.character.id, name: sender.character.name },
      message:   String(message).substring(0, 200),
    };
    this._broadcastAll('Conversation.battle.newMessage', msg);
  }

  // ─────────────────────────────────────────────────────
  //  Utility
  // ─────────────────────────────────────────────────────

  _buildPlayerInfoPayload(character) {
    // CharacterManager.fillCharacterData() expects the same flat format as
    // Client.characterInfo: { id, name, level, stat:{...}, set:{...}, point:{...}, ... }
    // It builds character_data / character_sets / character_points itself from those fields.
    return buildCharacterInfoPayload(character).character;
  }

  _getParticipant(charId) {
    if (this.host.character.id  == charId) return this.host;
    if (this.enemy.character.id == charId) return this.enemy;
    return null;
  }

  _getOpponent(participant) {
    return participant === this.host ? this.enemy : this.host;
  }

  _logAction(type, payload) {
    this._actionLog.push({ round: this.round, type, ...payload });
  }

  getSummary() {
    return {
      battleId: this.id,
      mode:     this.mode,
      stage:    this.stage,
      hostId:   this.host.character.id,
      enemyId:  this.enemy.character.id,
      spectators: this.spectators.size,
    };
  }
}

module.exports = Battle;
