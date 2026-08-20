import { useCallback, useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { FeedCollection } from '../../features/feed/FeedCollection.jsx';
import { useResource } from '../../app/use-resource.js';
import { previewCreators, previewTrends } from '../../fixtures/feed-preview.js';
import { getHomeFeedSnapshot, HOME_CHANNELS, loadHomeFeed } from '../../services/feed-service.js';

function selectedChannel(params) {
  if (params.get('feed') === 'following') return 'following';
  const value = params.get('channel');
  return HOME_CHANNELS.some((channel) => channel.id === value) ? value : 'recommended';
}

function channelHref(id) {
  if (id === 'following') return '/?feed=following';
  if (id === 'recommended') return '/';
  return `/?channel=${encodeURIComponent(id)}`;
}

function UnavailableFeed({ data }) {
  return (
    <section className="home-unavailable" role="status">
      <span aria-hidden="true">●</span>
      <h2>{data.message}</h2>
      <p>{data.detail}</p>
      <span className="capability-inline">{data.source === 'configuration-error' ? '需要完成配置' : '暂未开放'}</span>
    </section>
  );
}

function CommunityRail({ preview }) {
  if (!preview) {
    return (
      <aside className="home-side" aria-label="社区推荐">
        <section className="home-side-section home-side-unavailable" role="status">
          <h2>社区推荐暂未开放</h2>
          <p>当前 SDK 尚未提供趋势讨论量与创作者推荐。</p>
        </section>
      </aside>
    );
  }

  return (
    <aside className="home-side" aria-label="社区推荐">
      <section className="home-side-section">
        <header className="home-side-heading">
          <div><h2>正在发生</h2><p>社区此刻的共同兴趣</p></div>
          <Link to="/explore">全部</Link>
        </header>
        {previewTrends.map((trend) => (
          <Link className="trend-row" to={`/topic/${encodeURIComponent(trend.slug)}`} key={trend.slug}>
            <span className="trend-rank">{trend.rank}</span>
            <span className="trend-copy"><b>{trend.label}</b><small>{trend.count}</small></span>
            <span className="trend-signal" aria-hidden="true">{trend.signal}</span>
          </Link>
        ))}
      </section>
      <section className="home-side-section">
        <header className="home-side-heading">
          <div><h2>值得认识</h2><p>与你兴趣相近的创作者</p></div>
          <span className="capability-note">关注暂未开放</span>
        </header>
        {previewCreators.map((creator) => (
          <div className="creator-row" key={creator.id}>
            <span className={`creator-avatar tone-${creator.tone}`} aria-hidden="true">{creator.name.slice(0, 1)}</span>
            <span className="creator-copy"><b>{creator.name}</b><small>{creator.meta}</small></span>
            <span className="capability-inline">未开放</span>
          </div>
        ))}
      </section>
      <footer className="home-side-footer"><span>为你推荐</span><span>兴趣调整暂未开放</span></footer>
    </aside>
  );
}

export function HomePage() {
  const [params] = useSearchParams();
  const channel = selectedChannel(params);
  const channelLabel = HOME_CHANNELS.find((item) => item.id === channel)?.label || '推荐';
  const loader = useCallback((signal) => loadHomeFeed({ channel, signal }), [channel]);
  const initialData = useMemo(() => getHomeFeedSnapshot(channel), [channel]);
  const resource = useResource(loader, initialData);
  const data = resource.data || initialData;
  const isPreview = data.source === 'preview';
  const isBoundary = data.source === 'unavailable' || data.source === 'configuration-error';

  return (
    <AppShell>
      <main className="home-page" aria-labelledby="home-title">
        <nav className="home-channels" aria-label="内容频道">
          {(isPreview ? HOME_CHANNELS : HOME_CHANNELS.filter((item) => item.id === 'recommended' || item.id === 'latest')).map((item) => (
            <Link
              to={channelHref(item.id)}
              aria-current={channel === item.id ? 'page' : undefined}
              className={channel === item.id ? 'is-active' : ''}
              key={item.id}
            >{item.label}</Link>
          ))}
          {!isPreview && <span className="home-channels-note">关注与媒体频道暂未开放</span>}
        </nav>
        {isPreview && <p className="home-preview-disclosure" role="status">设计预览 · 演示数据 · 操作不会保存</p>}
        <header className="home-heading">
          <div>
            <h1 id="home-title">{channelLabel}</h1>
            <p>{data.strategy === 'hot' ? '当前按社区热度展示内容。' : '发现值得停留的内容，也遇见值得关注的人。'}</p>
          </div>
          <div className="home-heading-actions">
            {isPreview && <span className="preview-label">设计预览 · 演示数据 · 操作不会保存</span>}
            <span className="capability-note">推荐调整暂未开放</span>
          </div>
        </header>
        <div className="home-layout">
          <ResourceState status={resource.status} error={resource.error} empty={!isBoundary && !data.items?.length} onRetry={resource.reload} loadingVariant="feed" loadingClassName="home-feed" loadingLabel="首页内容加载中">
            {isBoundary ? <UnavailableFeed data={data} /> : (
              <FeedCollection className={`home-feed${data.source === 'live' ? ' is-live' : ''}`} ariaLabel={`${channelLabel}内容`} items={data.items} source={data.source} />
            )}
          </ResourceState>
          <CommunityRail preview={isPreview} />
        </div>
      </main>
    </AppShell>
  );
}
