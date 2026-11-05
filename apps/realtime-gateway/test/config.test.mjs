import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readConfig } from '../src/config.mjs';

const base = {
  LC_REALTIME_TICKET_SECRET: 'ticket-secret-abcdefghijklmnopqrstuvwxyz-1234',
  LC_REALTIME_INTERNAL_SECRET: 'internal-secret-abcdefghijklmnopqrstuvwxyz-1234',
};

test('config requires separate strong secrets', () => {
  assert.equal(readConfig(base).port, 7070);
  assert.throws(() => readConfig({ ...base, LC_REALTIME_INTERNAL_SECRET: base.LC_REALTIME_TICKET_SECRET }), /must differ/);
  assert.throws(() => readConfig({ ...base, LC_REALTIME_TICKET_SECRET: 'short' }), /at least 32/);
});

test('config rejects unsafe or out-of-range values', () => {
  assert.throws(() => readConfig({ ...base, LC_REALTIME_HOST: '0.0.0.0', LC_REALTIME_HEARTBEAT_SECONDS: '4' }), /between 5 and 120/);
  assert.throws(() => readConfig({ ...base, LC_REALTIME_PORT: '70000' }), /between 1 and 65535/);
  assert.throws(() => readConfig({ ...base, LC_REALTIME_HOST: '0.0.0.0.example' }), /not supported/);
});
