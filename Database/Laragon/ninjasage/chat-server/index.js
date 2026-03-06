'use strict';

require('dotenv').config();

const net              = require('net');
const { createServer } = require('http');
const { Server }       = require('socket.io');
const config           = require('./src/config');
const ChatNamespace    = require('./src/ChatNamespace');
const messageStore     = require('./src/messageStore');

// Flash Player cross-domain policy — served over HTTP for URLLoader (polling)
// AND served over raw TCP on port 843 for flash.net.Socket (websocket)
const CROSSDOMAIN_XML = `<?xml version="1.0"?>
<!DOCTYPE cross-domain-policy SYSTEM "http://www.adobe.com/xml/dtds/cross-domain-policy.dtd">
<cross-domain-policy>
  <allow-access-from domain="*" to-ports="*" secure="false"/>
  <allow-http-request-headers-from domain="*" headers="*" secure="false"/>
</cross-domain-policy>`;

// ─── Flash socket policy server (port 843) ──────────────────────────────────
// Flash Player checks port 843 for a socket policy BEFORE allowing any
// flash.net.Socket connection. Without this, raw WebSocket (Worlize library)
// is silently blocked by Flash's security sandbox — zero requests ever reach
// port 3002 and Flash fires SecurityErrorEvent immediately.
const policyServer = net.createServer(socket => {
  socket.setTimeout(3000, () => socket.destroy());
  socket.once('data', data => {
    if (data.toString().includes('<policy-file-request/>')) {
      socket.write(CROSSDOMAIN_XML + '\0');
    }
    socket.destroy();
  });
});
policyServer.listen(843, () => {
  console.log('[Chat] Flash socket policy server listening on port 843');
});
policyServer.on('error', err => {
  // On Linux port 843 requires root; log a warning and continue without it.
  // On Windows (Laragon) this should succeed without elevated privileges.
  console.warn('[Chat] Warning: could not bind port 843 for socket policy:', err.message);
});

// Namespace references — populated after messageStore.init() resolves.
// Used by the /admin/announce endpoint so the HTTP handler can reach them.
let globalNs = null;
let clanNs   = null;

// ─── HTTP server ────────────────────────────────────────────────────────────
const httpServer = createServer((req, res) => {
  console.log(`[Chat] HTTP ${req.method} ${req.url}`);

  if (req.url === '/crossdomain.xml') {
    res.writeHead(200, { 'Content-Type': 'text/x-cross-domain-policy' });
    res.end(CROSSDOMAIN_XML);
    return;
  }

  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'ok', uptime: process.uptime() }));
    return;
  }

  // ── Admin: broadcast an announcement to all connected chat users ──────────
  // POST /admin/announce   { channel: "global"|"clan", text: "..." }
  // Requires header:  X-Admin-Secret: <CHAT_ADMIN_SECRET>
  if (req.method === 'POST' && req.url === '/admin/announce') {
    const secret = config.adminSecret;
    if (!secret || req.headers['x-admin-secret'] !== secret) {
      res.writeHead(401, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ error: 'Unauthorized' }));
      return;
    }

    let body = '';
    req.on('data', chunk => { body += chunk.toString(); });
    req.on('end', () => {
      try {
        const { channel = 'global', text } = JSON.parse(body);
        if (!text || typeof text !== 'string' || !text.trim()) {
          res.writeHead(400, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ error: 'text is required' }));
          return;
        }
        const msg = text.trim();
        if (channel === 'clan') {
          clanNs?.announce(msg);
        } else {
          globalNs?.announce(msg);
        }
        console.log(`[Chat] Admin announcement → ${channel}: ${msg}`);
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: true }));
      } catch {
        res.writeHead(400, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'Invalid JSON body' }));
      }
    });
    return;
  }

  res.writeHead(404);
  res.end();
});

// ─── Socket.IO ──────────────────────────────────────────────────────────────
// allowEIO3: true is required — the compiled FlashSocket library sends EIO=3
// (Socket.IO v2 protocol). Without this flag, Socket.IO 4.x rejects every
// connection with HTTP 400 before the handshake completes.
const io = new Server(httpServer, {
  cors: {
    origin:  '*',
    methods: ['GET', 'POST'],
  },
  transports:  ['websocket', 'polling'],
  allowEIO3:   true,
});

// ─── Start ───────────────────────────────────────────────────────────────────
// Init the DB table first, then register namespaces (they preload history),
// then open the HTTP port.
messageStore.init()
  .then(() => {
    // WorldChat.as connects to:
    //   Character.chat_socket + "/global-chat"   (world chat)
    //   Character.chat_socket + "/clan-chat"     (clan chat)
    globalNs = new ChatNamespace(io.of('/global-chat'), { isClan: false });
    clanNs   = new ChatNamespace(io.of('/clan-chat'),   { isClan: true  });

    httpServer.listen(config.port, () => {
      console.log(`[Chat] Socket.IO server listening on port ${config.port}`);
      console.log(`[Chat] Namespaces: /global-chat, /clan-chat`);
    });
  })
  .catch(err => {
    console.error('[Chat] Startup failed (DB init):', err.message);
    process.exit(1);
  });

process.on('SIGTERM', () => {
  console.log('[Chat] SIGTERM received, shutting down…');
  httpServer.close(() => process.exit(0));
});