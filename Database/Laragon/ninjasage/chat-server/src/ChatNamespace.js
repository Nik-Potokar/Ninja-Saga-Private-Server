'use strict';

const auth         = require('./auth');
const config       = require('./config');
const messageStore = require('./messageStore');

/**
 * Manages one Socket.IO chat namespace (e.g. /global-chat or /clan-chat).
 *
 * Flash client protocol (RealtimeChat.as / WorldChat.as):
 *
 *  Client → Server events:
 *    auth         {character_id, session_key}
 *    open-chatbox []
 *    sendMessage  "message text"
 *
 *  Server → Client events:
 *    nickname-colors  {charId: "#rrggbb", ...}
 *    online-users     {total: N, users: [{id, name}, ...]}
 *    history          [{character, message}, ...]
 *    message          {character, message}
 *    announcement     "text"
 */
class ChatNamespace {
  constructor(nsp, { isClan = false } = {}) {
    this.nsp    = nsp;
    this.isClan = isClan;

    // In-memory cache (populated from DB on startup / first clan join)
    this.history       = [];
    this.clanHistories = {}; // clanRoom -> message[]

    this.nicknameColors = {};

    // Preload global-chat history so the first connector gets it instantly
    if (!isClan) {
      messageStore.load('global').then(msgs => {
        this.history = msgs;
      }).catch(() => {}); // errors already logged inside load()
    }

    nsp.on('connection', (socket) => this._onConnection(socket));
  }

  // ─── Connection ────────────────────────────────────────────────────────────

  _onConnection(socket) {
    console.log(`[Chat:${this.nsp.name}] connected ${socket.id}`);

    socket.on('auth',         (data)   => this._onAuth(socket, data));
    socket.on('open-chatbox', ()       => this._onOpenChatbox(socket));
    socket.on('sendMessage',  (msg)    => this._onSendMessage(socket, msg));
    socket.on('disconnect',   (reason) => this._onDisconnect(socket, reason));
  }

  // ─── Auth ──────────────────────────────────────────────────────────────────
  //
  // Flash sends `auth` and `open-chatbox` back-to-back immediately after
  // connecting. Because _processAuth awaits DB queries, open-chatbox can
  // arrive while auth is still in flight. We store _authDone so that
  // _onOpenChatbox can await it before trying to read socket.charId.

  _onAuth(socket, data) {
    socket._authDone = this._processAuth(socket, data);
  }

  async _processAuth(socket, data) {
    try {
      const result = await auth.validateSession(data?.character_id, data?.session_key);
      if (!result.valid) {
        console.warn(`[Chat:${this.nsp.name}] auth failed for char ${data?.character_id}: ${result.error}`);
        socket.disconnect(true);
        return;
      }

      socket.character = result.character;
      socket.charId    = result.character.id;
      socket.lastMsgAt = 0;

      this.nicknameColors[socket.charId] = this._colorFromId(socket.charId);

      if (this.isClan) {
        if (!result.character.clan_id) {
          socket.disconnect(true);
          return;
        }
        const room = `clan_${result.character.clan_id}`;
        socket.join(room);
        socket.clanRoom = room;

        // Load clan history from DB the first time this room is used
        if (!this.clanHistories[room]) {
          this.clanHistories[room] = await messageStore.load(room);
        }
      }

      console.log(`[Chat:${this.nsp.name}] char ${socket.charId} (${result.character.name}) joined`);
      this._broadcastOnlineUsers();
    } catch (err) {
      console.error(`[Chat:${this.nsp.name}] auth error:`, err.message);
      socket.disconnect(true);
    }
  }

  // ─── Open chatbox ──────────────────────────────────────────────────────────

  async _onOpenChatbox(socket) {
    // Wait for auth to finish (it may still be awaiting its DB query)
    if (socket._authDone) await socket._authDone;

    if (!socket.charId) return; // auth failed or disconnected

    socket.emit('nickname-colors', this.nicknameColors);
    socket.emit('online-users', this._buildOnlineUsers(socket));

    // History is preloaded (global: constructor, clan: _processAuth).
    // Fall back to a fresh DB load in the unlikely edge case it's missing.
    let hist;
    if (this.isClan && socket.clanRoom) {
      if (!this.clanHistories[socket.clanRoom]) {
        this.clanHistories[socket.clanRoom] = await messageStore.load(socket.clanRoom);
      }
      hist = this.clanHistories[socket.clanRoom];
    } else {
      hist = this.history;
    }

    socket.emit('history', hist);
  }

  // ─── Incoming message ──────────────────────────────────────────────────────

  _onSendMessage(socket, text) {
    if (!socket.charId) return;
    if (typeof text !== 'string' || !text.trim()) return;

    const now = Date.now();
    if (now - socket.lastMsgAt < config.messageCooldownMs) return;
    socket.lastMsgAt = now;

    const clean = text.trim().slice(0, config.maxMessageLength);

    const msg = {
      character: {
        id:      socket.character.id,
        name:    socket.character.name,
        level:   socket.character.level,
        rank:    socket.character.rank,
        premium: socket.character.premium,
      },
      message: clean,
    };

    this._pushHistory(socket, msg);

    if (this.isClan && socket.clanRoom) {
      this.nsp.to(socket.clanRoom).emit('message', msg);
    } else {
      this.nsp.emit('message', msg);
    }
  }

  // ─── Disconnect ────────────────────────────────────────────────────────────

  _onDisconnect(socket, reason) {
    console.log(`[Chat:${this.nsp.name}] disconnected ${socket.id} (${reason})`);
    if (socket.charId) {
      delete this.nicknameColors[socket.charId];
      this._broadcastOnlineUsers();
    }
  }

  // ─── Helpers ───────────────────────────────────────────────────────────────

  _broadcastOnlineUsers() {
    if (this.isClan) {
      const byRoom = {};
      for (const [, socket] of this.nsp.sockets) {
        if (!socket.charId || !socket.clanRoom) continue;
        if (!byRoom[socket.clanRoom]) byRoom[socket.clanRoom] = [];
        byRoom[socket.clanRoom].push(socket);
      }
      for (const [room, sockets] of Object.entries(byRoom)) {
        this.nsp.to(room).emit('online-users', this._buildOnlineUsersFromList(sockets));
      }
    } else {
      const allSockets = [...this.nsp.sockets.values()].filter(s => s.charId);
      this.nsp.emit('online-users', this._buildOnlineUsersFromList(allSockets));
    }
  }

  _buildOnlineUsers(socket) {
    if (this.isClan && socket.clanRoom) {
      const room    = this.nsp.adapter.rooms.get(socket.clanRoom);
      const sockets = room
        ? [...room].map(id => this.nsp.sockets.get(id)).filter(s => s?.charId)
        : [];
      return this._buildOnlineUsersFromList(sockets);
    }
    const allSockets = [...this.nsp.sockets.values()].filter(s => s.charId);
    return this._buildOnlineUsersFromList(allSockets);
  }

  _buildOnlineUsersFromList(sockets) {
    const users = sockets.map(s => ({ id: s.character.id, name: s.character.name }));
    return { total: users.length, users };
  }

  /** Channel name used as the key in chat_messages.channel */
  _getChannel(socket) {
    return (this.isClan && socket.clanRoom) ? socket.clanRoom : 'global';
  }

  _pushHistory(socket, msg) {
    // Persist to DB (fire-and-forget; errors logged inside save())
    messageStore.save(this._getChannel(socket), msg);

    // Update in-memory cache
    if (this.isClan && socket.clanRoom) {
      if (!this.clanHistories[socket.clanRoom]) {
        this.clanHistories[socket.clanRoom] = [];
      }
      const h = this.clanHistories[socket.clanRoom];
      if (h.length >= config.maxHistory) h.shift();
      h.push(msg);
    } else {
      if (this.history.length >= config.maxHistory) this.history.shift();
      this.history.push(msg);
    }
  }

  /**
   * Generate a deterministic, visually distinct hex color from a character ID.
   */
  _colorFromId(id) {
    const hue = (id * 137.508) % 360;
    return this._hslToHex(hue, 65, 45);
  }

  _hslToHex(h, s, l) {
    s /= 100;
    l /= 100;
    const a = s * Math.min(l, 1 - l);
    const f = (n) => {
      const k     = (n + h / 30) % 12;
      const color = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);
      return Math.round(255 * color).toString(16).padStart(2, '0');
    };
    return `${f(0)}${f(8)}${f(4)}`;
  }

  announce(text) {
    const systemMsg = {
      character: {
        id:      0,
        name:    '[System]',
        level:   0,
        rank:    0,
        premium: 0,
      },
      message: text,
    };

    // Pop-up notification for all live clients (existing behavior)
    this.nsp.emit('announcement', text);

    // Also inject as a regular chat message so it appears in the chat window
    this.nsp.emit('message', systemMsg);

    // Persist to DB and update in-memory caches
    if (this.isClan) {
      // Save to every clan room that is currently loaded in memory
      for (const [room, hist] of Object.entries(this.clanHistories)) {
        messageStore.save(room, systemMsg);
        if (hist.length >= config.maxHistory) hist.shift();
        hist.push(systemMsg);
      }
    } else {
      messageStore.save('global', systemMsg);
      if (this.history.length >= config.maxHistory) this.history.shift();
      this.history.push(systemMsg);
    }
  }
}

module.exports = ChatNamespace;