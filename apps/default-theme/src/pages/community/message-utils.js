export function conversationIdOf(item) {
  const id = Number(item?.conversation_id ?? item?.id);
  return Number.isInteger(id) && id > 0 ? id : null;
}

export function conversationRecipientOf(item, currentUserId) {
  const candidates = [item?.recipient_uid, item?.peer_uid, item?.other_user_uid, item?.participant_uid];
  const value = candidates.find((uid) => /^\d{12}$/.test(String(uid ?? '')) && String(uid) !== String(currentUserId));
  return value || null;
}

/**
 * The messaging endpoints currently return database timestamps without an
 * offset. The repository writes those values in UTC, so treat that narrow SQL
 * shape as UTC while leaving ISO strings and Date instances untouched.
 */
export function parseMessageDate(value) {
  if (value instanceof Date) return new Date(value.getTime());
  if (typeof value !== 'string') return new Date(value);
  const input = value.trim();
  if (!input) return new Date(Number.NaN);
  const sqlUtc = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(input);
  return new Date(sqlUtc ? `${input.replace(' ', 'T')}Z` : input);
}

export function conversationPreviewOf(item) {
  const candidates = [item?.last_message_body, item?.last_message, item?.preview];
  const preview = candidates.find((value) => typeof value === 'string' && value.trim());
  if (preview) return preview.trim();
  return item?.last_message_id || item?.last_message_at ? '消息内容暂不可见' : '还没有消息，发送第一条';
}

export function conversationTimeOf(item) {
  return item?.last_message_at || item?.updated_at || item?.created_at || null;
}

export function conversationUnreadCountOf(item) {
  const count = Number(item?.unread_count);
  return Number.isInteger(count) && count > 0 ? count : 0;
}

export function conversationUnreadTotalOf(items) {
  return Array.isArray(items) ? items.reduce((total, item) => total + conversationUnreadCountOf(item), 0) : 0;
}

export function formatMessageTime(value, now = new Date()) {
  const date = parseMessageDate(value);
  if (Number.isNaN(date.getTime())) return '时间未提供';
  const reference = parseMessageDate(now);
  if (Number.isNaN(reference.getTime())) return '时间未提供';
  const elapsed = Math.max(0, reference.getTime() - date.getTime());
  const minute = 60 * 1000;
  const hour = 60 * minute;
  const day = 24 * hour;
  if (elapsed < minute) return '刚刚';
  if (elapsed < hour) return `${Math.max(1, Math.floor(elapsed / minute))} 分钟前`;
  if (elapsed < day && date.toDateString() === reference.toDateString()) return `${Math.floor(elapsed / hour)} 小时前`;
  if (elapsed < 2 * day) return `昨天 ${new Intl.DateTimeFormat('zh-CN', { hour: '2-digit', minute: '2-digit' }).format(date)}`;
  return new Intl.DateTimeFormat('zh-CN', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date);
}
