import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createHeaderSearchSource } from '../services/header-search-source.js';
import { searchScope } from '../services/search-service.js';

vi.mock('../services/search-service.js', () => ({ searchScope: vi.fn() }));

const matchingUser = {
  uid: '123456789012',
  username: '第二张用户',
  avatar: '',
};

describe('header search source', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    searchScope.mockImplementation(async (scope, { query }) => ({ items: scope === 'users' && query === '第二张' ? [matchingUser] : [] }));
  });

  it('uses only matching results returned for the active search query', async () => {
    const source = createHeaderSearchSource();
    await source.bootstrap();
    const results = await source.search('第二张');
    expect(results.map((item) => item.label)).toContain(matchingUser.username);
    expect(searchScope.mock.calls.map(([scope]) => scope)).toEqual(['users', 'topics']);
    expect(results[0].auxiliaryData.href).toBe('/search?q=%E7%AC%AC%E4%BA%8C%E5%BC%A0');
  });

  it('does not issue a new request during IME composition', async () => {
    let composing = false;
    const source = createHeaderSearchSource({ isComposing: () => composing });
    const initial = await source.bootstrap();
    composing = true;
    const results = await source.search('di');
    expect(results).toEqual(initial);
    expect(searchScope).not.toHaveBeenCalled();
  });

  it('keeps only the real full-search route when suggestions fail', async () => {
    searchScope.mockRejectedValue(new Error('network failure'));
    const results = await createHeaderSearchSource().search('城市');
    expect(results.map((item) => item.id)).toEqual(['search-all:城市']);
    expect(results[0].auxiliaryData.href).toBe('/search?q=%E5%9F%8E%E5%B8%82');
  });
});
