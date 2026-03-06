'use strict';

const { v4: uuidv4 } = require('uuid');
const Battle = require('./battle/Battle');
const db     = require('./db');

const ROOM_EXPIRY_MS = 10 * 60 * 1000; // 10 minutes

/**
 * Room states:
 *   'waiting'    — created, waiting for enemy to join
 *   'ready'      — both players in, pre-battle
 *   'in_battle'  — battle is live
 *   'ended'      — battle finished
 */
class RoomManager {
  constructor(namespace, lobbyManager) {
    this._ns     = namespace;
    this._lobby  = lobbyManager;

    // roomId -> room object
    this._rooms = new Map();

    // charId -> roomId (which room a player is in)
    this._playerRoom = new Map();

    // socketId -> roomId (spectators)
    this._spectatorRoom = new Map();

    // Live battles: battleId -> Battle
    this._battles = new Map();

    // battleId -> roomId (reverse lookup)
    this._battleRoom = new Map();
  }

  // ─────────────────────────────────────────────────────
  //  Room lifecycle
  // ─────────────────────────────────────────────────────

  createRoom(socket, data) {
    if (!socket.charId) return;

    // Leave any current room first
    this.exitRoom(socket, true);

    const settings = data.settings || {};
    const roomId   = uuidv4();

    const room = {
      id:               roomId,
      host:             { socket, character: socket.character, ready: false, skills: [] },
      enemy:            null,
      password:         data.password || '',
      mode:             settings.mode            || 'ranked',
      stage:            settings.stage           || 'mission_1011',
      allowScrolls:     !!settings.allowScrolls,
      allowSpectators:  settings.allowSpectators !== false,
      spectators:       new Map(),  // socketId -> socket
      state:            'waiting',
      createdAt:        Date.now(),
    };

    this._rooms.set(roomId, room);
    this._playerRoom.set(socket.charId, roomId);

    socket.emit('Room.created', {
      room_id:          roomId,
      host:             socket.charId,
      password:         room.password,
      mode:             room.mode,
      stage:            room.stage,
      allow_scrolls:    room.allowScrolls,
      allow_spectators: room.allowSpectators,
    });

    this._scheduleRoomExpiry(roomId);
  }

  joinRoom(socket, data) {
    if (!socket.charId) return;

    const room = this._rooms.get(data.id);
    if (!room) {
      socket.emit('Notification.flash', 'Room not found');
      return;
    }
    if (room.state !== 'waiting') {
      socket.emit('Notification.flash', 'Room is not available');
      return;
    }
    if (room.enemy) {
      socket.emit('Notification.flash', 'Room is full');
      return;
    }
    if (room.password && room.password !== (data.password || '')) {
      socket.emit('Notification.flash', 'Wrong password');
      return;
    }
    if (room.host.socket.charId === socket.charId) {
      socket.emit('Notification.flash', 'You cannot join your own room');
      return;
    }

    // Leave any current room first
    this.exitRoom(socket, true);

    room.enemy = { socket, character: socket.character, ready: false, skills: [] };
    room.state = 'ready';
    this._playerRoom.set(socket.charId, room.id);

    // Notify host
    room.host.socket.emit('Room.newPlayerJoined', { enemy_id: socket.charId });

    // Notify joining enemy:
    //   host     = null (falsy) → BattleRoom.activate() routes to setupRoomAsEnemy()
    //   enemy_id = HOST's charId → setupRoomAsEnemy() uses this to fetch the host's
    //              outfit/info via CharacterService.getInfo for teamA_0 display
    socket.emit('Room.joinedAsEnemy', {
      room_id:          room.id,
      host:             null,
      enemy_id:         room.host.socket.charId,
      mode:             room.mode,
      stage:            room.stage,
      password:         room.password,
      allow_scrolls:    room.allowScrolls,
      allow_spectators: room.allowSpectators,
    });
  }

  exitRoom(socket, silent = false) {
    const roomId = this._playerRoom.get(socket.charId);
    if (!roomId) return;

    const room = this._rooms.get(roomId);
    if (!room) {
      this._playerRoom.delete(socket.charId);
      return;
    }

    if (room.state === 'in_battle') {
      // Handled by battle disconnect
      return;
    }

    const isHost  = room.host?.socket.charId === socket.charId;
    const isEnemy = room.enemy?.socket.charId === socket.charId;

    if (isHost) {
      // Notify enemy if present
      if (room.enemy && !silent) {
        room.enemy.socket.emit('Room.kicked', { charId: socket.charId });
        this._playerRoom.delete(room.enemy.socket.charId);
      }
      this._destroyRoom(roomId);
    } else if (isEnemy) {
      room.enemy = null;
      room.state = 'waiting';
      this._playerRoom.delete(socket.charId);
      if (!silent) {
        room.host.socket.emit('Room.kicked', { charId: socket.charId });
      }
    }
  }

  kickPlayer(socket, data) {
    const roomId = this._playerRoom.get(socket.charId);
    if (!roomId) return;

    const room = this._rooms.get(roomId);
    if (!room || room.host.socket.charId !== socket.charId) return;
    if (!room.enemy) return;

    const kicked = room.enemy;
    room.enemy = null;
    room.state = 'waiting';
    this._playerRoom.delete(kicked.socket.charId);
    kicked.socket.emit('Room.kicked', { charId: kicked.socket.charId });
  }

  setReady(socket /*, data */) {
    const roomId = this._playerRoom.get(socket.charId);
    if (!roomId) return;

    const room = this._rooms.get(roomId);
    if (!room || room.state !== 'ready') return;

    const participant = this._getRoomParticipant(room, socket.charId);
    if (participant) participant.ready = true;

    // Check if all ready
    if (room.host.ready && room.enemy?.ready) {
      room.host.socket.emit('Room.allReady', {});
      room.enemy.socket.emit('Room.allReady', {});
    }
  }

  async getSkillsList(socket) {
    const roomId = this._playerRoom.get(socket.charId);
    if (!roomId) return;
    const room = this._rooms.get(roomId);
    if (!room) return;

    const participant = this._getRoomParticipant(room, socket.charId);
    if (!participant) return;

    // Full skill inventory from character_skills table
    let allSkills = [];
    try {
      const rows = await db.query(
        'SELECT skill_id FROM character_skills WHERE character_id = ?',
        [socket.charId]
      );
      allSkills = rows.map(r => r.skill_id).filter(Boolean);
    } catch (_) {}

    // Fall back to equipment_skills if the inventory table is empty
    if (allSkills.length === 0) {
      allSkills = (socket.character.equipment_skills || '')
        .split(',').map(s => s.trim()).filter(Boolean);
    }

    // Currently equipped skills (pre-selection for the skill picker UI)
    const equipped = (socket.character.equipment_skills || '')
      .split(',').map(s => s.trim()).filter(Boolean);

    socket.emit('Room.skills.list', allSkills);
    socket.emit('Room.skills.set',  equipped);
  }

  setSkills(socket, skills) {
    const roomId = this._playerRoom.get(socket.charId);
    if (!roomId) return;
    const room = this._rooms.get(roomId);
    if (!room) return;

    const participant = this._getRoomParticipant(room, socket.charId);
    if (!participant) return;

    if (!Array.isArray(skills)) return;
    participant.skills = skills.slice(0, 8);

    // Confirm back to socket
    socket.emit('Room.skills.set', participant.skills);
  }

  startCountdown(socket) {
    const roomId = this._playerRoom.get(socket.charId);
    if (!roomId) return;
    const room = this._rooms.get(roomId);
    if (!room || room.state !== 'ready') return;
    if (room.host.socket.charId !== socket.charId) return;

    const payload = { countdown: 6 };
    room.host.socket.emit('Room.countdown.start', payload);
    if (room.enemy) room.enemy.socket.emit('Room.countdown.start', payload);
  }

  startBattle(socket, roomId) {
    const room = typeof roomId === 'string'
      ? this._rooms.get(roomId)
      : this._rooms.get(this._playerRoom.get(socket.charId));

    if (!room) {
      socket.emit('Notification.flash', 'Room not found');
      return;
    }
    if (room.host.socket.charId !== socket.charId) return;
    if (!room.enemy) {
      socket.emit('Notification.flash', 'Waiting for opponent');
      return;
    }
    if (room.state === 'in_battle') return;

    room.state = 'in_battle';

    // Override equipment_skills with room-selected skills if player changed them
    const hostChar  = { ...room.host.socket.character };
    const enemyChar = { ...room.enemy.socket.character };
    if (room.host.skills.length  > 0) hostChar.equipment_skills  = room.host.skills.join(',');
    if (room.enemy.skills.length > 0) enemyChar.equipment_skills = room.enemy.skills.join(',');

    const battle = new Battle({
      host:  { socket: room.host.socket,  character: hostChar  },
      enemy: { socket: room.enemy.socket, character: enemyChar },
      room: {
        mode:         room.mode,
        stage:        room.stage,
        allowScrolls: room.allowScrolls,
      },
      onEnd: (b) => this._onBattleEnd(b, room.id),
    });

    this._battles.set(battle.id, battle);
    this._battleRoom.set(battle.id, room.id);
    room.battleId = battle.id;

    // Add any existing spectators
    for (const [, sock] of room.spectators) {
      battle.addSpectator(sock);
    }

    battle.start();
  }

  // ─────────────────────────────────────────────────────
  //  Spectator
  // ─────────────────────────────────────────────────────

  joinAsSpectator(socket, data) {
    const room = this._rooms.get(data.roomId || data.room_id);
    if (!room) {
      socket.emit('Battle.spectator.join.error', { message: 'Room not found' });
      return;
    }
    if (!room.allowSpectators) {
      socket.emit('Battle.spectator.join.error', { message: 'Spectators not allowed' });
      return;
    }
    if (room.password && room.password !== (data.password || '')) {
      socket.emit('Battle.spectator.join.error', { message: 'Wrong password' });
      return;
    }

    this._spectatorRoom.set(socket.id, room.id);
    room.spectators.set(socket.id, socket);

    // If battle is already running, attach to it
    if (room.battleId) {
      const battle = this._battles.get(room.battleId);
      if (battle) battle.addSpectator(socket);
    }
  }

  leaveSpectator(socket) {
    const roomId = this._spectatorRoom.get(socket.id);
    if (!roomId) return;

    const room = this._rooms.get(roomId);
    if (room) {
      room.spectators.delete(socket.id);
      if (room.battleId) {
        const battle = this._battles.get(room.battleId);
        if (battle) battle.removeSpectator(socket.id);
      }
    }
    this._spectatorRoom.delete(socket.id);
  }

  // ─────────────────────────────────────────────────────
  //  Live matches
  // ─────────────────────────────────────────────────────

  listLiveMatches(socket) {
    const matches = [];
    for (const battle of this._battles.values()) {
      if (battle.running) {
        matches.push(battle.getSummary());
      }
    }
    socket.emit('System.listLiveMatches', matches);
  }

  // ─────────────────────────────────────────────────────
  //  Chat in battle
  // ─────────────────────────────────────────────────────

  sendBattleMessage(socket, data) {
    // Delegated to Battle instance via its own socket listener
    // (kept here as a no-op stub for the index event binding)
  }

  // ─────────────────────────────────────────────────────
  //  Disconnect
  // ─────────────────────────────────────────────────────

  handleDisconnect(socket) {
    // Remove from spectator if applicable
    this.leaveSpectator(socket);

    const roomId = this._playerRoom.get(socket.charId);
    if (!roomId) return;

    const room = this._rooms.get(roomId);
    if (!room) return;

    if (room.state === 'in_battle' && room.battleId) {
      const battle = this._battles.get(room.battleId);
      if (battle) battle.handleDisconnect(socket);
    } else {
      this.exitRoom(socket, true);
    }
  }

  // ─────────────────────────────────────────────────────
  //  Internal helpers
  // ─────────────────────────────────────────────────────

  _onBattleEnd(battle, roomId) {
    this._battles.delete(battle.id);
    this._battleRoom.delete(battle.id);
    this._destroyRoom(roomId);
  }

  _destroyRoom(roomId) {
    const room = this._rooms.get(roomId);
    if (!room) return;

    if (room.host) this._playerRoom.delete(room.host.socket.charId);
    if (room.enemy) this._playerRoom.delete(room.enemy.socket.charId);

    for (const socketId of room.spectators.keys()) {
      this._spectatorRoom.delete(socketId);
    }

    this._rooms.delete(roomId);
  }

  _getRoomParticipant(room, charId) {
    if (room.host?.socket.charId  === charId) return room.host;
    if (room.enemy?.socket.charId === charId) return room.enemy;
    return null;
  }

  _scheduleRoomExpiry(roomId) {
    setTimeout(() => {
      const room = this._rooms.get(roomId);
      if (room && room.state === 'waiting') {
        room.host.socket.emit('Notification.flash', 'Room expired — no opponent joined');
        this._destroyRoom(roomId);
      }
    }, ROOM_EXPIRY_MS);
  }

  // Expose for MatchmakingQueue
  createMatchmadeRoom(hostSocket, enemySocket, mode) {
    const roomId = uuidv4();

    const room = {
      id:               roomId,
      host:             { socket: hostSocket,  character: hostSocket.character,  ready: true, skills: [] },
      enemy:            { socket: enemySocket, character: enemySocket.character, ready: true, skills: [] },
      password:         '',
      mode,
      stage:            this._randomStage(),
      allowScrolls:     false,
      allowSpectators:  true,
      spectators:       new Map(),
      state:            'ready',
      createdAt:        Date.now(),
    };

    this._rooms.set(roomId, room);
    this._playerRoom.set(hostSocket.charId,  roomId);
    this._playerRoom.set(enemySocket.charId, roomId);

    // Host: host=hostCharId (truthy) → setupRoomAsHost(); enemy_id=enemyCharId for getEnemyData
    hostSocket.emit('Room.created', {
      room_id:          roomId,
      host:             hostSocket.charId,
      enemy_id:         enemySocket.charId,
      mode:             room.mode,
      stage:            room.stage,
      password:         '',
      allow_scrolls:    false,
      allow_spectators: true,
    });

    // Enemy: host=null (falsy) → setupRoomAsEnemy(); enemy_id=hostCharId for onGetHostInfo
    enemySocket.emit('Room.joinedAsEnemy', {
      room_id:          roomId,
      host:             null,
      enemy_id:         hostSocket.charId,
      mode:             room.mode,
      stage:            room.stage,
      password:         '',
      allow_scrolls:    false,
      allow_spectators: true,
    });

    // Auto-start after 6s countdown
    const countdown = { countdown: 6 };
    hostSocket.emit('Room.countdown.start',  countdown);
    enemySocket.emit('Room.countdown.start', countdown);

    setTimeout(() => {
      if (this._rooms.has(roomId)) {
        this.startBattle(hostSocket, roomId);
      }
    }, 6500);
  }

  _randomStage() {
    const stages = [
      'mission_1011', 'mission_1012', 'mission_1021',
      'mission_1031', 'mission_1041', 'mission_1051',
    ];
    return stages[Math.floor(Math.random() * stages.length)];
  }
}

module.exports = RoomManager;
