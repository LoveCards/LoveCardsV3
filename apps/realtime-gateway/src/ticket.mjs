import { createHmac, timingSafeEqual } from 'node:crypto';

const ticketPattern = /^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/;
const recipientPattern = /^user:[1-9][0-9]{0,18}$/;
const allowedTopics = new Set(['notifications', 'messages']);

export function signTicket(payload, secret) {
  const encoded = Buffer.from(JSON.stringify(payload)).toString('base64url');
  const signature = createHmac('sha256', secret).update(encoded).digest('base64url');
  return `${encoded}.${signature}`;
}

export function verifyTicket(ticket, secret, protocol, nowSeconds, ttlSeconds) {
  if (typeof ticket !== 'string' || ticket.length > 2048 || !ticketPattern.test(ticket)) return null;
  const [encoded, signature] = ticket.split('.');
  const expected = createHmac('sha256', secret).update(encoded).digest();
  const actual = Buffer.from(signature, 'base64url');
  if (actual.length !== expected.length || !timingSafeEqual(actual, expected)) return null;
  let payload;
  try { payload = JSON.parse(Buffer.from(encoded, 'base64url').toString('utf8')); }
  catch { return null; }
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) return null;
  if (payload.aud !== protocol || !recipientPattern.test(payload.recipient_key ?? '')) return null;
  if (!Array.isArray(payload.topics) || payload.topics.length < 1 || payload.topics.length > 2) return null;
  if (new Set(payload.topics).size !== payload.topics.length || payload.topics.some(topic => !allowedTopics.has(topic))) return null;
  if (!Number.isInteger(payload.iat) || !Number.isInteger(payload.exp)) return null;
  if (payload.iat > nowSeconds + 5 || payload.exp <= nowSeconds || payload.exp - payload.iat > ttlSeconds) return null;
  if (typeof payload.jti !== 'string' || !/^[A-Za-z0-9._:-]{8,128}$/.test(payload.jti)) return null;
  return payload;
}

export function verifyBearer(header, secret) {
  if (typeof header !== 'string' || !header.startsWith('Bearer ')) return false;
  const actual = Buffer.from(header.slice(7));
  const expected = Buffer.from(secret);
  return actual.length === expected.length && timingSafeEqual(actual, expected);
}

export function validSignal(value, nowMillis) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
  if (typeof value.signal_id !== 'string' || !/^[A-Za-z0-9._:-]{8,128}$/.test(value.signal_id)) return false;
  if (!allowedTopics.has(value.topic) || !recipientPattern.test(value.recipient_key ?? '')) return false;
  if (typeof value.cursor_hint !== 'string' || value.cursor_hint.length > 191 || /[\r\n\0]/.test(value.cursor_hint)) return false;
  if (typeof value.occurred_at !== 'string') return false;
  const occurredAt = Date.parse(value.occurred_at);
  return Number.isFinite(occurredAt) && Math.abs(nowMillis - occurredAt) <= 300_000;
}

export function allowedTopicsList() { return [...allowedTopics]; }
