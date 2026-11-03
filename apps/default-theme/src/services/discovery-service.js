import { getSdkClient, sdkConfigured } from '../app/sdk-client.js';
import { getRuntimeDataMode } from '../app/runtime-context.js';
import { previewCreators, previewFeed, previewTrends } from '../fixtures/feed-preview.js';
import { normalizeSdkCard } from './feed-service.js';

let discoveryBootstrapCache = [];

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
  return (Array.isArray(tags) ? tags : []).find((tag) => (
    (parts.id && Number(tag.id) === parts.id) || String(tag.name || '').trim().toLowerCase() === normalized
  )) || null;
}

function previewSearch(query) {
  const needle = query.trim().toLowerCase();
  const items = needle ? previewFeed.filter((item) => [item.title, item.body, item.author, item.topic].some((value) => String(value || '').toLowerCase().includes(needle))) : previewFeed;
  return { source: 'preview', items, trends: previewTrends, creators: previewCreators };
}
export function getDiscoverySnapshot(query = '') {
  const mode = getDiscoveryMode();
  if (mode === 'design-preview') return previewSearch(query);
  if (mode !== 'api') return { source: 'configuration-error', items: [], trends: [], creators: [], message: '数据模式配置无效', detail: 'VITE_DATA_MODE 只能是 api 或 design-preview。' };
  if (!sdkConfigured()) return { source: 'configuration-error', items: [], trends: [], creators: [], message: '尚未配置 API', detail: '请配置 VITE_API_URL，或显式启用 design-preview。' };
  return { source: 'loading', items: [], trends: [], creators: [] };
}
export async function loadDiscovery({ query = '', signal, topic = false } = {}) {
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
  const snapshot = getDiscoverySnapshot(query);
  if (snapshot.source !== 'loading') return snapshot;
  const client = getSdkClient();
  const tags = await client.tags.list();
  const selectedTopic = topic ? findTopic(tags, query) : null;
  if (topic && !selectedTopic) {
    return { source: 'live', items: [], trends: [], creators: [], topic: null, topicMissing: true };
  }
  const searchResult = await client.cards.search(topic
    ? { page: 1, list_rows: 30, tag_id: Number(selectedTopic.id) }
    : { page: 1, list_rows: 30, search_value: query.trim() || undefined });
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
  if (!searchResult || !Array.isArray(searchResult.data)) throw new Error('SDK 契约错误：cards.search() 应返回 ListResult。');
  let items = searchResult.data.map(normalizeSdkCard);
  const needle = query.trim().toLowerCase();
  if (!needle) {
    discoveryBootstrapCache = items;
  } else {
    if (!discoveryBootstrapCache.length && !items.length) {
      const fallback = await client.cards.search({ page: 1, list_rows: 30 });
      if (fallback && Array.isArray(fallback.data)) discoveryBootstrapCache = fallback.data.map(normalizeSdkCard);
    }
    const localMatches = discoveryBootstrapCache.filter((item) => [item.title, item.body, item.author, item.topic].some((value) => String(value || '').toLowerCase().includes(needle)));
    items = [...items, ...localMatches].filter((item, index, all) => all.findIndex((candidate) => candidate.id === item.id) === index);
  }
  return {
    source: 'live',
    items,
    topic: selectedTopic ? { id: Number(selectedTopic.id), name: String(selectedTopic.name || '') } : null,
    trends: Array.isArray(tags) ? tags.slice(0, 6).map((tag, index) => ({ rank: index + 1, tagId: Number(tag.id), name: String(tag.name || `话题 ${tag.id}`), label: `# ${tag.name || `话题 ${tag.id}`}`, count: '', signal: '' })) : [],
    creators: [],
  };
}
