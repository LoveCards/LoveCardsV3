import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { renderRoute } from './renderRoute.jsx';

describe('route smoke', () => {
  it('keeps lazy production routes aligned with the eager component-test route map', () => {
    const readRoutes = (file) => readFileSync(fileURLToPath(new URL(file, import.meta.url)), 'utf8');
    const paths = (source) => [...source.matchAll(/<Route\s+path="([^"]+)"/g)].map((match) => match[1]);
    const production = readRoutes('../app/router.jsx');
    const eager = readRoutes('../app/router-eager.test.jsx');
    expect(paths(production)).toEqual(paths(eager));
    expect(production.match(/path: '\/compose\/:type'/)?.[0]).toBe("path: '/compose/:type'");
    expect(eager.match(/path: '\/compose\/:type'/)?.[0]).toBe("path: '/compose/:type'");
  });

  it('renders Blueprint 01 as the product entry without engineering copy', () => {
    const html = renderRoute('/');
    expect(html).toContain('推荐');
    expect(html).toContain('搜索内容、作者或话题');
    expect(html).not.toContain('Home feed · Blueprint 01');
    expect(html).not.toContain('UI Engineering Baseline');
  });

  it('renders the discovery surface without fake write actions', () => {
    const html = renderRoute('/explore');
    expect(html).toContain('去发现');
    expect(html).toContain('暂未开放');
    expect(html).not.toContain('12.4 万条讨论');
  });

  it('renders the not-found route for unknown paths', () => {
    const html = renderRoute('/not-a-real-route');
    expect(html).toContain('这里还没有页面');
  });

  it('renders a real login form instead of the pending blueprint state', () => {
    const html = renderRoute('/auth/login');
    expect(html).toContain('欢迎回来');
    expect(html).toContain('手机号、邮箱或用户名');
    expect(html).toContain('current-password');
    expect(html).toContain('astryx-form-layout');
    expect(html).toContain('astryx-text-input');
    expect(html).toContain('astryx-button primary lg');
    expect(html).not.toContain('正在建设');
  });

  it('renders registration as registration instead of login', () => {
    const html = renderRoute('/auth/register');
    expect(html).toContain('创建你的空间');
    expect(html).toContain('确认密码');
    expect(html.match(/astryx-text-input/g)).toHaveLength(3);
  });

  it('renders saved items inside the personal profile tab shell', () => {
    const html = renderRoute('/me?tab=saved');
    expect(html).toContain('profile-page');
    expect(html).toContain('>收藏</a>');
    expect(html).toMatch(/>内容(?:\s+[^<]+)?<\/a>/);
    expect(html).not.toContain('没有这个资料分类');
    expect(html).not.toContain('Blueprint');
  });

  it('renders every available settings category as a real sibling route', () => {
    expect(renderRoute('/settings')).toContain('设置总览');
    expect(renderRoute('/settings/appearance')).toContain('外观与无障碍');
    expect(renderRoute('/settings/security')).toContain('账号与安全');
    expect(renderRoute('/settings/privacy')).toContain('隐私偏好');
    expect(renderRoute('/settings/session')).toContain('登录状态');
    for (const path of ['/settings/appearance', '/settings/security', '/settings/privacy', '/settings/session']) {
      expect(renderRoute(path)).not.toContain('独立设置分区暂未开放');
    }
  });
});
