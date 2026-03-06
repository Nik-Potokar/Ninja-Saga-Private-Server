# Ninja Sage — Live PVP Socket.IO Server

Real-time PVP battle server for Ninja Sage. Handles all live game events via
Socket.IO; the Laravel server handles persistence (leaderboard, battle history,
character stats) via direct DB access.

---

## Architecture

```
Flash Client  ←──Socket.IO(/pvp)──→  pvp-server (Node.js)
                                            │
                                            └──MySQL──→  Laravel DB
```

- **Port**: 3000 (default, change via `PORT` env var)
- **Namespace**: `/pvp`
- **Protocol**: Socket.IO v4 (WebSocket + long-poll)

The Flash client resolves the server URL from `Character.pvp_socket`, which
Laravel's `SystemLogin.checkVersion` AMF response populates from the
`PVP_SOCKET_URL` env var in the Laravel `.env`.

---

## Quick start

```bash
cd pvp-server
cp .env.example .env
# Edit .env — set DB credentials to match Laravel's .env
npm install
npm start
```

For development with auto-reload:
```bash
npm run dev
```

---

## Configuration (`.env`)

| Key             | Default                     | Description                              |
|-----------------|-----------------------------|------------------------------------------|
| `PORT`          | `3000`                      | TCP port to listen on                    |
| `DB_HOST`       | `127.0.0.1`                 | MySQL host (same DB as Laravel)          |
| `DB_PORT`       | `3306`                      | MySQL port                               |
| `DB_NAME`       | `ninjasage`                 | Database name                            |
| `DB_USER`       | `root`                      | Database username                        |
| `DB_PASS`       | *(empty)*                   | Database password                        |
| `TURN_DURATION` | `30`                        | Seconds per turn before auto-skip        |
| `MAX_ROUNDS`    | `50`                        | Max rounds before draw                   |
| `TROPHY_FLOOR`  | `0`                         | Minimum trophy count (can't go below)    |

In **Laravel's `.env`** also set:
```
PVP_SOCKET_URL=http://YOUR_SERVER_IP:3000/pvp
```

---

## Socket.IO events reference

### Client → Server

| Event                          | Payload                                      | Description              |
|-------------------------------|----------------------------------------------|--------------------------|
| `System.auth`                 | `{character_id, session_key, ver}`           | Authenticate on connect  |
| `System.listLiveMatches`      | *(none)*                                     | Get list of live battles |
| `Room.create`                 | `{settings:{mode,stage,...}, password}`      | Create a battle room     |
| `Room.join`                   | `{id, password}`                             | Join a room              |
| `Room.exit`                   | *(none)*                                     | Leave current room       |
| `Room.ready`                  | *(none)*                                     | Mark self as ready       |
| `Room.kick`                   | `{charId}`                                   | Kick player (host only)  |
| `Room.skills.list`            | *(none)*                                     | Get equipped skills      |
| `Room.skills.set`             | `[skill_id, ...]`                            | Set battle skills        |
| `Room.countdown.start`        | *(none)*                                     | Start pre-battle timer   |
| `Battle.start`                | `room_id`                                    | Start battle (host only) |
| `Battle.startMatchMaking`     | `{mode:"ranked"\|"casual"}`                  | Join matchmaking queue   |
| `Battle.stopMatchMaking`      | *(none)*                                     | Leave queue              |
| `Battle.spectator.join`       | `{roomId, password}`                         | Spectate a battle        |
| `Battle.spectator.leave`      | *(none)*                                     | Stop spectating          |
| `Battle.action.weapon`        | `{battle_id}`                                | Weapon attack            |
| `Battle.action.skill`         | `{battle_id, skillId}`                       | Use a skill              |
| `Battle.action.dodge`         | `{battle_id}`                                | Dodge stance             |
| `Battle.action.charge`        | `{battle_id}`                                | Charge chakra            |
| `Battle.action.scroll`        | `{battle_id, scroll_id}`                     | Use a scroll/item        |
| `Battle.action.run`           | `{battle_id}`                                | Forfeit battle           |
| `Battle.action.timeout`       | `{battle_id}`                                | Turn timed out           |
| `Battle.action.finished`      | `{battle_id, action}`                        | Animation complete       |
| `Conversation.lobby.sendMessage` | `"message text"`                          | Lobby chat               |
| `Conversation.battle.sendMessage`| `{message, battle_id}`                    | Battle chat              |

### Server → Client

| Event                          | Payload                                      | Description              |
|-------------------------------|----------------------------------------------|--------------------------|
| `System.mapIDs`               | `[mission_id, ...]`                          | Available stage IDs      |
| `System.activePlayers`        | `number`                                     | Online player count      |
| `System.listLiveMatches`      | `[{battleId, hostId, enemyId, ...}]`         | Live battles             |
| `Room.created`                | `{room_id, host, mode, stage, ...}`          | Room created (host)      |
| `Room.joinedAsEnemy`          | `{room_id, host, enemy_id, ...}`             | Room joined (enemy)      |
| `Room.newPlayerJoined`        | `{enemy_id}`                                 | Opponent entered room    |
| `Room.kicked`                 | `{charId}`                                   | Player was kicked        |
| `Room.allReady`               | *(none)*                                     | Both players ready       |
| `Room.skills.list`            | `[skill_id, ...]`                            | Character's skill list   |
| `Room.skills.set`             | `[skill_id, ...]`                            | Confirmed skill selection|
| `Room.countdown.start`        | `{countdown: 6}`                             | Start 6s countdown       |
| `Battle.started`              | `{battleId, background, hostId, enemyId}`    | Battle begins            |
| `Battle.stopMatchMaking`      | *(none)*                                     | Removed from queue       |
| `Battle.action.ambush`        | `{id: char_id}`                              | Whose turn it is         |
| `Battle.action.weapon`        | `{id, stat, targets, dodged, crit, stats}`   | Weapon attack result     |
| `Battle.action.skill`         | `{id, skillId, stat, targets, dodged, stats}`| Skill result             |
| `Battle.action.dodge`         | `{id}`                                       | Dodge action             |
| `Battle.action.charge`        | `{id, stat}`                                 | Charge result            |
| `Battle.action.scroll`        | `{id, stats}`                                | Scroll result            |
| `Battle.action.skip`          | `{id, effect_name?}`                         | Skipped turn             |
| `Battle.action.run`           | `{id}`                                       | Player ran               |
| `Battle.updateInfo`           | `{id, skillCooldowns, stat}`                 | Cooldown/stat update     |
| `Battle.ended`                | `{id, won, trophy, rewards:{gold,xp,...}}`   | Battle result            |
| `Battle.spectator.count`      | `number`                                     | Spectator count          |
| `Conversation.lobby.messageHistory` | `[{character,message}]`               | Chat history on join     |
| `Conversation.lobby.newMessage`     | `{character,message}`                 | New lobby chat           |
| `Conversation.battle.newMessage`    | `{character,message}`                 | New battle chat          |
| `Notification.flash`          | `"message"`                                  | Short notification       |
| `Notification.disconnect`     | `"reason"`                                   | Force-disconnect reason  |
| `Notification.queue`          | `"position string"`                          | Matchmaking status       |

---

## Stat formulas

Mirrors `Managers/StatManager.as` from the Flash client:

```
max_hp    = 60 + level × 40 + point_earth × 30
max_cp    = 60 + level × 40 + point_water × 30
agility   = 9  + level      + point_wind
critical% = 5  + point_lightning × 0.4
dodge%    = 5  + point_wind      × 0.4

atk (server)  = level × 5 + (point_fire + point_lightning) × 2
def (server)  = level × 2 + (point_earth + point_water)
```

---

## Trophy system

Elo-style with K-factor 32:

```
expected    = 1 / (1 + 10^((loserTrophy − winnerTrophy) / 400))
trophyDelta = round(32 × (1 − expected))   // minimum 1
```

Casual mode: no trophy change.
