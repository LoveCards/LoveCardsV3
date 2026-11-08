import { getSdkClient } from '../app/sdk-client.js';

// 通信运行时（契约 §4/§6）：浏览器唯一取数节奏调度器。
// 模式（polling/realtime）由服务端快照裁决；本模块只调度“何时触发刷新”，
// 刷新动作复用与模式无关的业务取数路径（SDK），业务组件绝不按模式分叉。

export const SIGNAL_UNREAD = 'unread';
export const SIGNAL_NOTIFICATIONS = 'notifications';
export const SIGNAL_MESSAGES = 'messages';

const MAX_RECONNECT_ATTEMPTS_BEFORE_POLL = 2;
const SETTINGS_REFRESH_INTERVAL_MS = 30_000;
const REALTIME_RECONCILE_INTERVAL_MS = 120_000;
const POLLING_SETTING_KEYS = ['notification_interval_seconds', 'message_interval_seconds', 'unread_interval_seconds', 'pause_when_hidden'];

function clampNumber(value, fallback) {
  const num = Number(value);
  return Number.isFinite(num) && num > 0 ? num : fallback;
}

/**
 * 可测试的运行时工厂：注入 client、EventSource、定时器与文档可见性。
 */
export function createCommunicationRuntime({
  clientResolver = getSdkClient,
  eventSourceFactory = (url) => (typeof globalThis.EventSource === 'function' ? new globalThis.EventSource(url) : null),
  documentRef = (typeof document !== 'undefined' ? document : null),
  setTimeoutFn = (fn, ms) => globalThis.setTimeout(fn, ms),
  clearTimeoutFn = (handle) => globalThis.clearTimeout(handle),
} = {}) {
  const listeners = new Set();
  let snapshot = null;
  let mode = null;
  const chainTimers = new Map();
  let timers = [];
  let eventSource = null;
  let watchdog = null;
  let reconnectDelay = null;
  let settingsRefresh = null;
  let realtimeReconcile = null;
  let lastCursor = null;
  let attempts = 0;
  let started = false;
  let destroyed = false;

  function emit(topic) {
    for (const listener of listeners) {
      try { listener(topic); } catch { /* 订阅方错误不影响调度 */ }
    }
  }

  function clearTransportScheduling() {
    for (const handle of chainTimers.values()) clearTimeoutFn(handle);
    chainTimers.clear();
    for (const handle of timers) clearTimeoutFn(handle);
    timers = [];
    if (watchdog) { clearTimeoutFn(watchdog); watchdog = null; }
    if (reconnectDelay) { clearTimeoutFn(reconnectDelay); reconnectDelay = null; }
    if (realtimeReconcile) { clearTimeoutFn(realtimeReconcile); realtimeReconcile = null; }
    if (eventSource) {
      try { eventSource.close(); } catch { /* 关闭失败可忽略 */ }
      eventSource = null;
    }
  }

  function clearScheduling() {
    clearTransportScheduling();
    if (settingsRefresh) { clearTimeoutFn(settingsRefresh); settingsRefresh = null; }
  }

  function isHidden() {
    return Boolean(documentRef && documentRef.visibilityState === 'hidden');
  }

  function startPolling() {
    mode = 'polling';
    const settings = snapshot?.settings || {};
    const notificationInterval = clampNumber(settings.notification_interval_seconds, 30);
    const messageInterval = clampNumber(settings.message_interval_seconds, 45);
    const unreadInterval = clampNumber(settings.unread_interval_seconds, 20);
    // 单条轮询链：三个周期各自触发对应 topic 的刷新信号；链句柄按 topic 覆盖，不泄漏。
    const tick = (topic, interval) => {
      const handle = setTimeoutFn(() => {
        chainTimers.delete(topic);
        if (!started) return;
        if (isHidden() && settings.pause_when_hidden !== false) { tick(topic, interval); return; }
        emit(topic);
        tick(topic, interval);
      }, Math.max(1000, Math.round(interval * 1000)));
      chainTimers.set(topic, handle);
    };
    tick(SIGNAL_NOTIFICATIONS, notificationInterval);
    tick(SIGNAL_MESSAGES, messageInterval);
    tick(SIGNAL_UNREAD, unreadInterval);
  }

  function fallBackToPolling() {
    clearTransportScheduling();
    mode = 'polling';
    if (started && !isHidden()) startPolling();
  }

  function scheduleRealtimeRetry() {
    if (!started || attempts >= MAX_RECONNECT_ATTEMPTS_BEFORE_POLL) return;
    attempts += 1;
    const settings = snapshot?.settings || {};
    const backoff = clampNumber(settings.polling_backoff_factor, 2);
    const maxDelay = clampNumber(settings.polling_max_interval_seconds, 300);
    const base = clampNumber(settings.notification_interval_seconds, 30);
    const delay = Math.min(maxDelay, base * Math.pow(backoff, attempts - 1));
    reconnectDelay = setTimeoutFn(() => {
      reconnectDelay = null;
      if (started) {
        clearTransportScheduling();
        void startRealtime();
      }
    }, delay * 1000);
  }

  function armWatchdog(heartbeatSeconds) {
    if (watchdog) clearTimeoutFn(watchdog);
    // 心跳注释不产生 EventSource 事件；看门狗超过 3 个心跳周期无信号即判定链路失效。
    const limit = Math.max(10_000, clampNumber(heartbeatSeconds, 15) * 3 * 1000);
    watchdog = setTimeoutFn(() => {
      watchdog = null;
      fallBackToPolling();
      scheduleRealtimeRetry();
    }, limit);
  }

  function scheduleRealtimeReconcile() {
    if (!started || mode !== 'realtime') return;
    realtimeReconcile = setTimeoutFn(() => {
      realtimeReconcile = null;
      if (!started || mode !== 'realtime') return;
      // 信号可能丢失或先于事务提交到达；低频回源保证页面最终收敛。
      emit(SIGNAL_NOTIFICATIONS);
      emit(SIGNAL_MESSAGES);
      emit(SIGNAL_UNREAD);
      scheduleRealtimeReconcile();
    }, REALTIME_RECONCILE_INTERVAL_MS);
  }

  async function startRealtime() {
    const client = clientResolver();
    if (!client) { fallBackToPolling(); return; }
    let ticket;
    try {
      ticket = await client.notifications.streamTicket();
    } catch {
      fallBackToPolling();
      scheduleRealtimeRetry();
      return;
    }
    if (destroyed || !started) return;
    const query = new URLSearchParams({ ticket: ticket.ticket });
    if (lastCursor) query.set('lastEventId', lastCursor);
    const source = eventSourceFactory(`${ticket.connect_url}?${query.toString()}`);
    if (!source) { fallBackToPolling(); scheduleRealtimeRetry(); return; }
    mode = 'realtime';
    eventSource = source;
    armWatchdog(ticket.heartbeat_seconds);
    scheduleRealtimeReconcile();
    source.addEventListener('open', () => { attempts = 0; });
    source.addEventListener('heartbeat', () => {
      armWatchdog(ticket.heartbeat_seconds);
    });
    source.addEventListener('resync', () => {
      emit(SIGNAL_NOTIFICATIONS);
      emit(SIGNAL_MESSAGES);
      emit(SIGNAL_UNREAD);
    });
    source.addEventListener('signal', (event) => {
      armWatchdog(ticket.heartbeat_seconds);
      let topic = SIGNAL_NOTIFICATIONS;
      try {
        const parsed = JSON.parse(event.data || '{}');
        if (parsed.cursor) lastCursor = String(parsed.cursor);
        if (parsed.topic === 'messages') topic = SIGNAL_MESSAGES;
      } catch { /* 保持默认 topic */ }
      emit(topic);
      emit(SIGNAL_UNREAD);
    });
    source.addEventListener('error', () => {
      // 先用轮询兜底，再重新签发一次性 ticket；重连成功后清掉轮询链。
      fallBackToPolling();
      scheduleRealtimeRetry();
    });
  }

  function scheduleSettingsRefresh() {
    if (settingsRefresh) clearTimeoutFn(settingsRefresh);
    if (!started) return;
    settingsRefresh = setTimeoutFn(async () => {
      settingsRefresh = null;
      if (!started) return;
      const client = clientResolver();
      if (!client) return;
      let next;
      try { next = await client.communication.settings(); } catch { scheduleSettingsRefresh(); return; }
      const previousMode = snapshot?.effective_mode;
      const previousStatus = snapshot?.status;
      const pollingSettingsChanged = POLLING_SETTING_KEYS.some((key) => next?.settings?.[key] !== snapshot?.settings?.[key]);
      snapshot = next;
      if (next?.effective_mode !== previousMode || next?.status !== previousStatus || (next?.effective_mode !== 'realtime' && pollingSettingsChanged)) {
        clearTransportScheduling();
        attempts = 0;
        if (next?.effective_mode === 'realtime') await startRealtime();
        else startPolling();
      }
      scheduleSettingsRefresh();
    }, SETTINGS_REFRESH_INTERVAL_MS);
  }

  async function start() {
    if (started) return;
    started = true;
    const client = clientResolver();
    if (!client) return;
    try {
      snapshot = await client.communication.settings();
    } catch {
      snapshot = null;
    }
    clearScheduling();
    if (snapshot?.effective_mode === 'realtime') await startRealtime();
    else startPolling();
    scheduleSettingsRefresh();
  }

  function stop() {
    started = false;
    clearScheduling();
  }

  function destroy() {
    destroyed = true;
    stop();
    listeners.clear();
  }

  function subscribe(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
  }

  function currentMode() { return mode; }

  if (documentRef) {
    documentRef.addEventListener?.('visibilitychange', () => {
      const settings = snapshot?.settings || {};
      if (settings.pause_when_hidden === false) return;
      if (isHidden()) {
        clearScheduling();
      } else if (started) {
        clearScheduling();
        void (async () => {
          const client = clientResolver();
          try { if (client) snapshot = await client.communication.settings(); } catch { /* 保留旧快照 */ }
          if (!started) return;
          if (snapshot?.effective_mode === 'realtime') await startRealtime();
          else startPolling();
          scheduleSettingsRefresh();
        })();
      }
    });
  }

  return { start, stop, destroy, subscribe, currentMode };
}

let singleton = null;
export function getCommunicationRuntime() {
  if (!singleton) singleton = createCommunicationRuntime();
  return singleton;
}
