import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import { ResourceState } from '../components/ResourceState.jsx';

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
    expect(error).toContain('网络错误');
    expect(empty).toContain('这里还没有内容');
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
