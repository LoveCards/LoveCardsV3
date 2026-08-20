import { useCallback, useMemo } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { FeedCollection } from '../../features/feed/FeedCollection.jsx';
import { useResource } from '../../app/use-resource.js';
import { getDiscoverySnapshot, loadDiscovery } from '../../services/discovery-service.js';
import { DiscoverySearchSurface } from '../../features/search/DiscoverySearchSurface.jsx';
import { MediaFrame } from '../../features/media/MediaFrame.jsx';

function Boundary({ data }) {
  return (
    <section className="discovery-boundary" role="status">
      <h2>{data.message}</h2>
      <p>{data.detail}</p>
      <Link className="discovery-link" to="/">回到首页</Link>
    </section>
  );
}

export function ExplorePage({ search = false, topic = false }) {
  const [params, setParams] = useSearchParams();
  const routeParams = useParams();
  const navigate = useNavigate();
  const scope = ['posts', 'media', 'topics'].includes(routeParams.scope) ? routeParams.scope : 'posts';
  const query = topic ? routeParams.slug || '' : params.get('q') || '';
  const loader = useCallback((signal) => loadDiscovery({ query, signal }), [query]);
  const initial = useMemo(() => getDiscoverySnapshot(query), [query]);
  const resource = useResource(loader, initial);
  const data = resource.data || initial;
  const featured = !search && !topic ? data.items.find((item) => item.mediaUrl) : null;
  const baseItems = featured ? data.items.filter((item) => item.id !== featured.id) : data.items;
  const feedItems = scope === 'media' ? baseItems.filter((item) => item.mediaUrl) : baseItems;
  const topicResults = data.trends.filter((trend) => !query || trend.label.toLowerCase().includes(query.toLowerCase()));
  const feedClass = `discovery-feed${search ? ' is-results' : ''}${topic ? ' is-topic' : ''}`;

  const updateQuery = (value) => {
    const nextValue = String(value || '').trim();
    const next = new URLSearchParams(params);
    if (nextValue) next.set('q', nextValue);
    else next.delete('q');
    if (search) setParams(next);
    else navigate(nextValue ? `/search?q=${encodeURIComponent(nextValue)}` : '/search');
  };

  return (
    <AppShell>
      <main className="discovery-page" aria-labelledby="discovery-title">
        <header className="discovery-heading">
          <div>
            <h1 id="discovery-title">{topic ? `# ${query}` : (search && query ? `搜索“${query}”` : '去发现，今天的新鲜事')}</h1>
            <p>{topic ? '围绕同一兴趣继续浏览内容。' : '从正在发生的城市、兴趣和创作者开始。'}</p>
          </div>
          <span className="capability-note">关注、趋势与个性推荐暂未开放</span>
        </header>

        {!topic && <DiscoverySearchSurface query={query} onSubmit={updateQuery} />}

        {data.source === 'preview' && <p className="preview-disclosure" role="status">设计预览 · 演示数据 · 操作不会保存</p>}

        {search && query && (
          <nav className="search-scope-nav" aria-label="搜索结果类型">
            {[
              ['posts', '内容'],
              ['media', '媒体'],
              ['topics', '话题'],
            ].map(([value, label]) => (
              <Link key={value} className={scope === value ? 'is-active' : ''} aria-current={scope === value ? 'page' : undefined} to={`/search/${value}?q=${encodeURIComponent(query)}`}>{label}</Link>
            ))}
          </nav>
        )}

        {data.source === 'configuration-error' ? <Boundary data={data} /> : (
          <div className="discovery-layout">
            <section className="discovery-main">
              {featured && (
                <Link className="discovery-feature" to={`/post/${featured.id}`}>
                  <MediaFrame className="discovery-feature-media" item={featured.mediaItems?.[0] || { url: featured.mediaUrl, type: 'image', fit: featured.mediaFit }} frameRatio={4 / 3} alt={featured.mediaAlt || featured.title} />
                  <div>
                    <span>编辑精选</span>
                    <h2>{featured.title}</h2>
                    <p>{featured.body || `${featured.author} · ${featured.topic || '社区精选'}`}</p>
                  </div>
                </Link>
              )}

              <section className="discovery-section" aria-labelledby="discovery-feed-title">
                <header>
                  <div>
                    <h2 id="discovery-feed-title">{scope === 'topics' ? '话题结果' : (search && query ? '内容结果' : (topic ? '话题内容' : '为你展开'))}</h2>
                    <p>内容形态和密度随兴趣变化</p>
                  </div>
                  <span>{scope === 'topics' ? topicResults.length : feedItems.length} 条</span>
                </header>
                <ResourceState status={resource.status} error={resource.error} empty={data.source !== 'loading' && (scope === 'topics' ? !topicResults.length : !feedItems.length)} onRetry={resource.reload} loadingVariant={scope === 'topics' ? 'generic' : 'feed'} loadingClassName={feedClass} loadingLabel="发现内容加载中">
                  {scope === 'topics' ? (
                    <div className="topic-result-list">{topicResults.map((trend) => <Link key={trend.label} to={`/topic/${encodeURIComponent(trend.label.replace(/^# /, ''))}`}><strong>{trend.label}</strong><span>{trend.count || '查看话题内容'}</span></Link>)}</div>
                  ) : <FeedCollection className={feedClass} ariaLabel="发现内容" items={feedItems} source={data.source} />}
                </ResourceState>
              </section>
            </section>

            <aside className="discovery-side" aria-label="发现辅助信息">
              <section>
                <header><h2>此刻正在发生</h2><span>{data.source === 'live' ? '实时' : '预览'}</span></header>
                {data.trends.length ? (
                  <ol>{data.trends.map((trend) => (
                    <li key={`${trend.rank}-${trend.label}`}>
                      <b>{String(trend.rank).padStart(2, '0')}</b>
                      <Link to={`/topic/${encodeURIComponent(trend.label.replace(/^# /, ''))}`}>
                        <strong>{trend.label}</strong><small>{trend.count || '话题详情'}</small>
                      </Link>
                    </li>
                  ))}</ol>
                ) : <p className="muted-copy">趋势数据暂未开放</p>}
              </section>
              <section>
                <header><h2>值得认识</h2><span>关注暂未开放</span></header>
                {data.creators.length ? data.creators.map((creator) => (
                  <div className="discovery-creator" key={creator.id}>
                    <span className={`creator-avatar tone-${creator.tone}`} aria-hidden="true">{creator.name.slice(0, 1)}</span>
                    <div><b>{creator.name}</b><small>{creator.meta}</small></div>
                    <span className="capability-inline">未开放</span>
                  </div>
                )) : <p className="muted-copy">创作者推荐与关注关系暂未开放</p>}
              </section>
            </aside>
          </div>
        )}
      </main>
    </AppShell>
  );
}
