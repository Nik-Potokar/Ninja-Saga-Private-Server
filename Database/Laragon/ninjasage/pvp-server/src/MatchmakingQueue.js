'use strict';

const MATCH_INTERVAL_MS  = 3000;   // poll for matches every 3 seconds
const TROPHY_RANGE_INIT  = 100;    // initial trophy range
const TROPHY_RANGE_GROW  = 50;     // grow by 50 per 3s tick
const TROPHY_RANGE_MAX   = 10000;  // match anyone after ~5 minutes

/**
 * Manages ranked and casual matchmaking queues.
 *
 * Each queue entry:
 *   { socket, mode, trophy, joinedAt, ticks }
 */
class MatchmakingQueue {
  constructor(namespace, roomManager) {
    this._ns          = namespace;
    this._roomManager = roomManager;

    // mode -> entry[]
    this._queues = {
      ranked: [],
      casual: [],
    };

    this._interval = setInterval(() => this._tick(), MATCH_INTERVAL_MS);
  }

  join(socket, data) {
    if (!socket.charId) return;

    const mode = (data?.mode === 'casual') ? 'casual' : 'ranked';

    // Already in queue?
    if (this._findEntry(socket.charId)) {
      socket.emit('Notification.flash', 'Already in matchmaking queue');
      return;
    }

    const entry = {
      socket,
      mode,
      trophy:   socket.character.pvp_trophy || 0,
      joinedAt: Date.now(),
      ticks:    0,
    };

    this._queues[mode].push(entry);
    socket.emit('Notification.queue', `Searching for ${mode} opponent…`);
  }

  leave(socket) {
    if (!socket.charId) return;
    this._removeFromQueue(socket.charId);
    socket.emit('Battle.stopMatchMaking', {});
  }

  _tick() {
    for (const mode of ['ranked', 'casual']) {
      const queue = this._queues[mode];

      // Increment ticks on all entries
      for (const entry of queue) entry.ticks++;

      // Try to match pairs
      let i = 0;
      while (i < queue.length) {
        const seeker = queue[i];
        const range  = Math.min(
          TROPHY_RANGE_INIT + seeker.ticks * TROPHY_RANGE_GROW,
          TROPHY_RANGE_MAX
        );

        // Find the best opponent (closest trophy within range)
        let bestIdx   = -1;
        let bestDiff  = Infinity;

        for (let j = 0; j < queue.length; j++) {
          if (j === i) continue;
          const diff = Math.abs(queue[j].trophy - seeker.trophy);
          if (diff <= range && diff < bestDiff) {
            bestDiff = diff;
            bestIdx  = j;
          }
        }

        if (bestIdx >= 0) {
          const opponent = queue[bestIdx];

          // Remove both from queue (higher index first to preserve lower index)
          const idxA = Math.min(i, bestIdx);
          const idxB = Math.max(i, bestIdx);
          queue.splice(idxB, 1);
          queue.splice(idxA, 1);

          this._matchPlayers(seeker, opponent, mode);
          // Don't increment i — the splice shifted entries
        } else {
          i++;
        }
      }
    }
  }

  _matchPlayers(a, b, mode) {
    // Notify both before creating room
    a.socket.emit('Notification.flash', 'Opponent found!');
    b.socket.emit('Notification.flash', 'Opponent found!');

    this._roomManager.createMatchmadeRoom(a.socket, b.socket, mode);
  }

  _findEntry(charId) {
    for (const queue of Object.values(this._queues)) {
      const entry = queue.find(e => e.socket.charId === charId);
      if (entry) return entry;
    }
    return null;
  }

  _removeFromQueue(charId) {
    for (const queue of Object.values(this._queues)) {
      const idx = queue.findIndex(e => e.socket.charId === charId);
      if (idx >= 0) {
        queue.splice(idx, 1);
        return;
      }
    }
  }

  destroy() {
    clearInterval(this._interval);
  }
}

module.exports = MatchmakingQueue;
