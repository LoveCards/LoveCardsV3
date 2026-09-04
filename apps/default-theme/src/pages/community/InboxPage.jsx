import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { getRuntimeDataMode } from '../../app/runtime-context.js';
import { ResourceState } from '../../components/ResourceState.jsx';
import { notificationPreview } from '../../fixtures/notification-preview.js';
import { AppShell } from '../../layouts/AppShell.jsx';
import {
  dedupeByStableId,
  getUnreadNotificationCount,
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from '../../services/community-service.js';
import { Button } from '../../ui/index.js';
import { ScopedBanner } from '../../ui/interaction.jsx';
import { MessageCenterHeader } from './MessageCenterHeader.jsx';
import { MessageCenterTabs } from './MessageCenterTabs.jsx';
import { NotificationItem, NotificationListSkeleton } from './NotificationItem.jsx';
import {
  groupNotifications,
  NOTIFICATION_CATEGORIES,
  notificationListParams,
  notificationMatchesCategory,
} from './notification-model.js';
import { parseMessageDate } from './message-utils.js';

function mergeNotifications(current, incoming) {
  return dedupeByStableId([...incoming, ...current]).sort((a, b) => {
    const createdDifference = (parseMessageDate(b?.created_at).getTime() || 0) - (parseMessageDate(a?.created_at).getTime() || 0);
    return createdDifference || Number(b?.id || 0) - Number(a?.id || 0);
  });
}

function previewPage(category) {
  return {
    items: notificationPreview.filter((item) => notificationMatchesCategory(item, category)),
    nextCursor: null,
    hasMore: false,
  };
}

function previewUnreadByCategory() {
  return Object.fromEntries(NOTIFICATION_CATEGORIES.map(({ value }) => [
    value,
    previewPage(value).items.filter((item) => !item.read_at).length,
  ]));
}

const emptyUnreadByCategory = Object.fromEntries(NOTIFICATION_CATEGORIES.map(({ value }) => [value, 0]));

export function InboxPage() {
  const { pathname } = useLocation();
  const auth = useAuth();
  const designPreview = getRuntimeDataMode() === 'design-preview';
  const activeFilter = pathname === '/inbox/system' ? 'system' : 'engagement';
  const initialPage = designPreview ? previewPage(activeFilter) : { items: [], nextCursor: null, hasMore: false };
  const [state, setState] = useState({ status: designPreview ? 'success' : 'loading', filter: activeFilter, ...initialPage, error: null });
  const [unreadByCategory, setUnreadByCategory] = useState(() => designPreview ? previewUnreadByCategory() : emptyUnreadByCategory);
  const [busy, setBusy] = useState('');
  const [syncMessage, setSyncMessage] = useState('');
  const [loadMoreError, setLoadMoreError] = useState('');
  const activeFilterRef = useRef(activeFilter);
  activeFilterRef.current = activeFilter;

  const syncUnreadByCategory = useCallback(async () => {
    if (designPreview) {
      setUnreadByCategory(previewUnreadByCategory());
      return;
    }
    const results = await Promise.allSettled(NOTIFICATION_CATEGORIES.map(({ value }) => getUnreadNotificationCount({ category: value })));
    setUnreadByCategory((current) => Object.fromEntries(NOTIFICATION_CATEGORIES.map(({ value }, index) => {
      const result = results[index];
      const unread = result.status === 'fulfilled' ? Math.max(0, Number(result.value?.unread) || 0) : current[value];
      return [value, unread];
    })));
  }, [designPreview]);

  const load = useCallback(async ({ silent = false, cursor = null, append = false } = {}) => {
    const requestedFilter = activeFilter;
    if (designPreview) {
      const page = previewPage(requestedFilter);
      setState({ status: 'success', filter: requestedFilter, ...page, error: null });
      setUnreadByCategory(previewUnreadByCategory());
      setSyncMessage('');
      return;
    }
    if (!auth.isAuthenticated) return;
    if (!silent && !append) {
      setState((current) => ({ ...current, status: 'loading', filter: requestedFilter, error: null }));
      setLoadMoreError('');
    }
    try {
      const [page] = await Promise.all([
        listNotifications(notificationListParams(requestedFilter, cursor)),
        syncUnreadByCategory(),
      ]);
      if (activeFilterRef.current !== requestedFilter) return;
      setState((current) => ({
        status: 'success',
        filter: requestedFilter,
        items: append || silent ? mergeNotifications(current.items, page.items) : page.items,
        nextCursor: silent ? current.nextCursor : page.nextCursor,
        hasMore: silent ? current.hasMore || page.hasMore : page.hasMore,
        error: null,
      }));
      setSyncMessage('');
      if (!append) setLoadMoreError('');
    } catch (error) {
      if (activeFilterRef.current !== requestedFilter) return;
      if (append) throw error;
      if (!silent) setState((current) => ({ ...current, status: 'error', error }));
      else setSyncMessage(getUserFacingError(error, { fallback: '通知自动同步暂时不可用，你仍可手动刷新。' }));
    }
  }, [activeFilter, auth.isAuthenticated, designPreview, syncUnreadByCategory]);

  useEffect(() => { if (designPreview || (auth.status === 'ready' && auth.isAuthenticated)) void load(); }, [auth.isAuthenticated, auth.status, designPreview, load]);
  useEffect(() => {
    if (designPreview || !auth.isAuthenticated) return undefined;
    const sync = () => { void load({ silent: true }); };
    const onVisibility = () => { if (document.visibilityState === 'visible') sync(); };
    const interval = window.setInterval(sync, 20_000);
    document.addEventListener('visibilitychange', onVisibility);
    return () => { window.clearInterval(interval); document.removeEventListener('visibilitychange', onVisibility); };
  }, [auth.isAuthenticated, designPreview, load]);

  const loadMore = async () => {
    if (!state.hasMore || !state.nextCursor || busy) return;
    setBusy('load-more');
    setLoadMoreError('');
    try { await load({ cursor: state.nextCursor, append: true }); }
    catch (error) { setLoadMoreError(getUserFacingError(error, { fallback: '更多通知暂时无法加载，请重试。' })); }
    finally { setBusy(''); }
  };

  const markRead = async (item) => {
    if (item.read_at || busy) return;
    const snapshot = state.items;
    const previousUnreadByCategory = unreadByCategory;
    setBusy(`read-${item.id}`);
    setState((current) => ({ ...current, items: current.items.map((row) => Number(row.id) === Number(item.id) ? { ...row, read_at: new Date().toISOString() } : row) }));
    setUnreadByCategory((current) => ({ ...current, [activeFilter]: Math.max(0, current[activeFilter] - 1) }));
    if (designPreview) { setBusy(''); return; }
    try { await markNotificationRead(item.id); window.dispatchEvent(new CustomEvent('lc-notifications-changed')); }
    catch (error) { setState((current) => ({ ...current, items: snapshot, error })); setUnreadByCategory(previousUnreadByCategory); setSyncMessage(getUserFacingError(error, { fallback: '这条通知暂时没有标记为已读，请稍后重试。' })); }
    finally { setBusy(''); }
  };

  const markAll = async () => {
    const unread = unreadByCategory[activeFilter] || 0;
    if (!unread || busy) return;
    const snapshot = state.items;
    const previousUnreadByCategory = unreadByCategory;
    setBusy('read-all');
    setState((current) => ({ ...current, items: current.items.map((item) => ({ ...item, read_at: item.read_at || new Date().toISOString() })) }));
    setUnreadByCategory((current) => ({ ...current, [activeFilter]: 0 }));
    if (designPreview) { setBusy(''); return; }
    try { await markAllNotificationsRead({ category: activeFilter }); window.dispatchEvent(new CustomEvent('lc-notifications-changed')); }
    catch (error) { setState((current) => ({ ...current, items: snapshot, error })); setUnreadByCategory(previousUnreadByCategory); setSyncMessage(getUserFacingError(error, { fallback: '通知暂时没有全部标记为已读，请稍后重试。' })); }
    finally { setBusy(''); }
  };

  const groups = useMemo(() => groupNotifications(state.items), [state.items]);
  const filterLabel = NOTIFICATION_CATEGORIES.find((item) => item.value === activeFilter)?.label || '互动消息';
  const unread = unreadByCategory[activeFilter] || 0;
  const notificationUnread = NOTIFICATION_CATEGORIES.reduce((total, item) => total + (unreadByCategory[item.value] || 0), 0);
  const canRead = designPreview || auth.isAuthenticated;
  const notificationContent = state.filter !== activeFilter || !designPreview && auth.status === 'checking'
    ? <NotificationListSkeleton />
    : !canRead
      ? <ResourceState status="empty" emptyTitle="登录后查看通知" emptyDescription="通知只属于你的正式账号。" emptyAction={<Link className="action-link action-link-primary" to="/auth/login">去登录</Link>} />
      : state.status === 'loading'
        ? <NotificationListSkeleton />
        : state.status === 'error'
          ? <ResourceState status="error" error={state.error} onRetry={load} />
          : state.items.length
            ? <section className="notification-stream" aria-label={`${filterLabel}列表`}>
              {groups.map((group) => <section className="notification-group" aria-labelledby={`notification-group-${group.label}`} key={group.label}><header><h2 id={`notification-group-${group.label}`}>{group.label}</h2><span>{group.items.filter((item) => !item.read_at).length ? `${group.items.filter((item) => !item.read_at).length} 条未读` : `${group.items.length} 条`}</span></header><div className="notification-list">{group.items.map((item, index) => <NotificationItem item={item} busy={Boolean(busy)} onRead={markRead} key={item.id || index} />)}</div></section>)}
            </section>
            : <ResourceState
              status="empty"
              className="notification-empty"
              emptyTitle={`${filterLabel}暂时为空`}
              emptyDescription={activeFilter === 'system'
                ? '账号安全、内容管理与服务通知会显示在这里。'
                : '新的私信、关注、点赞、收藏、评论、回复与提及会依时间显示在这里。'}
            />;

  return <AppShell><main className="community-page message-center-page inbox-page" aria-labelledby="message-center-title">
    <div className="message-center-chrome">
      <MessageCenterHeader
        description={unread ? `${filterLabel}有 ${unread} 条未读` : `${filterLabel}会依时间汇集在这里。`}
      />
      <MessageCenterTabs notificationUnread={notificationUnread} />
    </div>
    <div className="notification-workspace">
      <nav className="notification-filters" aria-label="通知范围">{NOTIFICATION_CATEGORIES.map((item) => {
        const categoryUnread = unreadByCategory[item.value] || 0;
        const active = activeFilter === item.value;
        return <Link
          className={active ? 'is-active' : ''}
          aria-current={active ? 'page' : undefined}
          aria-label={categoryUnread ? `${item.label}，${categoryUnread} 条未读` : item.label}
          key={item.value}
          to={item.to}
        >
          <span>{item.label}</span>
          {categoryUnread > 0 && <span className="notification-filter-unread" aria-hidden="true">{categoryUnread > 99 ? '99+' : categoryUnread}</span>}
        </Link>;
      })}</nav>
      <div className="notification-range-heading"><div><h2>{filterLabel}</h2><p>{unread ? `${unread} 条未读` : '暂无未读'}</p></div><Button type="button" label="全部已读" variant="secondary" size="lg" isDisabled={!unread || Boolean(busy)} isLoading={busy === 'read-all'} onClick={markAll} /></div>
      <div className="notification-content">
        {syncMessage && <ScopedBanner status="warning" title="同步状态" description={syncMessage} />}
        {notificationContent}
        {loadMoreError && <ScopedBanner className="notification-load-error" title="更多通知加载失败" description={loadMoreError} actionLabel="重试" onAction={loadMore} />}
        {state.status === 'success' && state.hasMore && !loadMoreError && <div className="notification-load-more"><Button type="button" label="加载更多" variant="secondary" size="lg" isDisabled={Boolean(busy)} isLoading={busy === 'load-more'} onClick={loadMore} /></div>}
      </div>
    </div>
  </main></AppShell>;
}
