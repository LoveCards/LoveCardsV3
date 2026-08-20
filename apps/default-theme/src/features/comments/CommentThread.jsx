import { useEffect, useMemo, useState } from 'react';
import { ActionMenu, ChatComposer, ChatComposerInput, ChatSendButton, IconButton } from '../../ui/index.js';
import { Avatar, Icon } from '../../ui/icons.jsx';

function rootIdFor(comment, commentById) {
  let current = comment;
  const seen = new Set();
  while (current?.parentId && !seen.has(current.id)) {
    seen.add(current.id);
    const parent = commentById.get(current.parentId);
    if (!parent) break;
    current = parent;
  }
  return current?.id || comment.id;
}

function CommentRow({ item, replies, depth, source, canComment, viewerId, focusedCommentId, onReply, onLogin, onOpenEdit, onRequestDelete, onCopy, isRepliesExpanded, onToggleReplies }) {
  const ownsComment = source === 'live' && item.ownerId === viewerId;
  const isFocused = Number(item.id) === focusedCommentId;
  const menuItems = ownsComment ? [
    { label: '复制评论', icon: <Icon name="copy" size={16} />, onClick: () => onCopy(item.content) },
    { label: '编辑评论', icon: <Icon name="edit" size={16} />, onClick: () => onOpenEdit(item) },
    { type: 'divider' },
    { label: '删除评论', icon: <Icon name="trash" size={16} />, onClick: () => onRequestDelete(item) },
  ] : [];
  const replyAction = canComment ? () => onReply(item) : source === 'live' ? onLogin : undefined;

  return (
    <article id={`comment-${item.id}`} className={`comment-thread-row${depth ? ' is-reply' : ''}${isFocused ? ' is-focused' : ''}`} aria-current={isFocused ? 'true' : undefined} aria-labelledby={`comment-${item.id}-author`} tabIndex={isFocused ? -1 : undefined}>
      <Avatar label={item.author} tone={depth ? 'ink' : 'rose'} size="sm" />
      <div className="comment-thread-content">
        <header>
          <b id={`comment-${item.id}-author`}>{item.author}</b>
          <small>{item.createdAt || '时间未提供'}</small>
          {menuItems.length > 0 && <ActionMenu items={menuItems} label="评论操作" />}
        </header>
        <p>{item.content}</p>
        <footer>
          <span>{item.likes} 个喜欢</span>
          {replyAction && <button type="button" onClick={replyAction}>回复</button>}
        </footer>
        {replies?.length > 0 && <div className="comment-thread-replies">
          {(isRepliesExpanded ? replies : replies.slice(0, 3)).map((reply) => <CommentRow key={reply.id} item={reply} replies={[]} depth={1} source={source} canComment={canComment} viewerId={viewerId} focusedCommentId={focusedCommentId} onReply={onReply} onLogin={onLogin} onOpenEdit={onOpenEdit} onRequestDelete={onRequestDelete} onCopy={onCopy} />)}
          {replies.length > 3 && <button className="comment-thread-more" type="button" onClick={onToggleReplies}>{isRepliesExpanded ? '收起回复' : `查看其余 ${replies.length - 3} 条回复`}</button>}
        </div>}
      </div>
    </article>
  );
}

export function CommentThread({ comments = [], focusedCommentId, source, isAuthenticated, canComment, viewerId, commentsUnavailable, onSubmitComment, onLogin, onOpenEdit, onRequestDelete, onCopy }) {
  const [replyTarget, setReplyTarget] = useState(null);
  const [draft, setDraft] = useState('');
  const [status, setStatus] = useState('idle');
  const [message, setMessage] = useState('');
  const [expandedRoots, setExpandedRoots] = useState(() => new Set());
  const commentItems = comments;
  const commentById = useMemo(() => new Map(commentItems.map((item) => [item.id, item])), [commentItems]);
  const roots = useMemo(() => commentItems.filter((item) => !item.parentId || !commentById.has(item.parentId)), [commentById, commentItems]);
  const repliesByRoot = useMemo(() => {
    const grouped = new Map();
    for (const item of commentItems) {
      if (!item.parentId) continue;
      const key = rootIdFor(item, commentById);
      grouped.set(key, [...(grouped.get(key) || []), item]);
    }
    return grouped;
  }, [commentById, commentItems]);
  const focusedId = Number(focusedCommentId);
  const focusedComment = Number.isInteger(focusedId) ? commentById.get(focusedId) : null;
  const focusedRootId = focusedComment ? rootIdFor(focusedComment, commentById) : null;

  useEffect(() => {
    if (!focusedComment?.parentId || !focusedRootId) return;
    setExpandedRoots((current) => current.has(focusedRootId) ? current : new Set([...current, focusedRootId]));
  }, [focusedComment?.parentId, focusedRootId]);

  useEffect(() => {
    if (!focusedComment) return undefined;
    const frame = window.requestAnimationFrame(() => {
      const element = document.getElementById(`comment-${focusedId}`);
      const reducedMotion = document.documentElement.dataset.reduceMotion === 'true' || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
      element?.scrollIntoView({ block: 'center', behavior: reducedMotion ? 'auto' : 'smooth' });
      element?.focus({ preventScroll: true });
    });
    return () => window.cancelAnimationFrame(frame);
  }, [expandedRoots, focusedComment, focusedId]);

  const submit = async (value) => {
    if (value.length > 140) {
      setDraft(value);
      setStatus('error');
      setMessage('评论最多 140 个字符。');
      return;
    }
    const parentId = replyTarget?.id || null;
    setStatus('sending');
    setMessage('');
    try {
      await onSubmitComment(value, parentId);
      setReplyTarget(null);
      setDraft('');
      setStatus('success');
      setMessage('已发布');
    } catch (error) {
      setDraft(value);
      setStatus('error');
      setMessage(error?.message || '发送失败，请重试。');
    }
  };

  const composer = source === 'preview'
    ? <p className="comment-thread-boundary">设计预览不会发送评论；连接 API 后会显示真实讨论。</p>
    : isAuthenticated && canComment
      ? <div className="comment-thread-composer-wrap">
        {replyTarget && <div className="comment-thread-context"><span>回复 <b>@{replyTarget.author}</b></span><IconButton type="button" label="取消回复" variant="ghost" size="lg" icon={<Icon name="close" size={17} />} onClick={() => setReplyTarget(null)} /></div>}
        <ChatComposer className="comment-chat-composer" value={draft} onChange={setDraft} onSubmit={submit} placeholder={replyTarget ? `回复 @${replyTarget.author}` : '写下你的回复…'} isDisabled={status === 'sending'} density="compact" elevation="none" status={status === 'error' ? { type: 'error', message } : undefined} footerActions={<span className="comment-composer-hint">Enter 发送 · Shift+Enter 换行</span>} sendButton={<ChatSendButton label="发送" isIconOnly={false} sendIcon={null} size="sm" isDisabled={draft.length > 140 || !draft.trim()} />} input={<div className="comment-composer-input-shell"><ChatComposerInput label={replyTarget ? `回复 ${replyTarget.author}` : '写下你的回复'} value={draft} onChange={setDraft} maxLength={140} maxRows={6} /><span className="comment-composer-count" aria-live="polite">{draft.length} / 140</span></div>} />
        {status === 'success' && <span className="comment-thread-success" role="status">{message}</span>}
      </div>
      : <p className="comment-thread-boundary">{isAuthenticated ? '当前账号没有发表评论的权限。' : <>登录后参与讨论。 <button type="button" onClick={onLogin}>去登录</button></>}</p>;

  return (
    <section className="comment-thread" aria-labelledby="comments-title">
      <header className="comment-thread-heading"><div><h2 id="comments-title">讨论</h2><span>{commentItems.length} 条</span></div><span className="comment-thread-sort">按时间</span></header>
      {commentsUnavailable ? <p className="comment-thread-boundary" role="status">{commentsUnavailable}</p> : <>
        {Number.isInteger(focusedId) && !focusedComment && <p className="comment-thread-boundary" role="status">这条评论当前不可见，可能已被删除或尚未通过审核。</p>}
        {roots.length > 0 ? <div className="comment-thread-list">{roots.map((item) => <CommentRow key={item.id} item={item} replies={repliesByRoot.get(item.id)} depth={0} source={source} canComment={isAuthenticated && canComment} viewerId={viewerId} focusedCommentId={focusedId} onReply={setReplyTarget} onLogin={isAuthenticated ? undefined : onLogin} onOpenEdit={onOpenEdit} onRequestDelete={onRequestDelete} onCopy={onCopy} isRepliesExpanded={expandedRoots.has(item.id)} onToggleReplies={() => setExpandedRoots((current) => { const next = new Set(current); if (next.has(item.id)) next.delete(item.id); else next.add(item.id); return next; })} />)}</div> : <p className="comment-thread-empty">还没有讨论，成为第一个回复的人。</p>}
      </>}
      {composer}
    </section>
  );
}
