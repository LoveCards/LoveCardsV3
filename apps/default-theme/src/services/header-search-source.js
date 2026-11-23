import { topicHref } from './discovery-service.js';
import { searchScope } from './search-service.js';
import { searchHref } from '../pages/search/search-route.js';

function toItems(scope, data) {
  if (scope === 'users') return data.items.slice(0, 2).map((user) => ({
    id: `user:${user.uid}`,
    label: user.username,
    auxiliaryData: { kind: 'user', avatar: user.avatar, description: `UID · ${user.uid}`, href: `/user/${user.uid}` },
  }));
  return data.items.slice(0, 2).map((topic) => ({
    id: `topic:${topic.id || topic.name}`,
    label: topic.label,
    auxiliaryData: { kind: 'topic', description: '查看话题内容', href: topicHref(topic) },
  }));
}

export function createHeaderSearchSource({ isComposing } = {}) {
  let controller = null;
  let lastItems = [];

  const run = async (raw) => {
    const query = String(raw || '').trim();
    if (isComposing?.()) return lastItems;
    controller?.abort();
    controller = new AbortController();
    if (!query) { lastItems = []; return lastItems; }
    const signal = controller.signal;
    const scopes = ['users', 'topics'];
    const results = await Promise.allSettled(scopes.map((scope) => searchScope(scope, { query, limit: 2, signal })));
    if (signal.aborted) throw new DOMException('Request aborted', 'AbortError');
    const items = results.flatMap((result, index) => result.status === 'fulfilled' ? toItems(scopes[index], result.value) : []);
    const allLink = { id: `search-all:${query}`, label: `搜索“${query}”的全部结果`, auxiliaryData: { kind: 'search', description: '查看综合结果', href: searchHref('all', query) } };
    lastItems = [allLink, ...items];
    return lastItems;
  };

  return {
    search: run,
    bootstrap: () => run(''),
    cancel: () => controller?.abort(),
  };
}
