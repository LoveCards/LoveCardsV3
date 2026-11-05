function numberSetting(env, key, fallback, min, max) {
  const raw = env[key];
  const value = raw === undefined || raw === '' ? fallback : Number(raw);
  if (!Number.isInteger(value) || value < min || value > max) throw new Error(`${key} must be an integer between ${min} and ${max}`);
  return value;
}

export function readConfig(env = process.env) {
  const host = env.LC_REALTIME_HOST || '127.0.0.1';
  if (host !== '127.0.0.1' && host !== '::1' && host !== '0.0.0.0') throw new Error('LC_REALTIME_HOST is not supported');
  const ticketSecret = env.LC_REALTIME_TICKET_SECRET;
  const internalSecret = env.LC_REALTIME_INTERNAL_SECRET;
  if (typeof ticketSecret !== 'string' || ticketSecret.length < 32) throw new Error('LC_REALTIME_TICKET_SECRET must have at least 32 characters');
  if (typeof internalSecret !== 'string' || internalSecret.length < 32) throw new Error('LC_REALTIME_INTERNAL_SECRET must have at least 32 characters');
  if (ticketSecret === internalSecret) throw new Error('ticket and internal secrets must differ');
  return {
    host,
    port: numberSetting(env, 'LC_REALTIME_PORT', 7070, 1, 65535),
    version: env.LC_REALTIME_VERSION || 'dev',
    protocol: 'lc-realtime-1',
    ticketSecret, internalSecret,
    heartbeatSeconds: numberSetting(env, 'LC_REALTIME_HEARTBEAT_SECONDS', 15, 5, 120),
    ticketTtlSeconds: numberSetting(env, 'LC_REALTIME_TICKET_TTL_SECONDS', 60, 15, 300),
    maxConnections: numberSetting(env, 'LC_REALTIME_MAX_CONNECTIONS', 1000, 1, 100000),
    maxConnectionsPerRecipient: numberSetting(env, 'LC_REALTIME_MAX_CONNECTIONS_PER_RECIPIENT', 3, 1, 20),
    drainSeconds: numberSetting(env, 'LC_REALTIME_DRAIN_SECONDS', 5, 1, 30),
  };
}
