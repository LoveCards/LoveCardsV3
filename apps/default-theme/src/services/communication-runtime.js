import { getSdkClient } from '../app/sdk-client.js';

// 通信运行时（契约 §4/§6）：浏览器唯一取数节奏调度器。
// 模式（polling/realtime）由服务端快照裁决；本模块只调度“何时触发刷新”，
// 刷新动作复用与模式无关的业务取数路径（SDK），业务组件绝不按模式分叉。

export const SIGNAL_UNREAD = 'unread';
export const SIGNAL_NOTIFICATIONS = 'notifications';
export const SIGNAL_MESSAGES = 'messages';
export const SIGNAL_FEED = 'feed';

const SETTINGS_REFRESH_INTERVAL_MS = 30_000;
const REALTIME_RETRY_BASE_MS = 2_000;
const REALTIME_RETRY_MAX_MS = 60_000;
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
  const statusListeners = new Set();
  let snapshot = null;
  let mode = null;
  let connectionStatus = 'idle';
  const chainTimers = new Map();
  let timers = [];
  let eventSource = null;
  let watchdog = null;
  let reconnectDelay = null;
  let settingsRefresh = null;
  let attempts = 0;
  let started = false;
  let destroyed = false;

  function emit(topic) {
    for (const listener of listeners) {
      try { listener(topic); } catch { /* 订阅方错误不影响调度 */ }
    }
  }

  function setConnectionStatus(value) {
    if (connectionStatus === value) return;
    connectionStatus = value;
    for (const listener of statusListeners) {
      try { listener(value); } catch { /* 状态订阅方错误不影响连接。 */ }
    }
  }

  function clearTransportScheduling() {
    for (const handle of chainTimers.values()) clearTimeoutFn(handle);
    chainTimers.clear();
    for (const handle of timers) clearTimeoutFn(handle);
    timers = [];
    if (watchdog) { clearTimeoutFn(watchdog); watchdog = null; }
    if (reconnectDelay) { clearTimeoutFn(reconnectDelay); reconnectDelay = null; }
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
    setConnectionStatus('idle');
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

  function scheduleRealtimeRetry() {
    if (!started || mode !== 'realtime' || reconnectDelay) return;
    attempts += 1;
    const delay = Math.min(REALTIME_RETRY_MAX_MS, REALTIME_RETRY_BASE_MS * 2 ** Math.min(attempts - 1, 8));
    reconnectDelay = setTimeoutFn(() => {
      reconnectDelay = null;
      if (started && mode === 'realtime') {
        clearTransportScheduling();
        void startRealtime();
      }
    }, delay);
  }

  function armWatchdog(heartbeatSeconds) {
    if (watchdog) clearTimeoutFn(watchdog);
    // 心跳注释不产生 EventSource 事件；看门狗超过 3 个心跳周期无信号即判定链路失效。
    const limit = Math.max(10_000, clampNumber(heartbeatSeconds, 15) * 3 * 1000);
    watchdog = setTimeoutFn(() => {
      watchdog = null;
      clearTransportScheduling();
      setConnectionStatus('fault');
      scheduleRealtimeRetry();
    }, limit);
  }

  function refreshAfterConnection() {
    // 首次连接和重连成功后补读当前事实；断线期间不启动定时取数。
    emit(SIGNAL_NOTIFICATIONS);
    emit(SIGNAL_MESSAGES);
    emit(SIGNAL_UNREAD);
  }

  async function startRealtime() {
    mode = 'realtime';
    if (snapshot?.status !== 'ready') {
      setConnectionStatus('fault');
      return;
    }
    setConnectionStatus('connecting');
    const client = clientResolver();
    if (!client) { setConnectionStatus('fault'); scheduleRealtimeRetry(); return; }
    let ticket;
    try {
      ticket = await client.notifications.streamTicket();
    } catch {
      setConnectionStatus('fault');
      scheduleRealtimeRetry();
      return;
    }
    if (destroyed || !started) return;
    const query = new URLSearchParams({ ticket: ticket.ticket });
    let source;
    try { source = eventSourceFactory(`${ticket.connect_url}?${query.toString()}`); } catch { /* 按连接故障处理。 */ }
    if (!source) { setConnectionStatus('fault'); scheduleRealtimeRetry(); return; }
    eventSource = source;
    armWatchdog(ticket.heartbeat_seconds);
    source.addEventListener('open', () => {
      if (eventSource !== source) return;
      attempts = 0;
      setConnectionStatus('ready');
      refreshAfterConnection();
    });
    source.addEventListener('heartbeat', () => {
      armWatchdog(ticket.heartbeat_seconds);
    });
    source.addEventListener('signal', (event) => {
      armWatchdog(ticket.heartbeat_seconds);
      let topic = null;
      try {
        const parsed = JSON.parse(event.data || '{}');
        if ([SIGNAL_NOTIFICATIONS, SIGNAL_MESSAGES, SIGNAL_UNREAD, SIGNAL_FEED].includes(parsed.topic)) topic = parsed.topic;
      } catch { /* 非法信号不触发业务读取。 */ }
      if (topic === null) return;
      emit(topic);
      if (topic === SIGNAL_NOTIFICATIONS || topic === SIGNAL_MESSAGES) emit(SIGNAL_UNREAD);
    });
    source.addEventListener('error', () => {
      // 一次性 ticket 失效后重新签发；管理员未切换模式时不启动轮询。
      if (eventSource !== source) return;
      clearTransportScheduling();
      setConnectionStatus('fault');
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
      const previousMode = snapshot?.desired_mode ?? snapshot?.effective_mode;
      const previousStatus = snapshot?.status;
      const nextMode = next?.desired_mode ?? next?.effective_mode;
      const pollingSettingsChanged = POLLING_SETTING_KEYS.some((key) => next?.settings?.[key] !== snapshot?.settings?.[key]);
      snapshot = next;
      if (nextMode !== previousMode || (nextMode === 'realtime' && next?.status !== previousStatus) || (nextMode === 'polling' && pollingSettingsChanged)) {
        clearTransportScheduling();
        attempts = 0;
        if (nextMode === 'realtime') await startRealtime();
        else if (nextMode === 'polling') startPolling();
        else { mode = null; setConnectionStatus('idle'); }
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
    const selectedMode = snapshot?.desired_mode ?? snapshot?.effective_mode;
    if (selectedMode === 'realtime') await startRealtime();
    else if (selectedMode === 'polling') startPolling();
    scheduleSettingsRefresh();
  }

  function stop() {
    started = false;
    clearScheduling();
    setConnectionStatus('idle');
  }

  function destroy() {
    destroyed = true;
    stop();
    listeners.clear();
    statusListeners.clear();
  }

  function subscribe(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
  }

  function subscribeStatus(listener) {
    statusListeners.add(listener);
    return () => statusListeners.delete(listener);
  }

  function currentMode() { return mode; }
  function currentConnectionStatus() { return connectionStatus; }

  if (documentRef) {
    documentRef.addEventListener?.('visibilitychange', () => {
      const settings = snapshot?.settings || {};
      if (settings.pause_when_hidden === false) return;
      if (isHidden()) {
        clearScheduling();
        setConnectionStatus('idle');
      } else if (started) {
        clearScheduling();
        void (async () => {
          const client = clientResolver();
          try { if (client) snapshot = await client.communication.settings(); } catch { /* 保留旧快照 */ }
          if (!started) return;
          const selectedMode = snapshot?.desired_mode ?? snapshot?.effective_mode;
          if (selectedMode === 'realtime') await startRealtime();
          else if (selectedMode === 'polling') startPolling();
          scheduleSettingsRefresh();
        })();
      }
    });
  }

  return { start, stop, destroy, subscribe, subscribeStatus, currentMode, currentConnectionStatus };
}

let singleton = null;
export function getCommunicationRuntime() {
  if (!singleton) singleton = createCommunicationRuntime();
  return singleton;
}
