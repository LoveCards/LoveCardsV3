import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { ResourceState } from '../../components/ResourceState.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { deleteComposeMedia, normalizeDraftMedia } from '../../services/compose-service.js';
import { deleteDraft, draftSummary, listDrafts, normalizeDraftPayload } from '../../services/community-service.js';
import { ConfirmActionDialog, Icon, IconButton, ScopedBanner, useToast } from '../../ui/index.js';
import { formatMessageTime as formatDraftTime } from './message-utils.js';

function draftPresentation(item) {
  const payload = normalizeDraftPayload(item?.payload);
  const media = normalizeDraftMedia(payload.media);
  const isVideo = payload.publishType === 'video';
  const isImage = payload.publishType === 'image' || payload.kind === 'gallery';
  const typeLabel = isVideo ? '视频' : isImage ? '图文' : '随笔';
  const firstMedia = media[0] || null;
  const mediaLabel = isImage && media.length ? `${media.length} 张图片` : '';
  return { firstMedia, icon: isVideo ? 'play' : isImage ? 'upload' : 'edit', isImage, isVideo, mediaLabel, typeLabel };
}

function draftExcerpt(item) {
  const content = String(item?.content || '').trim();
  if (!content) return '这份草稿还没有正文，恢复后继续完成发布。';
  const lines = content.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
  if (lines.length > 1) return lines.slice(1).join(' ').slice(0, 96);
  if (lines[0]?.length > 48) return lines[0].slice(48, 144);
  return '恢复后继续补充内容并完成发布。';
}

function DraftThumbnail({ item }) {
  const presentation = draftPresentation(item);
  const [imageFailed, setImageFailed] = useState(false);
  const imageUrl = presentation.isImage ? presentation.firstMedia?.url || '' : '';
  useEffect(() => setImageFailed(false), [imageUrl]);
  return <span className={`draft-thumb is-${presentation.isVideo ? 'video' : presentation.isImage ? 'image' : 'text'}`} aria-hidden="true">
    {imageUrl && !imageFailed
      ? <img src={imageUrl} alt="" onError={() => setImageFailed(true)} />
      : <Icon name={presentation.icon} size={19} />}
  </span>;
}

function DraftsSkeleton() {
  return <section className="drafts-list drafts-list-skeleton" aria-label="草稿列表加载中" aria-busy="true">
    <header><span /><i /></header>
    {[0, 1, 2].map((index) => <article className="draft-row" key={index} aria-hidden="true">
      <div className="draft-row-main"><span className="draft-thumb" /><span className="draft-row-copy"><i /><i /><i /></span><span className="draft-row-action" /></div>
      <span className="draft-skeleton-delete" />
    </article>)}
  </section>;
}

function DraftList({ items, onDelete }) {
  return <section className="drafts-list" aria-label="草稿列表">
    <header><h2>最近编辑</h2><span>按更新时间排列</span></header>
    {items.map((item) => {
      const presentation = draftPresentation(item);
      const metadata = [presentation.typeLabel, presentation.mediaLabel, formatDraftTime(item.updated_at)].filter(Boolean).join(' · ');
      return <article className="draft-row" key={item.id}>
        <Link className="draft-row-main" to={`/compose?draft=${item.id}`} aria-label={`继续编辑：${draftSummary(item)}`}>
          <DraftThumbnail item={item} />
          <span className="draft-row-copy"><strong>{draftSummary(item)}</strong><span>{draftExcerpt(item)}</span><small>{metadata}</small></span>
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

  const load = useCallback(async () => {
    if (!auth.isAuthenticated) return;
    setDeleteError('');
    setState((current) => ({ ...current, status: 'loading', error: null }));
    try {
      const result = await listDrafts();
      setState({ status: 'success', items: result.items, error: null });
    } catch (error) {
      setState((current) => ({ ...current, status: 'error', error }));
    }
  }, [auth.isAuthenticated]);

  useEffect(() => {
    if (auth.status === 'ready' && auth.isAuthenticated) void load();
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
    content = <ResourceState status="empty" className="drafts-boundary drafts-empty" emptyTitle="登录后使用草稿箱" emptyDescription="游客会话不会创建或同步私人草稿。" emptyAction={<Link className="action-link action-link-primary" to="/auth/login">去登录</Link>} />;
  } else if (state.status === 'loading') {
    content = <DraftsSkeleton />;
  } else if (state.status === 'error') {
    content = <ResourceState status="error" error={state.error} onRetry={load} actionLabel="重新加载草稿" className="drafts-boundary" />;
  } else if (!state.items.length) {
    content = <ResourceState status="empty" className="drafts-boundary drafts-empty" emptyTitle="还没有草稿" emptyDescription="只有在发布页主动保存的内容才会出现在这里。" emptyAction={<Link className="action-link action-link-primary" to="/compose">发布新内容</Link>} />;
  } else {
    content = <>{deleteError && <ScopedBanner className="drafts-delete-feedback" title="草稿没有删除" description={deleteError} />}<DraftList items={state.items} onDelete={setDeleteTarget} /></>;
  }

  return <AppShell mobileMode="task">
    <main className="community-page drafts-page" aria-labelledby="drafts-title">
      <header className="drafts-page-head">
        <Link className="drafts-back" to="/compose"><span aria-hidden="true">←</span><span>返回发布</span></Link>
        <div className="drafts-page-head-copy"><h1 id="drafts-title">草稿箱</h1><p>选择一份草稿，继续在发布页完成内容。</p></div>
        {auth.isAuthenticated && <Link className="drafts-new" to="/compose"><span className="drafts-new-desktop">发布新内容</span><span className="drafts-new-mobile">发布</span></Link>}
      </header>
      <div className="drafts-content">{content}</div>
      <ConfirmActionDialog isOpen={Boolean(deleteTarget)} onOpenChange={(open) => { if (!open && !deleting) setDeleteTarget(null); }} title="删除这份草稿？" description="删除后无法恢复，已发布的内容不会受影响。" actionLabel="删除草稿" isLoading={deleting} onAction={remove} />
    </main>
  </AppShell>;
}
