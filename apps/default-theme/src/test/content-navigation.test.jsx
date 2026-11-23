import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cardPreviewState, cardReturnSource } from '../app/card-preview-routing.js';
import { renderRoute } from './renderRoute.jsx';
import { canonicalHomeFeedPath, canonicalLegacyFeedPath } from '../app/auth-routing.jsx';

function navigation(html, className) {
  return html.match(new RegExp(`<nav class="${className}"[\\s\\S]*?<\\/nav>`))?.[0] || '';
}

describe('content navigation', () => {
  it('restores only a trusted full-detail source, including asset tabs and search filters', () => {
    for (const [pathname, search] of [['/me', '?tab=saved'], ['/explore', '?sort=liked'], ['/search/posts', '?q=foo&sort=latest&page=2']]) {
      const source = { pathname, search, hash: '#list', state: null, key: 'original' };
      const state = { ...cardPreviewState(source, 42), previewMode: 'full' };
      expect(cardReturnSource({ pathname: '/post/42', state })).toEqual(source);
      expect(cardReturnSource({ pathname: '/post/42', state: { ...state, previewSession: 'old-document' } })).toBeNull();
      expect(cardReturnSource({ pathname: '/post/43', state })).toBeNull();
      expect(cardReturnSource({ pathname: '/post/42', state: { ...state, backgroundLocation: { pathname: '//external.example' } } })).toBeNull();
    }
  });
  beforeEach(() => vi.stubEnv('VITE_DATA_MODE', 'design-preview'));
  afterEach(() => vi.unstubAllEnvs());

  it.each([['/', '/'], ['/explore', '/explore'], ['/topic/demo', '/explore'], ['/search/posts?q=demo', null]])(
    'selects the matching desktop destination on %s',
    (path, expected) => {
      const nav = navigation(renderRoute(path), 'desktop-nav');
      const active = [...nav.matchAll(/<a(?=[^>]*aria-current="page")[^>]*href="([^"]+)"[^>]*>/g)];
      expect(active.map((match) => match[1])).toEqual(expected ? [expected] : []);
      if (path === '/' || path === '/explore') {
        expect(navigation(renderRoute(path), 'mobile-bottom-nav')).toMatch(/<a(?=[^>]*aria-current="page")(?=[^>]*href="\/")[^>]*>/);
      }
    },
  );

  it.each(['/activity', '/activity?src=legacy'])(
    'selects only the activity entry on %s',
    (path) => {
      const html = renderRoute(path);
      for (const className of ['desktop-nav', 'mobile-bottom-nav']) {
        const nav = navigation(html, className);
        expect(nav).toMatch(/<a(?=[^>]*aria-current="page")(?=[^>]*href="\/activity")[^>]*>/);
        const dynamicEntry = nav.match(/<a[^>]*href="\/activity"[^>]*>[\s\S]*?<\/a>/)?.[0] || '';
        expect(dynamicEntry).toContain('动态');
        expect(dynamicEntry).not.toContain('关注');
        expect(nav).not.toMatch(/<a(?=[^>]*aria-current="page")(?=[^>]*href="\/")[^>]*>/);
      }
      expect(html).toContain('>动态</h1>');
      expect(html).not.toContain('aria-label="信息流布局"');
      expect(html).not.toContain('class="home-channels"');
    },
  );

  it('keeps discovery as the only sibling of recommended in the home channels', () => {
    const home = navigation(renderRoute('/'), 'home-channels');
    const explore = navigation(renderRoute('/explore'), 'home-channels');
    expect(home).toMatch(/>推荐<\/a>[\s\S]*href="\/explore"[^>]*>发现<\/a>/);
    expect(explore).toMatch(/<a(?=[^>]*aria-current="page")(?=[^>]*href="\/explore")[^>]*>发现<\/a>/);
    expect(home).not.toContain('最新');
  });

  it.each([
    ['?feed=following', '/activity'],
    ['?channel=following&src=old', '/activity?src=old'],
    ['?channel=latest&src=old', '/explore?src=old'],
    ['?feed=following&channel=latest&src=old', '/activity?src=old'],
    ['?channel=unknown&src=old', '/?src=old'],
  ])('canonicalizes the legacy home query %s', (search, expected) => {
    expect(canonicalHomeFeedPath({ search, hash: '' })).toBe(expected);
  });

  it('preserves the fragment and leaves canonical home URLs alone', () => {
    expect(canonicalHomeFeedPath({ search: '?channel=latest&src=old', hash: '#card' })).toBe('/explore?src=old#card');
    expect(canonicalHomeFeedPath({ search: '?src=other', hash: '' })).toBeNull();
  });

  it('preserves unrelated query parameters and fragment on legacy path redirects', () => {
    expect(canonicalLegacyFeedPath({ search: '?channel=latest&src=old', hash: '#card' }, '/explore')).toBe('/explore?src=old#card');
    expect(canonicalLegacyFeedPath({ search: '?feed=following&src=old', hash: '#card' }, '/activity')).toBe('/activity?src=old#card');
  });
});
