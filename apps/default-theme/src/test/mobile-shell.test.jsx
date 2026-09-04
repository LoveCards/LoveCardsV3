import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { renderRoute } from './renderRoute.jsx';

describe('mobile shell contract', () => {
  beforeEach(() => vi.stubEnv('VITE_DATA_MODE', 'design-preview'));
  afterEach(() => vi.unstubAllEnvs());

  it.each(['/', '/explore', '/messages', '/inbox', '/me', '/user/demo'])(
    'keeps global navigation on standard destination %s',
    (path) => {
      const html = renderRoute(path);
      expect(html).toContain('app-shell is-mobile-standard');
      expect(html).toContain('class="mobile-bottom-nav"');
    },
  );

  it.each([
    ['/settings', 'task'],
    ['/settings/privacy', 'task'],
    ['/drafts', 'task'],
    ['/me/following', 'task'],
    ['/user/demo/followers', 'task'],
    ['/messages/search', 'task'],
    ['/messages/1', 'task'],
    ['/messages/new/demo', 'immersive'],
    ['/compose', 'immersive'],
    ['/compose/photo', 'immersive'],
  ])('removes global mobile navigation from %s using the %s shell', (path, mode) => {
    const html = renderRoute(path);
    expect(html).toContain(`app-shell is-mobile-${mode}`);
    expect(html).not.toContain('class="mobile-bottom-nav"');
  });

  it('uses one explicit shell API and hides the global header for focused modes', () => {
    const shell = readFileSync(fileURLToPath(new URL('../layouts/AppShell.jsx', import.meta.url)), 'utf8');
    const css = readFileSync(fileURLToPath(new URL('../styles/components.css', import.meta.url)), 'utf8');

    expect(shell).toContain("mobileMode = 'standard'");
    expect(shell).not.toContain('showMobileBottomNav');
    expect(css).toContain('.app-shell.is-mobile-task > .product-header');
    expect(css).toContain('.app-shell.is-mobile-immersive > .product-header');
    expect(css).toContain('.app-shell.is-mobile-gateway > .product-header');
  });
});
