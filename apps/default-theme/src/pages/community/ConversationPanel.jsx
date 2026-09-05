import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { AuthorIdentity } from '../../components/AuthorIdentity.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { ensureClientMessageId, listConversations, listMessages, sendMessage } from '../../services/community-service.js';
import { Button, ChatComposer, ChatComposerInput, ChatSendButton } from '../../ui/index.js';
import { conversationIdOf, conversationRecipientOf, formatMessageTime } from './message-utils.js';

export function conversationPeerOf(item, currentUserId) {
  if (!item) return null;
  const handle = item.peer_username || item.recipient_username || item.username || null;
  const uid = [item.peer_uid, item.recipient_uid, item.other_user_uid, item.participant_uid].find((value) => /^\d{12}$/.test(String(value || '')) && String(value) !== String(currentUserId)) || null;
  const title = handle || item.peer_name || item.recipient_name || item.title || '私信对象';
  const status = item.online === true
    ? '在线'
    : item.mutual_following === true
      ? '互相关注'
      : typeof item.status === 'string' && item.status.trim()
        ? item.status.trim()
        : '私信会话';
  return {
    title,
    handle,
    uid,
    avatar: item.peer_avatar || item.recipient_avatar || item.avatar || '',
    recipientId: conversationRecipientOf(item, currentUserId),
    status,
  };
}

function ConversationStateBoundary({ children, standalone }) {
  if (!standalone) return children;
  return <section className="message-workspace-chat is-standalone" aria-label="私信会话">
    <header className="message-chat-header"><Link className="message-chat-back" to="/messages" aria-label="返回私信列表">←</Link><strong className="message-state-title">私信</strong></header>
    <div className="message-state-boundary">{children}</div>
  </section>;
}

export function ConversationThreadSkeleton({ standalone = false }) {
  return <section className={`message-workspace-chat message-thread-skeleton-shell${standalone ? ' is-standalone' : ''}`} role="status" aria-label="会话加载中">
    <header className="message-chat-header">{standalone && <Link className="message-chat-back" to="/messages" aria-label="返回私信列表">←</Link>}<i className="message-skeleton-avatar" aria-hidden="true" /><span className="message-skeleton-peer" aria-hidden="true"><i /><i /></span></header>
    <div className="message-thread message-thread-skeleton" aria-hidden="true"><i className="message-skeleton-bubble is-incoming" /><i className="message-skeleton-bubble is-outgoing" /><i className="message-skeleton-bubble is-incoming short" /><i className="message-skeleton-bubble is-outgoing short" /></div>
    <div className="message-composer message-composer-skeleton" aria-hidden="true"><i /><i /></div>
  </section>;
}

export function ConversationPanel({ conversationId, initialRecipientId = null, standalone = false }) {
  const auth = useAuth();
  const numericId = Number(conversationId);
  const validId = Number.isInteger(numericId) && numericId > 0;
  const [state, setState] = useState({ status: 'loading', items: [], error: null });
  const [peer, setPeer] = useState(null);
  const [recipientId, setRecipientId] = useState(() => /^\d{12}$/.test(String(initialRecipientId || '')) ? String(initialRecipientId) : null);
  const [body, setBody] = useState('');
  const [pending, setPending] = useState([]);
  const [busy, setBusy] = useState(false);
  const threadRef = useRef(null);

  const load = useCallback(async () => {
    if (!auth.isAuthenticated || !validId) return;
    setState((current) => ({ ...current, status: 'loading', error: null }));
    try {
      const [messages, conversations] = await Promise.all([listMessages(numericId), listConversations()]);
      const currentUserId = String(auth.session?.uid || '');
      const conversation = conversations.items.find((item) => conversationIdOf(item) === numericId);
      const nextPeer = conversationPeerOf(conversation, currentUserId);
      const fromMessages = messages.items.map((item) => item.sender_uid).find((uid) => /^\d{12}$/.test(String(uid || '')) && String(uid) !== currentUserId);
      setPeer(nextPeer);
      setRecipientId((current) => current || nextPeer?.recipientId || fromMessages || null);
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
      const result = await sendMessage({ recipientUid: recipientId, body: message.body, clientMessageId: message.client_message_id });
      setPending((current) => current.map((item) => item.client_message_id === message.client_message_id ? { ...item, id: result?.id, status: 'sent' } : item));
      await load();
    } catch (error) {
      setPending((current) => current.map((item) => item.client_message_id === message.client_message_id ? { ...item, status: 'failed', error: getUserFacingError(error, { fallback: '私信没有发送，点击重试。', validationFallback: '私信内容不符合要求，请修改后重试。' }) } : item));
    } finally { setBusy(false); }
  };

  const send = async () => {
    const value = body.trim();
    if (!value || !recipientId || busy) return;
    const message = { client_message_id: ensureClientMessageId(''), body: value, sender_uid: auth.session?.uid, status: 'sending', created_at: new Date().toISOString() };
    setPending((current) => [...current, message]); setBody(''); await transmit(message);
  };
  const rows = useMemo(() => [...state.items, ...pending.filter((item) => !state.items.some((row) => row.client_message_id === item.client_message_id))], [pending, state.items]);

  useEffect(() => {
    const node = threadRef.current;
    if (!node || state.status !== 'success') return undefined;
    const scrollToLatest = () => { node.scrollTop = node.scrollHeight; };
    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
      const frame = window.requestAnimationFrame(scrollToLatest);
      return () => window.cancelAnimationFrame(frame);
    }
    scrollToLatest();
    return undefined;
  }, [rows.length, state.status]);

  if (!validId) return <ConversationStateBoundary standalone={standalone}><ResourceState status="error" title="会话地址无效" description="请返回私信列表后重新选择会话。" actionLabel="返回私信" onRetry={() => { window.location.href = '/messages'; }} /></ConversationStateBoundary>;
  if (auth.status === 'checking') return <ConversationThreadSkeleton standalone={standalone} />;
  if (auth.status === 'error') return <ConversationStateBoundary standalone={standalone}><ResourceState status="error" error={auth.error} onRetry={auth.refresh} /></ConversationStateBoundary>;
  if (!auth.isAuthenticated) return <ConversationStateBoundary standalone={standalone}><ResourceState status="empty" emptyTitle="登录后查看会话" emptyDescription="游客会话不能读取私人对话。" emptyAction={<Link className="action-link action-link-primary" to="/auth/login">去登录</Link>} /></ConversationStateBoundary>;

  if (state.status === 'loading') return <ConversationThreadSkeleton standalone={standalone} />;
  if (state.status === 'error') return <ConversationStateBoundary standalone={standalone}><ResourceState status="error" error={state.error} onRetry={load} /></ConversationStateBoundary>;

  return <section className={`message-workspace-chat${standalone ? ' is-standalone' : ''}`} aria-label={`${peer?.title || `会话 ${numericId}`}的聊天内容`}>
      <header className="message-chat-header">
        {standalone && <Link className="message-chat-back" to="/messages" aria-label="返回私信列表">←</Link>}
        <AuthorIdentity author={peer?.title || `会话 ${numericId}`} uid={peer?.uid} handle={peer?.handle} avatarUrl={peer?.avatar} meta={peer?.status} size="md" variant="list" className="message-chat-identity" />
      </header>
      <section className="message-thread" ref={threadRef} tabIndex={0} aria-label="消息记录" aria-live="polite">
        {rows.length ? rows.map((item, index) => { const own = String(item.sender_uid || '') === String(auth.session?.uid || ''); return <article className={`message-bubble${own ? ' is-own' : ''}${item.status === 'failed' ? ' is-failed' : ''}`} key={item.id || item.client_message_id || index}><p>{item.body || item.content}</p><footer><span>{item.status === 'sending' ? '发送中…' : item.status === 'failed' ? item.error : formatMessageTime(item.created_at)}</span>{item.status === 'failed' && <Button type="button" label="重试" variant="ghost" size="sm" isDisabled={busy} onClick={() => void transmit(item)} />}</footer></article>; }) : <div className="message-empty"><strong>还没有消息</strong><span>{recipientId ? '发送第一条消息开始对话。' : '服务端尚未提供该会话的对方用户标识，因此暂时不能发送。'}</span></div>}
      </section>
      <ChatComposer className="message-composer" value={body} onChange={setBody} onSubmit={send} placeholder="写一条私信" isDisabled={busy || !recipientId} density="comfortable" elevation="none" status={!recipientId ? { type: 'warning', message: '收件人标识不可用，发送功能已禁用。' } : undefined} footerActions={<span className="message-composer-hint">Enter 发送 · Shift+Enter 换行</span>} sendButton={<ChatSendButton isDisabled={busy || !recipientId || !body.trim()} />} input={<ChatComposerInput label="写一条私信" value={body} onChange={setBody} maxLength={2000} maxRows={6} />} />
    </section>;
}
