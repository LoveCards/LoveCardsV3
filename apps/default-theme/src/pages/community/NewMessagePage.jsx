import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { ResourceState } from '../../components/ResourceState.jsx';
import { AuthorIdentity } from '../../components/AuthorIdentity.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { getPublicProfile, listConversations, sendMessage } from '../../services/community-service.js';
import { Button } from '../../ui/index.js';
import { ScopedBanner } from '../../ui/interaction.jsx';
import { conversationRecipientOf } from './MessagesPage.jsx';

export function NewMessagePage() {
  const { uid } = useParams();
  const auth = useAuth();
  const navigate = useNavigate();
  const [state, setState] = useState({ status: 'loading', profile: null, error: null });
  const [body, setBody] = useState('');
  const [sending, setSending] = useState(false);

  const load = useCallback(async () => {
    if (!auth.isAuthenticated) return;
    setState((current) => ({ ...current, status: 'loading', error: null }));
    try {
      const [profile, conversations] = await Promise.all([getPublicProfile(uid), listConversations()]);
      const existing = conversations.items.find((item) => conversationRecipientOf(item, auth.session?.uid) === profile.uid);
      const existingId = Number(existing?.conversation_id ?? existing?.id);
      if (Number.isInteger(existingId) && existingId > 0) {
        navigate(`/messages/${existingId}`, { replace: true });
        return;
      }
      setState({ status: 'success', profile, error: null });
    } catch (error) {
      setState({ status: 'error', profile: null, error });
    }
  }, [auth.isAuthenticated, auth.session?.uid, uid, navigate]);

  useEffect(() => {
    if (auth.status === 'ready' && auth.isAuthenticated) void load();
  }, [auth.isAuthenticated, auth.status, load]);

  const submit = async (event) => {
    event.preventDefault();
    const recipientUid = state.profile?.uid;
    const value = body.trim();
    if (!/^\d{12}$/.test(String(recipientUid || '')) || !value || sending) return;
    setSending(true);
    setState((current) => ({ ...current, error: null }));
    try {
      const result = await sendMessage({ recipientUid, body: value });
      const conversationId = Number(result?.conversation_id);
      if (!Number.isInteger(conversationId) || conversationId < 1) throw new Error('消息已发送，但未返回可打开的会话。');
      navigate(`/messages/${conversationId}`, { replace: true });
    } catch (error) {
      setState((current) => ({ ...current, error }));
    } finally {
      setSending(false);
    }
  };

  const profile = state.profile;
  return <AppShell mobileMode="immersive"><main className="community-page new-message-page" aria-labelledby="new-message-title">
    <Link className="detail-back" to="/messages">← 返回私信</Link>
    <header className="community-page-header"><div><p className="community-context">私信</p><h1 id="new-message-title">新私信</h1><p>发送后会创建一段私信会话，对方可以在消息中心回复你。</p></div></header>
    {auth.status === 'checking' ? <ResourceState status="loading" /> : auth.status === 'error' ? <ResourceState status="error" error={auth.error} onRetry={auth.refresh} /> : !auth.isAuthenticated ? <ResourceState status="empty" emptyTitle="登录后发送私信" emptyDescription="私信只会发送给正式账号。" emptyAction={<Link className="action-link action-link-primary" to="/auth/login" state={{ from: `/messages/new/${uid}` }}>去登录</Link>} /> : <ResourceState status={state.status} error={state.error} onRetry={load}>
      {profile && <form className="new-message-form" onSubmit={submit}>
        <section className="new-message-recipient" aria-label="收件人"><AuthorIdentity author={profile.username || '用户'} uid={profile.uid} avatarUrl={profile.avatar} meta={profile.uid ? `UID ${profile.uid}` : '主页身份不可用'} size="md" variant="list" className="recipient-identity" /></section>
        <label className="new-message-field" htmlFor="new-message-body"><span>想说些什么</span><textarea id="new-message-body" value={body} maxLength={5000} rows={7} disabled={sending} onChange={(event) => setBody(event.target.value)} aria-describedby="new-message-help new-message-error" /></label>
        <footer className="new-message-footer"><p id="new-message-help">发送后将创建新的私信对话，对方可在私信列表中看到并回复你。</p><Button type="submit" label="发送消息" variant="primary" size="lg" isDisabled={!body.trim() || sending} isLoading={sending} /></footer>
        {state.error && <ScopedBanner className="new-message-error" title="私信没有发送" description={getUserFacingError(state.error, { fallback: '正文已保留，请稍后重试。', validationFallback: '私信内容不符合要求，请修改后重试。' })} />}
      </form>}
    </ResourceState>}
  </main></AppShell>;
}
