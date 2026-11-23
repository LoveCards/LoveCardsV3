import { describe, expect, it } from 'vitest';
import { browseTaskBack, browseTaskState } from '../app/browse-task-routing.js';

describe('browse task return', () => {
  it('returns across the search session to its source, including filters and anchor', () => {
    const source = { pathname: '/explore', search: '?sort=liked', hash: '#cards' };
    expect(browseTaskBack(browseTaskState(source, 3), 'search', 6)).toEqual({
      to: source, label: '返回来源页', delta: -3,
    });
  });

  it('returns from a topic to the exact search result entry', () => {
    const source = { pathname: '/search/topics', search: '?q=城市&page=2', hash: '' };
    expect(browseTaskBack(browseTaskState(source, 4), 'topic', 5)).toMatchObject({ to: source, delta: -1 });
  });

  it('uses fixed in-site parents for direct or expired entries', () => {
    expect(browseTaskBack(null, 'search', 0)).toEqual({ to: '/', label: '返回首页' });
    expect(browseTaskBack({ browseTaskSource: { session: 'old', pathname: '/search', index: 0 } }, 'topic', 1)).toEqual({ to: '/explore', label: '返回发现' });
    expect(browseTaskBack(browseTaskState({ pathname: '//external.test' }, 0), 'topic', 1).to).toBe('/explore');
    expect(browseTaskBack(browseTaskState({ pathname: '/search' }, 2), 'topic', 2).to).toBe('/explore');
  });
});
