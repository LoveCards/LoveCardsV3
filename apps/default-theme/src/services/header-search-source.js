import { loadDiscovery } from './discovery-service.js';

function topicSlug(label) {
  return String(label || '').replace(/^#\s*/, '').trim();
}

function toSearchItems(data, query) {
  if (data.source === 'configuration-error') {
    return [{ id: 'search-unavailable', label: data.message || '搜索暂不可用', auxiliaryData: { kind: 'status', description: data.detail || '请检查 API 配置。', href: '/search' } }];
  }

  const needle = query.trim().toLowerCase();
  const topics = (data.trends || [])
    .filter((trend) => !needle || String(trend.label || '').toLowerCase().includes(needle))
    .slice(0, 4)
    .map((trend) => ({
      id: `topic:${topicSlug(trend.label)}`,
      label: trend.label,
      auxiliaryData: {
        kind: 'topic',
        description: trend.count || '查看话题内容',
        href: `/topic/${encodeURIComponent(topicSlug(trend.label))}`,
      },
    }));

  const posts = (data.items || []).slice(0, 7).map((card) => ({
    id: `post:${card.id}`,
    label: card.title,
    auxiliaryData: {
      kind: 'post',
      author: card.author,
      description: `${card.author}${card.topic ? ` · ${card.topic}` : ''}`,
      href: `/post/${card.id}`,
    },
  }));

  return query.trim() ? [...posts, ...topics] : [...topics, ...posts];
}

export function createHeaderSearchSource({ isComposing } = {}) {
  let controller = null;
  let bootstrapItems = [];
  let lastItems = [];

  const run = async (query) => {
    if (isComposing?.()) return lastItems;
    controller?.abort();
    controller = new AbortController();
    try {
      const data = await loadDiscovery({ query, signal: controller.signal });
      const remoteItems = toSearchItems(data, query);
      if (!query) {
        bootstrapItems = remoteItems;
        lastItems = remoteItems;
        return lastItems;
      }
      const needle = query.toLowerCase();
      const localMatches = bootstrapItems.filter((item) => `${item.label} ${item.auxiliaryData?.description || ''}`.toLowerCase().includes(needle));
      const merged = [...remoteItems, ...localMatches].filter((item, index, items) => items.findIndex((candidate) => candidate.id === item.id) === index);
      lastItems = merged;
      return lastItems;
    } catch (error) {
      if (error?.name === 'AbortError') throw error;
      lastItems = [{ id: 'search-error', label: '暂时无法搜索', auxiliaryData: { kind: 'status', description: '进入完整搜索页后可重试。', href: `/search?q=${encodeURIComponent(query)}` } }];
      return lastItems;
    }
  };

  return {
    search: (query) => run(query.trim()),
    bootstrap: () => run(''),
    cancel: () => controller?.abort(),
  };
}
