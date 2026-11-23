import { getSdkClient, sdkConfigured } from '../app/sdk-client.js';
import { getRuntimeDataMode } from '../app/runtime-context.js';
import { previewFeed } from '../fixtures/feed-preview.js';
import { normalizeSdkCard } from './feed-service.js';

const PAGE_SIZE = 30;
const SORT_KEYS = Object.freeze({ latest: 'created_at', liked: 'goods', discussed: 'comments' });

export function normalizeDiscoverySort(value) {
  return Object.hasOwn(SORT_KEYS, value) ? value : 'latest';
}

function paginationOf(result, page) {
  const pagination = result?.pagination;
  const currentPage = Number(pagination?.currentPage ?? page);
  const totalPages = Number(pagination?.totalPages);
  const totalItems = Number(pagination?.totalItems);
  return {
    currentPage,
    totalPages: Number.isFinite(totalPages) ? totalPages : currentPage,
    totalItems: Number.isFinite(totalItems) ? totalItems : null,
  };
}

export function getDiscoveryMode() { return getRuntimeDataMode(); }

export function topicHref(topic) {
  const id = Number(topic?.id ?? topic?.tagId);
  const name = String(topic?.name || topic?.label || '').replace(/^#\s*/, '').trim();
  if (Number.isInteger(id) && id > 0) return `/topic/${id}-${encodeURIComponent(name)}`;
  return `/topic/${encodeURIComponent(name)}`;
}

function topicRouteParts(value) {
  const raw = decodeURIComponent(String(value || ''));
  const match = raw.match(/^(\d+)(?:-(.*))?$/);
  return { id: match ? Number(match[1]) : null, name: match?.[2] || raw };
}

function findTopic(tags, routeValue) {
  const parts = topicRouteParts(routeValue);
  const normalized = parts.name.replace(/^#\s*/, '').trim().toLowerCase();
  return tags.find((tag) => (
    (parts.id && Number(tag.id) === parts.id) || String(tag.name || '').trim().toLowerCase() === normalized
  )) || null;
}

function normalizeTopics(tags) {
  if (!Array.isArray(tags)) throw new Error('SDK 契约错误：tags.list() 应返回公开话题数组。');
  return tags.filter((tag) => String(tag?.name || '').trim()).map((tag) => ({
    id: Number(tag.id) || null,
    name: String(tag.name).trim(),
    label: `# ${String(tag.name).trim()}`,
  }));
}

function normalizeCards(result, page) {
  if (!result || !Array.isArray(result.data)) throw new Error('SDK 契约错误：cards.search() 应返回 ListResult。');
  return { items: result.data.map(normalizeSdkCard), pagination: paginationOf(result, page) };
}

function previewSearch(query, sort = 'latest') {
  const needle = query.trim().toLowerCase();
  const items = needle ? previewFeed.filter((item) => [item.title, item.body, item.author, item.topic].some((value) => String(value || '').toLowerCase().includes(needle))) : previewFeed;
  const ordered = sort === 'latest' ? items : [...items].sort((left, right) => Number(right[sort === 'liked' ? 'likes' : 'replies'] || 0) - Number(left[sort === 'liked' ? 'likes' : 'replies'] || 0));
  return { source: 'preview', items: ordered, pagination: { currentPage: 1, totalPages: 1, totalItems: ordered.length } };
}

export function getDiscoverySnapshot(query = '', sort = 'latest') {
  const mode = getDiscoveryMode();
  if (mode === 'design-preview') return previewSearch(query, normalizeDiscoverySort(sort));
  if (mode !== 'api') return { source: 'configuration-error', items: [], message: '数据模式配置无效', detail: 'VITE_DATA_MODE 只能是 api 或 design-preview。' };
  if (!sdkConfigured()) return { source: 'configuration-error', items: [], message: '尚未配置 API', detail: '请配置 VITE_API_URL，或显式启用 design-preview。' };
  return { source: 'loading', items: [], pagination: null };
}

async function readWhileActive(request, signal) {
  // SDK 请求结束时同时检查成功与失败，避免旧排序的结果覆盖当前页面。
  try {
    return await request;
  } finally {
    signal?.throwIfAborted();
  }
}

export async function loadDiscovery({ query = '', signal, topic = false, sort = 'latest', page = 1 } = {}) {
  signal?.throwIfAborted();
  const normalizedSort = normalizeDiscoverySort(sort);
  const snapshot = getDiscoverySnapshot(query, normalizedSort);
  if (snapshot.source !== 'loading') return snapshot;
  const client = getSdkClient();
  const cardParams = { page, list_rows: PAGE_SIZE, order_key: SORT_KEYS[normalizedSort], order_desc: true };
  let selectedTopic = null;
  if (topic) {
    const tags = await readWhileActive(client.tags.list(), signal);
    selectedTopic = findTopic(normalizeTopics(tags), query);
    if (!selectedTopic) return { source: 'live', items: [], topic: null, topicMissing: true };
    cardParams.tag_id = selectedTopic.id;
  } else if (query.trim()) {
    cardParams.search_value = query.trim();
  }
  const result = await readWhileActive(client.cards.search(cardParams), signal);
  return { source: 'live', ...normalizeCards(result, page), topic: selectedTopic };
}
