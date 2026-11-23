import { getSdkClient, sdkConfigured } from '../app/sdk-client.js';
import { getRuntimeDataMode } from '../app/runtime-context.js';
import { previewFeed, previewTrends } from '../fixtures/feed-preview.js';
import { normalizeSdkCard } from './feed-service.js';

export const SEARCH_SCOPES = Object.freeze(['posts', 'users', 'topics']);
export const SEARCH_PAGE_SIZE = 24;
export const SEARCH_PREVIEW_SIZE = Object.freeze({ posts: 6, users: 3, topics: 4 });

export function contentSearchHint(query) {
  const term = String(query || '').trim();
  if (term.startsWith('#')) return term.slice(1).trim() ? '' : '请输入话题名称';
  return Array.from(term).length < 2 ? '输入至少两个字搜索正文' : '';
}

function ensureCurrent(signal) {
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
}

function paginationOf(result, page, limit) {
  const raw = result?.pagination || {};
  const currentPage = Number(raw.currentPage) || page;
  const totalPages = Number(raw.totalPages);
  const totalItems = Number(raw.totalItems);
  return {
    currentPage,
    totalPages: Number.isFinite(totalPages) ? totalPages : currentPage,
    totalItems: Number.isFinite(totalItems) ? totalItems : null,
    hasMore: Number.isFinite(totalPages) && currentPage < totalPages,
    pageSize: limit,
  };
}

function normalizeResult(result, page, limit, mapItem) {
  if (!result || !Array.isArray(result.data)) throw new Error('搜索接口返回的数据格式不正确。');
  return { source: 'live', items: result.data.map(mapItem).filter(Boolean), pagination: paginationOf(result, page, limit) };
}

function previewResult(scope, query, page, limit, sort) {
  const topicOnly = query.startsWith('#');
  const needle = (topicOnly ? query.slice(1).trim() : query).toLocaleLowerCase();
  const textOf = (item) => String(item.content || item.title || '').trim().toLocaleLowerCase();
  const topicOf = (item) => String(item.topic || '').replace(/^#\s*/, '').toLocaleLowerCase();
  const items = scope === 'posts'
    ? previewFeed.filter((item) => topicOnly ? topicOf(item) === needle : textOf(item).includes(needle) || topicOf(item) === needle)
    : scope === 'topics'
      ? previewTrends.filter((item) => String(item.slug || item.label || '').toLocaleLowerCase().includes(needle))
        .map((item) => ({ id: null, name: item.slug, label: item.label }))
      : [];
  if (scope === 'posts' && sort !== 'latest') {
    const rank = (item) => textOf(item).startsWith(needle) ? 0 : textOf(item).includes(needle) ? 1 : 2;
    items.sort((a, b) => rank(a) - rank(b));
  }
  const start = (page - 1) * limit;
  return {
    source: 'preview',
    items: items.slice(start, start + limit),
    pagination: { currentPage: page, totalPages: Math.max(1, Math.ceil(items.length / limit)), totalItems: items.length, hasMore: start + limit < items.length, pageSize: limit },
  };
}

export async function searchScope(scope, { query, page = 1, limit = SEARCH_PAGE_SIZE, sort = 'match', signal } = {}) {
  const term = String(query || '').trim();
  if (!SEARCH_SCOPES.includes(scope)) throw new Error('未知的搜索范围。');
  if (Array.from(term).length > 100) throw new Error('搜索词不能超过 100 个字符。');
  if (!term) return { source: 'live', items: [], pagination: { currentPage: 1, totalPages: 1, totalItems: 0, hasMore: false, pageSize: limit } };
  ensureCurrent(signal);
  const hint = scope === 'posts' ? contentSearchHint(term) : '';
  if (hint || (scope === 'topics' && !term.replace(/^#+/, '').trim())) {
    return { source: 'live', items: [], hint: hint || '请输入话题名称', pagination: { currentPage: 1, totalPages: 1, totalItems: 0, hasMore: false, pageSize: limit } };
  }
  const mode = getRuntimeDataMode();
  if (mode === 'design-preview') return previewResult(scope, term, page, limit, sort);
  if (mode !== 'api' || !sdkConfigured()) throw new Error('搜索服务尚未配置，请稍后重试。');

  const client = getSdkClient();
  const params = { search_value: term, page, list_rows: limit };
  let result;
  if (scope === 'posts') {
    result = normalizeResult(await client.cards.searchPublic({ ...params, sort }), page, limit, normalizeSdkCard);
  } else if (scope === 'users') {
    result = normalizeResult(await client.users.searchPublic(params), page, limit, (item) => {
      const uid = String(item?.uid || '');
      if (!/^\d{12}$/.test(uid)) return null;
      return { uid, username: String(item.username || '').trim(), avatar: item.avatar || '', bio: String(item.bio || '').trim() };
    });
  } else {
    result = normalizeResult(await client.tags.search(params), page, limit, (item) => {
      const name = String(item?.name || '').trim();
      return name ? { id: Number(item.id) || null, name, label: `# ${name}` } : null;
    });
  }
  ensureCurrent(signal);
  return result;
}
