import { getSdkClient } from '../app/sdk-client.js';
import { normalizeSdkCard } from './feed-service.js';

const STABLE_ID_KEYS = [
  'id',
  'notification_id',
  'message_id',
  'conversation_id',
  'draft_id',
  'public_id',
  'client_message_id',
];

function stableItemId(item) {
  if (!item || typeof item !== 'object') return null;
  for (const key of STABLE_ID_KEYS) {
    const value = item[key];
    if (typeof value === 'string' && value.trim()) return `${key}:${value.trim()}`;
    if (typeof value === 'number' && Number.isFinite(value)) return `${key}:${value}`;
  }
  return null;
}

export function dedupeByStableId(items) {
  const seen = new Set();
  return items.filter((item) => {
    const id = stableItemId(item);
    if (id === null) return true;
    if (seen.has(id)) return false;
    seen.add(id);
    return true;
  });
}

export function normalizeCollection(value) {
  if (value && typeof value === 'object' && 'data' in value) {
    const nested = value.data;
    if (Array.isArray(nested)) {
      return {
        items: dedupeByStableId(nested),
        nextCursor: value.next_cursor == null ? null : String(value.next_cursor),
        hasMore: Boolean(value.has_more),
      };
    }
    if (nested && typeof nested === 'object') return normalizeCollection(nested);
  }
  if (Array.isArray(value)) {
    return { items: dedupeByStableId(value), nextCursor: null, hasMore: false };
  }
  if (!value || typeof value !== 'object' || !Array.isArray(value.items)) {
    return { items: [], nextCursor: null, hasMore: false };
  }
  const nextCursor = value.next_cursor ?? null;
  return {
    items: dedupeByStableId(value.items),
    nextCursor: nextCursor === null ? null : String(nextCursor),
    hasMore: Boolean(value.has_more),
  };
}

export function classifyCommunityError(error) {
  const status = Number(error?.status ?? error?.response?.status ?? 0);
  const kind = status === 401
    ? 'unauthenticated'
    : status === 403
      ? 'forbidden'
      : status === 404
        ? 'not-found'
        : status === 0
          ? 'network'
          : 'error';
  return {
    kind,
    status,
    message: error?.message || '请求失败，请稍后重试。',
    error,
  };
}

function defaultMessageId() {
  if (typeof globalThis.crypto?.randomUUID === 'function') return globalThis.crypto.randomUUID();
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
}

export function ensureClientMessageId(value, createId = defaultMessageId) {
  const existing = typeof value === 'string' ? value.trim() : '';
  return existing || `lc-message-${createId()}`;
}

function client() {
  const value = getSdkClient();
  if (!value) throw new Error('尚未配置 API。');
  return value;
}

export class CommunityCapabilityError extends Error {
  constructor(capability, message) {
    super(message);
    this.name = 'CommunityCapabilityError';
    this.code = 'community_capability_unavailable';
    this.capability = capability;
  }
}

export function normalizeDraftPayload(value) {
  if (value && typeof value === 'object' && !Array.isArray(value)) return value;
  if (typeof value !== 'string' || !value.trim()) return {};
  try {
    const parsed = JSON.parse(value);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch {
    return {};
  }
}

export function draftSummary(item) {
  const content = String(item?.content || '').trim();
  const firstLine = content.split(/\r?\n/).map((line) => line.trim()).find(Boolean);
  if (firstLine) return firstLine.slice(0, 48);
  const payload = normalizeDraftPayload(item?.payload);
  const type = payload.publishType === 'video' ? '视频' : payload.publishType === 'image' || payload.kind === 'gallery' ? '图文' : '随笔';
  return `${type}草稿`;
}

export const getPublicProfile = (uid) => client().social.profile(uid);
function positiveInteger(value, fallback) {
  if (value === null || value === undefined || value === '' || typeof value === 'boolean') return fallback;
  const number = typeof value === 'number' ? value : Number(value);
  return Number.isInteger(number) && number > 0 ? number : fallback;
}

function normalizePageSize(value) {
  if (value === null || value === undefined || value === '' || typeof value === 'boolean') return 30;
  const number = typeof value === 'number' ? value : Number(value);
  if (!Number.isInteger(number)) return 30;
  return Math.min(100, Math.max(1, number));
}

export async function listPublicCardsByUser(uid, params = {}) {
  const normalizedUid = typeof uid === 'string' ? uid.trim() : '';
  if (!/^\d{12}$/.test(normalizedUid)) throw new Error('用户主页 UID 无效。');
  const sdk = client();
  if (typeof sdk.cards?.listPublicByUser !== 'function') {
    throw new CommunityCapabilityError('cards.listPublicByUser', '当前 SDK 尚未提供公开用户内容接口。');
  }
  const result = await sdk.cards.listPublicByUser(normalizedUid, {
    page: positiveInteger(params.page, 1),
    list_rows: normalizePageSize(params.list_rows),
  });
  if (!result || !Array.isArray(result.data)) throw new Error('SDK 契约错误：cards.listPublicByUser() 应返回 ListResult。');
  return { ...result, data: result.data.map(normalizeSdkCard) };
}
export const getUserRelation = (uid) => client().social.relation(uid);
export const listFollowers = async (uid, params = {}) => normalizeCollection(await client().social.followers(uid, { cursor: params.cursor || undefined, limit: params.limit || 20 }));
export const listFollowing = async (uid, params = {}) => normalizeCollection(await client().social.following(uid, { cursor: params.cursor || undefined, limit: params.limit || 20 }));
export const followUser = (uid) => client().social.follow(uid);
export const unfollowUser = (uid) => client().social.unfollow(uid);

export const listDrafts = async () => normalizeCollection(await client().drafts.list());
export const getDraft = (draftId) => client().drafts.get(draftId);
export const createDraft = (data) => client().drafts.create(data);
export const updateDraft = (draftId, data) => client().drafts.update(draftId, data);
export const deleteDraft = (draftId) => client().drafts.delete(draftId);

export const listSavedItems = async () => normalizeCollection(await client().library.list({ limit: 50 }));
export const saveItem = (data) => client().library.save(data);
export function saveCard(cardId) {
  const value = Number(cardId);
  if (!Number.isInteger(value) || value <= 0) throw new Error('内容标识无效，无法收藏。');
  return saveItem({ resource_type: 'card', resource_id: String(value) });
}
export function removeSavedItem(input) {
  const reference = input && typeof input === 'object'
    ? input
    : { resource_type: 'card', resource_id: input };
  const resourceType = String(reference.resource_type ?? '').trim().toLowerCase();
  const resourceId = String(reference.resource_id ?? '').trim();
  if (!/^[a-z][a-z0-9_]{0,31}$/.test(resourceType)) {
    throw new Error('资源类型无效，无法取消收藏。');
  }
  if (!/^[A-Za-z0-9][A-Za-z0-9._:-]*$/.test(resourceId) || resourceId.length > 128) {
    throw new Error('资源标识无效，无法取消收藏。');
  }
  return client().library.remove({ resource_type: resourceType, resource_id: resourceId });
}
export const listHistory = async () => normalizeCollection(await client().library.history({ limit: 50 }));
export function recordHistoryItem(resourceId) {
  const value = String(resourceId ?? '').trim();
  if (!/^[A-Za-z0-9][A-Za-z0-9._:-]*$/.test(value) || value.length > 128) {
    throw new Error('资源标识无效，无法记录浏览历史。');
  }
  return client().library.history_record({ resource_type: 'card', resource_id: value });
}
export const clearHistory = () => client().library.history_clear();

export const listNotifications = async (params) => normalizeCollection(await client().notifications.list(params));
export const getUnreadNotificationCount = (params) => client().notifications.unread(params);
export function markNotificationRead(input) {
  const values = Array.isArray(input) ? input : [input];
  const ids = [...new Set(values.map(Number).filter((value) => Number.isInteger(value) && value > 0))];
  if (!ids.length) throw new Error('请选择要标记为已读的通知。');
  return client().notifications.read({ ids });
}
export const markAllNotificationsRead = (params) => client().notifications.readAll(params);
export const getNotificationStreamTicket = () => client().notifications.streamTicket();

export const listConversations = async () => normalizeCollection(await client().messages.conversations());
export const listMessages = async (conversationId) => normalizeCollection(await client().messages.list(conversationId));
export function sendMessage({ recipientUid, body, clientMessageId }) {
  const normalizedRecipientUid = String(recipientUid ?? '').trim();
  const normalizedBody = typeof body === 'string' ? body.trim() : '';
  if (!/^\d{12}$/.test(normalizedRecipientUid)) {
    throw new Error('当前会话缺少有效的收件人 UID，暂时无法发送消息。');
  }
  if (!normalizedBody) {
    throw new Error('消息内容不能为空。');
  }
  return client().messages.send({
    recipient_uid: normalizedRecipientUid,
    body: normalizedBody,
    client_message_id: ensureClientMessageId(clientMessageId),
  });
}

export const getPrivacyPreferences = () => client().safety.preferences();
export const updatePrivacyPreferences = (data) => client().safety.preferences_update(data);
export const blockUser = (userId) => client().safety.block(userId);
export const unblockUser = (userId) => client().safety.unblock(userId);
export const reportResource = (data) => client().safety.report(data);

export const requestAccountRecovery = (data) => client().session.recovery_request(data);
export const resetAccountRecovery = (data) => client().session.recovery_reset(data);
