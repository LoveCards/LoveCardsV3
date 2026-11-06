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

  it('applies changed polling intervals after the settings snapshot refresh', async () => {
    const client = makeClient();
    let unreadInterval = 5;
    client.communication.settings = vi.fn(async () => ({
      effective_mode: 'polling', status: 'ready',
      settings: { notification_interval_seconds: 10, message_interval_seconds: 12, unread_interval_seconds: unreadInterval, pause_when_hidden: true },
    }));
    const runtime = createCommunicationRuntime({ clientResolver: () => client });
    const seen = [];
    runtime.subscribe((topic) => seen.push(topic));
    await runtime.start();
    unreadInterval = 7;
    await vi.advanceTimersByTimeAsync(30_000);
    seen.length = 0;
    await vi.advanceTimersByTimeAsync(5_000);
    expect(seen).not.toContain(SIGNAL_UNREAD);
    await vi.advanceTimersByTimeAsync(2_000);
    expect(seen).toContain(SIGNAL_UNREAD);
    runtime.destroy();
  });

  it('continues polling in a hidden tab when pause_when_hidden is disabled', async () => {
    const client = makeClient({ pause_when_hidden: false });
    const documentRef = { visibilityState: 'hidden', addEventListener: vi.fn() };
    const runtime = createCommunicationRuntime({ clientResolver: () => client, documentRef });
    const seen = [];
    runtime.subscribe((topic) => seen.push(topic));
    await runtime.start();
    await vi.advanceTimersByTimeAsync(5_000);
    expect(seen).toContain(SIGNAL_UNREAD);
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

  it('reconciles missed realtime signals and stops reconciliation with the stream', async () => {
    const client = makeClient();
    client.communication.settings = vi.fn(async () => ({ effective_mode: 'realtime', status: 'ready', settings: {} }));
    client.notifications.streamTicket = vi.fn(async () => ({ ticket: 't'.repeat(26), connect_url: 'https://gateway.test/stream', heartbeat_seconds: 100 }));
    const runtime = createCommunicationRuntime({
      clientResolver: () => client,
      eventSourceFactory: (url) => new FakeEventSource(url),
    });
    const seen = [];
    runtime.subscribe((topic) => seen.push(topic));
    await runtime.start();
    await vi.advanceTimersByTimeAsync(120_000);
    expect(seen).toEqual([SIGNAL_NOTIFICATIONS, SIGNAL_MESSAGES, SIGNAL_UNREAD]);
    expect(client.notifications.streamTicket).toHaveBeenCalledTimes(1);
    runtime.stop();
    await vi.advanceTimersByTimeAsync(120_000);
    expect(seen).toHaveLength(3);
    expect(FakeEventSource.instances[0].closed).toBe(true);
    runtime.destroy();
  });

  it('re-signs a one-time ticket after a realtime link error without keeping both transports', async () => {
    const client = makeClient();
    const runtime = createCommunicationRuntime({
      clientResolver: () => client,
      eventSourceFactory: (url) => new FakeEventSource(url),
    });
    client.communication.settings = vi.fn(async () => ({ effective_mode: 'realtime', status: 'ready', settings: { notification_interval_seconds: 10, message_interval_seconds: 12, unread_interval_seconds: 5, polling_backoff_factor: 2, polling_max_interval_seconds: 60, pause_when_hidden: true } }));
    const seen = [];
    runtime.subscribe((topic) => seen.push(topic));
    await runtime.start();
    expect(runtime.currentMode()).toBe('realtime');
    const source = FakeEventSource.instances[0];
    source.emitSignal({ topic: 'notifications', cursor: 'cursor-1' });
    source.emitError();
    expect(runtime.currentMode()).toBe('polling');
    expect(source.closed).toBe(true);
    await vi.advanceTimersByTimeAsync(10_000);
    expect(seen).toContain(SIGNAL_NOTIFICATIONS);
    // 重试时必须重新签发一次性 ticket，并清理轮询链。
    expect(FakeEventSource.instances).toHaveLength(2);
    expect(client.notifications.streamTicket).toHaveBeenCalledTimes(2);
    expect(FakeEventSource.instances[0].closed).toBe(true);
    expect(FakeEventSource.instances[1].url).toContain('lastEventId=cursor-1');
    expect(runtime.currentMode()).toBe('realtime');
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
