import 'dotenv/config';
import express from 'express';
import { createServer } from 'node:http';
import { Server } from 'socket.io';
import { verifyToken } from './services/auth.js';
import { room, publish } from './services/messageBus.js';

const app = express();
const http = createServer(app);
const io = new Server(http, {
  cors: { origin: process.env.ALLOWED_ORIGIN || false, methods: ['GET', 'POST'] },
  maxHttpBufferSize: 1e6,
  pingTimeout: 20000
});
const site = (process.env.SITE_URL || '').replace(/\/$/, '');
const internalKey = process.env.MESSENGER_INTERNAL_KEY || '';

async function internal(path, data) {
  const response = await fetch(site + path, {
    method: 'POST',
    headers: { 'content-type': 'application/json', 'x-messenger-internal-key': internalKey },
    body: JSON.stringify(data)
  });
  const result = await response.json();
  if (!response.ok || !result.ok) throw new Error(result.message || 'denied');
  return result;
}

async function authorize(socket, conversationId) {
  const id = Number(conversationId);
  if (!id) throw new Error('invalid conversation');
  return internal('/api/messenger/internal/access.php', { user_id: socket.userId, conversation_id: id });
}

io.use((socket, next) => {
  try {
    socket.userId = verifyToken(socket.handshake.auth?.token, process.env.REALTIME_SECRET || '');
    next();
  } catch {
    next(new Error('unauthorized'));
  }
});

io.on('connection', async socket => {
  await internal('/api/messenger/internal/presence.php', { user_id: socket.userId, status: 'online', socket_id: socket.id }).catch(() => {});
  io.emit('presence:update', { user_id: socket.userId, status: 'online' });

  socket.on('conversation:join', async (conversationId, ack) => {
    try {
      const access = await authorize(socket, conversationId);
      socket.join(room(conversationId));
      ack?.({ ok: true, access });
    } catch { ack?.({ ok: false }); }
  });
  socket.on('conversation:leave', conversationId => socket.leave(room(conversationId)));

  socket.on('message:send', async (data, ack) => {
    try {
      await authorize(socket, data.conversation_id);
      const result = await internal('/api/messenger/internal/send.php', { ...data, user_id: socket.userId });
      publish(io, data.conversation_id, 'message:new', result.data);
      ack?.({ ok: true, data: result.data });
    } catch (error) { ack?.({ ok: false, message: error.message }); }
  });

  const relay = (incoming, outgoing) => socket.on(incoming, async data => {
    try {
      await authorize(socket, data.conversation_id);
      socket.to(room(data.conversation_id)).emit(outgoing, { ...data, user_id: socket.userId });
    } catch { /* unauthorized events are intentionally ignored */ }
  });
  relay('message:typing:start', 'typing:update');
  relay('message:typing:stop', 'typing:update');
  relay('message:read', 'receipt:read');
  relay('message:delivered', 'receipt:delivered');
  relay('message:edited', 'message:edited');
  relay('message:deleted', 'message:deleted');
  relay('message:pinned', 'message:pinned');
  relay('reaction:update', 'reaction:updated');
  relay('location:update', 'location:updated');

  socket.on('disconnect', () => {
    internal('/api/messenger/internal/presence.php', { user_id: socket.userId, status: 'offline', socket_id: socket.id }).catch(() => {});
    io.emit('presence:update', { user_id: socket.userId, status: 'offline' });
  });
});

app.get('/health', (_, response) => response.json({ ok: true, service: 'sobhan-messenger-realtime' }));
http.listen(Number(process.env.PORT || 3100), '0.0.0.0');
