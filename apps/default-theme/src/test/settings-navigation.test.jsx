import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { settingsPathFromLegacyHash } from '../pages/settings/SettingsPage.jsx';
import { renderRoute } from './renderRoute.jsx';

describe('settings navigation hierarchy', () => {
  beforeEach(() => vi.stubEnv('VITE_DATA_MODE', 'design-preview'));
  afterEach(() => vi.unstubAllEnvs());

  it('renders a real settings overview with an immediate-parent back action', () => {
    const html = renderRoute('/settings');

    expect(html).toContain('设置总览');
    expect(html).toContain('class="settings-overview-list"');
    expect(html).toContain('class="detail-back" href="/me"');
    expect(html).toContain('aria-label="返回我的主页" href="/me"');
    expect(html).toContain('href="/settings/appearance"');
    expect(html).toContain('href="/settings/security"');
    expect(html).toContain('href="/settings/privacy"');
    expect(html).toContain('href="/settings/session"');
  });

  it('keeps category pages in one desktop workspace and returns mobile tasks to overview', () => {
    for (const path of ['/settings/appearance', '/settings/security', '/settings/privacy', '/settings/session']) {
      const html = renderRoute(path);
      expect(html).toContain('settings-layout');
      expect(html).toContain('settings-sidebar');
      expect(html).toContain('settings-panel');
      expect(html).toContain('class="detail-back" href="/settings"');
      expect(html).toContain('aria-label="返回设置" href="/settings"');
      expect(html).not.toContain('settings-mobile-navigation');
      expect(html).not.toContain('<details');
    }
  });

  it('uses task shell mode and retains legacy hash destinations', () => {
    const source = readFileSync(fileURLToPath(new URL('../pages/settings/SettingsPage.jsx', import.meta.url)), 'utf8');
    expect(source.match(/<AppShell mobileMode="task">/g)).toHaveLength(2);
    expect(settingsPathFromLegacyHash('#settings-appearance')).toBe('/settings/appearance');
    expect(settingsPathFromLegacyHash('#settings-account')).toBe('/settings/security');
    expect(settingsPathFromLegacyHash('#settings-session')).toBe('/settings/session');
    expect(settingsPathFromLegacyHash('#unknown')).toBe('/settings/appearance');
  });

  it('keeps settings loading states aligned with final setting rows', () => {
    const privacy = renderRoute('/settings/privacy');
    const layoutSource = readFileSync(fileURLToPath(new URL('../pages/settings/SettingsLayout.jsx', import.meta.url)), 'utf8');
    const css = readFileSync(fileURLToPath(new URL('../pages/settings/settings.css', import.meta.url)), 'utf8');

    expect(privacy).toContain('class="settings-rows-skeleton"');
    expect(privacy).toContain('aria-label="隐私设置加载中"');
    expect(layoutSource).toContain('export function SettingsRowsSkeleton');
    expect(css).toContain('grid-template-columns: minmax(0, 1fr) minmax(150px, 220px);');
    expect(css).toContain('.settings-rows-skeleton-row { min-height: 92px; grid-template-columns: 1fr;');
  });
});
