import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createHeaderSearchSource } from '../services/header-search-source.js';
import { loadDiscovery } from '../services/discovery-service.js';

vi.mock('../services/discovery-service.js', () => ({ loadDiscovery: vi.fn() }));

const cachedCard = {
  id: '4',
  title: '第二张：用于验证搜索接口',
  author: '用户 1',
  topic: '# 搜索',
};

describe('header search source', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    loadDiscovery.mockImplementation(async ({ query }) => ({
      source: 'live',
      items: query ? [] : [cachedCard],
      trends: query ? [] : [{ label: '# 热门话题', count: '查看话题内容' }],
    }));
  });

  it('keeps bootstrap items as a local fallback for live incremental search', async () => {
    const source = createHeaderSearchSource();
    await source.bootstrap();
    const results = await source.search('第二张');
    expect(results.map((item) => item.label)).toContain(cachedCard.title);
  });

  it('does not issue a new request during IME composition', async () => {
    let composing = false;
    const source = createHeaderSearchSource({ isComposing: () => composing });
    const initial = await source.bootstrap();
    composing = true;
    const results = await source.search('di');
    expect(results).toEqual(initial);
    expect(loadDiscovery).toHaveBeenCalledTimes(1);
  });
});
