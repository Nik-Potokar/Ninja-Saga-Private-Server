'use strict';

require('dotenv').config();

const { createServer } = require('http');
const { Server }       = require('socket.io');
const config           = require('./src/config');
const auth             = require('./src/auth');
const { buildCharacterInfoPayload } = require('./src/characterInfo');
const LobbyManager     = require('./src/LobbyManager');
const RoomManager      = require('./src/RoomManager');
const MatchmakingQueue = require('./src/MatchmakingQueue');
const SkillData        = require('./src/SkillData');

// Load skill + skill-effect data from the compiled game-data bin files.
// This must run before any Battle is created so that StatsCalc can look up
// real CP costs, cooldowns, skill_damage values, and effect lists.
SkillData.load();

// ─── HTTP server ───────────────────────────────────────────────────────────────
const httpServer = createServer((req, res) => {
  // Basic health-check endpoint
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'ok', uptime: process.uptime() }));
    return;
  }
  res.writeHead(404);
  res.end();
});

// ─── Socket.IO ─────────────────────────────────────────────────────────────────
const io = new Server(httpServer, {
  cors: {
    origin: '*',
    methods: ['GET', 'POST'],
  },
  // The Flash client uses Engine.io (Skein library) — disable HTTP long-poll fallback
  transports: ['websocket', 'polling'],
});

// ─── /pvp namespace ────────────────────────────────────────────────────────────
const pvp = io.of('/pvp');

const lobbyManager     = new LobbyManager(pvp);
const roomManager      = new RoomManager(pvp, lobbyManager);
const matchmakingQueue = new MatchmakingQueue(pvp, roomManager);

pvp.on('connection', (socket) => {
  console.log(`[PVP] socket connected ${socket.id}`);

  // ── Authentication ──────────────────────────────────────────────────────────
  socket.on('System.auth', async (data) => {
    try {
      const result = await auth.validateSession(data?.character_id, data?.session_key);
      if (!result.valid) {
        console.warn(`[PVP] auth failed for char ${data?.character_id}: ${result.error}`);
        socket.emit('Notification.disconnect', result.error || 'Authentication failed');
        socket.disconnect(true);
        return;
      }

      socket.charId    = parseInt(result.character.id, 10);  // must be a Number for AS3 `is Number` checks
      socket.character = result.character;

      console.log(`[PVP] char ${socket.charId} (${result.character.name}) authenticated`);

      lobbyManager.addPlayer(socket);

      // Send character data so the client can populate the profile UI and
      // set pvp.character (required before entering any room).
      // PvPLobby.as listens on "Client.characterInfo" and feeds this into
      // CharacterManager which handles nameTxt, txt_lvl, txt_hp, outfit, etc.
      socket.emit('Client.characterInfo', buildCharacterInfoPayload(result.character));

      socket.emit('System.mapIDs', STAGE_IDS);
      pvp.emit('System.activePlayers', lobbyManager.getActivePlayerCount());
    } catch (err) {
      console.error('[PVP] auth error:', err.message);
      socket.emit('Notification.disconnect', 'Server error during authentication');
      socket.disconnect(true);
    }
  });

  // ── Room events ─────────────────────────────────────────────────────────────
  socket.on('Room.create',          (data) => guardAuth(socket, () => roomManager.createRoom(socket, data)));
  socket.on('Room.join',            (data) => guardAuth(socket, () => roomManager.joinRoom(socket, data)));
  socket.on('Room.exit',            ()     => guardAuth(socket, () => roomManager.exitRoom(socket)));
  socket.on('Room.ready',           (data) => guardAuth(socket, () => roomManager.setReady(socket, data)));
  socket.on('Room.kick',            (data) => guardAuth(socket, () => roomManager.kickPlayer(socket, data)));
  socket.on('Room.skills.list',     ()     => guardAuth(socket, () => roomManager.getSkillsList(socket)));
  socket.on('Room.skills.set',      (data) => guardAuth(socket, () => roomManager.setSkills(socket, data)));
  socket.on('Room.countdown.start', ()     => guardAuth(socket, () => roomManager.startCountdown(socket)));

  // ── Matchmaking ─────────────────────────────────────────────────────────────
  socket.on('Battle.startMatchMaking', (data) => guardAuth(socket, () => matchmakingQueue.join(socket, data)));
  socket.on('Battle.stopMatchMaking',  ()     => guardAuth(socket, () => matchmakingQueue.leave(socket)));

  // ── Battle start ────────────────────────────────────────────────────────────
  socket.on('Battle.start', (roomId) => guardAuth(socket, () => roomManager.startBattle(socket, roomId)));

  // ── Spectator ───────────────────────────────────────────────────────────────
  socket.on('Battle.spectator.join',  (data) => guardAuth(socket, () => roomManager.joinAsSpectator(socket, data)));
  socket.on('Battle.spectator.leave', ()     => guardAuth(socket, () => roomManager.leaveSpectator(socket)));

  // ── Live match list ─────────────────────────────────────────────────────────
  socket.on('System.listLiveMatches', () => guardAuth(socket, () => roomManager.listLiveMatches(socket)));

  // ── Chat ────────────────────────────────────────────────────────────────────
  socket.on('Conversation.lobby.sendMessage', (msg) =>
    guardAuth(socket, () => lobbyManager.sendMessage(socket, msg)));

  // Battle chat is handled inside Battle.js via its own socket.on bindings.
  // The stub below keeps backwards-compat in case the event fires before a
  // battle's listeners are attached.
  socket.on('Conversation.battle.sendMessage', () => {});

  // ── Disconnect ──────────────────────────────────────────────────────────────
  socket.on('disconnect', (reason) => {
    console.log(`[PVP] socket disconnected ${socket.id} (${reason})`);
    if (socket.charId) {
      lobbyManager.removePlayer(socket);
      roomManager.handleDisconnect(socket);
      matchmakingQueue.leave(socket);
      pvp.emit('System.activePlayers', lobbyManager.getActivePlayerCount());
    }
  });
});

// ─── Helpers ───────────────────────────────────────────────────────────────────
function guardAuth(socket, fn) {
  if (!socket.charId) {
    socket.emit('Notification.disconnect', 'Not authenticated');
    socket.disconnect(true);
    return;
  }
  try {
    fn();
  } catch (err) {
    console.error(`[PVP] unhandled error for char ${socket.charId}:`, err);
  }
}

// Mission stage IDs available for PVP backgrounds
const STAGE_IDS = [
  'mission_1011', 'mission_1012', 'mission_1021',
  'mission_1031', 'mission_1041', 'mission_1051',
  'mission_2011', 'mission_2021', 'mission_3011',
];

// ─── Start ─────────────────────────────────────────────────────────────────────
httpServer.listen(config.port, () => {
  console.log(`[PVP] Socket.IO server listening on port ${config.port}`);
  console.log(`[PVP] Namespace: /pvp`);
  console.log(`[PVP] Turn duration: ${config.turnDuration}s`);
  console.log(`[PVP] Max rounds: ${config.maxRounds}`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
  console.log('[PVP] SIGTERM received, shutting down…');
  matchmakingQueue.destroy();
  httpServer.close(() => process.exit(0));
});
