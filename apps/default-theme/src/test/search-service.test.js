import { beforeEach, describe, expect, it, vi } from 'vitest';
import { searchScope } from '../services/search-service.js';

const { searchPublic, cardsSearch } = vi.hoisted(() => ({ searchPublic: vi.fn(), cardsSearch: vi.fn() }));
vi.mock('../app/sdk-client.js', () => ({
  sdkConfigured: () => true,
  getSdkClient: () => ({ cards: { searchPublic, search: cardsSearch } }),
}));
vi.mock('../app/runtime-context.js', () => ({ getRuntimeDataMode: () => 'api' }));

describe('public content search service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    searchPublic.mockResolvedValue({ data: [], pagination: { currentPage: 2, totalPages: 3, totalItems: 55 } });
  });

  it('uses the public endpoint and preserves server pagination and sort', async () => {
    const result = await searchScope('posts', { query: ' 城市 ', page: 2, sort: 'latest' });
    expect(searchPublic).toHaveBeenCalledWith({ search_value: '城市', page: 2, list_rows: 24, sort: 'latest' });
    expect(cardsSearch).not.toHaveBeenCalled();
    expect(result.pagination).toMatchObject({ currentPage: 2, totalPages: 3, hasMore: true });
  });

  it('returns a useful short-term hint without querying content', async () => {
    expect(await searchScope('posts', { query: '猫' })).toMatchObject({ items: [], hint: '输入至少两个字搜索正文' });
    expect(searchPublic).not.toHaveBeenCalled();
  });

  it('supports one-character exact topics', async () => {
    await searchScope('posts', { query: '#猫' });
    expect(searchPublic).toHaveBeenCalledWith(expect.objectContaining({ search_value: '#猫' }));
    expect(await searchScope('posts', { query: '# ' })).toMatchObject({ hint: '请输入话题名称' });
  });

  it('rejects oversized terms and aborted requests', async () => {
    await expect(searchScope('posts', { query: 'a'.repeat(101) })).rejects.toThrow('100');
    const controller = new AbortController();
    controller.abort();
    await expect(searchScope('posts', { query: '城市', signal: controller.signal })).rejects.toMatchObject({ name: 'AbortError' });
    expect(searchPublic).not.toHaveBeenCalled();
  });
});
