import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionMenu, ChatComposer, ChatComposerInput, ChatSendButton, IconButton } from '../../ui/index.js';
import { Icon } from '../../ui/icons.jsx';
import { AuthorIdentity } from '../../components/AuthorIdentity.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { getSdkClient } from '../../app/sdk-client.js';
import { createMentionTrigger, createPreviewMentionAdapter, createSdkMentionAdapter, normalizeMentionCaret } from '../../services/mentions-service.js';
import { renderMentionCandidateItem } from '../mentions/MentionCandidateItem.jsx';
import { MentionText } from '../../components/MentionText.jsx';

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

export function buildCommentActionMenuItems({ item, source, viewerId, canDeleteComment, canPinComment = false, pinBusy = false, onRequestDelete, onRequestPinChange, onCopy }) {
  const ownsComment = source === 'live' && ((item.authorUid && item.authorUid === viewerId) || (!item.authorUid && Number(item.ownerId) === Number(viewerId)));
  const canPinRoot = source === 'live' && canPinComment && !item.parentId && typeof onRequestPinChange === 'function';
  if (!ownsComment && !canPinRoot) return [];
  const items = [];
  if (typeof onCopy === 'function') items.push({ label: '复制评论', icon: <Icon name="copy" size={16} />, onClick: () => onCopy(item.content) });
  if (canPinRoot) {
    items.push({
      label: item.isPinned ? '取消置顶' : '置顶评论',
      icon: <Icon name="pin" size={16} />,
      isDisabled: pinBusy,
      onClick: () => onRequestPinChange(item, !item.isPinned),
    });
  }
  if (ownsComment && canDeleteComment && typeof onRequestDelete === 'function') {
    if (items.length) items.push({ type: 'divider' });
    items.push({ label: '删除评论', icon: <Icon name="trash" size={16} />, onClick: () => onRequestDelete(item) });
  }
  return items;
}

function CommentRow({ item, replies, depth, source, isAuthenticated, canComment, canLike, canDeleteComment, canPinComment, pinBusy, viewerId, focusedCommentId, onReply, onLogin, onToggleLike, likeBusyIds, onRequestDelete, onRequestPinChange, onCopy, isRepliesExpanded, onToggleReplies }) {
  const menuItems = buildCommentActionMenuItems({ item, source, viewerId, canDeleteComment, canPinComment, pinBusy, onRequestDelete, onRequestPinChange, onCopy });
  const isFocused = Number(item.id) === focusedCommentId;
  const replyAction = canComment ? () => onReply(item) : source === 'live' ? onLogin : undefined;
  const likeAction = source === 'live' && canLike
    ? <button className={`comment-like-button${item.viewerLiked ? ' is-active' : ''}`} type="button" aria-pressed={Boolean(item.viewerLiked)} onClick={() => { void onToggleLike(item, !item.viewerLiked); }} disabled={likeBusyIds?.has(item.id)}><Icon name="heart" size={15} /><span>{item.likes}</span><span className="sr-only">{item.viewerLiked ? '取消喜欢' : '喜欢这条评论'}</span></button>
    : source === 'live' && !canLike && !isAuthenticated
      ? <button className="comment-like-button" type="button" onClick={onLogin}><Icon name="heart" size={15} /><span>{item.likes}</span><span className="sr-only">登录后喜欢这条评论</span></button>
      : <span>{item.likes} 个喜欢</span>;

  return (
    <article id={`comment-${item.id}`} className={`comment-thread-row${depth ? ' is-reply' : ''}${isFocused ? ' is-focused' : ''}`} aria-current={isFocused ? 'true' : undefined} aria-labelledby={`comment-${item.id}-author`} tabIndex={isFocused ? -1 : undefined}>
      <header className="comment-thread-row-header">
        <AuthorIdentity id={`comment-${item.id}-author`} author={item.author} uid={item.authorUid} handle={item.authorHandle} avatarUrl={item.authorAvatar} meta={item.createdAt || '时间未提供'} tone={depth ? 'ink' : 'rose'} variant="comment" className="comment-author-identity" />
        {!depth && item.isPinned && <span className="comment-pinned-badge"><Icon name="pin" size={13} />置顶</span>}
        {menuItems.length > 0 && <ActionMenu items={menuItems} label="评论操作" />}
      </header>
      <div className="comment-thread-content">
        <p><MentionText text={item.content} mentions={item.mentions} /></p>
        <footer>
          {likeAction}
          {replyAction && <button type="button" onClick={replyAction}>回复</button>}
        </footer>
        {replies?.length > 0 && <div className="comment-thread-replies">
          {(isRepliesExpanded ? replies : replies.slice(0, 3)).map((reply) => <CommentRow key={reply.id} item={reply} replies={[]} depth={1} source={source} isAuthenticated={isAuthenticated} canComment={canComment} canLike={canLike} canDeleteComment={canDeleteComment} canPinComment={canPinComment} pinBusy={pinBusy} viewerId={viewerId} focusedCommentId={focusedCommentId} onReply={onReply} onLogin={onLogin} onToggleLike={onToggleLike} likeBusyIds={likeBusyIds} onRequestDelete={onRequestDelete} onRequestPinChange={onRequestPinChange} onCopy={onCopy} />)}
          {replies.length > 3 && <button className="comment-thread-more" type="button" onClick={onToggleReplies}>{isRepliesExpanded ? '收起回复' : `查看其余 ${replies.length - 3} 条回复`}</button>}
        </div>}
      </div>
    </article>
  );
}

export function CommentThread({ comments = [], focusedCommentId, source, isAuthenticated, canComment, canLike, canDeleteComment, canPinComment = false, pinBusy = false, viewerId, commentsUnavailable, onSubmitComment, onLogin, onToggleLike, likeBusyIds, onRequestDelete, onRequestPinChange, onCopy }) {
  const [replyTarget, setReplyTarget] = useState(null);
  const [draft, setDraft] = useState('');
  const [status, setStatus] = useState('idle');
  const [message, setMessage] = useState('');
  const [expandedRoots, setExpandedRoots] = useState(() => new Set());
  const commentInputRoot = useRef(null);
  const commentItems = comments;
  const mentionAdapter = useMemo(() => {
    if (source === 'preview') {
      return createPreviewMentionAdapter(commentItems.map((item) => ({
        id: item.id,
        username: item.authorHandle || item.author,
        display_name: item.author,
        avatar: item.authorAvatar || null,
      })));
    }
    return createSdkMentionAdapter(getSdkClient());
  }, [commentItems, source]);
  const mentionTrigger = useMemo(() => mentionAdapter ? createMentionTrigger(mentionAdapter, {
    limit: 8,
    renderItem: renderMentionCandidateItem,
    menuLabel: '提及用户',
    loadingText: '正在查找用户…',
    emptySearchResultsText: '没有找到可提及的用户，仍可按普通文本发布。',
  }) : null, [mentionAdapter]);
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
      setMessage(getUserFacingError(error, { fallback: '评论没有发送，正文已保留，请稍后重试。', validationFallback: '评论内容不符合要求，请修改后重试。' }));
    }
  };

  const handleCommentInputChange = (value) => {
    setDraft(value);
    normalizeMentionCaret(commentInputRoot.current);
  };

  const composer = source === 'preview'
    ? <p className="comment-thread-boundary">设计预览不会发送评论；连接 API 后会显示真实讨论。</p>
    : isAuthenticated && canComment
      ? <div className="comment-thread-composer-wrap">
        {replyTarget && <div className="comment-thread-context"><span>回复 <b>@{replyTarget.author}</b></span><IconButton type="button" label="取消回复" variant="ghost" size="lg" icon={<Icon name="close" size={17} />} onClick={() => setReplyTarget(null)} /></div>}
        <ChatComposer className="comment-chat-composer" value={draft} onChange={setDraft} onSubmit={submit} placeholder={replyTarget ? `回复 @${replyTarget.author}` : '写下你的回复…'} isDisabled={status === 'sending'} density="compact" elevation="none" status={status === 'error' ? { type: 'error', message } : undefined} footerActions={<span className="comment-composer-hint">Enter 发送 · Shift+Enter 换行</span>} sendButton={<ChatSendButton label="发送" isIconOnly={false} sendIcon={null} size="sm" isDisabled={draft.length > 140 || !draft.trim()} />} input={<div className="comment-composer-input-shell"><ChatComposerInput ref={commentInputRoot} label={replyTarget ? `回复 ${replyTarget.author}` : '写下你的回复'} value={draft} onChange={handleCommentInputChange} onKeyDown={(event) => normalizeMentionCaret(event.currentTarget)} triggers={mentionTrigger ? [mentionTrigger] : undefined} debounceMs={180} hasHistory={false} pasteAsToken={false} maxRows={6} /><span className="comment-composer-count" aria-live="polite">{draft.length} / 140</span></div>} />
        {status === 'success' && <span className="comment-thread-success" role="status">{message}</span>}
      </div>
      : <p className="comment-thread-boundary">{isAuthenticated ? '当前账号没有发表评论的权限。' : <>登录后参与讨论。 <button type="button" onClick={onLogin}>去登录</button></>}</p>;

  return (
    <section className="comment-thread" aria-labelledby="comments-title">
      <header className="comment-thread-heading"><div><h2 id="comments-title">讨论</h2><span>{commentItems.length} 条</span></div><span className="comment-thread-sort">置顶优先</span></header>
      {commentsUnavailable ? <p className="comment-thread-boundary" role="status">{commentsUnavailable}</p> : <>
        {Number.isInteger(focusedId) && !focusedComment && <p className="comment-thread-boundary" role="status">这条评论当前不可见，可能已被删除或尚未通过审核。</p>}
        {roots.length > 0 ? <div className="comment-thread-list">{roots.map((item) => <CommentRow key={item.id} item={item} replies={repliesByRoot.get(item.id)} depth={0} source={source} isAuthenticated={isAuthenticated} canComment={isAuthenticated && canComment} canLike={isAuthenticated && canLike} canDeleteComment={isAuthenticated && canDeleteComment} canPinComment={isAuthenticated && canPinComment} pinBusy={pinBusy} viewerId={viewerId} focusedCommentId={focusedId} onReply={setReplyTarget} onLogin={isAuthenticated ? undefined : onLogin} onToggleLike={onToggleLike} likeBusyIds={likeBusyIds} onRequestDelete={onRequestDelete} onRequestPinChange={onRequestPinChange} onCopy={onCopy} isRepliesExpanded={expandedRoots.has(item.id)} onToggleReplies={() => setExpandedRoots((current) => { const next = new Set(current); if (next.has(item.id)) next.delete(item.id); else next.add(item.id); return next; })} />)}</div> : <p className="comment-thread-empty">还没有讨论，成为第一个回复的人。</p>}
      </>}
      {composer}
    </section>
  );
}
