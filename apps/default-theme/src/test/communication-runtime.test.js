import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createCommunicationRuntime, SIGNAL_FEED, SIGNAL_MESSAGES, SIGNAL_NOTIFICATIONS, SIGNAL_UNREAD } from '../services/communication-runtime.js';

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
  emitOpen() { this.listeners.open?.({}); }
  emitSignal(payload) { this.listeners.signal?.({ data: JSON.stringify(payload) }); }
  emitError() { this.listeners.error?.({}); }
}

function deferred() {
  let resolve;
  const promise = new Promise((done) => { resolve = done; });
  return { promise, resolve };
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
    runtime.subscribe((topic, hint) => topics.push([topic, hint]));
    await runtime.start();
    expect(client.notifications.streamTicket).toHaveBeenCalledTimes(1);
    expect(sources).toHaveLength(1);
    expect(sources[0].url).toContain('ticket=');
    expect(runtime.currentMode()).toBe('realtime');
    sources[0].emitSignal({ topic: 'messages', cursor: 'c1' });
    expect(topics).toContainEqual([SIGNAL_MESSAGES, 'c1']);
    expect(topics).toContainEqual([SIGNAL_UNREAD, '']);
    topics.length = 0;
    sources[0].emitSignal({ topic: 'feed', cursor: 'feed-1' });
    expect(topics).toEqual([[SIGNAL_FEED, 'feed-1']]);
    sources[0].emitSignal({ topic: 'unknown', cursor: 'x' });
    expect(topics).toEqual([[SIGNAL_FEED, 'feed-1']]);
    runtime.destroy();
  });

  it('refreshes facts on connection and does not poll during realtime operation', async () => {
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
    expect(runtime.currentConnectionStatus()).toBe('connecting');
    FakeEventSource.instances[0].emitOpen();
    expect(runtime.currentConnectionStatus()).toBe('ready');
    expect(seen).toEqual([SIGNAL_NOTIFICATIONS, SIGNAL_MESSAGES, SIGNAL_UNREAD, SIGNAL_FEED]);
    seen.length = 0;
    await vi.advanceTimersByTimeAsync(120_000);
    expect(seen).toEqual([]);
    expect(client.notifications.streamTicket).toHaveBeenCalledTimes(1);
    runtime.stop();
    await vi.advanceTimersByTimeAsync(120_000);
    expect(seen).toHaveLength(0);
    expect(FakeEventSource.instances[0].closed).toBe(true);
    runtime.destroy();
  });

  it('re-signs a one-time ticket after a link error without starting polling', async () => {
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
    expect(runtime.currentMode()).toBe('realtime');
    expect(runtime.currentConnectionStatus()).toBe('fault');
    expect(source.closed).toBe(true);
    seen.length = 0;
    await vi.advanceTimersByTimeAsync(2_000);
    expect(seen).toEqual([]);
    // 重试时重新签发一次性 ticket；重连成功后补读。
    expect(FakeEventSource.instances).toHaveLength(2);
    expect(client.notifications.streamTicket).toHaveBeenCalledTimes(2);
    expect(FakeEventSource.instances[0].closed).toBe(true);
    expect(FakeEventSource.instances[1].url).not.toContain('lastEventId=');
    FakeEventSource.instances[1].emitOpen();
    expect(runtime.currentConnectionStatus()).toBe('ready');
    expect(seen).toEqual([SIGNAL_NOTIFICATIONS, SIGNAL_MESSAGES, SIGNAL_UNREAD, SIGNAL_FEED]);
    expect(runtime.currentMode()).toBe('realtime');
    runtime.destroy();
  });

  it('keeps the selected realtime mode when ticket issuance fails', async () => {
    const client = makeClient();
    client.notifications.streamTicket = vi.fn(async () => { throw new Error('gateway down'); });
    const runtime = createCommunicationRuntime({ clientResolver: () => client, eventSourceFactory: () => new FakeEventSource('x') });
    client.communication.settings = vi.fn(async () => ({ effective_mode: 'realtime', status: 'ready', settings: { notification_interval_seconds: 2, message_interval_seconds: 3, unread_interval_seconds: 1, polling_backoff_factor: 2, polling_max_interval_seconds: 4, pause_when_hidden: true } }));
    const seen = [];
    runtime.subscribe((topic) => seen.push(topic));
    await runtime.start();
    expect(runtime.currentMode()).toBe('realtime');
    expect(runtime.currentConnectionStatus()).toBe('fault');
    await vi.advanceTimersByTimeAsync(30_000);
    expect(runtime.currentMode()).toBe('realtime');
    expect(seen).toEqual([]);
    expect(client.notifications.streamTicket.mock.calls.length).toBeGreaterThan(2);
    runtime.destroy();
  });

  it('does not assume polling when the selected mode cannot be read', async () => {
    const client = makeClient();
    client.communication.settings = vi.fn(async () => { throw new Error('settings unavailable'); });
    const runtime = createCommunicationRuntime({ clientResolver: () => client });
    const seen = [];
    runtime.subscribe((topic) => seen.push(topic));
    await runtime.start();
    await vi.advanceTimersByTimeAsync(60_000);
    expect(runtime.currentMode()).toBe(null);
    expect(seen).toEqual([]);
    runtime.destroy();
  });

  it('honors a realtime fault snapshot and resumes only after the server reports ready', async () => {
    const client = makeClient();
    let status = 'degraded';
    client.communication.settings = vi.fn(async () => ({ desired_mode: 'realtime', status, settings: {} }));
    const runtime = createCommunicationRuntime({ clientResolver: () => client, eventSourceFactory: (url) => new FakeEventSource(url) });
    await runtime.start();
    expect(runtime.currentMode()).toBe('realtime');
    expect(runtime.currentConnectionStatus()).toBe('fault');
    expect(client.notifications.streamTicket).not.toHaveBeenCalled();
    status = 'ready';
    await vi.advanceTimersByTimeAsync(30_000);
    expect(FakeEventSource.instances).toHaveLength(1);
    FakeEventSource.instances[0].emitOpen();
    expect(runtime.currentConnectionStatus()).toBe('ready');
    runtime.destroy();
  });

  it('starts polling only after the server explicitly selects polling', async () => {
    const client = makeClient();
    let desiredMode = 'realtime';
    client.communication.settings = vi.fn(async () => ({ desired_mode: desiredMode, status: 'ready', settings: { unread_interval_seconds: 5 } }));
    const runtime = createCommunicationRuntime({ clientResolver: () => client, eventSourceFactory: (url) => new FakeEventSource(url) });
    const seen = [];
    runtime.subscribe((topic) => seen.push(topic));
    await runtime.start();
    FakeEventSource.instances[0].emitError();
    await vi.advanceTimersByTimeAsync(10_000);
    expect(runtime.currentMode()).toBe('realtime');
    expect(seen).toEqual([]);
    desiredMode = 'polling';
    await vi.advanceTimersByTimeAsync(20_000);
    expect(runtime.currentMode()).toBe('polling');
    expect(runtime.currentConnectionStatus()).toBe('idle');
    await vi.advanceTimersByTimeAsync(5_000);
    expect(seen).toContain(SIGNAL_UNREAD);
    runtime.destroy();
  });

  it('discards a slow retry ticket after the server selects polling', async () => {
    const client = makeClient();
    const slowTicket = deferred();
    let desiredMode = 'realtime';
    let ticketCalls = 0;
    client.communication.settings = vi.fn(async () => ({ desired_mode: desiredMode, status: 'ready', settings: { unread_interval_seconds: 5 } }));
    client.notifications.streamTicket = vi.fn(() => {
      ticketCalls += 1;
      return ticketCalls === 1
        ? Promise.resolve({ ticket: 't'.repeat(26), connect_url: 'https://gateway.test/stream', heartbeat_seconds: 15 })
        : slowTicket.promise;
    });
    const runtime = createCommunicationRuntime({ clientResolver: () => client, eventSourceFactory: (url) => new FakeEventSource(url) });
    const seen = [];
    runtime.subscribe((topic) => seen.push(topic));
    await runtime.start();
    FakeEventSource.instances[0].emitError();
    await vi.advanceTimersByTimeAsync(2_000);
    expect(client.notifications.streamTicket).toHaveBeenCalledTimes(2);
    desiredMode = 'polling';
    await vi.advanceTimersByTimeAsync(28_000);
    expect(runtime.currentMode()).toBe('polling');
    slowTicket.resolve({ ticket: 'u'.repeat(26), connect_url: 'https://gateway.test/stream', heartbeat_seconds: 15 });
    await Promise.resolve();
    expect(FakeEventSource.instances).toHaveLength(1);
    await vi.advanceTimersByTimeAsync(5_000);
    expect(seen).toContain(SIGNAL_UNREAD);
    runtime.destroy();
  });

  it('discards a slow ticket when the page becomes hidden', async () => {
    const client = makeClient();
    const slowTicket = deferred();
    client.communication.settings = vi.fn(async () => ({ desired_mode: 'realtime', status: 'ready', settings: { pause_when_hidden: true } }));
    client.notifications.streamTicket = vi.fn(() => slowTicket.promise);
    let visibilityChange;
    const documentRef = { visibilityState: 'visible', addEventListener: vi.fn((name, callback) => { if (name === 'visibilitychange') visibilityChange = callback; }) };
    const runtime = createCommunicationRuntime({ clientResolver: () => client, documentRef, eventSourceFactory: (url) => new FakeEventSource(url) });
    const starting = runtime.start();
    await Promise.resolve();
    expect(client.notifications.streamTicket).toHaveBeenCalledTimes(1);
    documentRef.visibilityState = 'hidden';
    visibilityChange();
    slowTicket.resolve({ ticket: 't'.repeat(26), connect_url: 'https://gateway.test/stream', heartbeat_seconds: 15 });
    await starting;
    expect(FakeEventSource.instances).toHaveLength(0);
    expect(runtime.currentConnectionStatus()).toBe('idle');
    runtime.destroy();
  });

  it('ignores a settings response that arrives after stop', async () => {
    const client = makeClient();
    const slowSettings = deferred();
    let settingsCalls = 0;
    client.communication.settings = vi.fn(() => {
      settingsCalls += 1;
      return settingsCalls === 1
        ? Promise.resolve({ desired_mode: 'polling', status: 'ready', settings: { unread_interval_seconds: 5 } })
        : slowSettings.promise;
    });
    const runtime = createCommunicationRuntime({ clientResolver: () => client });
    const seen = [];
    runtime.subscribe((topic) => seen.push(topic));
    await runtime.start();
    vi.advanceTimersByTime(30_000);
    await Promise.resolve();
    expect(client.communication.settings).toHaveBeenCalledTimes(2);
    runtime.stop();
    seen.length = 0;
    slowSettings.resolve({ desired_mode: 'polling', status: 'ready', settings: { unread_interval_seconds: 1 } });
    await Promise.resolve();
    await vi.advanceTimersByTimeAsync(60_000);
    expect(seen).toEqual([]);
    expect(client.communication.settings).toHaveBeenCalledTimes(2);
    runtime.destroy();
  });

  it('keeps realtime mode and backs off when a stream repeatedly fails before opening', async () => {
    const client = makeClient();
    client.communication.settings = vi.fn(async () => ({ effective_mode: 'realtime', status: 'ready', settings: { notification_interval_seconds: 1 } }));
    const runtime = createCommunicationRuntime({ clientResolver: () => client, eventSourceFactory: (url) => new FakeEventSource(url) });
    const seen = [];
    runtime.subscribe((topic) => seen.push(topic));
    await runtime.start();
    FakeEventSource.instances[0].emitError();
    await vi.advanceTimersByTimeAsync(2000);
    expect(FakeEventSource.instances).toHaveLength(2);
    FakeEventSource.instances[1].emitError();
    await vi.advanceTimersByTimeAsync(4000);
    expect(FakeEventSource.instances).toHaveLength(3);
    FakeEventSource.instances[2].emitError();
    await vi.advanceTimersByTimeAsync(8000);
    expect(FakeEventSource.instances).toHaveLength(4);
    expect(runtime.currentMode()).toBe('realtime');
    expect(runtime.currentConnectionStatus()).toBe('connecting');
    expect(seen).toEqual([]);
    FakeEventSource.instances[3].emitOpen();
    expect(runtime.currentConnectionStatus()).toBe('ready');
    expect(client.notifications.streamTicket).toHaveBeenCalledTimes(4);
    runtime.destroy();
  });
});
