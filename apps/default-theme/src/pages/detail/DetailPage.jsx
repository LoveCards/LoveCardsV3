import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { Avatar, Icon } from '../../ui/icons.jsx';
import { useResource } from '../../app/use-resource.js';
import { createDetailComment, getDetailSnapshot, loadDetail } from '../../services/detail-service.js';
import { deleteCard, deleteComment, updateCard, updateComment } from '../../services/content-service.js';
import { useAuth } from '../../app/providers.jsx';
import { loadViewerLikeIds, updateCardLike } from '../../services/feed-service.js';
import { ActionMenu, Button, ConfirmActionDialog, FormDialog, TextArea, ToggleButton, useToast } from '../../ui/index.js';
import { MediaGallery } from '../../features/media/MediaGallery.jsx';
import { CommentThread } from '../../features/comments/CommentThread.jsx';
import { SaveCardButton } from '../../features/library/SaveCardButton.jsx';
import { listSavedItems } from '../../services/community-service.js';

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

function topicSlug(value) {
  return String(value || '').replace(/^#\s*/, '').trim();
}

export function DetailPage() {
  const { id, commentId } = useParams();
  const navigate = useNavigate();
  const toast = useToast();
  const { isAuthenticated, session, can } = useAuth();
  const loader = useCallback((signal) => loadDetail({ id, commentId, signal }), [commentId, id]);
  const initial = useMemo(() => getDetailSnapshot(id), [id]);
  const resource = useResource(loader, initial);
  const data = resource.data || initial;
  const [editing, setEditing] = useState(false);
  const [editContent, setEditContent] = useState('');
  const [editOriginal, setEditOriginal] = useState('');
  const [cardEditBusy, setCardEditBusy] = useState(false);
  const [discardEditOpen, setDiscardEditOpen] = useState(false);
  const [actionMessage, setActionMessage] = useState('');
  const [deleteCardOpen, setDeleteCardOpen] = useState(false);
  const [editCommentItem, setEditCommentItem] = useState(null);
  const [deleteCommentItem, setDeleteCommentItem] = useState(null);
  const [commentDraft, setCommentDraft] = useState('');
  const [dialogBusy, setDialogBusy] = useState(false);
  const [dialogMessage, setDialogMessage] = useState('');
  const [detailLiked, setDetailLiked] = useState(false);
  const [detailLikeCount, setDetailLikeCount] = useState(0);
  const [detailSaved, setDetailSaved] = useState(false);

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
      toast({ body: error?.message || '操作失败，请稍后重试', type: 'error', uniqueID: 'detail-like' });
      throw error;
    }
  };

  const saveCard = async () => {
    if (cardEditBusy || !editContent.trim()) return;
    setCardEditBusy(true);
    setActionMessage('');
    try { await updateCard({ id, content: editContent }); setEditOriginal(editContent); setEditing(false); toast({ body: '内容已更新', uniqueID: 'card-updated' }); await resource.reload(); }
    catch (error) { setActionMessage(error?.message || '更新失败。'); }
    finally { setCardEditBusy(false); }
  };

  const startCardEdit = () => {
    const value = data.card?.body || data.card?.title || '';
    setEditOriginal(value);
    setEditContent(value);
    setActionMessage('');
    setEditing(true);
  };

  const closeCardEdit = () => {
    setDiscardEditOpen(false);
    setEditing(false);
    setEditContent(editOriginal);
    setActionMessage('');
  };

  const cancelCardEdit = () => {
    if (cardEditBusy) return;
    if (editContent !== editOriginal) setDiscardEditOpen(true);
    else closeCardEdit();
  };

  const removeCard = async () => {
    setDialogBusy(true);
    try { await deleteCard(id); setDeleteCardOpen(false); toast({ body: '内容已删除', uniqueID: 'card-deleted' }); navigate('/me', { replace: true }); }
    catch (error) { toast({ body: error?.message || '删除失败。', type: 'error', uniqueID: 'card-delete-error' }); }
    finally { setDialogBusy(false); }
  };

  const saveCommentEdit = async () => {
    if (!editCommentItem || !commentDraft.trim()) return;
    setDialogBusy(true);
    setDialogMessage('');
    try { await updateComment(editCommentItem.id, commentDraft); setEditCommentItem(null); toast({ body: '评论已更新', uniqueID: 'comment-updated' }); await resource.reload(); }
    catch (error) { setDialogMessage(error?.message || '评论更新失败。'); }
    finally { setDialogBusy(false); }
  };

  const removeSelectedComment = async () => {
    if (!deleteCommentItem) return;
    setDialogBusy(true);
    try { await deleteComment(deleteCommentItem.id); setDeleteCommentItem(null); toast({ body: '评论已删除', uniqueID: 'comment-deleted' }); await resource.reload(); }
    catch (error) { toast({ body: error?.message || '评论删除失败。', type: 'error', uniqueID: 'comment-delete-error' }); }
    finally { setDialogBusy(false); }
  };

  const openCommentEditor = (item) => {
    setCommentDraft(item.content);
    setDialogMessage('');
    setEditCommentItem(item);
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

  const ownsCard = data.card?.ownerId === session?.uid;
  const canUpdateCard = data.source === 'live' && ownsCard && can('cards.update');
  const canDeleteCard = data.source === 'live' && ownsCard && can('cards.delete');
  const contentMenuItems = [
    ...(canUpdateCard ? [{ label: '编辑内容', icon: <Icon name="edit" size={17} />, onClick: startCardEdit }] : []),
    ...(canDeleteCard ? [...(canUpdateCard ? [{ type: 'divider' }] : []), { label: '删除内容', icon: <Icon name="trash" size={17} />, onClick: () => setDeleteCardOpen(true) }] : []),
  ];

  let content;
  if (data.source === 'configuration-error') {
    content = <DetailBoundary title="内容详情暂不可用" detail={data.detail} />;
  } else if (data.source !== 'loading' && !data.card) {
    content = <DetailBoundary title="没有找到这条内容" detail="它可能已被删除、移动，或当前预览数据中不存在。" />;
  } else if (data.card) {
    content = (
      <article className="detail-article">
        <header className="detail-author">
          <Avatar label={data.card.author} tone="rose" size="md" />
          <div><b>{data.card.author}</b><small>{data.card.meta || '时间信息暂未提供'}</small></div>
          {data.card.authorHref ? <Link className="detail-capability" to={data.card.authorHref}>查看作者主页</Link> : <span className="detail-capability">作者主页暂未提供</span>}
          {contentMenuItems.length > 0 && <ActionMenu items={contentMenuItems} label="内容操作" />}
        </header>
          <div className="detail-copy">
          {data.card.topic ? <Link className="detail-topic" to={`/topic/${encodeURIComponent(topicSlug(data.card.topic))}`}>{data.card.topic}</Link> : <span className="detail-topic">LoveCards 社区</span>}
          <h1 id="detail-title">{data.card.title}</h1>
          {editing ? <div className="detail-edit"><TextArea label="编辑内容" isLabelHidden value={editContent} onChange={setEditContent} rows={6} isDisabled={cardEditBusy} width="100%" /><div className="detail-edit-actions"><Button label="取消" variant="ghost" size="lg" onClick={cancelCardEdit} isDisabled={cardEditBusy} /><Button label="保存修改" variant="primary" size="lg" clickAction={saveCard} isDisabled={cardEditBusy || !editContent.trim()} isLoading={cardEditBusy} /></div></div> : data.card.body && <p>{data.card.body}</p>}
        </div>
        <MediaGallery items={detailMediaItems(data.card)} title={data.card.title} />
        {data.source === 'preview' && <p className="preview-disclosure">设计预览 · 演示数据 · 操作不会保存</p>}
        <footer className="detail-actions">
          {data.source === 'live' && isAuthenticated && can('likes.create') && can('likes.delete') ? <span className={`detail-like-control${detailLiked ? ' is-active' : ''}`}><ToggleButton label={detailLiked ? '取消喜欢' : '喜欢'} isPressed={detailLiked} icon={<Icon name="heart" size={18} />} pressedIcon={<Icon name="heart" size={18} />} size="lg" pressedChangeAction={toggleDetailLike}>{detailLikeCount}</ToggleButton></span> : data.source === 'live' && !isAuthenticated ? <span className="detail-like-control"><ToggleButton label="登录后喜欢" isPressed={false} icon={<Icon name="heart" size={18} />} size="lg" onPressedChange={(_next, event) => { event.preventDefault(); navigate('/auth/login', { state: { from: `/post/${id}` } }); }}>{detailLikeCount}</ToggleButton></span> : <span className="detail-metric" aria-label={`${detailLikeCount} 个喜欢`}><Icon name="heart" size={18} /> {detailLikeCount}</span>}
          <span className="detail-metric" aria-label={`${data.card.replies} 条回复`}>回复 {data.card.replies}</span>
          <SaveCardButton cardId={data.card.id} source={data.source} isAuthenticated={isAuthenticated} isSaved={detailSaved} onRequireLogin={() => navigate('/auth/login', { state: { from: `/post/${id}` } })} onFeedback={(body, type) => toast({ body, type: type === 'error' ? 'error' : undefined, uniqueID: 'detail-save', collisionBehavior: 'overwrite' })} />
          <Button className="detail-share" type="button" label="分享" variant="ghost" size="lg" clickAction={share} />
        </footer>
        {actionMessage && <p className="form-message" role="status">{actionMessage}</p>}
        <CommentThread
          comments={data.comments}
          focusedCommentId={commentId}
          source={data.source}
           isAuthenticated={isAuthenticated}
           canComment={can('comments.create')}
           viewerId={session?.uid}
           commentsUnavailable={data.commentsError}
           onSubmitComment={submitComment}
           onLogin={() => navigate('/auth/login', { state: { from: `/post/${id}` } })}
          onOpenEdit={openCommentEditor}
          onRequestDelete={setDeleteCommentItem}
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
        <ConfirmActionDialog isOpen={discardEditOpen} onOpenChange={setDiscardEditOpen} title="放弃这次修改？" description="尚未保存的内容修改将不会保留。" actionLabel="放弃修改" onAction={closeCardEdit} />
        <ConfirmActionDialog isOpen={deleteCardOpen} onOpenChange={setDeleteCardOpen} title="删除这条内容？" description="内容和相关讨论将不再显示，此操作无法撤销。" actionLabel="删除内容" isLoading={dialogBusy} onAction={removeCard} />
        <ConfirmActionDialog isOpen={Boolean(deleteCommentItem)} onOpenChange={(open) => { if (!open) setDeleteCommentItem(null); }} title="删除这条评论？" description="评论删除后无法恢复。" actionLabel="删除评论" isLoading={dialogBusy} onAction={removeSelectedComment} />
        <FormDialog
          isOpen={Boolean(editCommentItem)}
          onOpenChange={(open) => { if (!open) { setEditCommentItem(null); setDialogMessage(''); } }}
          title="编辑评论"
          subtitle="修改后会立即更新到讨论中。"
          isBusy={dialogBusy}
          footer={<><Button label="取消" variant="ghost" size="lg" onClick={() => setEditCommentItem(null)} isDisabled={dialogBusy} /><Button label="保存修改" variant="primary" size="lg" clickAction={saveCommentEdit} isDisabled={!commentDraft.trim()} isLoading={dialogBusy} /></>}
        >
          <TextArea label="评论内容" isLabelHidden value={commentDraft} onChange={setCommentDraft} maxLength={1000} rows={5} width="100%" status={dialogMessage ? { type: 'error', message: dialogMessage } : undefined} statusVariant="detached" />
          {dialogMessage && <p className="sr-only" role="alert">{dialogMessage}</p>}
        </FormDialog>
      </main>
    </AppShell>
  );
}
