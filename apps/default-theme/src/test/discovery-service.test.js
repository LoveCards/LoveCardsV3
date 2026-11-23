import { beforeEach, describe, expect, it, vi } from 'vitest';
import { loadDiscovery } from '../services/discovery-service.js';

const sdk = vi.hoisted(() => ({ search: vi.fn(), list: vi.fn() }));

vi.mock('../app/sdk-client.js', () => ({
  sdkConfigured: () => true,
  getSdkClient: () => ({ cards: { search: sdk.search }, tags: { list: sdk.list } }),
}));
vi.mock('../app/runtime-context.js', () => ({ getRuntimeDataMode: () => 'api' }));
vi.mock('../services/feed-service.js', () => ({ normalizeSdkCard: (card) => card }));

describe('discovery data sources', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    sdk.search.mockResolvedValue({ data: [{ id: 1, title: '公开内容' }] });
    sdk.list.mockResolvedValue([{ id: 2, name: '城市漫步' }]);
  });

  it('loads the discovery feed without requesting the topic catalog', async () => {
    const result = await loadDiscovery();
    expect(result.items).toEqual([{ id: 1, title: '公开内容' }]);
    expect(sdk.list).not.toHaveBeenCalled();
    expect(result).not.toHaveProperty('trends');
    expect(sdk.search).toHaveBeenCalledWith({ page: 1, list_rows: 30, order_key: 'created_at', order_desc: true });
  });

  it.each([
    ['liked', 'goods'],
    ['discussed', 'comments'],
  ])('requests page two from the server using %s ordering', async (sort, orderKey) => {
    sdk.search.mockResolvedValue({ data: [{ id: 3 }], pagination: { currentPage: 2, totalPages: 4, totalItems: 91, itemsPerPage: 30 } });
    const result = await loadDiscovery({ sort, page: 2 });
    expect(sdk.search).toHaveBeenCalledWith({ page: 2, list_rows: 30, order_key: orderKey, order_desc: true });
    expect(sdk.list).not.toHaveBeenCalled();
    expect(result.pagination).toEqual({ currentPage: 2, totalPages: 4, totalItems: 91 });
  });

  it('surfaces a card loading failure to the page retry boundary', async () => {
    sdk.search.mockRejectedValue(new Error('cards unavailable'));
    await expect(loadDiscovery()).rejects.toThrow('cards unavailable');
  });

  it('never mixes bootstrap cards into search results', async () => {
    sdk.search.mockResolvedValue({ data: [] });
    const searched = await loadDiscovery({ query: '另一个词' });
    expect(searched.items).toEqual([]);
    expect(sdk.search).toHaveBeenCalledOnce();
  });

  it('resolves topic deep links before fetching cards and skips unknown topics', async () => {
    const found = await loadDiscovery({ topic: true, query: '2-城市漫步' });
    expect(found.topic.name).toBe('城市漫步');
    expect(sdk.search).toHaveBeenCalledWith(expect.objectContaining({ tag_id: 2 }));
    const missing = await loadDiscovery({ topic: true, query: '不存在' });
    expect(missing.topicMissing).toBe(true);
    expect(sdk.search).toHaveBeenCalledTimes(1);
  });

  it.each(['success', 'failure'])('ignores a stale card %s after a sort change', async (outcome) => {
    const controller = new AbortController();
    const deferred = Promise.withResolvers();
    sdk.search.mockReturnValueOnce(deferred.promise);
    const result = loadDiscovery({ signal: controller.signal });
    controller.abort();
    if (outcome === 'success') deferred.resolve({ data: [] });
    else deferred.reject(new Error('stale error'));
    await expect(result).rejects.toMatchObject({ name: 'AbortError' });
  });
});
