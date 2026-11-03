import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import { ResourceErrorBanner, ResourceState } from '../components/ResourceState.jsx';

describe('ResourceState', () => {
  it('renders an accessible loading skeleton', () => {
    const html = renderToStaticMarkup(<ResourceState status="loading">content</ResourceState>);
    expect(html).toContain('role="status"');
    expect(html).toContain('正在加载');
    expect(html).toContain('resource-skeleton');
  });

  it('keeps errors and empty results semantically distinct', () => {
    const error = renderToStaticMarkup(<ResourceState status="error" error={new Error('网络错误')}>content</ResourceState>);
    const empty = renderToStaticMarkup(<ResourceState status="success" empty>content</ResourceState>);
    expect(error).toContain('role="alert"');
    expect(error).toContain('无法连接到服务');
    expect(error).toContain('data-error-kind="offline"');
    expect(empty).toContain('这里还没有内容');
    expect(empty).toContain('astryx-empty-state');
  });

  it('never lets an empty collection hide its request failure', () => {
    const html = renderToStaticMarkup(<ResourceState status="error" empty error={{ status: 503, message: '服务不可用' }} onRetry={() => {}} />);
    expect(html).toContain('服务暂时不可用');
    expect(html).toContain('data-error-kind="service-error"');
    expect(html).not.toContain('这里还没有内容');
  });

  it('distinguishes SDK compatibility failures and hides invalid retry actions', () => {
    const contract = renderToStaticMarkup(<ResourceState status="error" error={new Error('SDK 契约错误：接口没有返回有效列表。')} onRetry={() => {}} />);
    const forbidden = renderToStaticMarkup(<ResourceState status="error" error={{ status: 403, message: '权限不足' }} onRetry={() => {}} />);
    expect(contract).toContain('页面暂时无法读取数据');
    expect(contract).toContain('重新加载');
    expect(forbidden).toContain('当前内容不可访问');
    expect(forbidden).not.toContain('重试加载');
  });

  it('does not let page-specific copy override a classified SDK error', () => {
    const html = renderToStaticMarkup(<ResourceState status="error" error={{ status: 503 }} title="个人内容暂时无法加载" description="旧说明" onRetry={() => {}} />);
    expect(html).toContain('服务暂时不可用');
    expect(html).not.toContain('个人内容暂时无法加载');
    expect(html).not.toContain('旧说明');
  });

  it('uses the same classification in scoped resource banners', () => {
    const html = renderToStaticMarkup(<ResourceErrorBanner error={{ code: -3, status: 0, message: '网络连接失败' }} onRetry={() => {}} />);
    expect(html).toContain('无法连接到服务');
    expect(html).toContain('重新连接');
    expect(html).toContain('is-offline');
  });

  it('renders an empty state when the resource status uses the empty vocabulary', () => {
    const html = renderToStaticMarkup(<ResourceState status="empty" emptyTitle="暂时没有通知" emptyDescription="新的通知会显示在这里。" />);
    expect(html).toContain('暂时没有通知');
    expect(html).toContain('新的通知会显示在这里。');
    expect(html).toContain('astryx-empty-state');
  });

  it('renders explicit permission and availability states without treating them as empty', () => {
    const forbidden = renderToStaticMarkup(<ResourceState status="forbidden" />);
    const deleted = renderToStaticMarkup(<ResourceState status="deleted" />);
    const offline = renderToStaticMarkup(<ResourceState status="offline" onRetry={() => {}} />);
    expect(forbidden).toContain('当前内容不可访问');
    expect(forbidden).toContain('role="status"');
    expect(deleted).toContain('内容已删除');
    expect(offline).toContain('role="alert"');
    expect(offline).toContain('重试加载');
  });

  it('renders a feed skeleton with the same card structure when requested', () => {
    const html = renderToStaticMarkup(<ResourceState status="loading" loadingVariant="feed" loadingClassName="home-feed" />);
    expect(html).toContain('feed-skeleton-grid');
    expect(html).toContain('feed-story-skeleton');
    expect(html).toContain('story-media');
    expect(html).toContain('story-copy');
    expect(html).toContain('home-feed');
    expect(html).not.toContain('resource-skeleton');
  });

  it('marks generic loading surfaces for the shared radius contract', () => {
    const html = renderToStaticMarkup(<ResourceState status="loading" />);
    expect(html).toContain('resource-state is-loading');
  });
});
