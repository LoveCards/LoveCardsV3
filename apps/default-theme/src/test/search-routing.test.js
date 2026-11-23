import { describe, expect, it } from 'vitest';
import { canonicalExplorePath } from '../pages/explore/ExplorePage.jsx';
import { canonicalSearchPath, searchHref } from '../pages/search/search-route.js';

describe('search route canonicalization', () => {
  it.each([
    [undefined, '?q=city&src=feed', '#results', null],
    ['media', '?q=city&src=feed', '#results', '/search?q=city&src=feed#results'],
    ['topics', '?q=city', '', null],
    ['posts', '?q=city', '', null],
    ['posts', '?q=city&page=2&sort=latest', '', null],
    ['posts', '?q=city&sort=match', '', '/search/posts?q=city'],
    ['posts', '?q=city&sort=invalid', '', '/search/posts?q=city'],
    ['users', '?q=city&sort=latest', '', '/search/users?q=city'],
    ['topics', '?q=', '#top', '/search#top'],
    ['media', '?src=feed', '', '/search?src=feed'],
    [undefined, '', '', null],
  ])('normalizes scope %s with search %s', (scope, search, hash, expected) => {
    expect(canonicalSearchPath({ scope, search, hash })).toBe(expected);
  });

  it('preserves latest sort when moving between result pages', () => {
    expect(searchHref('posts', '城市', 2, 'latest')).toBe('/search/posts?q=%E5%9F%8E%E5%B8%82&page=2&sort=latest');
    expect(searchHref('posts', '城市', 1, 'latest')).toBe('/search/posts?q=%E5%9F%8E%E5%B8%82&sort=latest');
  });
});

describe('explore sort route canonicalization', () => {
  it.each([
    ['', '', null],
    ['?sort=liked', '#post', null],
    ['?sort=discussed', '', null],
    ['?sort=latest&src=nav', '#post', '/explore?src=nav#post'],
    ['?sort=invalid', '', '/explore'],
    ['?sort=liked&sort=discussed', '', '/explore?sort=liked'],
    ['?q=city&src=old', '#results', '/search?q=city&src=old#results'],
    ['?q=%20city%20&sort=liked', '', '/search?q=city'],
  ])('normalizes sort in %s', (search, hash, expected) => {
    expect(canonicalExplorePath({ search, hash })).toBe(expected);
  });
});
