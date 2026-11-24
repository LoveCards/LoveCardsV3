import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { ResourceErrorBanner, ResourceState } from '../../components/ResourceState.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { deleteComposeMedia, normalizeDraftMedia } from '../../services/compose-service.js';
import { dedupeByStableId, deleteDraft, draftSummary, listDrafts, normalizeDraftPayload } from '../../services/community-service.js';
import { Button, ConfirmActionDialog, Icon, IconButton, ScopedBanner, useToast } from '../../ui/index.js';
import { formatMessageTime as formatDraftTime } from './message-utils.js';

function draftPresentation(item) {
  const payload = normalizeDraftPayload(item?.payload);
  const media = normalizeDraftMedia(payload.media);
  const isVideo = payload.publishType === 'video';
  const isImage = payload.publishType === 'image' || payload.kind === 'gallery';
  const typeLabel = isVideo ? '视频' : isImage ? '图文' : '随笔';
  const firstMedia = media[0] || null;
  const mediaLabel = isImage && media.length ? `${media.length} 张图片` : '';
  return { firstMedia, icon: isVideo ? 'play' : isImage ? 'upload' : 'edit', isImage, isVideo, mediaCount: media.length, mediaLabel, typeLabel };
}

function draftExcerpt(item) {
  const content = String(item?.content || '').trim();
  if (!content) return '';
  const lines = content.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
  if (lines.length > 1) return lines.slice(1).join(' ').slice(0, 96);
  if (lines[0]?.length > 48) return lines[0].slice(48, 144);
  return '';
}

function DraftThumbnail({ item }) {
  const presentation = draftPresentation(item);
  const [imageFailed, setImageFailed] = useState(false);
  const imageUrl = presentation.isImage ? presentation.firstMedia?.url || '' : '';
  useEffect(() => setImageFailed(false), [imageUrl]);
  return <span className={`draft-thumb is-${presentation.isVideo ? 'video' : presentation.isImage ? 'image' : 'text'}`} aria-hidden="true">
    {imageUrl && !imageFailed
      ? <img src={imageUrl} alt="" onError={() => setImageFailed(true)} />
      : presentation.isVideo || presentation.isImage
        ? <Icon name={presentation.icon} size={21} />
        : <span className="draft-thumb-text-lines"><i /><i /><i /></span>}
    {presentation.isImage && presentation.mediaCount > 1 && <span className="draft-thumb-count">{presentation.mediaCount} 张</span>}
  </span>;
}

function DraftsSkeleton() {
  return <section className="drafts-list drafts-list-skeleton" aria-label="草稿列表加载中" aria-busy="true">
    {[0, 1, 2].map((index) => <article className="draft-row" key={index} aria-hidden="true">
      <div className="draft-row-main"><span className="draft-thumb" /><span className="draft-row-copy"><i /><i /><i /></span><span className="draft-row-action" /></div>
      <span className="draft-skeleton-delete" />
    </article>)}
  </section>;
}

function DraftList({ items, onDelete }) {
  return <section className="drafts-list" aria-label="草稿列表">
    {items.map((item) => {
      const presentation = draftPresentation(item);
      const excerpt = draftExcerpt(item);
      return <article className="draft-row" key={item.id}>
        <Link className="draft-row-main" to={`/compose?draft=${item.id}`} aria-label={`继续编辑：${draftSummary(item)}`}>
          <DraftThumbnail item={item} />
          <span className="draft-row-copy"><strong>{draftSummary(item)}</strong>{excerpt && <span>{excerpt}</span>}<small><b>{presentation.typeLabel}</b>{presentation.mediaLabel && <><i aria-hidden="true">·</i>{presentation.mediaLabel}</>}<i aria-hidden="true">·</i>{formatDraftTime(item.updated_at)}</small></span>
          <span className="draft-row-action">继续编辑<Icon name="arrow" size={15} /></span>
        </Link>
        <IconButton className="draft-delete-button" type="button" label={`删除草稿：${draftSummary(item)}`} tooltip="删除草稿" variant="ghost" size="lg" icon={<Icon name="trash" size={17} />} onClick={() => onDelete(item)} />
      </article>;
    })}
  </section>;
}

export function DraftsPage() {
  const auth = useAuth();
  const toast = useToast();
  const [state, setState] = useState({ status: 'loading', items: [], error: null });
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleteError, setDeleteError] = useState('');
  const [deleting, setDeleting] = useState(false);
  const requestRef = useRef(null);
  const viewerUid = auth.session?.uid;

  const load = useCallback(async (cursor = null) => {
    if (!auth.isAuthenticated || requestRef.current) return;
    const request = {};
    requestRef.current = request;
    setDeleteError('');
    setState((current) => cursor
      ? { ...current, loadingMore: true, loadMoreError: null }
      : { status: 'loading', items: [], error: null, viewerUid });
    try {
      const result = await listDrafts({ cursor: cursor ?? undefined });
      if (requestRef.current !== request) return;
      setState((current) => ({
        ...result,
        status: 'success',
        viewerUid,
        items: cursor ? dedupeByStableId([...current.items, ...result.items]) : result.items,
        error: null,
      }));
    } catch (error) {
      if (requestRef.current !== request) return;
      setState((current) => cursor
        ? { ...current, loadingMore: false, loadMoreError: error }
        : { ...current, status: 'error', error });
    } finally {
      if (requestRef.current === request) requestRef.current = null;
    }
  }, [auth.isAuthenticated, viewerUid]);

  useEffect(() => {
    if (auth.status === 'ready' && auth.isAuthenticated) void load();
    return () => { requestRef.current = null; };
  }, [auth.isAuthenticated, auth.status, load]);

  const remove = async () => {
    if (!deleteTarget || deleting) return;
    const target = deleteTarget;
    setDeleting(true);
    setDeleteError('');
    try {
      await deleteDraft(Number(target.id));
      setState((current) => ({ ...current, items: current.items.filter((item) => Number(item.id) !== Number(target.id)) }));
      setDeleteTarget(null);
      await deleteComposeMedia(normalizeDraftMedia(normalizeDraftPayload(target.payload).media).map((item) => item.id));
      toast({ body: '草稿已删除', uniqueID: 'draft-deleted', collisionBehavior: 'overwrite' });
    } catch (error) {
      setDeleteError(getUserFacingError(error, { fallback: '草稿暂时没有删除，列表内容仍然保留，请稍后重试。' }));
      setDeleteTarget(null);
    } finally {
      setDeleting(false);
    }
  };

  let content;
  if (auth.status === 'checking') {
    content = <DraftsSkeleton />;
  } else if (auth.status === 'error') {
    content = <ResourceState status="error" error={auth.error} onRetry={auth.refresh} actionLabel="重新检查登录状态" className="drafts-boundary" />;
  } else if (!auth.isAuthenticated) {
    content = <ResourceState status="empty" className="drafts-boundary" emptyTitle="登录后使用草稿箱" emptyDescription="游客会话不会创建或同步私人草稿。" emptyAction={<Link className="action-link action-link-primary" to="/auth/login">去登录</Link>} />;
  } else if (state.status === 'loading' || state.viewerUid !== viewerUid) {
    content = <DraftsSkeleton />;
  } else if (state.status === 'error') {
    content = <ResourceState status="error" error={state.error} onRetry={() => void load()} actionLabel="重新加载草稿" className="drafts-boundary" />;
  } else if (!state.items.length && !state.hasMore) {
    content = <ResourceState status="empty" className="drafts-boundary" emptyTitle="还没有草稿" emptyDescription="在发布页保存的内容会出现在这里。" />;
  } else {
    content = <>
      {deleteError && <ScopedBanner className="drafts-delete-feedback" title="草稿没有删除" description={deleteError} />}
      {state.items.length > 0 && <DraftList items={state.items} onDelete={setDeleteTarget} />}
      {state.loadMoreError && <ResourceErrorBanner error={state.loadMoreError} onRetry={() => void load(state.nextCursor)} />}
      {state.hasMore && !state.loadMoreError && <div className="drafts-load-more"><Button type="button" label="加载更多草稿" variant="secondary" size="lg" isLoading={state.loadingMore} isDisabled={state.loadingMore} onClick={() => void load(state.nextCursor)} /></div>}
    </>;
  }

  return <AppShell mobileMode="task">
    <main className="community-page drafts-page" aria-labelledby="drafts-title">
      <header className="drafts-page-head">
        <Link className="drafts-back" to="/compose" aria-label="返回发布"><Icon name="arrowLeft" size={18} /><span>返回发布</span></Link>
        <div className="drafts-page-head-copy"><h1 id="drafts-title">草稿箱</h1><p>继续完成未发布的内容。</p></div>
      </header>
      <div className="drafts-content">
        {content}
      </div>
      <ConfirmActionDialog isOpen={Boolean(deleteTarget)} onOpenChange={(open) => { if (!open && !deleting) setDeleteTarget(null); }} title="删除这份草稿？" description="删除后无法恢复，已发布的内容不会受影响。" actionLabel="删除草稿" isLoading={deleting} onAction={remove} />
    </main>
  </AppShell>;
}
