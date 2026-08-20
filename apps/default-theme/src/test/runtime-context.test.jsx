import { describe, expect, it } from 'vitest';
import { readThemeBootstrap } from '../app/runtime-context.js';

function rootWith(value) {
  return { getElementById: () => ({ textContent: value }) };
}

describe('theme runtime bootstrap', () => {
  it('reads the backend SPA contract', () => {
    const context = readThemeBootstrap(rootWith(JSON.stringify({
      apiUrl: '/api',
      theme: 'default',
      mode: 'spa',
      release: '1.0.0-digest',
      assetsBase: '/_lc/theme-assets/default/1.0.0-digest/',
      config: { siteName: 'My LoveCards' },
    })));

    expect(context).toEqual(expect.objectContaining({ apiUrl: '/api', theme: 'default', mode: 'spa', release: '1.0.0-digest' }));
    expect(context.config.siteName).toBe('My LoveCards');
  });

  it('returns null when Vite dev mode has no injected bootstrap', () => {
    expect(readThemeBootstrap({ getElementById: () => null })).toBeNull();
  });

  it('rejects invalid JSON and non-SPA modes', () => {
    expect(() => readThemeBootstrap(rootWith('{'))).toThrow('主题启动配置不是有效 JSON。');
    expect(() => readThemeBootstrap(rootWith('{"apiUrl":"/api","mode":"ssr"}'))).toThrow('主题启动模式无效。');
  });

  it('rejects incomplete backend contexts instead of silently falling back', () => {
    expect(() => readThemeBootstrap(rootWith(JSON.stringify({
      apiUrl: '/api',
      theme: 'default',
      mode: 'spa',
      release: '',
      assetsBase: '/_lc/theme-assets/default/release/',
      config: {},
    })))).toThrow('主题启动配置缺少 release。');
  });
});
