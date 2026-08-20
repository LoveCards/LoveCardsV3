import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { deleteComposeMedia } from '../../services/compose-service.js';
import { deleteDraft, draftSummary, listDrafts, normalizeDraftPayload } from '../../services/community-service.js';
import { ConfirmActionDialog, Icon, IconButton } from '../../ui/index.js';

export function DraftsPage() {
  const auth = useAuth();
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
  useEffect(() => { if (auth.status === 'ready' && auth.isAuthenticated) void load(); }, [auth.isAuthenticated, auth.status, load]);
  const remove = async () => {
    if (!deleteTarget || deleting) return;
    setDeleting(true);
    setDeleteError('');
    try {
      await deleteDraft(Number(deleteTarget.id));
      await deleteComposeMedia((normalizeDraftPayload(deleteTarget.payload).media || []).map((item) => item.id));
      setState((current) => ({ ...current, items: current.items.filter((item) => Number(item.id) !== Number(deleteTarget.id)) }));
      setDeleteTarget(null);
    } catch (error) {
      setDeleteError(error?.message || '删除失败，请稍后重试。');
      setDeleteTarget(null);
    } finally {
      setDeleting(false);
    }
  };
  return <AppShell><main className="community-page drafts-page" aria-labelledby="drafts-title">
    <Link className="detail-back" to="/compose">← 返回发布</Link>
    <header className="community-page-header drafts-heading"><div><p className="community-context">创作空间</p><h1 id="drafts-title">草稿箱</h1><p>这里是发布页主动保存的未发布内容，恢复后会回到同一套发布编辑器。</p></div></header>
    {auth.status === 'checking' ? <ResourceState status="loading" /> : auth.status === 'error' ? <ResourceState status="error" error={auth.error} onRetry={auth.refresh} /> : !auth.isAuthenticated ? <section className="community-boundary"><h2>登录后使用草稿箱</h2><p>游客会话不会创建或同步私人草稿。</p><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : <ResourceState status={state.status} error={state.error} onRetry={load}>
      {state.items.length ? <div className="drafts-workspace">
         <aside className="drafts-summary" aria-label="草稿同步摘要"><strong>{state.items.length}</strong><span>份未发布内容</span><p><i aria-hidden="true" />云端同步正常</p><small>最近更新 · {state.items[0]?.updated_at || '刚刚'}</small></aside>
        <section className="drafts-list" aria-label="草稿列表">
          <header><div><h2>最近编辑</h2><span>按更新时间排列</span></div>{deleteError && <p role="alert">{deleteError}</p>}</header>
          {state.items.map((item) => <article className="draft-row" key={item.id}>
             <Link className="draft-row-main" to={`/compose?draft=${item.id}`}>
               <span className="draft-thumb" aria-hidden="true">{draftSummary(item).slice(0, 1) || '文'}</span>
               <span className="draft-row-copy"><strong>{draftSummary(item)}</strong><span>{String(item.content || '').trim().slice(0, 90) || '这份草稿没有正文，恢复后继续完成发布。'}</span><small>{normalizeDraftPayload(item.payload).kind === 'gallery' ? '图文 · 图集' : normalizeDraftPayload(item.payload).publishType === 'video' ? '视频' : normalizeDraftPayload(item.payload).publishType === 'image' ? '图文' : '随笔'} · 最近更新 {item.updated_at || '时间未提供'}</small></span>
               <span className="draft-row-action">恢复编辑<Icon name="arrow" size={15} /></span>
            </Link>
             <IconButton className="draft-delete-button" type="button" label={`删除草稿：${draftSummary(item)}`} tooltip="删除草稿" variant="ghost" size="lg" icon={<Icon name="trash" size={17} />} onClick={() => setDeleteTarget(item)} />
          </article>)}
        </section>
      </div> : <section className="community-boundary drafts-empty"><span aria-hidden="true"><Icon name="edit" size={24} /></span><h2>还没有未发布内容</h2><p>在发布页写到一半时，点击“存入草稿箱”，内容才会出现在这里。</p><Link className="action-link action-link-primary" to="/compose">开始发布</Link></section>}
    </ResourceState>}
    <ConfirmActionDialog isOpen={Boolean(deleteTarget)} onOpenChange={(open) => { if (!open && !deleting) setDeleteTarget(null); }} title="删除这份草稿？" description="删除后无法恢复，已发布的内容不会受影响。" actionLabel="删除草稿" isLoading={deleting} onAction={remove} />
  </main></AppShell>;
}
