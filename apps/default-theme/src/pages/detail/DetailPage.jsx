import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { Icon } from '../../ui/icons.jsx';
import { AuthorIdentity } from '../../components/AuthorIdentity.jsx';
import { MentionText } from '../../components/MentionText.jsx';
import { useResource } from '../../app/use-resource.js';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { createDetailComment, getDetailSnapshot, loadDetail, supportsUserCommentPinning, updateDetailCommentLike, updatePinnedComment } from '../../services/detail-service.js';
import { deleteCard, deleteComment } from '../../services/content-service.js';
import { useAuth } from '../../app/providers.jsx';
import { loadViewerLikeIds, updateCardLike } from '../../services/feed-service.js';
import { ActionMenu, AuthPromptDialog, Button, ConfirmActionDialog, ToggleButton, useToast } from '../../ui/index.js';
import { MediaGallery } from '../../features/media/MediaGallery.jsx';
import { CommentThread } from '../../features/comments/CommentThread.jsx';
import { SaveCardButton } from '../../features/library/SaveCardButton.jsx';
import { listSavedItems } from '../../services/community-service.js';
import { topicHref } from '../../services/discovery-service.js';

function DetailBoundary({ title, detail }) {
  return (
    <section className="detail-boundary" role="status">
      <h1 id="detail-title">{title}</h1>
      <p>{detail}</p>
    </section>
  );
}

function detailMediaItems(card) {
  if (Array.isArray(card?.mediaItems) && card.mediaItems.length) return card.mediaItems;
  return card?.mediaUrl ? [{ url: card.mediaUrl, alt: card.mediaAlt || card.title, fit: card.mediaFit || 'smart' }] : [];
}

function detailTopics(card) {
  const source = Array.isArray(card?.topics) && card.topics.length
    ? card.topics
    : card?.topic
      ? [{ id: card.topicId, name: card.topic }]
      : [];
  const seen = new Set();
  return source.map((topic) => {
    const name = String(topic?.name || topic?.title || '').trim().replace(/^#\s*/, '').trim();
    const id = Number(topic?.id);
    const key = Number.isInteger(id) && id > 0 ? `id:${id}` : `name:${name.toLowerCase()}`;
    if (!name || seen.has(key)) return null;
    seen.add(key);
    return { id: Number.isInteger(id) && id > 0 ? id : null, name };
  }).filter(Boolean);
}

export function DetailPage() {
  const { id, commentId } = useParams();
  const location = useLocation();
  const navigate = useNavigate();
  const toast = useToast();
  const { isAuthenticated, session, can } = useAuth();
  const loader = useCallback((signal) => loadDetail({ id, commentId, signal }), [commentId, id]);
  const initial = useMemo(() => getDetailSnapshot(id), [id]);
  const resource = useResource(loader, initial);
  const data = resource.data || initial;
  const [deleteCardOpen, setDeleteCardOpen] = useState(false);
  const [deleteCommentItem, setDeleteCommentItem] = useState(null);
  const [dialogBusy, setDialogBusy] = useState(false);
  const [detailLiked, setDetailLiked] = useState(false);
  const [detailLikeCount, setDetailLikeCount] = useState(0);
  const [detailSaved, setDetailSaved] = useState(false);
  const [likedCommentIds, setLikedCommentIds] = useState(() => new Set());
  const [commentLikeCounts, setCommentLikeCounts] = useState(() => new Map());
  const [commentLikeBusyIds, setCommentLikeBusyIds] = useState(() => new Set());
  const [commentPinBusy, setCommentPinBusy] = useState(false);
  const [replacePinCommentItem, setReplacePinCommentItem] = useState(null);
  const [authPromptAction, setAuthPromptAction] = useState('');
  const requestLogin = (action) => setAuthPromptAction(action);
  const loginFrom = `${location.pathname}${location.search}${location.hash}`;

  useEffect(() => {
    let active = true;
    setDetailLikeCount(data.card?.likes || 0);
    setDetailLiked(false);
    if (data.source === 'live' && isAuthenticated && can('likes.read') && data.card?.id) {
      loadViewerLikeIds().then((ids) => { if (active) setDetailLiked(ids.has(Number(data.card.id))); }).catch(() => undefined);
    }
    return () => { active = false; };
  }, [can, data.card?.id, data.card?.likes, data.source, isAuthenticated]);
  useEffect(() => {
    let active = true;
    setDetailSaved(false);
    if (data.source === 'live' && isAuthenticated && data.card?.id) {
      listSavedItems().then((page) => {
        if (active) setDetailSaved(page.items.some((item) => String(item.resource_type || 'card') === 'card' && Number(item.resource_id) === Number(data.card.id)));
      }).catch(() => undefined);
    }
    return () => { active = false; };
  }, [data.card?.id, data.source, isAuthenticated]);
  useEffect(() => {
    let active = true;
    setLikedCommentIds(new Set());
    setCommentLikeCounts(new Map());
    if (data.source === 'live' && isAuthenticated && can('likes.read')) {
      loadViewerLikeIds('comment').then((ids) => { if (active) setLikedCommentIds(ids); }).catch(() => undefined);
    }
    return () => { active = false; };
  }, [can, data.card?.id, data.source, isAuthenticated]);

  const share = async () => {
    try {
      if (navigator.share) {
        await navigator.share({ title: data.card.title, url: window.location.href });
        toast({ body: '已打开系统分享', uniqueID: 'detail-share' });
      } else {
        await navigator.clipboard.writeText(window.location.href);
        toast({ body: '链接已复制', uniqueID: 'detail-share' });
      }
    } catch (error) {
      if (error?.name !== 'AbortError') toast({ body: '暂时无法分享，请稍后重试', type: 'error', uniqueID: 'detail-share' });
    }
  };

  const toggleDetailLike = async (next) => {
    try {
      const result = await updateCardLike(data.card.id, next);
      setDetailLiked(next);
      setDetailLikeCount((current) => next && Number.isFinite(result?.likes) ? result.likes : Math.max(0, current + (next ? 1 : -1)));
      toast({ body: next ? '已喜欢' : '已取消喜欢', uniqueID: 'detail-like', collisionBehavior: 'overwrite' });
    } catch (error) {
      toast({ body: getUserFacingError(error, { fallback: next ? '暂时无法喜欢这条内容，请稍后重试。' : '暂时无法取消喜欢，请稍后重试。' }), type: 'error', uniqueID: 'detail-like' });
      throw error;
    }
  };

  const removeCard = async () => {
    setDialogBusy(true);
    try { await deleteCard(id); setDeleteCardOpen(false); toast({ body: '内容已删除', uniqueID: 'card-deleted' }); navigate('/me', { replace: true }); }
    catch (error) { toast({ body: getUserFacingError(error, { fallback: '内容暂时没有删除，请稍后重试。' }), type: 'error', uniqueID: 'card-delete-error' }); }
    finally { setDialogBusy(false); }
  };

  const removeSelectedComment = async () => {
    if (!deleteCommentItem) return;
    setDialogBusy(true);
    try { await deleteComment(deleteCommentItem.id); setDeleteCommentItem(null); toast({ body: '评论已删除', uniqueID: 'comment-deleted' }); await resource.reload(); }
    catch (error) { toast({ body: getUserFacingError(error, { fallback: '评论暂时没有删除，请稍后重试。' }), type: 'error', uniqueID: 'comment-delete-error' }); }
    finally { setDialogBusy(false); }
  };

  const copyComment = async (content) => {
    try {
      await navigator.clipboard.writeText(content);
      toast({ body: '评论已复制', uniqueID: 'comment-copied' });
    } catch {
      toast({ body: '复制失败，请稍后重试', type: 'error', uniqueID: 'comment-copied' });
    }
  };

  const submitComment = async (content, parentId) => {
    await createDetailComment(id, content, parentId);
    toast({ body: parentId ? '回复已发布' : '评论已发布', uniqueID: 'comment-created' });
    await resource.reload();
  };

  const toggleCommentLike = async (comment, liked) => {
    if (commentLikeBusyIds.has(comment.id)) return;
    setCommentLikeBusyIds((current) => new Set(current).add(comment.id));
    try {
      const result = await updateDetailCommentLike(comment.id, liked);
      setLikedCommentIds((current) => {
        const next = new Set(current);
        if (liked) next.add(comment.id); else next.delete(comment.id);
        return next;
      });
      setCommentLikeCounts((current) => {
        const next = new Map(current);
        next.set(comment.id, liked && Number.isFinite(result?.likes) ? result.likes : Math.max(0, Number(comment.likes || 0) + (liked ? 1 : -1)));
        return next;
      });
      toast({ body: liked ? '已喜欢评论' : '已取消喜欢', uniqueID: `comment-like-${comment.id}`, collisionBehavior: 'overwrite' });
    } catch (error) {
      toast({ body: getUserFacingError(error, { fallback: liked ? '暂时无法喜欢这条评论，请稍后重试。' : '暂时无法取消喜欢，请稍后重试。' }), type: 'error', uniqueID: `comment-like-${comment.id}` });
    } finally {
      setCommentLikeBusyIds((current) => { const next = new Set(current); next.delete(comment.id); return next; });
    }
  };

  const changePinnedComment = async (comment, pinned) => {
    if (commentPinBusy) return;
    setCommentPinBusy(true);
    try {
      await updatePinnedComment(data.card.id, comment.id, pinned);
      await resource.reload();
      setReplacePinCommentItem(null);
      toast({ body: pinned ? '评论已置顶' : '已取消置顶', uniqueID: 'comment-pin', collisionBehavior: 'overwrite' });
    } catch (error) {
      toast({ body: getUserFacingError(error, { fallback: pinned ? '评论暂时无法置顶，请稍后重试。' : '暂时无法取消置顶，请稍后重试。' }), type: 'error', uniqueID: 'comment-pin' });
    } finally {
      setCommentPinBusy(false);
    }
  };

  const requestPinnedCommentChange = (comment, pinned) => {
    const currentPinned = data.comments.find((item) => item.isPinned && !item.parentId);
    if (pinned && currentPinned && currentPinned.id !== comment.id) {
      setReplacePinCommentItem(comment);
      return;
    }
    void changePinnedComment(comment, pinned);
  };

  const ownsCard = Boolean(data.card?.ownerUid ? data.card.ownerUid === session?.uid : Number(data.card?.ownerId) === Number(session?.uid));
  const canUpdateCard = data.source === 'live' && ownsCard && can('cards.update');
  const canDeleteCard = data.source === 'live' && ownsCard && can('cards.delete');
  const canPinComment = data.source === 'live' && isAuthenticated && ownsCard && can('comments.pin') && supportsUserCommentPinning();
  const contentMenuItems = [
    ...(canUpdateCard ? [{ label: '编辑内容', icon: <Icon name="edit" size={17} />, onClick: () => navigate(`/compose?edit=${data.card.id}`) }] : []),
    ...(canDeleteCard ? [...(canUpdateCard ? [{ type: 'divider' }] : []), { label: '删除内容', icon: <Icon name="trash" size={17} />, onClick: () => setDeleteCardOpen(true) }] : []),
  ];
  const comments = useMemo(() => data.comments.map((comment) => ({
    ...comment,
    likes: commentLikeCounts.get(comment.id) ?? comment.likes,
    viewerLiked: likedCommentIds.has(comment.id),
  })), [commentLikeCounts, data.comments, likedCommentIds]);
  const topics = useMemo(() => detailTopics(data.card), [data.card]);

  let content;
  if (data.source === 'configuration-error') {
    content = <DetailBoundary title="内容详情暂不可用" detail={data.detail} />;
  } else if (data.source !== 'loading' && !data.card) {
    content = <DetailBoundary title="没有找到这条内容" detail="它可能已被删除、移动，或当前预览数据中不存在。" />;
  } else if (data.card) {
    content = (
      <article className="detail-article">
        <header className="detail-author">
          <AuthorIdentity author={data.card.author} uid={data.card.authorUid} handle={data.card.authorHandle} avatarUrl={data.card.authorAvatar} meta={data.card.meta || '时间信息暂未提供'} size="md" variant="byline" className="detail-author-identity" />
          {!data.card.authorUid && <span className="detail-capability">作者主页暂未提供</span>}
          {contentMenuItems.length > 0 && <ActionMenu items={contentMenuItems} label="内容操作" />}
        </header>
          <div className="detail-copy">
          {topics.length > 0 ? <nav className="detail-topics" aria-label="内容话题">{topics.map((topic) => <Link className="detail-topic" key={topic.id || topic.name} to={topicHref(topic)}>#{topic.name}</Link>)}</nav> : <span className="detail-topic">LoveCards 社区</span>}
          <h1 id="detail-title" className="sr-only">内容详情</h1>
          <p className="detail-content"><MentionText text={data.card.content || data.card.title} mentions={data.card.mentions} /></p>
        </div>
        <MediaGallery items={detailMediaItems(data.card)} title={data.card.title} />
        {data.source === 'preview' && <p className="preview-disclosure">设计预览 · 演示数据 · 操作不会保存</p>}
        <footer className="detail-actions">
          {data.source === 'live' && isAuthenticated && can('likes.create') && can('likes.delete') ? <span className={`detail-like-control${detailLiked ? ' is-active' : ''}`}><ToggleButton label={detailLiked ? '取消喜欢' : '喜欢'} isPressed={detailLiked} icon={<Icon name="heart" size={18} />} pressedIcon={<Icon name="heart" size={18} />} size="lg" pressedChangeAction={toggleDetailLike}>{detailLikeCount}</ToggleButton></span> : data.source === 'live' && !isAuthenticated ? <span className="detail-like-control"><ToggleButton label="登录后喜欢" isPressed={false} icon={<Icon name="heart" size={18} />} size="lg" onPressedChange={(_next, event) => { event.preventDefault(); requestLogin('喜欢内容'); }}>{detailLikeCount}</ToggleButton></span> : <span className="detail-metric" aria-label={`${detailLikeCount} 个喜欢`}><Icon name="heart" size={18} /> {detailLikeCount}</span>}
          <span className="detail-metric" aria-label={`${data.card.replies} 条回复`}>回复 {data.card.replies}</span>
          <SaveCardButton cardId={data.card.id} source={data.source} isAuthenticated={isAuthenticated} isSaved={detailSaved} onRequireLogin={() => requestLogin('收藏内容')} onFeedback={(body, type) => toast({ body, type: type === 'error' ? 'error' : undefined, uniqueID: 'detail-save', collisionBehavior: 'overwrite' })} />
          <Button className="detail-share" type="button" label="分享" variant="ghost" size="lg" clickAction={share} />
        </footer>
        <CommentThread
          comments={comments}
          focusedCommentId={commentId}
          source={data.source}
           isAuthenticated={isAuthenticated}
           canComment={can('comments.create')}
           canLike={can('likes.create') && can('likes.delete')}
           canDeleteComment={can('comments.delete')}
           canPinComment={canPinComment}
           pinBusy={commentPinBusy}
           viewerId={session?.uid}
           commentsUnavailable={data.commentsError}
           onRetryComments={resource.reload}
           onSubmitComment={submitComment}
           onLogin={() => requestLogin('参与评论和回复')}
          onToggleLike={toggleCommentLike}
          likeBusyIds={commentLikeBusyIds}
          onRequestDelete={setDeleteCommentItem}
          onRequestPinChange={requestPinnedCommentChange}
          onCopy={(content) => { void copyComment(content); }}
        />
      </article>
    );
  }

  return (
    <AppShell>
      <main className="detail-page" aria-labelledby="detail-title">
        <Link className="detail-back" to="/">← 回到信息流</Link>
        <ResourceState status={resource.status} error={resource.error} onRetry={resource.reload}>{content}</ResourceState>
        <ConfirmActionDialog isOpen={deleteCardOpen} onOpenChange={setDeleteCardOpen} title="删除这条内容？" description="内容和相关讨论将不再显示，此操作无法撤销。" actionLabel="删除内容" isLoading={dialogBusy} onAction={removeCard} />
        <ConfirmActionDialog isOpen={Boolean(deleteCommentItem)} onOpenChange={(open) => { if (!open) setDeleteCommentItem(null); }} title="删除这条评论？" description="评论删除后无法恢复。" actionLabel="删除评论" isLoading={dialogBusy} onAction={removeSelectedComment} />
        <ConfirmActionDialog isOpen={Boolean(replacePinCommentItem)} onOpenChange={(open) => { if (!open && !commentPinBusy) setReplacePinCommentItem(null); }} title="替换置顶评论？" description="当前置顶评论将取消置顶，并把这条评论放到讨论顶部。" actionLabel="替换置顶" isLoading={commentPinBusy} onAction={() => { if (replacePinCommentItem) void changePinnedComment(replacePinCommentItem, true); }} />
        <AuthPromptDialog isOpen={Boolean(authPromptAction)} action={authPromptAction} onOpenChange={(open) => { if (!open) setAuthPromptAction(''); }} onLogin={() => { setAuthPromptAction(''); navigate('/auth/login', { state: { from: loginFrom } }); }} />
      </main>
    </AppShell>
  );
}
