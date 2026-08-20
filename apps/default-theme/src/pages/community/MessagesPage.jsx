import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { listConversations } from '../../services/community-service.js';
import { MessageCenterTabs } from './MessageCenterTabs.jsx';

export function conversationIdOf(item) {
  const id = Number(item?.conversation_id ?? item?.id);
  return Number.isInteger(id) && id > 0 ? id : null;
}

export function conversationRecipientOf(item, currentUserId) {
  const candidates = [item?.recipient_id, item?.peer_id, item?.other_user_id, item?.participant_id];
  const value = candidates.map(Number).find((id) => Number.isInteger(id) && id > 0 && id !== Number(currentUserId));
  return value || null;
}

export function MessagesPage() {
  const auth = useAuth();
  const [state, setState] = useState({ status: 'loading', items: [], error: null });
  const load = useCallback(async () => {
    if (!auth.isAuthenticated) return;
    setState((current) => ({ ...current, status: 'loading', error: null }));
    try { const result = await listConversations(); setState({ status: 'success', items: result.items, error: null }); }
    catch (error) { setState((current) => ({ ...current, status: 'error', error })); }
  }, [auth.isAuthenticated]);
  useEffect(() => { if (auth.status === 'ready' && auth.isAuthenticated) void load(); }, [auth.isAuthenticated, auth.status, load]);
  return <AppShell><main className="community-page messages-page" aria-labelledby="messages-title">
    <header className="community-page-header"><div><p className="community-context">消息中心</p><h1 id="messages-title">私信</h1><p>与你正在联系的人继续对话。</p></div><Link className="action-link" to="/messages/requests">消息请求 · 暂未开放</Link></header>
    <MessageCenterTabs />
    {auth.status === 'checking' ? <ResourceState status="loading" /> : !auth.isAuthenticated ? <section className="community-boundary"><h2>登录后查看私信</h2><p>游客会话不能读取私人对话。</p><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : <ResourceState status={state.status} error={state.error} onRetry={load}>
      {state.items.length ? <section className="community-list conversation-list">{state.items.map((item, index) => { const id = conversationIdOf(item); const recipientId = conversationRecipientOf(item, auth.session?.uid); const title = item.peer_username || item.recipient_username || item.title || (id ? `会话 ${id}` : `会话 ${index + 1}`); return <article className="community-row conversation-row" key={id || item.id || index}><span className="conversation-avatar" aria-hidden="true">{String(title).slice(0, 1)}</span><div className="community-row-main"><strong>{title}</strong><span>{item.last_message || item.preview || '打开会话查看消息'}</span><small>{item.updated_at || item.created_at || '时间未提供'}</small></div>{id ? <Link className="community-row-link" state={{ recipientId }} to={`/messages/${id}`}>进入会话</Link> : <span className="community-unavailable">会话标识不可用</span>}</article>; })}</section> : <section className="community-boundary"><h2>还没有私信</h2><p>当你与其他用户开始对话后，会话会显示在这里。</p></section>}
    </ResourceState>}
  </main></AppShell>;
}
