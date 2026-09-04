import { getSdkClient, sdkConfigured } from '../app/sdk-client.js';
import { getRuntimeDataMode } from '../app/runtime-context.js';
import { previewFeed } from '../fixtures/feed-preview.js';
import { normalizeSdkCard } from './feed-service.js';
import { recordHistoryItem } from './community-service.js';

function dataMode() {
  return getRuntimeDataMode();
}

function normalizeComment(comment) {
  if (!Number.isInteger(comment?.id) || !Number.isInteger(comment?.user_id)) throw new Error('SDK 契约错误：评论缺少有效 id 或 user_id。');
  const authorUid = /^\d{12}$/.test(String(comment.uid || '').trim()) ? String(comment.uid).trim() : null;
  const authorHandle = typeof comment.username === 'string' && comment.username.trim() ? comment.username.trim() : null;
  const authorAvatar = typeof comment.avatar === 'string' && comment.avatar.trim() ? comment.avatar.trim() : null;
  return {
    id: comment.id,
    ownerId: comment.user_id,
    authorUid,
    author: authorHandle || `用户 ${comment.user_id}`,
    authorHandle,
    authorAvatar,
    content: String(comment.content || '').trim(),
    mentions: Array.isArray(comment.mentions) ? comment.mentions : [],
    createdAt: comment.created_at || '',
    createdAtMs: Date.parse(comment.created_at || ''),
    likes: Number(comment.goods || 0),
    parentId: comment.parent_id ?? null,
    status: Number(comment.status ?? 0),
    isPinned: comment.is_pinned === true,
  };
}

function normalizeCommentCoverage(result) {
  const loaded = Array.isArray(result?.data) ? result.data.length : 0;
  const total = Number(result?.pagination?.totalItems);
  return {
    loaded,
    total: Number.isFinite(total) ? total : loaded,
    complete: !Number.isFinite(total) || total <= loaded,
  };
}

function commentCardId(comment) {
  const value = Number(comment?.card_id ?? comment?.aid ?? comment?.pid);
  return Number.isInteger(value) ? value : null;
}

async function includeFocusedComment(client, cardId, result, focusedCommentId) {
  const id = Number(focusedCommentId);
  if (!Number.isInteger(id) || id <= 0 || result.data.some((comment) => Number(comment?.id) === id)) return result;

  try {
    const focused = await client.comments.get(id);
    if (commentCardId(focused) !== cardId) return result;

    const additions = [focused];
    const parentId = Number(focused?.parent_id);
    if (Number.isInteger(parentId) && parentId > 0 && !result.data.some((comment) => Number(comment?.id) === parentId)) {
      try {
        const parent = await client.comments.get(parentId);
        if (commentCardId(parent) === cardId) additions.unshift(parent);
      } catch {
        // The focused reply can still be shown when its parent is unavailable.
      }
    }
    return { ...result, data: [...result.data, ...additions] };
  } catch (error) {
    if (error?.status === 404) return result;
    throw error;
  }
}

export function getDetailSnapshot(id) {
  const mode = dataMode();
  if (mode === 'design-preview') return { source: 'preview', card: previewFeed.find((item) => item.id === id) || null, comments: [], commentCoverage: { loaded: 0, total: 0, complete: true } };
  if (mode !== 'api') return { source: 'configuration-error', card: null, comments: [], commentCoverage: { loaded: 0, total: 0, complete: true }, detail: 'VITE_DATA_MODE 只能是 api 或 design-preview。' };
  if (!sdkConfigured()) return { source: 'configuration-error', card: null, comments: [], commentCoverage: { loaded: 0, total: 0, complete: true }, detail: '请配置 VITE_API_URL，或显式启用 design-preview。' };
  return { source: 'loading', card: null, comments: [], commentCoverage: { loaded: 0, total: 0, complete: true } };
}

export async function loadDetail({ id, commentId, signal }) {
  const snapshot = getDetailSnapshot(id);
  if (snapshot.source !== 'loading') return snapshot;
  const numericId = Number(id);
  if (!Number.isInteger(numericId)) return { source: 'not-found', card: null, comments: [], commentCoverage: { loaded: 0, total: 0, complete: true } };
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
  const client = getSdkClient();
  let rawCard;
  let rawComments;
  try {
    rawCard = await client.cards.get(numericId);
  } catch (error) {
    if (error?.status === 404) return { source: 'not-found', card: null, comments: [], commentCoverage: { loaded: 0, total: 0, complete: true } };
    throw error;
  }
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
  const historyWrite = client.getToken?.()
    ? recordHistoryItem(numericId).catch(() => undefined)
    : Promise.resolve();
  try {
    rawComments = await client.comments.cardList(numericId, { page: 1, list_rows: 50 });
  } catch (error) {
    if (error?.status === 403) {
      await historyWrite;
      return { source: 'live', card: normalizeSdkCard(rawCard), comments: [], commentsError: '评论暂不可见，内容本身仍可查看。', commentCoverage: { loaded: 0, total: 0, complete: false } };
    }
    throw error;
  }
  if (!rawComments || !Array.isArray(rawComments.data)) throw new Error('SDK 契约错误：comments.cardList() 应返回 ListResult。');
  rawComments = await includeFocusedComment(client, numericId, rawComments, commentId);
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
  await historyWrite;
  const commentCoverage = normalizeCommentCoverage(rawComments);
  return { source: 'live', card: normalizeSdkCard(rawCard), comments: rawComments.data.map(normalizeComment), commentCoverage };
}

export async function createDetailComment(cardId, content, parentId = null) {
  if (dataMode() !== 'api') throw new Error('设计预览为只读，评论不会保存。');
  const client = getSdkClient();
  if (!client) throw new Error('尚未配置 API，评论未发送。');
  if (!client.getToken()) throw new Error('请先登录后再发表评论。');
  const numericId = Number(cardId);
  if (!Number.isInteger(numericId)) throw new Error('当前内容不能发表评论。');
  if (content.trim().length > 140) throw new Error('评论最多 140 个字符。');
  const numericParentId = parentId === null || parentId === undefined || parentId === '' ? null : Number(parentId);
  return client.comments.create(numericId, {
    content: content.trim(),
    ...(Number.isInteger(numericParentId) && numericParentId > 0 ? { parent_id: numericParentId } : {}),
  });
}

export async function updateDetailCommentLike(commentId, liked) {
  if (dataMode() !== 'api') throw new Error('当前模式不能修改评论喜欢状态。');
  const client = getSdkClient();
  if (!client?.getToken()) throw new Error('请先登录后再进行互动。');
  const numericId = Number(commentId);
  if (!Number.isInteger(numericId) || numericId <= 0) throw new Error('当前评论不可操作。');
  if (liked) return client.comments.like(numericId);
  await client.comments.unlike(numericId);
  return undefined;
}

export function supportsUserCommentPinning() {
  const comments = getSdkClient()?.comments;
  return typeof comments?.pin === 'function' && typeof comments?.unpin === 'function';
}

export async function updatePinnedComment(cardId, commentId, pinned) {
  if (dataMode() !== 'api') throw new Error('设计预览为只读，评论置顶不会保存。');
  const client = getSdkClient();
  if (!client?.getToken()) throw new Error('请先登录后再管理评论。');
  const numericCardId = Number(cardId);
  const numericCommentId = Number(commentId);
  if (!Number.isInteger(numericCardId) || numericCardId <= 0) throw new Error('当前卡片不可操作。');
  if (!supportsUserCommentPinning()) throw new Error('当前 SDK 暂不支持作者置顶评论。');
  if (pinned) {
    if (!Number.isInteger(numericCommentId) || numericCommentId <= 0) throw new Error('当前评论不可操作。');
    return client.comments.pin(numericCardId, { comment_id: numericCommentId });
  }
  return client.comments.unpin(numericCardId);
}
