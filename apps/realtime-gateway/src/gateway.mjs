import { createServer } from 'node:http';
import { allowedTopicsList, validSignal, verifyBearer, verifyTicket } from './ticket.mjs';

const MAX_BODY_BYTES = 16 * 1024;
const MAX_CURSOR_LENGTH = 191;

function sendJson(response, status, body) {
  response.writeHead(status, { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-store' });
  response.end(JSON.stringify(body));
}

function frame(event, data, id = '') {
  const eol = '\r\n';
  let result = '';
  if (id) result += `id: ${id}${eol}`;
  result += `event: ${event}${eol}`;
  result += `data: ${JSON.stringify(data)}${eol}${eol}`;
  return result;
}

async function readJson(request) {
  let length = 0;
  const chunks = [];
  for await (const chunk of request) {
    length += chunk.length;
    if (length > MAX_BODY_BYTES) throw new Error('body-too-large');
    chunks.push(chunk);
  }
  return JSON.parse(Buffer.concat(chunks).toString('utf8'));
}

export function createGateway(config, clock = () => Date.now()) {
  const connections = new Set();
  const ticketJtis = new Map();
  const signalIds = new Map();
  const counters = { signals_received: 0, signals_delivered: 0, connections_rejected: 0, slow_connections: 0 };
  let state = 'starting';
  let heartbeat;
  let server;

  function sweepReplayCache() {
    const now = clock();
    for (const [key, expiry] of ticketJtis) if (expiry <= now) ticketJtis.delete(key);
    for (const [key, expiry] of signalIds) if (expiry <= now) signalIds.delete(key);
  }

  function closeConnection(connection) {
    if (!connections.delete(connection)) return;
    connection.response.end();
  }

  function write(connection, payload) {
    if (connection.response.destroyed) { closeConnection(connection); return false; }
    if (connection.response.writableLength > MAX_BODY_BYTES || !connection.response.write(payload)) {
      counters.slow_connections++;
      closeConnection(connection);
      return false;
    }
    return true;
  }

  function health() {
    return {
      status: state,
      service: 'realtime-gateway',
      protocol: config.protocol,
      version: config.version,
      capabilities: { sse: true, cursor: true, last_event_id: true, topics: allowedTopicsList() },
      limits: { max_connections: config.maxConnections, max_connections_per_recipient: config.maxConnectionsPerRecipient },
    };
  }

  async function route(request, response) {
    const url = new URL(request.url, 'http://localhost');
    if (request.method === 'GET' && url.pathname === '/health') return sendJson(response, state === 'ready' ? 200 : 503, health());
    if (request.method === 'GET' && url.pathname === '/internal/health') {
      if (!verifyBearer(request.headers.authorization, config.internalSecret)) return sendJson(response, 401, { error: 'unauthorized' });
      return sendJson(response, state === 'ready' ? 200 : 503, { ...health(), connections: connections.size, counters });
    }
    if (state !== 'ready') return sendJson(response, 503, { error: 'unavailable' });
    if (request.method === 'GET' && url.pathname === '/stream') {
      const nowSeconds = Math.floor(clock() / 1000);
      const ticket = verifyTicket(url.searchParams.get('ticket'), config.ticketSecret, config.protocol, nowSeconds, config.ticketTtlSeconds);
      if (!ticket || ticketJtis.has(ticket.jti)) return sendJson(response, 401, { error: 'invalid-ticket' });
      const after = url.searchParams.get('after') || request.headers['last-event-id'] || '';
      if (after.length > MAX_CURSOR_LENGTH || /[\r\n\0]/.test(after)) return sendJson(response, 422, { error: 'invalid-cursor' });
      if (connections.size >= config.maxConnections || [...connections].filter(item => item.recipientKey === ticket.recipient_key).length >= config.maxConnectionsPerRecipient) {
        counters.connections_rejected++;
        return sendJson(response, 429, { error: 'connection-limit' });
      }
      ticketJtis.set(ticket.jti, ticket.exp * 1000);
      response.writeHead(200, {
        'content-type': 'text/event-stream; charset=utf-8',
        'cache-control': 'no-cache, no-transform',
        connection: 'keep-alive',
        'x-accel-buffering': 'no',
      });
      const connection = { response, recipientKey: ticket.recipient_key, topics: new Set(ticket.topics) };
      connections.add(connection);
      request.on('close', () => closeConnection(connection));
      write(connection, frame('ready', { protocol: config.protocol, after }));
      return;
    }
    if (request.method === 'POST' && url.pathname === '/internal/signals') {
      if (!verifyBearer(request.headers.authorization, config.internalSecret)) return sendJson(response, 401, { error: 'unauthorized' });
      if (!String(request.headers['content-type'] || '').startsWith('application/json')) return sendJson(response, 415, { error: 'unsupported-media-type' });
      let signal;
      try { signal = await readJson(request); }
      catch (error) { return sendJson(response, error.message === 'body-too-large' ? 413 : 400, { error: 'invalid-json' }); }
      sweepReplayCache();
      if (!validSignal(signal, clock()) || signalIds.has(signal.signal_id)) return sendJson(response, 422, { error: 'invalid-signal' });
      signalIds.set(signal.signal_id, clock() + 300_000);
      counters.signals_received++;
      let delivered = 0;
      const payload = frame('signal', { topic: signal.topic, cursor_hint: signal.cursor_hint }, signal.cursor_hint);
      for (const connection of connections) {
        if (connection.recipientKey === signal.recipient_key && connection.topics.has(signal.topic) && write(connection, payload)) delivered++;
      }
      counters.signals_delivered += delivered;
      return sendJson(response, 202, { accepted: true, connections: delivered });
    }
    return sendJson(response, 404, { error: 'not-found' });
  }

  server = createServer((request, response) => { void route(request, response).catch(() => sendJson(response, 500, { error: 'internal-error' })); });

  return {
    async start() {
      await new Promise((resolve, reject) => { server.once('error', reject); server.listen(config.port, config.host, resolve); });
      state = 'ready';
      heartbeat = setInterval(() => {
        sweepReplayCache();
        for (const connection of connections) write(connection, ': ping\r\n\r\n');
      }, config.heartbeatSeconds * 1000);
      heartbeat.unref();
    },
    address() { return server.address(); },
    async stop() {
      if (state === 'stopped') return;
      state = 'stopping';
      clearInterval(heartbeat);
      for (const connection of connections) write(connection, frame('shutdown', { reason: 'server-stopping' }));
      await new Promise(resolve => setTimeout(resolve, config.drainSeconds * 1000));
      for (const connection of [...connections]) closeConnection(connection);
      server.closeAllConnections();
      await new Promise(resolve => server.close(resolve));
      state = 'stopped';
    },
  };
}
