import { parseMessageDate } from './message-utils.js';

export const NOTIFICATION_TYPES = new Set([
  'social.followed',
  'message',
  'content.card_liked',
  'content.comment_liked',
  'library.saved',
  'content.commented',
  'content.replied',
  'content.mentioned',
  'system.notice',
  'system.security',
  'system.moderation',
]);

export const NOTIFICATION_CATEGORIES = [
  { value: 'engagement', label: '互动消息', to: '/inbox' },
  { value: 'system', label: '系统通知', to: '/inbox/system' },
];

const TYPE_PRESENTATION = {
  'social.followed': { category: 'social', title: '关注了你', description: '可以前往对方主页了解更多。', icon: 'user' },
  message: { category: 'message', title: '给你发来一条私信', description: '打开会话继续交流。', icon: 'mail' },
  'content.card_liked': { category: 'interaction', title: '喜欢了你的内容', description: '你的分享收到了新的喜欢。', icon: 'heart' },
  'content.comment_liked': { category: 'interaction', title: '喜欢了你的评论', description: '你留下的评论收到了新的喜欢。', icon: 'heart' },
  'library.saved': { category: 'interaction', title: '收藏了你的内容', description: '你的分享被加入了收藏。', icon: 'bookmark' },
  'content.commented': { category: 'interaction', title: '评论了你的内容', description: '打开讨论查看完整评论。', icon: 'comment' },
  'content.replied': { category: 'interaction', title: '回复了你的评论', description: '打开讨论继续这段对话。', icon: 'comment' },
  'content.mentioned': { category: 'interaction', title: '在内容中提到了你', description: '打开相关内容查看提及上下文。', icon: 'user' },
  'system.notice': { category: 'system', title: '系统通知', description: 'LoveCards 向你发送了一条服务通知。', icon: 'info' },
  'system.security': { category: 'system', title: '账号安全提醒', description: '请留意与你的账号安全相关的变化。', icon: 'settings' },
  'system.moderation': { category: 'system', title: '内容管理通知', description: '你的内容状态有了新的变化。', icon: 'flag' },
};

const RESOURCE_STATE_LABELS = {
  deleted: '内容已删除',
  restricted: '当前不可访问',
  unavailable: '当前不可访问',
};

const VALID_CATEGORIES = new Set(['social', 'message', 'interaction', 'system']);
const ENGAGEMENT_CATEGORIES = new Set(['social', 'message', 'interaction']);
const VALID_ACTOR_STATES = new Set(['active', 'unavailable', 'system']);
const VALID_RESOURCE_STATES = new Set(['available', 'deleted', 'restricted', 'unavailable']);
const VALID_SEVERITIES = new Set(['info', 'success', 'warning', 'critical']);

const SEVERITY_LABELS = {
  info: '信息',
  success: '完成',
  warning: '重要',
  critical: '紧急',
};

export function safeNotificationActionUrl(value) {
  if (typeof value !== 'string') return null;
  const path = value.trim();
  const containsControlCharacter = [...path].some((character) => {
    const code = character.charCodeAt(0);
    return code <= 31 || code === 127;
  });
  if (!path.startsWith('/') || path.startsWith('//') || path.includes('\\') || containsControlCharacter) return null;
  return path;
}

export function notificationPresentation(type) {
  return TYPE_PRESENTATION[type] || { category: 'interaction', title: '新的社区通知', description: '你收到了一条新的社区动态。', icon: 'info' };
}

export function normalizeNotificationItem(item) {
  const source = item && typeof item === 'object' ? item : {};
  const type = typeof source.type === 'string' && source.type.trim() ? source.type.trim() : 'unknown';
  const presentation = notificationPresentation(type);
  const payload = source.payload && typeof source.payload === 'object'
    ? source.payload
    : source.data && typeof source.data === 'object' ? source.data : {};
  const category = VALID_CATEGORIES.has(source.category) ? source.category : presentation.category;
  const resourceState = VALID_RESOURCE_STATES.has(source.resource_state) ? source.resource_state : 'unavailable';
  const actor = source.actor && typeof source.actor === 'object' ? source.actor : null;
  const hasActiveActor = Boolean(actor && typeof actor.username === 'string' && actor.username.trim());
  const isSystem = type.startsWith('system.') || category === 'system';
  const declaredActorState = VALID_ACTOR_STATES.has(source.actor_state) ? source.actor_state : isSystem ? 'system' : 'unavailable';
  const actorState = isSystem ? 'system' : declaredActorState === 'active' && !hasActiveActor ? 'unavailable' : declaredActorState;
  const severity = VALID_SEVERITIES.has(payload.severity) ? payload.severity : 'info';
  const actionUrl = resourceState === 'available' ? safeNotificationActionUrl(source.action_url) : null;
  const hasDeclaredResource = typeof source.resource_type === 'string' && Boolean(source.resource_type.trim());
  const hasInvalidAction = source.action_url != null && !actionUrl;
  const resourceStateLabel = RESOURCE_STATE_LABELS[resourceState]
    || (resourceState === 'available' && !actionUrl && (hasDeclaredResource || hasInvalidAction) ? '当前不可访问' : '');

  return {
    ...source,
    id: Number(source.id) || 0,
    type,
    knownType: NOTIFICATION_TYPES.has(type),
    category,
    presentation,
    payload,
    actor,
    actorState,
    resourceState,
    resourceStateLabel,
    actionUrl,
    severity,
    severityLabel: SEVERITY_LABELS[severity],
  };
}

export function notificationMatchesCategory(item, category) {
  const itemCategory = normalizeNotificationItem(item).category;
  if (category === 'system') return itemCategory === 'system';
  if (category === 'engagement') return ENGAGEMENT_CATEGORIES.has(itemCategory);
  return false;
}

export function notificationListParams(category, cursor = null) {
  return {
    cursor: cursor || undefined,
    limit: 20,
    category: category === 'system' ? 'system' : 'engagement',
  };
}

export function notificationDateGroup(value, now = new Date()) {
  const date = parseMessageDate(value);
  if (Number.isNaN(date.getTime())) return '更早';
  const reference = parseMessageDate(now);
  if (Number.isNaN(reference.getTime())) return '更早';
  const today = new Date(reference.getFullYear(), reference.getMonth(), reference.getDate());
  const target = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  const days = Math.round((today.getTime() - target.getTime()) / 86400000);
  if (days <= 0) return '今天';
  if (days === 1) return '昨天';
  return '更早';
}

export function groupNotifications(items, now = new Date()) {
  const groups = new Map();
  for (const item of items) {
    const label = notificationDateGroup(item?.created_at, now);
    if (!groups.has(label)) groups.set(label, []);
    groups.get(label).push(item);
  }
  return ['今天', '昨天', '更早'].filter((label) => groups.has(label)).map((label) => ({ label, items: groups.get(label) }));
}
