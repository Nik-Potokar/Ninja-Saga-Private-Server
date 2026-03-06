'use strict';

module.exports = {
  port: parseInt(process.env.PORT || '3000', 10),

  db: {
    host:     process.env.DB_HOST     || '127.0.0.1',
    port:     parseInt(process.env.DB_PORT || '3306', 10),
    database: process.env.DB_NAME     || 'ninjasage',
    user:     process.env.DB_USER     || 'root',
    password: process.env.DB_PASS     || '',
  },

  // Seconds a player has to take their turn
  turnDuration: parseInt(process.env.TURN_DURATION || '30', 10),

  // Maximum rounds (ambushes) before battle ends as draw
  maxRounds: parseInt(process.env.MAX_ROUNDS || '50', 10),

  // Trophy floor — trophies never go below this
  trophyFloor: parseInt(process.env.TROPHY_FLOOR || '0', 10),

  // Elo K-factor for ranked trophy changes (fallback; DB value takes priority at runtime)
  trophyKFactor: parseInt(process.env.TROPHY_K_FACTOR || '32', 10),

  // Trophy change for casual mode (flat)
  casualTrophyChange: 0,

  // PvP points awarded per win / deducted per loss (fallback; DB value takes priority)
  pvpPointsWin:  parseInt(process.env.PVP_POINTS_WIN  || '10', 10),
  pvpPointsLose: parseInt(process.env.PVP_POINTS_LOSE || '0',  10),

  // Prestige awarded per ranked PvP win (fallback; DB value takes priority)
  pvpPrestigeWin: parseInt(process.env.PVP_PRESTIGE_WIN || '10', 10),
};
