import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { after, before, test } from 'node:test';
import { createGateway } from '../src/gateway.mjs';
import { signTicket } from '../src/ticket.mjs';

const ticketSecret = 'test-ticket-secret-abcdefghijklmnopqrstuvwxyz';
const internalSecret = 'test-internal-secret-abcdefghijklmnopqrstuvwxyz';
let gateway;
let base;

before(async () => {
  gateway = createGateway({
    host: '127.0.0.1', port: 0, version: 'test', protocol: 'lc-realtime-1',
    ticketSecret, internalSecret, heartbeatSeconds: 5, ticketTtlSeconds: 60,
    maxConnections: 2, maxConnectionsPerRecipient: 1, drainSeconds: 1,
  });
  await gateway.start();
  base = `http://127.0.0.1:${gateway.address().port}`;
});

after(async () => { await gateway.stop(); });

function ticket(recipientKey, topics = ['notifications'], changes = {}) {
  const now = Math.floor(Date.now() / 1000);
  return signTicket({
    aud: 'lc-realtime-1', recipient_key: recipientKey, topics,
    iat: now, exp: now + 60, jti: randomUUID(), ...changes,
  }, ticketSecret);
}

function streamUrl(value) { return `${base}/stream?ticket=${encodeURIComponent(value)}`; }

async function connect(value) {
  const controller = new AbortController();
  const response = await fetch(streamUrl(value), { signal: controller.signal });
  return { response, controller, reader: response.body?.getReader() };
}

async function disconnect(connection) {
  connection.controller.abort();
  await connection.reader?.cancel().catch(() => {});
  await new Promise(resolve => setTimeout(resolve, 10));
}

async function nextFrame(reader) {
  const timeout = AbortSignal.timeout(3000);
  let buffer = '';
  while (!buffer.includes('\r\n\r\n')) {
    const part = await Promise.race([
      reader.read(),
      new Promise((_, reject) => timeout.addEventListener('abort', () => reject(new Error('SSE timeout')), { once: true })),
    ]);
    if (part.done) throw new Error('SSE stream ended');
    buffer += new TextDecoder().decode(part.value);
  }
  return buffer;
}

test('health advertises protocol and authenticated internal capability', async () => {
  const publicHealth = await fetch(`${base}/health`);
  assert.equal(publicHealth.status, 200);
  const body = await publicHealth.json();
  assert.equal(body.protocol, 'lc-realtime-1');
  assert.deepEqual(body.capabilities.topics, ['notifications', 'messages']);
  assert.equal(body.limits.max_connections, 2);
  assert.equal((await fetch(`${base}/internal/health`)).status, 401);
  const internalHealth = await fetch(`${base}/internal/health`, { headers: { authorization: `Bearer ${internalSecret}` } });
  assert.equal(internalHealth.status, 200);
});

test('stream rejects invalid and replayed tickets', async () => {
  assert.equal((await fetch(streamUrl('broken'))).status, 401);
  assert.equal((await fetch(streamUrl(ticket('user:1', ['notifications'], { exp: 1 })))).status, 401);
  assert.equal((await fetch(streamUrl(ticket('user:1', ['notifications'], { aud: 'wrong' })))).status, 401);
  const value = ticket('user:1');
  const first = await connect(value);
  assert.equal(first.response.status, 200);
  assert.match(await nextFrame(first.reader), /event: ready\r?\n/);
  assert.equal((await fetch(streamUrl(value))).status, 401);
  await disconnect(first);
});

test('signals require authentication and stay within recipient and topic scope', async () => {
  const first = await connect(ticket('user:101', ['notifications']));
  const second = await connect(ticket('user:202', ['messages']));
  assert.equal(first.response.status, 200);
  assert.equal(second.response.status, 200);
  await nextFrame(first.reader);
  await nextFrame(second.reader);
  const signal = { signal_id: randomUUID(), topic: 'notifications', recipient_key: 'user:101', cursor_hint: 'opaque-9', occurred_at: new Date().toISOString() };
  assert.equal((await fetch(`${base}/internal/signals`, { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify(signal) })).status, 401);
  const sent = await fetch(`${base}/internal/signals`, { method: 'POST', headers: { authorization: `Bearer ${internalSecret}`, 'content-type': 'application/json' }, body: JSON.stringify(signal) });
  assert.equal(sent.status, 202);
  assert.equal((await sent.json()).connections, 1);
  const frame = await nextFrame(first.reader);
  assert.match(frame, /event: notifications\r?\n/);
  assert.match(frame, /id: opaque-9\r?\n/);
  assert.match(frame, /data: \{"topic":"notifications","cursor_hint":"opaque-9"\}\r?\n\r?\n/);
  const duplicate = await fetch(`${base}/internal/signals`, { method: 'POST', headers: { authorization: `Bearer ${internalSecret}`, 'content-type': 'application/json' }, body: JSON.stringify(signal) });
  assert.equal(duplicate.status, 422);
  await disconnect(first);
  await disconnect(second);
});

test('connection limits apply per recipient and globally', async () => {
  const first = await connect(ticket('user:301'));
  assert.equal(first.response.status, 200);
  await nextFrame(first.reader);
  assert.equal((await fetch(streamUrl(ticket('user:301')))).status, 429);
  const second = await connect(ticket('user:302'));
  assert.equal(second.response.status, 200);
  await nextFrame(second.reader);
  assert.equal((await fetch(streamUrl(ticket('user:303')))).status, 429);
  await disconnect(first);
  await disconnect(second);
});
