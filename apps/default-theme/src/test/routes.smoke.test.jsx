import { describe, expect, it } from 'vitest';
import { renderRoute } from './renderRoute.jsx';

describe('route smoke', () => {
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

  it('renders saved items as a real personal asset route', () => {
    const html = renderRoute('/me/library/saved');
    expect(html).toContain('我的收藏');
    expect(html).toContain('个人资产');
    expect(html).not.toContain('没有这个资料分类');
    expect(html).not.toContain('Blueprint');
  });
});
