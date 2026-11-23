import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { browseTaskBack } from '../../app/browse-task-routing.js';
import { TaskPageHeader } from '../../components/TaskPageHeader.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { ResourceErrorBanner, ResourceState } from '../../components/ResourceState.jsx';
import { FeedCollection } from '../../features/feed/FeedCollection.jsx';
import { FeedLayoutControl } from '../../features/feed/FeedLayout.jsx';
import { useResource } from '../../app/use-resource.js';
import { getDiscoverySnapshot, loadDiscovery, normalizeDiscoverySort } from '../../services/discovery-service.js';
import { Button } from '../../ui/index.js';

export { canonicalSearchPath } from '../search/search-route.js';

const sortOptions = [
  { value: 'latest', label: '最新发布' },
  { value: 'liked', label: '最多喜欢' },
  { value: 'discussed', label: '讨论最多' },
];

export function canonicalExplorePath({ search = '', hash = '' }) {
  const params = new URLSearchParams(search);
  const query = params.get('q')?.trim();
  if (query) {
    params.delete('sort');
    params.set('q', query);
    return `/search?${params}${hash}`;
  }
  const requested = params.get('sort');
  const normalized = normalizeDiscoverySort(requested);
  if (requested === null || (requested === normalized && requested !== 'latest' && params.getAll('sort').length === 1)) return null;
  if (normalized === 'latest') params.delete('sort');
  else params.set('sort', normalized);
  const next = params.toString();
  return `/explore${next ? `?${next}` : ''}${hash}`;
}

function DiscoveryFeed({ initial, topic, query, sort }) {
  const loader = useCallback((signal) => loadDiscovery({ query, signal, topic, sort }), [query, topic, sort]);
  const { data, status, error, reload } = useResource(loader, initial);
  const [more, setMore] = useState({ items: [], page: 1, loading: false, error: null });
  const moreController = useRef(null);
  useEffect(() => () => moreController.current?.abort(), []);

  const feedClass = `discovery-feed${topic ? ' is-topic' : ''}`;
  const feedItems = [...data.items, ...more.items];
  const topicName = data.topic?.name || query.replace(/^\d+-(?=.)/, '');
  const hasMore = !topic && status === 'success' && more.page < Number(data.pagination?.totalPages || 1);

  const loadMore = async () => {
    if (!hasMore || moreController.current) return;
    const controller = new AbortController();
    moreController.current = controller;
    setMore((current) => ({ ...current, loading: true, error: null }));
    try {
      const next = await loadDiscovery({ sort, page: more.page + 1, signal: controller.signal });
      if (controller.signal.aborted) return;
      const seen = new Set(feedItems.map((item) => item.id));
      setMore({ items: [...more.items, ...next.items.filter((item) => !seen.has(item.id))], page: next.pagination.currentPage, loading: false, error: null });
    } catch (reason) {
      if (!controller.signal.aborted) setMore((current) => ({ ...current, loading: false, error: reason }));
    } finally {
      moreController.current = null;
    }
  };

  return (
    <section className="discovery-main" aria-label={topic ? '话题内容' : '发现内容'}>
      {topic && <header className="discovery-heading">
        <h1 id="discovery-title"># {topicName}</h1>
        <FeedLayoutControl />
      </header>}
      {data.topicMissing ? <section className="discovery-boundary" role="status">
        <h2>这个话题不存在</h2><p>它可能已被删除，或链接已经失效。</p>
        <Link className="discovery-link" to="/explore">回到发现</Link>
      </section> : <>
        <ResourceState status={status} error={error} empty={!feedItems.length} onRetry={reload}
          loadingVariant="feed" loadingClassName={feedClass} loadingLabel={topic ? '话题内容加载中' : '发现内容加载中'}
          className="discovery-result-state" emptyTitle={topic ? '这个话题还没有内容' : '还没有公开内容'} emptyDescription="稍后再来看看。">
          <FeedCollection className={feedClass} ariaLabel={topic ? '话题内容' : '发现内容'} items={feedItems} source={data.source} />
        </ResourceState>
        {hasMore && !more.error && <div className="discovery-load-more"><Button type="button" label="加载更多" variant="secondary" size="lg" isDisabled={more.loading} isLoading={more.loading} onClick={() => void loadMore()} /></div>}
        {more.error && <ResourceErrorBanner className="discovery-more-error" error={more.error} onRetry={() => void loadMore()} />}
      </>}
    </section>
  );
}

export function ExplorePage({ topic = false }) {
  const [params] = useSearchParams();
  const location = useLocation();
  const navigate = useNavigate();
  const { slug } = useParams();
  const query = topic ? slug || '' : '';
  const sort = topic ? 'latest' : normalizeDiscoverySort(params.get('sort'));
  const initial = useMemo(() => getDiscoverySnapshot(query, sort), [query, sort]);
  const redirect = !topic && canonicalExplorePath({ search: location.search, hash: location.hash });
  if (redirect) return <Navigate replace to={redirect} state={location.state} />;

  const updateSort = (event) => {
    const next = new URLSearchParams(params);
    const value = normalizeDiscoverySort(event.target.value);
    if (value === 'latest') next.delete('sort');
    else next.set('sort', value);
    navigate({ pathname: '/explore', search: next.toString(), hash: location.hash });
  };

  return (
    <AppShell mobileMode={topic ? 'task' : 'standard'}>
      <main className={topic ? 'discovery-page topic-page' : 'discovery-page'} aria-labelledby="discovery-title">
        {topic && <TaskPageHeader title="话题" back={browseTaskBack(location.state, 'topic')} />}
        {!topic && <>
          <h1 id="discovery-title" className="sr-only">发现</h1>
          <header className="feed-toolbar">
            <div className="feed-toolbar-actions">
              <select className="discovery-sort" aria-label="发现内容排序" value={sort} onChange={updateSort}>
                {sortOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
              </select>
              <FeedLayoutControl />
            </div>
          </header>
        </>}
        {initial.source === 'preview' && <p className="preview-disclosure" role="status">设计预览 · 演示数据 · 操作不会保存</p>}
        {initial.source === 'configuration-error' ? <section className="discovery-boundary" role="status">
          {topic && <h1 id="discovery-title"># {query}</h1>}
          <h2>{initial.message}</h2><p>{initial.detail}</p>
          <Link className="discovery-link" to="/">回到首页</Link>
        </section> : <div className="discovery-layout">
          <DiscoveryFeed key={JSON.stringify([topic, query, sort])} initial={initial} query={query} topic={topic} sort={sort} />
        </div>}
      </main>
    </AppShell>
  );
}
