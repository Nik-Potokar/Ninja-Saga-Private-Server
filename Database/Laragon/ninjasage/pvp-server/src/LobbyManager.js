'use strict';

const MAX_CHAT_HISTORY = 200;

/**
 * Manages the lobby: connected players, active-player count, and lobby chat.
 */
class LobbyManager {
  constructor(namespace) {
    this._ns = namespace;
    // charId (number) -> socket
    this._players = new Map();
    this._chatHistory = [];
  }

  addPlayer(socket) {
    const charId = socket.charId;
    if (!charId) return;

    // If the same character reconnects, remove old socket first
    if (this._players.has(charId)) {
      const old = this._players.get(charId);
      if (old.id !== socket.id) {
        old.emit('Notification.disconnect', 'Logged in from another location');
        old.disconnect(true);
      }
    }

    this._players.set(charId, socket);

    // Send chat history to newly connected player
    socket.emit('Conversation.lobby.messageHistory', this._chatHistory);
  }

  removePlayer(socket) {
    const charId = socket.charId;
    if (!charId) return;
    // Only remove if this is the current socket for the character
    if (this._players.get(charId) === socket) {
      this._players.delete(charId);
    }
  }

  getActivePlayerCount() {
    return this._players.size;
  }

  sendMessage(socket, message) {
    if (!socket.charId || !message) return;
    if (typeof message !== 'string') return;

    const msg = {
      character: { id: socket.charId, name: socket.character.name },
      message: String(message).substring(0, 200),
    };

    this._chatHistory.push(msg);
    if (this._chatHistory.length > MAX_CHAT_HISTORY) {
      this._chatHistory.shift();
    }

    this._ns.emit('Conversation.lobby.newMessage', msg);
  }

  getSocket(charId) {
    return this._players.get(charId) || null;
  }
}

module.exports = LobbyManager;
