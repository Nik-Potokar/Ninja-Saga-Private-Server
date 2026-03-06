'use strict';

const config = require('../config');

/**
 * Elo-style trophy system.
 *
 * expected = 1 / (1 + 10^((opponentTrophy - playerTrophy) / 400))
 * change   = K * (outcome - expected)    outcome: 1=win, 0=loss
 */
function calcTrophyDelta(winnerTrophy, loserTrophy, kFactor) {
  const K = kFactor != null ? kFactor : config.trophyKFactor;
  const expected = 1 / (1 + Math.pow(10, (loserTrophy - winnerTrophy) / 400));
  const delta = Math.round(K * (1 - expected));
  return Math.max(1, delta);   // always at least 1 trophy
}

/**
 * Returns { winnerDelta, loserDelta, winnerAfter, loserAfter }.
 * Loser trophy is floored at trophyFloor (defaults to config value).
 */
function applyTrophies(winnerTrophy, loserTrophy, kFactor, trophyFloor) {
  const delta = calcTrophyDelta(winnerTrophy, loserTrophy, kFactor);
  const floor = trophyFloor != null ? trophyFloor : config.trophyFloor;

  const winnerAfter = winnerTrophy + delta;
  const loserAfter  = Math.max(floor, loserTrophy - delta);

  return {
    delta,
    winnerAfter,
    loserAfter,
    winnerDeltaText: `+${delta}`,
    loserDeltaText:  `-${delta}`,
  };
}

module.exports = { calcTrophyDelta, applyTrophies };
