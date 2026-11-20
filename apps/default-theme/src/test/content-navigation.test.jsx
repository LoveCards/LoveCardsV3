import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { renderRoute } from './renderRoute.jsx';

function navigation(html, className) {
  return html.match(new RegExp(`<nav class="${className}"[\\s\\S]*?<\\/nav>`))?.[0] || '';
}

describe('content navigation', () => {
  beforeEach(() => vi.stubEnv('VITE_DATA_MODE', 'design-preview'));
  afterEach(() => vi.unstubAllEnvs());

  it.each(['/', '/?channel=latest', '/explore', '/search?q=demo', '/topic/demo'])(
    'keeps the home entry selected on public content route %s',
    (path) => {
      const html = renderRoute(path);
      for (const className of ['desktop-nav', 'mobile-bottom-nav']) {
        const nav = navigation(html, className);
        expect(nav).toMatch(/<a(?=[^>]*aria-current="page")(?=[^>]*href="\/")[^>]*>/);
        expect(nav).not.toMatch(/<a(?=[^>]*aria-current="page")(?=[^>]*href="\/\?feed=following")[^>]*>/);
      }
    },
  );

  it.each(['/?feed=following', '/?channel=following'])(
    'selects only the following entry on %s',
    (path) => {
      const html = renderRoute(path);
      for (const className of ['desktop-nav', 'mobile-bottom-nav']) {
        const nav = navigation(html, className);
        expect(nav).toMatch(/<a(?=[^>]*aria-current="page")(?=[^>]*href="\/\?feed=following")[^>]*>/);
        const dynamicEntry = nav.match(/<a[^>]*href="\/\?feed=following"[^>]*>[\s\S]*?<\/a>/)?.[0] || '';
        expect(dynamicEntry).toContain('动态');
        expect(dynamicEntry).not.toContain('关注');
        expect(nav).not.toMatch(/<a(?=[^>]*aria-current="page")(?=[^>]*href="\/")[^>]*>/);
      }
      expect(html).toContain('>动态</h1>');
      expect(html).not.toContain('class="home-channels"');
    },
  );

  it('places discovery second in the home channels and selects it on explore', () => {
    const home = navigation(renderRoute('/'), 'home-channels');
    const explore = navigation(renderRoute('/explore'), 'home-channels');
    expect(home).toMatch(/>推荐<\/a>[\s\S]*href="\/explore"[^>]*>发现<\/a>[\s\S]*>最新<\/a>/);
    expect(explore).toMatch(/<a(?=[^>]*aria-current="page")(?=[^>]*href="\/explore")[^>]*>发现<\/a>/);
  });
});
