import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { ensureClientMessageId, listConversations, listMessages, sendMessage } from '../../services/community-service.js';
import { Button, ChatComposer, ChatComposerInput, ChatSendButton } from '../../ui/index.js';
import { conversationIdOf, conversationRecipientOf } from './MessagesPage.jsx';

export function ConversationPage() {
  const { conversationId } = useParams();
  const location = useLocation();
  const auth = useAuth();
  const numericId = Number(conversationId);
  const validId = Number.isInteger(numericId) && numericId > 0;
  const [state, setState] = useState({ status: 'loading', items: [], error: null });
  const [recipientId, setRecipientId] = useState(() => Number(location.state?.recipientId) || null);
  const [body, setBody] = useState('');
  const [pending, setPending] = useState([]);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    if (!auth.isAuthenticated || !validId) return;
    setState((current) => ({ ...current, status: 'loading', error: null }));
    try {
      const [messages, conversations] = await Promise.all([listMessages(numericId), listConversations()]);
      const currentUserId = Number(auth.session?.uid);
      const conversation = conversations.items.find((item) => conversationIdOf(item) === numericId);
      const fromConversation = conversationRecipientOf(conversation, currentUserId);
      const fromMessages = messages.items.map((item) => Number(item.sender_id)).find((id) => Number.isInteger(id) && id > 0 && id !== currentUserId);
      setRecipientId((current) => current || fromConversation || fromMessages || null);
      setState({ status: 'success', items: [...messages.items].sort((a, b) => Number(a.id || 0) - Number(b.id || 0)), error: null });
      setPending((current) => current.filter((item) => item.status === 'failed' || !messages.items.some((row) => row.client_message_id === item.client_message_id)));
    } catch (error) { setState((current) => ({ ...current, status: 'error', error })); }
  }, [auth.isAuthenticated, auth.session?.uid, numericId, validId]);
  useEffect(() => { if (auth.status === 'ready' && auth.isAuthenticated && validId) void load(); }, [auth.isAuthenticated, auth.status, load, validId]);

  const transmit = async (message) => {
    if (!recipientId || busy) return;
    setBusy(true);
    setPending((current) => current.map((item) => item.client_message_id === message.client_message_id ? { ...item, status: 'sending', error: '' } : item));
    try {
      const result = await sendMessage({ recipientId, body: message.body, clientMessageId: message.client_message_id });
      setPending((current) => current.map((item) => item.client_message_id === message.client_message_id ? { ...item, id: result?.id, status: 'sent' } : item));
      await load();
    } catch (error) {
      setPending((current) => current.map((item) => item.client_message_id === message.client_message_id ? { ...item, status: 'failed', error: error?.message || '发送失败。' } : item));
    } finally { setBusy(false); }
  };
  const send = async () => {
    const value = body.trim();
    if (!value || !recipientId || busy) return;
    const message = { client_message_id: ensureClientMessageId(''), body: value, sender_id: auth.session?.uid, status: 'sending', created_at: new Date().toISOString() };
    setPending((current) => [...current, message]); setBody(''); await transmit(message);
  };
  const rows = useMemo(() => [...state.items, ...pending.filter((item) => !state.items.some((row) => row.client_message_id === item.client_message_id))], [pending, state.items]);
  return <AppShell><main className="community-page conversation-page" aria-labelledby="conversation-title">
    <header className="conversation-header"><Link className="detail-back" to="/messages">← 私信</Link><div><h1 id="conversation-title">会话 {validId ? numericId : ''}</h1><p>{recipientId ? '消息发送失败时可以安全重试，不会重复发送。' : '当前会话缺少可用的收件人标识。'}</p></div></header>
    {!validId ? <section className="community-boundary"><h2>会话地址无效</h2><Link className="action-link" to="/messages">返回私信</Link></section> : auth.status === 'checking' ? <ResourceState status="loading" /> : !auth.isAuthenticated ? <section className="community-boundary"><h2>登录后查看会话</h2><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : <ResourceState status={state.status} error={state.error} onRetry={load}>
      <section className="message-thread" aria-live="polite">{rows.length ? rows.map((item, index) => { const own = Number(item.sender_id) === Number(auth.session?.uid); return <article className={`message-bubble${own ? ' is-own' : ''}${item.status === 'failed' ? ' is-failed' : ''}`} key={item.id || item.client_message_id || index}><p>{item.body || item.content}</p><footer><span>{item.status === 'sending' ? '发送中…' : item.status === 'failed' ? item.error : item.created_at || ''}</span>{item.status === 'failed' && <Button type="button" label="重试" variant="ghost" size="sm" isDisabled={busy} onClick={() => void transmit(item)} />}</footer></article>; }) : <div className="message-empty"><strong>还没有消息</strong><span>{recipientId ? '发送第一条消息开始对话。' : '服务端尚未提供该会话的对方用户标识，因此暂时不能发送。'}</span></div>}</section>
      <ChatComposer className="message-composer" value={body} onChange={setBody} onSubmit={send} placeholder="写一条私信" isDisabled={busy || !recipientId} density="comfortable" elevation="none" status={!recipientId ? { type: 'warning', message: '收件人标识不可用，发送功能已禁用。' } : undefined} footerActions={<span className="message-composer-hint">Enter 发送 · Shift+Enter 换行</span>} sendButton={<ChatSendButton isDisabled={busy || !recipientId || !body.trim()} />} input={<ChatComposerInput label="写一条私信" value={body} onChange={setBody} maxLength={2000} maxRows={6} />} />
    </ResourceState>}
  </main></AppShell>;
}
