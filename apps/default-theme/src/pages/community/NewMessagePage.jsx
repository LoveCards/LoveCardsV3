import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { ResourceState } from '../../components/ResourceState.jsx';
import { AuthorIdentity } from '../../components/AuthorIdentity.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { ensureClientMessageId, getPublicProfile, listConversations, sendMessage } from '../../services/community-service.js';
import { Button } from '../../ui/index.js';
import { ScopedBanner } from '../../ui/interaction.jsx';
import { conversationRecipientOf } from './MessagesPage.jsx';

export function NewMessagePage() {
  const { uid } = useParams();
  const auth = useAuth();
  const navigate = useNavigate();
  const [state, setState] = useState({ status: 'loading', profile: null, error: null, routeUid: uid });
  const [body, setBody] = useState('');
  const [sending, setSending] = useState(false);
  const sendingRef = useRef(false);
  const pendingMessageIdsRef = useRef(new Map());
  const draftsRef = useRef(new Map());
  const loadRequestRef = useRef(0);

  const load = useCallback(async () => {
    if (!auth.isAuthenticated) return;
    const requestId = ++loadRequestRef.current;
    setState({ status: 'loading', profile: null, error: null, routeUid: uid });
    try {
      const [profile, conversations] = await Promise.all([getPublicProfile(uid), listConversations()]);
      if (requestId !== loadRequestRef.current) return;
      const existing = conversations.items.find((item) => conversationRecipientOf(item, auth.session?.uid) === profile.uid);
      const existingId = Number(existing?.conversation_id ?? existing?.id);
      if (Number.isInteger(existingId) && existingId > 0) {
        navigate(`/messages/${existingId}`, { replace: true });
        return;
      }
      setState({ status: 'success', profile, error: null, routeUid: uid });
    } catch (error) {
      if (requestId !== loadRequestRef.current) return;
      setState({ status: 'error', profile: null, error, routeUid: uid });
    }
  }, [auth.isAuthenticated, auth.session?.uid, uid, navigate]);

  useLayoutEffect(() => () => {
    // 路由切换提交时立即废弃旧收件人的请求，避免旧响应在普通 effect 清理前导航。
    loadRequestRef.current += 1;
  }, [uid]);

  useEffect(() => {
    if (auth.status === 'ready' && auth.isAuthenticated) void load();
    return () => { loadRequestRef.current += 1; };
  }, [auth.isAuthenticated, auth.status, load]);
  useEffect(() => { setBody(draftsRef.current.get(uid) || ''); }, [uid]);

  const submit = async (event) => {
    event.preventDefault();
    const recipientUid = state.routeUid === uid ? state.profile?.uid : null;
    const value = body.trim();
    if (!/^\d{12}$/.test(String(recipientUid || '')) || !value || sendingRef.current) return;
    const requestId = loadRequestRef.current;
    sendingRef.current = true;
    setSending(true);
    setState((current) => ({ ...current, error: null }));
    try {
      // 同一消息重试复用 ID，避免服务端已接收而客户端超时后的重复发送。
      const previousAttempt = pendingMessageIdsRef.current.get(uid);
      const clientMessageId = previousAttempt?.recipientUid === recipientUid && previousAttempt.body === value
        ? previousAttempt.id : ensureClientMessageId('');
      pendingMessageIdsRef.current.set(uid, { recipientUid, body: value, id: clientMessageId });
      const result = await sendMessage({ recipientUid, body: value, clientMessageId });
      const conversationId = Number(result?.conversation_id);
      if (!Number.isInteger(conversationId) || conversationId < 1) throw new Error('消息已发送，但未返回可打开的会话。');
      if (requestId === loadRequestRef.current) navigate(`/messages/${conversationId}`, { replace: true });
    } catch (error) {
      if (requestId === loadRequestRef.current) setState((current) => ({ ...current, error }));
    } finally {
      sendingRef.current = false;
      setSending(false);
    }
  };

  const profile = state.routeUid === uid ? state.profile : null;
  const loadStatus = state.routeUid === uid ? state.status : 'loading';
  return <AppShell mobileMode="immersive"><main className="community-page new-message-page" aria-labelledby="new-message-title">
    <Link className="detail-back" to="/messages">← 返回私信</Link>
    <header className="community-page-header"><div><p className="community-context">私信</p><h1 id="new-message-title">新私信</h1><p>发送后会创建一段私信会话，对方可以在消息中心回复你。</p></div></header>
    {auth.status === 'checking' ? <ResourceState status="loading" /> : auth.status === 'error' ? <ResourceState status="error" error={auth.error} onRetry={auth.refresh} /> : !auth.isAuthenticated ? <ResourceState status="empty" emptyTitle="登录后发送私信" emptyDescription="私信只会发送给正式账号。" emptyAction={<Link className="action-link action-link-primary" to="/auth/login" state={{ from: `/messages/new/${uid}` }}>去登录</Link>} /> : <ResourceState status={loadStatus} error={state.error} onRetry={load}>
      {profile && <form className="new-message-form" onSubmit={submit}>
        <section className="new-message-recipient" aria-label="收件人"><AuthorIdentity author={profile.username || '用户'} uid={profile.uid} avatarUrl={profile.avatar} meta={profile.uid ? `UID ${profile.uid}` : '主页身份不可用'} size="md" variant="list" className="recipient-identity" /></section>
        <label className="new-message-field" htmlFor="new-message-body"><span>想说些什么</span><textarea id="new-message-body" value={body} maxLength={5000} rows={7} disabled={sending} onChange={(event) => { setBody(event.target.value); draftsRef.current.set(uid, event.target.value); pendingMessageIdsRef.current.delete(uid); }} aria-describedby="new-message-help new-message-error" /></label>
        <footer className="new-message-footer"><p id="new-message-help">发送后将创建新的私信对话，对方可在私信列表中看到并回复你。</p><Button type="submit" label="发送消息" variant="primary" size="lg" isDisabled={!body.trim() || sending} isLoading={sending} /></footer>
        {state.error && <ScopedBanner className="new-message-error" title="私信没有发送" description={getUserFacingError(state.error, { fallback: '正文已保留，请稍后重试。', validationFallback: '私信内容不符合要求，请修改后重试。' })} />}
      </form>}
    </ResourceState>}
  </main></AppShell>;
}
