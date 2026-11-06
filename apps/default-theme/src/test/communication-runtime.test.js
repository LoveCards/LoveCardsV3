import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createCommunicationRuntime, SIGNAL_MESSAGES, SIGNAL_NOTIFICATIONS, SIGNAL_UNREAD } from '../services/communication-runtime.js';

function makeClient(settings = {}) {
  return {
    communication: { settings: vi.fn(async () => ({ effective_mode: 'polling', status: 'ready', settings: { notification_interval_seconds: 10, message_interval_seconds: 12, unread_interval_seconds: 5, polling_backoff_factor: 2, polling_max_interval_seconds: 60, pause_when_hidden: true, ...settings } })) },
    notifications: { streamTicket: vi.fn(async () => ({ ticket: 't'.repeat(26), expires_in: 60, connect_url: 'https://gateway.test/stream', heartbeat_seconds: 15 })) },
  };
}

class FakeEventSource {
  constructor(url) {
    this.url = url;
    this.listeners = {};
    this.closed = false;
    FakeEventSource.instances.push(this);
  }
  addEventListener(name, fn) { this.listeners[name] = fn; }
  close() { this.closed = true; }
  emitSignal(payload) { this.listeners.signal?.({ data: JSON.stringify(payload) }); }
  emitError() { this.listeners.error?.({}); }
}

describe('communication runtime', () => {
  beforeEach(() => { vi.useFakeTimers(); FakeEventSource.instances = []; });
  afterEach(() => { vi.useRealTimers(); });

  it('schedules polling chains from server-provided intervals', async () => {
    const client = makeClient();
    const runtime = createCommunicationRuntime({ clientResolver: () => client });
    const seen = [];
    runtime.subscribe((topic) => seen.push(topic));
    await runtime.start();
    expect(runtime.currentMode()).toBe('polling');
    await vi.advanceTimersByTimeAsync(5000);
    expect(seen).toContain(SIGNAL_UNREAD);
    await vi.advanceTimersByTimeAsync(5000);
    expect(seen).toContain(SIGNAL_NOTIFICATIONS);
    runtime.destroy();
  });

  it('uses the realtime chain when the server snapshot says so', async () => {
    const client = makeClient();
    const sources = [];
    const runtime = createCommunicationRuntime({
      clientResolver: () => client,
      eventSourceFactory: (url) => { const source = new FakeEventSource(url); sources.push(source); return source; },
    });
    // 让快照声明 realtime 生效。
    client.communication.settings = vi.fn(async () => ({ effective_mode: 'realtime', status: 'ready', settings: { notification_interval_seconds: 10, message_interval_seconds: 12, unread_interval_seconds: 5, polling_backoff_factor: 2, polling_max_interval_seconds: 60, pause_when_hidden: true } }));
    const topics = [];
    runtime.subscribe((topic) => topics.push(topic));
    await runtime.start();
    expect(client.notifications.streamTicket).toHaveBeenCalledTimes(1);
    expect(sources).toHaveLength(1);
    expect(sources[0].url).toContain('ticket=');
    expect(runtime.currentMode()).toBe('realtime');
    sources[0].emitSignal({ topic: 'messages', cursor: 'c1' });
    expect(topics).toContain(SIGNAL_MESSAGES);
    expect(topics).toContain(SIGNAL_UNREAD);
    runtime.destroy();
  });

  it('falls back to the polling chain when the realtime link errors and never runs both', async () => {
    const client = makeClient();
    const runtime = createCommunicationRuntime({
      clientResolver: () => client,
      eventSourceFactory: () => new FakeEventSource('https://gateway.test/stream?ticket=x'),
    });
    client.communication.settings = vi.fn(async () => ({ effective_mode: 'realtime', status: 'ready', settings: { notification_interval_seconds: 10, message_interval_seconds: 12, unread_interval_seconds: 5, polling_backoff_factor: 2, polling_max_interval_seconds: 60, pause_when_hidden: true } }));
    const seen = [];
    runtime.subscribe((topic) => seen.push(topic));
    await runtime.start();
    expect(runtime.currentMode()).toBe('realtime');
    const source = FakeEventSource.instances[0];
    source.emitError();
    expect(runtime.currentMode()).toBe('polling');
    expect(source.closed).toBe(true);
    await vi.advanceTimersByTimeAsync(10_000);
    expect(seen).toContain(SIGNAL_NOTIFICATIONS);
    // 不得再次打开 SSE：轮询与实时不长期双轨。
    expect(FakeEventSource.instances).toHaveLength(1);
    runtime.destroy();
  });

  it('degrades to polling when settings or ticket issuance fails', async () => {
    const client = makeClient();
    client.notifications.streamTicket = vi.fn(async () => { throw new Error('gateway down'); });
    const runtime = createCommunicationRuntime({ clientResolver: () => client, eventSourceFactory: () => new FakeEventSource('x') });
    client.communication.settings = vi.fn(async () => ({ effective_mode: 'realtime', status: 'ready', settings: { notification_interval_seconds: 2, message_interval_seconds: 3, unread_interval_seconds: 1, polling_backoff_factor: 2, polling_max_interval_seconds: 4, pause_when_hidden: true } }));
    await runtime.start();
    // ticket 连续失败超过阈值后转轮询。
    await vi.advanceTimersByTimeAsync(30_000);
    expect(runtime.currentMode()).toBe('polling');
    runtime.destroy();
  });
});
