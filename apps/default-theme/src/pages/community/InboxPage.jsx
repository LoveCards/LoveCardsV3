import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import {
  dedupeByStableId,
  getNotificationStreamSnapshot,
  getUnreadNotificationCount,
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  normalizeCollection,
} from '../../services/community-service.js';
import { Button } from '../../ui/index.js';
import { MessageCenterTabs } from './MessageCenterTabs.jsx';

const filters = [
  { value: 'all', label: '全部' },
  { value: 'mention', label: '提及' },
  { value: 'reply', label: '回复' },
  { value: 'system', label: '系统' },
];

function mergeNotifications(current, incoming) {
  return dedupeByStableId([...incoming, ...current]).sort((a, b) => Number(b.id || 0) - Number(a.id || 0));
}

function notificationText(item) {
  if (typeof item.data?.message === 'string' && item.data.message.trim()) return item.data.message;
  const type = String(item.notification_type || item.type || 'interaction');
  if (type === 'message') return '你收到了一条新私信。';
  if (type.includes('follow')) return '有人关注了你。';
  if (type.includes('mention')) return '有人在内容中提到了你。';
  if (type.includes('reply') || type.includes('comment')) return '你的内容有了新的回复。';
  return '你收到了一条新的社区通知。';
}

function notificationLink(item) {
  const conversationId = item.data?.conversation_id;
  if (conversationId) return `/messages/${conversationId}`;
  if (item.resource_type === 'card' && item.resource_id) return `/post/${item.resource_id}`;
  return '';
}

export function InboxPage() {
  const { type } = useParams();
  const auth = useAuth();
  const activeFilter = filters.some((item) => item.value === type) ? type : 'all';
  const [state, setState] = useState({ status: 'loading', items: [], error: null });
  const [unread, setUnread] = useState(0);
  const [syncMode, setSyncMode] = useState('connecting');
  const [busy, setBusy] = useState('');
  const [syncMessage, setSyncMessage] = useState('');

  const load = useCallback(async ({ silent = false } = {}) => {
    if (!auth.isAuthenticated) return;
    if (!silent) setState((current) => ({ ...current, status: 'loading', error: null }));
    try {
      const [page, count] = await Promise.all([listNotifications(), getUnreadNotificationCount()]);
      setState((current) => ({ status: 'success', items: silent ? mergeNotifications(current.items, page.items) : page.items, error: null }));
      setUnread(Math.max(0, Number(count?.unread) || 0));
      setSyncMessage('');
    } catch (error) {
      if (!silent) setState((current) => ({ ...current, status: 'error', error }));
      else setSyncMessage(error?.message || '自动同步暂时不可用。');
    }
  }, [auth.isAuthenticated]);

  useEffect(() => { if (auth.status === 'ready' && auth.isAuthenticated) void load(); }, [auth.isAuthenticated, auth.status, load]);
  useEffect(() => {
    if (!auth.isAuthenticated) return undefined;
    let active = true;
    const sync = async () => {
      try {
        const snapshot = await getNotificationStreamSnapshot();
        const payload = snapshot?.data || snapshot;
        const page = normalizeCollection(payload);
        if (active && page.items.length) setState((current) => ({ ...current, items: mergeNotifications(current.items, page.items) }));
        if (active) { setSyncMode('snapshot'); setSyncMessage(''); }
        const count = await getUnreadNotificationCount();
        if (active) setUnread(Math.max(0, Number(count?.unread) || 0));
      } catch {
        if (!active) return;
        setSyncMode('polling');
        await load({ silent: true });
      }
    };
    const onVisibility = () => { if (document.visibilityState === 'visible') void sync(); };
    void sync();
    const interval = window.setInterval(sync, 20_000);
    document.addEventListener('visibilitychange', onVisibility);
    return () => { active = false; window.clearInterval(interval); document.removeEventListener('visibilitychange', onVisibility); };
  }, [auth.isAuthenticated, load]);

  const visible = useMemo(() => state.items.filter((item) => {
    if (activeFilter === 'all') return true;
    const value = String(item.notification_type || item.type || '');
    return value.includes(activeFilter) || (activeFilter === 'reply' && value.includes('comment'));
  }), [activeFilter, state.items]);

  const markRead = async (item) => {
    if (item.read_at || busy) return;
    const snapshot = state.items;
    const previousUnread = unread;
    setBusy(`read-${item.id}`);
    setState((current) => ({ ...current, items: current.items.map((row) => Number(row.id) === Number(item.id) ? { ...row, read_at: new Date().toISOString() } : row) }));
    setUnread((value) => Math.max(0, value - 1));
    try { await markNotificationRead(item.id); window.dispatchEvent(new CustomEvent('lc-notifications-changed')); }
    catch (error) { setState((current) => ({ ...current, items: snapshot, error })); setUnread(previousUnread); setSyncMessage(error?.message || '标记已读失败。'); }
    finally { setBusy(''); }
  };

  const markAll = async () => {
    if (!unread || busy) return;
    const snapshot = state.items;
    const previousUnread = unread;
    setBusy('read-all'); setState((current) => ({ ...current, items: current.items.map((item) => ({ ...item, read_at: item.read_at || new Date().toISOString() })) })); setUnread(0);
    try { await markAllNotificationsRead(); window.dispatchEvent(new CustomEvent('lc-notifications-changed')); }
    catch (error) { setState((current) => ({ ...current, items: snapshot, error })); setUnread(previousUnread); setSyncMessage(error?.message || '全部标记已读失败。'); }
    finally { setBusy(''); }
  };

  return <AppShell><main className="community-page inbox-page" aria-labelledby="inbox-title">
    <header className="community-page-header"><div><p className="community-context">消息中心</p><h1 id="inbox-title">通知</h1><p>{unread ? `${unread} 条未读通知` : '新的互动会汇集在这里。'}</p></div><div className="community-toolbar"><span className="sync-status">{syncMode === 'polling' ? '定时刷新' : '自动同步'}</span><Button type="button" label="刷新" variant="ghost" size="lg" onClick={() => void load()} /><Button type="button" label="全部已读" variant="secondary" size="lg" isDisabled={!unread || Boolean(busy)} isLoading={busy === 'read-all'} onClick={markAll} /></div></header>
    <MessageCenterTabs />
    <nav className="notification-filters" aria-label="通知筛选">{filters.map((item) => <Link className={activeFilter === item.value ? 'is-active' : ''} aria-current={activeFilter === item.value ? 'page' : undefined} key={item.value} to={item.value === 'all' ? '/inbox' : `/inbox/${item.value}`}>{item.label}</Link>)}</nav>
    {syncMessage && <p className="community-inline-error" role="status">{syncMessage}</p>}
    {auth.status === 'checking' ? <ResourceState status="loading" /> : !auth.isAuthenticated ? <section className="community-boundary"><h2>登录后查看通知</h2><p>通知只属于你的正式账号。</p><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : <ResourceState status={state.status} error={state.error} onRetry={load}>
      {visible.length ? <section className="community-list notification-list">{visible.map((item) => { const href = notificationLink(item); return <article className={`community-row notification-row${item.read_at ? '' : ' is-unread'}`} key={item.id}><button className="notification-main" type="button" onClick={() => void markRead(item)} disabled={Boolean(busy)}><span className="notification-dot" aria-hidden="true" /><span><strong>{notificationText(item)}</strong><small>{item.created_at || '时间未提供'}</small></span></button>{href && <Link className="community-row-link" to={href}>查看</Link>}</article>; })}</section> : <section className="community-boundary"><h2>{activeFilter === 'all' ? '还没有通知' : '这个分类暂时为空'}</h2><p>有新的关注、回复或系统消息时会显示在这里。</p></section>}
    </ResourceState>}
  </main></AppShell>;
}
