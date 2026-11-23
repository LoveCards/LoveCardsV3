import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { browseTaskBack, browseTaskState } from '../../app/browse-task-routing.js';
import { HeaderSearch } from '../../components/HeaderSearch.jsx';
import { TaskPageHeader } from '../../components/TaskPageHeader.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { DiscoverySearchSurface } from '../../features/search/DiscoverySearchSurface.jsx';
import { FeedCollection } from '../../features/feed/FeedCollection.jsx';
import { FeedLayoutControl } from '../../features/feed/FeedLayout.jsx';
import { FeedSkeleton } from '../../features/feed/FeedSkeleton.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { SEARCH_PREVIEW_SIZE, SEARCH_SCOPES, SEARCH_PAGE_SIZE, searchScope } from '../../services/search-service.js';
import { getRuntimeDataMode } from '../../app/runtime-context.js';
import { topicHref } from '../../services/discovery-service.js';
import { Avatar } from '../../ui/index.js';
import { canonicalSearchPath, searchHref } from './search-route.js';

const scopeLabels = { posts: '内容', users: '用户', topics: '话题' };
const emptyCopy = { posts: '没有找到相关内容', users: '没有找到相关用户', topics: '没有找到相关话题' };

function SearchRows({ scope, items, source }) {
  const location = useLocation();
  if (scope === 'posts') return <FeedCollection className="search-feed" ariaLabel="搜索内容" items={items} source={source} />;
  if (scope === 'users') return <div className="search-result-list" aria-label="用户结果">
    {items.map((item) => <Link className="search-person" key={item.uid} to={`/user/${item.uid}`}>
      <Avatar label={item.username || '用户'} src={item.avatar} tone="sand" size="md" />
      <span className="search-person-copy"><strong>{item.username || `用户 ${item.uid}`}</strong><small>UID · {item.uid}</small>{item.bio && <span>{item.bio}</span>}</span>
      <span className="search-row-arrow" aria-hidden="true">›</span>
    </Link>)}
  </div>;
  return <div className="search-result-list" aria-label="话题结果">
    {items.map((item) => <Link className="search-topic" key={item.id || item.name} to={topicHref(item)} state={browseTaskState(location)}><strong>{item.label}</strong><span>查看话题内容</span><span className="search-row-arrow" aria-hidden="true">›</span></Link>)}
  </div>;
}

function SearchSection({ scope, query, full, state, onRetry, sort, onSortChange, navigationState }) {
  if (!full && state.status === 'success' && state.data.items.length === 0 && !state.data.hint) return null;
  const title = scopeLabels[scope];
  const data = state.data;
  return <section className={`search-section is-${scope}`} aria-label={`${title}搜索结果`}>
    {(scope === 'posts' || !full) && <header className="search-section-head">
      {!full && <h2>{title}</h2>}
      <div className="search-section-actions">
        {full && <label className="search-sort">排序 <select aria-label="搜索内容排序" value={sort} onChange={onSortChange}><option value="match">匹配程度</option><option value="latest">最新发布</option></select></label>}
        {!full && state.status === 'success' && (data.pagination.hasMore || data.items.length > SEARCH_PREVIEW_SIZE[scope]) && <Link to={searchHref(scope, query)} state={navigationState}>查看全部{title}</Link>}
        {scope === 'posts' && <FeedLayoutControl />}
      </div>
    </header>}
    {state.status === 'loading' ? scope === 'posts'
      ? <FeedSkeleton className="search-feed" label="内容结果加载中" />
      : <div className="search-row-skeleton" role="status" aria-label={`${title}结果加载中`}>{[0, 1, 2].map((index) => <span key={index} aria-hidden="true" />)}</div>
      : state.status === 'error'
        ? <ResourceState status="error" error={state.error} onRetry={onRetry} className="search-result-state" />
        : state.data.hint
          ? <p className="search-query-hint" role="status">{state.data.hint}</p>
        : state.data.items.length === 0
          ? <ResourceState status="empty" className="search-result-state" emptyTitle={emptyCopy[scope]} emptyDescription="换个关键词试试。" />
          : <SearchRows scope={scope} items={full ? data.items : data.items.slice(0, SEARCH_PREVIEW_SIZE[scope])} source={data.source} />}
  </section>;
}

export function SearchPage() {
  const location = useLocation();
  const navigate = useNavigate();
  const route = useParams();
  const [params] = useSearchParams();
  const query = String(params.get('q') || '').trim();
  const scope = SEARCH_SCOPES.includes(route.scope) ? route.scope : 'all';
  const page = Math.max(1, Number(params.get('page')) || 1);
  const sort = scope === 'posts' && params.get('sort') === 'latest' ? 'latest' : 'match';
  const canonical = canonicalSearchPath({ scope: route.scope, search: location.search, hash: location.hash });
  const topicFirst = query.startsWith('#');
  const activeScopes = useMemo(() => scope === 'all' ? (topicFirst ? ['topics', 'posts', 'users'] : SEARCH_SCOPES) : [scope], [scope, topicFirst]);
  const requestKey = JSON.stringify([query, scope, page, sort]);
  const [results, setResults] = useState({ key: '', parts: {} });
  const controllers = useRef(new Map());

  const loadPart = useCallback((target, key = requestKey) => {
    controllers.current.get(target)?.abort();
    const controller = new AbortController();
    controllers.current.set(target, controller);
    setResults((current) => current.key !== key ? current : { ...current, parts: { ...current.parts, [target]: { status: 'loading', data: null, error: null } } });
    const limit = scope === 'all' ? SEARCH_PREVIEW_SIZE[target] + 1 : SEARCH_PAGE_SIZE;
    void searchScope(target, { query, page: scope === 'all' ? 1 : page, limit, sort, signal: controller.signal }).then((data) => {
      if (controller.signal.aborted) return;
      setResults((current) => current.key !== key ? current : { ...current, parts: { ...current.parts, [target]: { status: 'success', data, error: null } } });
    }).catch((error) => {
      if (controller.signal.aborted || error?.name === 'AbortError') return;
      setResults((current) => current.key !== key ? current : { ...current, parts: { ...current.parts, [target]: { status: 'error', data: null, error } } });
    });
  }, [page, query, requestKey, scope, sort]);

  useEffect(() => {
    for (const controller of controllers.current.values()) controller.abort();
    controllers.current.clear();
    if (!query || canonical) return undefined;
    setResults({ key: requestKey, parts: Object.fromEntries(activeScopes.map((target) => [target, { status: 'loading', data: null, error: null }])) });
    for (const target of activeScopes) loadPart(target);
    return () => { for (const controller of controllers.current.values()) controller.abort(); controllers.current.clear(); };
  }, [activeScopes, canonical, loadPart, query, requestKey]);

  if (canonical) return <Navigate replace to={canonical} state={location.state} />;
  const current = results.key === requestKey ? results.parts : {};
  const part = (target) => current[target] || { status: 'loading', data: null, error: null };
  const allEmpty = scope === 'all' && activeScopes.every((target) => part(target).status === 'success' && part(target).data.items.length === 0 && !part(target).data.hint);
  const fullState = scope !== 'all' ? part(scope) : null;

  const goTo = (href) => navigate(href, { state: href.startsWith('/search') ? location.state : browseTaskState(location) });

  const searchSurface = <DiscoverySearchSurface query={query} onSubmit={(value) => goTo(value ? searchHref('all', value) : '/search')} />;
  return <AppShell mobileMode="task" desktopSearch={searchSurface}>
    <main className={query ? 'search-page' : 'search-page is-search-start'} aria-labelledby="search-page-title">
      <TaskPageHeader title="搜索" back={browseTaskBack(location.state, 'search')} />
      <h1 id="search-page-title" className="sr-only">{query ? `搜索“${query}”` : '搜索'}</h1>
      {getRuntimeDataMode() === 'design-preview' && <p className="preview-disclosure" role="status">设计预览 · 演示数据 · 操作不会保存</p>}
      {searchSurface}
      {!query && <HeaderSearch mobile onNavigate={goTo} />}
      {!query ? <p className="search-start-hint">输入关键词，开始搜索。</p> : <>
        <nav className="search-scope-nav" aria-label="搜索结果范围">
          {[['all', '综合'], ['posts', '内容'], ['users', '用户'], ['topics', '话题']].map(([value, label]) => <Link key={value} to={searchHref(value, query)} state={location.state} aria-current={scope === value ? 'page' : undefined} className={scope === value ? 'is-active' : ''}>{label}</Link>)}
        </nav>
        {allEmpty ? <ResourceState status="empty" className="search-result-state search-all-empty" emptyTitle="没有找到相关结果" emptyDescription="换个关键词试试。" /> : <div className="search-results">
          {activeScopes.map((target) => <SearchSection key={target} scope={target} query={query} full={scope !== 'all'} state={part(target)} onRetry={() => loadPart(target)} sort={sort} navigationState={location.state} onSortChange={(event) => goTo(searchHref('posts', query, 1, event.target.value))} />)}
        </div>}
        {fullState?.status === 'success' && fullState.data.pagination.totalPages > 1 && <nav className="search-pagination" aria-label={`${scopeLabels[scope]}结果分页`}>
          {page > 1 ? <Link to={searchHref(scope, query, page - 1, sort)} state={location.state}>上一页</Link> : <span />}
          <span>第 {fullState.data.pagination.currentPage} / {fullState.data.pagination.totalPages} 页</span>
          {fullState.data.pagination.hasMore ? <Link to={searchHref(scope, query, page + 1, sort)} state={location.state}>下一页</Link> : <span />}
        </nav>}
      </>}
    </main>
  </AppShell>;
}
