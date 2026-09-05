import { getSdkClient, sdkConfigured } from '../app/sdk-client.js';
import { getRuntimeDataMode } from '../app/runtime-context.js';
import { feedMediaByKey, previewFeed } from '../fixtures/feed-preview.js';
import { inferCardPattern, resolveCardPattern } from '../features/feed/card-patterns.js';
import { normalizeCardInteractions } from '../features/feed/card-interactions.js';
import { normalizeMediaFit } from '../features/media/media-fit.js';

export const HOME_CHANNELS = [
  { id: 'recommended', label: '推荐' },
  { id: 'following', label: '关注' },
  { id: 'latest', label: '最新' },
];

export class FeatureUnavailableError extends Error {
  constructor(message) {
    super(message);
    this.name = 'FeatureUnavailableError';
  }
}

const VIDEO_FILE_PATTERN = /\.(?:m4v|mov|mp4|ogv|webm)(?:$|[?#])/i;
const GIF_FILE_PATTERN = /\.gif(?:$|[?#])/i;
const MAX_MEDIA_ITEMS = 15;

function abortIfNeeded(signal) {
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
}

function readDataObject(value) {
  return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
}

function readString(value) {
  return typeof value === 'string' ? value.trim() : '';
}

function readStringList(value) {
  let candidate = value;
  if (typeof candidate === 'string') {
    try {
      candidate = JSON.parse(candidate);
    } catch {
      return [];
    }
  }
  return Array.isArray(candidate) ? candidate.map(readString).filter(Boolean) : [];
}

function inferMediaType(url) {
  if (VIDEO_FILE_PATTERN.test(url)) return 'video';
  if (GIF_FILE_PATTERN.test(url)) return 'gif';
  return 'image';
}

function normalizeMediaItems(card, data, isCurated, fallbackAlt) {
  const cardMediaFit = normalizeMediaFit(data.mediaFit || data.media_fit || data.contentMode || data.content_mode);
  const mediaFits = Array.isArray(data.mediaFits) ? data.mediaFits : [];
  const mediaFocalPoints = Array.isArray(data.mediaFocalPoints) ? data.mediaFocalPoints : [];
  if (Array.isArray(card?.media)) {
    const orderedMedia = card.media.map((value, index) => ({ value, index, position: Number(readDataObject(value).position) }))
      .sort((left, right) => {
        const leftPosition = Number.isSafeInteger(left.position) && left.position >= 0 ? left.position : left.index;
        const rightPosition = Number.isSafeInteger(right.position) && right.position >= 0 ? right.position : right.index;
        return leftPosition - rightPosition || left.index - right.index;
      })
      .map(({ value }) => value);
    const seenMedia = new Set();
    const typedMedia = orderedMedia.map((value, index) => {
      const item = readDataObject(value);
      const url = readString(item.url);
      if (!url || seenMedia.has(url)) return null;
      seenMedia.add(url);
      const mime = readString(item.mime_type).toLowerCase();
      const type = item.type === 'video' || mime.startsWith('video/')
        ? 'video'
        : item.type === 'gif' || item.is_animated === true || mime === 'image/gif' ? 'gif' : 'image';
      const media = {
        url,
        type,
        alt: readString(item.alt) || (orderedMedia.length > 1 ? `${fallbackAlt}（${index + 1}/${orderedMedia.length}）` : fallbackAlt),
        poster: type === 'video' ? (readString(item.poster_url) || null) : null,
        fit: normalizeMediaFit(item.fit || item.media_fit || item.content_mode || mediaFits[index], cardMediaFit),
      };
      if (Number.isInteger(item.file_id)) media.fileId = item.file_id;
      if (Number.isFinite(Number(item.width))) media.width = Number(item.width);
      if (Number.isFinite(Number(item.height))) media.height = Number(item.height);
      if (Number.isFinite(Number(item.duration_ms))) media.durationMs = Number(item.duration_ms);
      const focalOverride = readDataObject(mediaFocalPoints[index]);
      const focalX = Number(item.focal_x ?? item.focalX ?? focalOverride.x);
      const focalY = Number(item.focal_y ?? item.focalY ?? focalOverride.y);
      if (Number.isFinite(focalX) && focalX >= 0 && focalX <= 1 && Number.isFinite(focalY) && focalY >= 0 && focalY <= 1) media.focalPoint = { x: focalX, y: focalY };
      return media;
    }).filter(Boolean).slice(0, MAX_MEDIA_ITEMS);
    if (typedMedia.length > 0) return typedMedia;
  }

  const pictures = readStringList(card?.pictures);
  const mediaKeys = isCurated && Array.isArray(data.mediaKeys)
    ? data.mediaKeys.map(readString).filter((key) => feedMediaByKey[key])
    : [];
  const bundledUrls = mediaKeys.map((key) => feedMediaByKey[key]);
  const mediaKey = isCurated ? readString(data.mediaKey) : '';
  const fallbackUrl = readString(card?.cover) || feedMediaByKey[mediaKey] || '';
  const sourceUrls = pictures.length > 0 ? pictures : bundledUrls.length > 0 ? bundledUrls : fallbackUrl ? [fallbackUrl] : [];
  const seen = new Set();
  const urls = sourceUrls.filter((url) => {
    if (seen.has(url)) return false;
    seen.add(url);
    return true;
  }).slice(0, MAX_MEDIA_ITEMS);

  return urls.map((url, index) => {
    const type = readString(data.kind) === 'video' && urls.length === 1 ? 'video' : inferMediaType(url);
    return {
      url,
      type,
      alt: urls.length > 1 ? `${fallbackAlt}（${index + 1}/${urls.length}）` : fallbackAlt,
      poster: type === 'video' && card?.cover && card.cover !== url ? card.cover : null,
      fit: normalizeMediaFit(mediaFits[index], cardMediaFit),
    };
  });
}

function resolveLayout(card, pattern, value) {
  if (Number(card?.is_top) !== 1) return null;
  const expected = resolveCardPattern(pattern).layout;
  return value === expected ? expected : null;
}

export function normalizeSdkCard(card) {
  if (!Number.isInteger(card?.id) || !Number.isInteger(card?.user_id)) {
    throw new Error('SDK 契约错误：Card 必须包含有效的 id 与 user_id。');
  }
  const data = readDataObject(card?.data);
  const isCurated = Number(card?.is_top) === 1;
  const interactions = normalizeCardInteractions();
  const content = String(card?.content || '').trim();
  // Card has one authorable text field. The view-model keeps the historical
  // `title` key for component compatibility, but it always represents content.
  // Legacy curated titles are only a last-resort fallback for records without
  // content and never create a second text hierarchy.
  const title = content || readString(data.title) || '未命名内容';
  const body = '';
  const mediaAlt = readString(data.alt) || title;
  const mediaItems = normalizeMediaItems(card, data, isCurated, mediaAlt);
  const kind = inferCardPattern({ requestedKind: readString(data.kind), mediaItems });
  const pattern = resolveCardPattern(kind);
  const authorUid = /^\d{12}$/.test(readString(card.uid)) ? readString(card.uid) : null;
  const authorHandle = readString(card.username) || null;
  const authorAvatar = readString(card.avatar) || readString(data.avatar) || null;

  return {
    id: String(card.id),
    ownerId: card.user_id,
    ownerUid: authorUid,
    kind,
    layout: resolveLayout(card, kind, readString(data.layout)),
    pattern,
    patternLabel: pattern.label,
    interactions,
    author: authorHandle || (isCurated ? readString(data.author) : '') || `用户 ${card.user_id}`,
    authorHandle,
    authorAvatar,
    authorUid,
    authorHref: authorUid
      ? `/user/${encodeURIComponent(authorUid)}`
      : (authorHandle ? `/user/${encodeURIComponent(authorHandle)}` : null),
    visibility: card.visibility,
    meta: (isCurated ? readString(data.meta) : '') || card?.created_at || '',
    content: title,
    title,
    body,
    mentions: Array.isArray(card?.mentions) ? card.mentions : [],
    topics: Array.isArray(card?.topics) ? card.topics.filter((topic) => Number.isInteger(Number(topic?.id)) && String(topic?.name || '').trim()).map((topic) => ({ id: Number(topic.id), name: String(topic.name).trim() })) : [],
    // The hydrated topics relation is authoritative. `data.topic` is a legacy
    // presentation field and may contain an id-like value (for example `#12`).
    // Never let that legacy value override the human-readable tag name.
    topic: card?.topics?.[0]?.name
      ? `# ${String(card.topics[0].name).trim()}`
      : (/^#?\s*\d+$/.test(readString(data.topic))
        ? ''
        : (readString(data.topic) || (typeof card?.tags?.[0] === 'string' && !/^\s*\d+\s*$/.test(card.tags[0]) ? `# ${card.tags[0]}` : ''))),
    topicId: Number(card?.topics?.[0]?.id) || null,
    likes: Number(card?.goods || 0),
    replies: Number(card?.comments || 0),
    viewerLiked: null,
    mediaItems,
    mediaFit: normalizeMediaFit(data.mediaFit || data.media_fit || data.contentMode || data.content_mode),
    mediaUrl: mediaItems[0]?.url || null,
    mediaAlt,
    mediaCount: mediaItems.length > 1 ? mediaItems.length : null,
    mediaLabel: kind === 'video' ? (readString(data.mediaLabel) || null) : null,
    duration: kind === 'video' ? (readString(data.duration) || null) : null,
  };
}

export function stabilizeFeedLayouts(cards) {
  const used = new Set();
  return cards.map((card) => {
    if (!card.layout) return card;
    if (used.has(card.layout)) return { ...card, layout: null };
    used.add(card.layout);
    return card;
  });
}

function filterCards(cards, channel) {
  if (channel === 'latest') return [...cards];
  if (channel === 'gallery' || channel === 'video' || channel === 'discussion') {
    return cards.filter((card) => card.kind === channel);
  }
  return cards;
}

export function getDataMode() {
  return getRuntimeDataMode();
}

export function getHomeFeedSnapshot(channel = 'recommended') {
  const mode = getDataMode();
  if (channel === 'following' && mode === 'design-preview') {
    return {
      source: 'unavailable',
      items: [],
      message: '关注流预览暂未开放',
      detail: '关注流需要登录并读取真实关注关系，设计预览不会伪造关注数据。',
    };
  }

  if (mode === 'design-preview') {
    return { source: 'preview', items: stabilizeFeedLayouts(filterCards(previewFeed, channel)) };
  }
  if (mode !== 'api') {
    return {
      source: 'configuration-error',
      items: [],
      message: '数据模式配置无效',
      detail: 'VITE_DATA_MODE 只能设置为 api 或 design-preview。',
    };
  }
  if (!sdkConfigured()) {
    return {
      source: 'configuration-error',
      items: [],
      message: '尚未配置 API',
      detail: '请配置 VITE_API_URL，或显式启用 design-preview。',
    };
  }
  if (['gallery', 'video', 'discussion'].includes(channel)) {
    return {
      source: 'unavailable',
      items: [],
      message: `${HOME_CHANNELS.find((item) => item.id === channel)?.label || '该'}频道暂未开放`,
      detail: '当前 SDK 尚未提供这一内容类型的服务端筛选能力。',
    };
  }

  return { source: 'loading', items: [] };
}

export async function loadRecommendedCards(client, signal) {
  let response;
  try {
    response = await client.cards.hot();
  } catch (error) {
    if (error?.name === 'AbortError') throw error;
    const fallback = await client.cards.list({
      page: 1,
      list_rows: 30,
      order_key: 'created_at',
      order_desc: true,
    });
    abortIfNeeded(signal);
    if (!fallback || !Array.isArray(fallback.data)) throw new Error('SDK 契约错误：cards.list() 应返回 ListResult。', { cause: error });
    return { rawItems: fallback.data, strategy: 'latest-fallback', degradedError: error };
  }
  abortIfNeeded(signal);
  if (!Array.isArray(response)) throw new Error('SDK 契约错误：cards.hot() 应返回数组。');
  return { rawItems: response, strategy: 'hot', degradedError: null };
}

export async function loadHomeFeed({ channel = 'recommended', signal } = {}) {
  abortIfNeeded(signal);
  const snapshot = getHomeFeedSnapshot(channel);
  if (snapshot.source !== 'loading') return snapshot;

  const client = getSdkClient();
  if (!client) return getHomeFeedSnapshot(channel);

  const recommended = channel === 'recommended'
    ? await loadRecommendedCards(client, signal)
    : null;
  const response = channel === 'following'
      ? await client.cards.listFollowing({ page: 1, list_rows: 30 })
    : channel === 'latest' ? await client.cards.list({
      page: 1,
      list_rows: 30,
      order_key: 'created_at',
      order_desc: true,
    }) : null;
  abortIfNeeded(signal);

  let rawItems;
  if (channel === 'recommended') {
    rawItems = recommended.rawItems;
  } else {
    if (!response || !Array.isArray(response.data)) throw new Error(`SDK 契约错误：cards.${channel === 'following' ? 'listFollowing' : 'list'}() 应返回 ListResult。`);
    rawItems = response.data;
  }
  return {
    source: 'live',
    strategy: channel === 'recommended' ? recommended.strategy : channel === 'following' ? 'following' : 'latest',
    degradedError: recommended?.degradedError || null,
    items: stabilizeFeedLayouts(filterCards(rawItems.map(normalizeSdkCard), channel)),
  };
}

export async function updateCardLike(cardId, liked) {
  const mode = getDataMode();
  if (mode === 'design-preview') throw new FeatureUnavailableError('当前为只读预览，操作不会保存。');
  if (mode !== 'api') throw new FeatureUnavailableError('数据模式配置无效，操作未执行。');
  const client = getSdkClient();
  if (!client) throw new FeatureUnavailableError('尚未配置 API，操作未执行。');
  if (!client.getToken()) throw new FeatureUnavailableError('请先登录后再进行互动。');
  if (!/^\d+$/.test(String(cardId))) throw new FeatureUnavailableError('预览内容不可操作。');
  if (liked) return client.cards.like(Number(cardId));
  await client.cards.unlike(Number(cardId));
  return undefined;
}

export async function loadViewerLikeIds(type = 'card') {
  const client = getSdkClient();
  if (!client?.getToken()) return new Set();
  const likes = await client.likes.list({ type });
  return new Set((Array.isArray(likes) ? likes : []).map((item) => Number(item.ref_id ?? item.pid)).filter(Number.isInteger));
}
