import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { AuthorIdentity } from '../../components/AuthorIdentity.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { listConversations } from '../../services/community-service.js';
import { Icon } from '../../ui/icons.jsx';
import { MessageCenterHeader } from './MessageCenterHeader.jsx';
import { MessageCenterTabs } from './MessageCenterTabs.jsx';
import { ConversationPanel, conversationPeerOf } from './ConversationPanel.jsx';
export { conversationIdOf, conversationRecipientOf } from './message-utils.js';
import {
  conversationIdOf,
  conversationPreviewOf,
  conversationTimeOf,
  conversationUnreadCountOf,
  conversationUnreadTotalOf,
  formatMessageTime,
} from './message-utils.js';

function isCompactViewport() {
  return typeof window !== 'undefined'
    && typeof window.matchMedia === 'function'
    && window.matchMedia('(max-width: 760px)').matches;
}

function MessageWorkspaceSkeleton({ count = 5 }) {
  return <div className="message-workspace message-workspace-skeleton" role="status" aria-label="私信加载中">
    <aside className="message-conversations" aria-hidden="true">
      <header className="message-conversations-header"><span className="message-skeleton-title" /><span className="message-skeleton-count" /></header>
      <div className="conversation-list-skeleton">{Array.from({ length: count }, (_, index) => <div className="conversation-skeleton-row" key={index}><i /><span><i /><i /></span><i /></div>)}</div>
    </aside>
    <section className="message-workspace-desktop-panel" aria-hidden="true">
      <div className="message-workspace-chat message-thread-skeleton-shell">
        <header className="message-chat-header"><i className="message-skeleton-avatar" /><span className="message-skeleton-peer"><i /><i /></span></header>
        <div className="message-thread message-thread-skeleton"><i className="message-skeleton-bubble is-incoming" /><i className="message-skeleton-bubble is-outgoing" /><i className="message-skeleton-bubble is-incoming short" /><i className="message-skeleton-bubble is-outgoing short" /></div>
        <div className="message-composer message-composer-skeleton"><i /><i /></div>
      </div>
    </section>
  </div>;
}

export function MessagesPage() {
  const auth = useAuth();
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const searchMode = pathname === '/messages/search';
  const compactViewport = isCompactViewport();
  const [state, setState] = useState({ status: 'loading', items: [], error: null });
  const [selectedId, setSelectedId] = useState(null);
  const [query, setQuery] = useState('');
  const load = useCallback(async () => {
    if (!auth.isAuthenticated) return;
    setState((current) => ({ ...current, status: 'loading', error: null }));
    try {
      const result = await listConversations();
      setState({ status: 'success', items: result.items, error: null });
      setSelectedId((current) => {
        const ids = result.items.map(conversationIdOf).filter(Boolean);
        return ids.includes(current) ? current : (isCompactViewport() ? null : (ids[0] || null));
      });
    }
    catch (error) { setState((current) => ({ ...current, status: 'error', error })); }
  }, [auth.isAuthenticated]);
  const visibleItems = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!needle) return state.items;
    return state.items.filter((item) => {
      const peer = conversationPeerOf(item, auth.session?.uid);
      return [peer?.title, conversationPreviewOf(item)].filter(Boolean).some((value) => String(value).toLowerCase().includes(needle));
    });
  }, [auth.session?.uid, query, state.items]);
  const messageUnread = state.status === 'success' ? conversationUnreadTotalOf(state.items) : undefined;
  useEffect(() => { if (auth.status === 'ready' && auth.isAuthenticated) void load(); }, [auth.isAuthenticated, auth.status, load]);
  const activateConversation = (id, recipientId) => {
    if (!id) return;
    if (isCompactViewport()) {
      navigate(`/messages/${id}`, { state: { recipientId } });
      return;
    }
    setState((current) => ({ ...current, items: current.items.map((item) => conversationIdOf(item) === id ? { ...item, unread_count: 0 } : item) }));
    setSelectedId(id);
    window.dispatchEvent(new CustomEvent('lc-messages-changed'));
  };
  const content = auth.status === 'checking'
    ? <MessageWorkspaceSkeleton />
    : !auth.isAuthenticated
      ? <ResourceState status="empty" emptyTitle="登录后查看私信" emptyDescription="游客会话不能读取私人对话。" emptyAction={<Link className="action-link action-link-primary" to="/auth/login">去登录</Link>} />
      : state.status === 'loading'
        ? <MessageWorkspaceSkeleton />
        : state.status === 'error'
          ? <ResourceState status="error" error={state.error} onRetry={load} />
          : state.items.length
            ? <div className="message-workspace">
              <aside className="message-conversations" aria-label="私信会话列表">
                <header className="message-conversations-header"><div><h2>私信</h2><p>{state.items.length} 个会话</p></div></header>
                <div className="message-conversation-tools"><label htmlFor="message-search">搜索私信</label><input id="message-search" type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="搜索会话" /></div>
                <div className="conversation-list">{visibleItems.map((item, index) => {
                  const id = conversationIdOf(item);
                  const peer = conversationPeerOf(item, auth.session?.uid);
                  const active = !compactViewport && id === selectedId;
                  const recipientId = peer?.recipientId;
                  const label = peer?.title || (id ? `会话 ${id}` : `会话 ${index + 1}`);
                  const unreadCount = conversationUnreadCountOf(item);
                  const time = formatMessageTime(conversationTimeOf(item));
                  return <article className={`conversation-row${active ? ' is-active' : ''}${unreadCount ? ' is-unread' : ''}`} key={id || item.id || index}>
                    <AuthorIdentity author={label} uid={peer?.uid} handle={peer?.handle} avatarUrl={peer?.avatar} size="md" variant="list" className="conversation-identity" />
                    {id
                      ? <button type="button" className="conversation-row-open" aria-label={`打开与${label}的会话`} aria-current={active ? 'true' : undefined} onClick={() => activateConversation(id, recipientId)}>
                        <span className="conversation-preview">{conversationPreviewOf(item)}</span>
                        <span className="conversation-row-status">
                          {unreadCount > 0 && <b className="conversation-unread" aria-label={`${unreadCount} 条未读`}>{unreadCount > 99 ? '99+' : unreadCount}</b>}
                          <small>{time}</small>
                        </span>
                      </button>
                      : <span className="conversation-row-unavailable">会话标识不可用</span>}
                  </article>;
                })}{query && !visibleItems.length && <div className="message-search-empty"><strong>没有找到匹配会话</strong><span>换个用户名或消息关键词试试。</span></div>}</div>
              </aside>
              <section className="message-workspace-desktop-panel" aria-label="当前会话">{selectedId ? <ConversationPanel key={selectedId} conversationId={selectedId} /> : <div className="message-workspace-placeholder"><strong>选择一个会话</strong><span>从左侧选择对话后，消息内容会显示在这里。</span></div>}</section>
            </div>
            : <ResourceState status="empty" emptyTitle={query ? '没有找到匹配会话' : '还没有私信'} emptyDescription={query ? '换个关键词试试。' : '当你与其他用户开始对话后，会话会显示在这里。'} />;

  return <AppShell mobileMode={searchMode ? 'task' : 'standard'}><main className={`community-page message-center-page messages-page${searchMode ? ' is-search-page' : ''}`} aria-labelledby="message-center-title">
    {searchMode
      ? <header className="message-search-page-header"><Link to="/messages" aria-label="返回私信列表">←</Link><label htmlFor="message-search-page">搜索会话</label><input id="message-search-page" type="search" autoFocus value={query} onChange={(event) => setQuery(event.target.value)} placeholder="搜索用户名或消息" /></header>
      : <div className="message-center-chrome"><MessageCenterHeader description="私信按最近活动排列。" actions={<Link className="message-search-trigger" to="/messages/search" aria-label="搜索会话"><Icon name="search" size={20} /></Link>} /><MessageCenterTabs messageUnread={messageUnread} /></div>}
    {content}
  </main></AppShell>;
}
