import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getSdkClient } from '../../app/sdk-client.js';
import { ResourceState } from '../../components/ResourceState.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { clearHistory, listHistory, listSavedItems, removeSavedItem } from '../../services/community-service.js';
import { Button, ConfirmActionDialog, useToast } from '../../ui/index.js';
import { ProfileContentTabs } from '../profile/ProfileContentTabs.jsx';

async function hydrateResource(item) {
  const type = String(item.resource_type || 'card');
  const id = String(item.resource_id || '');
  if (type !== 'card' || !id) return { ...item, resource: null, unsupported: true };
  try { return { ...item, resource: await getSdkClient().cards.get(Number(id)), unsupported: false }; }
  catch (error) { return { ...item, resource: null, deleted: error?.status === 404, unavailable: error?.status !== 404 }; }
}

export function LibraryAssetsPage({ section }) {
  const history = section === 'history';
  const auth = useAuth();
  const toast = useToast();
  const [state, setState] = useState({ status: 'loading', items: [], error: null, hasMore: false });
  const [clearOpen, setClearOpen] = useState(false);
  const [clearing, setClearing] = useState(false);
  const [removing, setRemoving] = useState('');
  const load = useCallback(async () => {
    if (!auth.isAuthenticated) return;
    setState((current) => ({ ...current, status: 'loading', error: null }));
    try {
      const page = history ? await listHistory() : await listSavedItems();
      const items = await Promise.all(page.items.map(hydrateResource));
      setState({ status: 'success', items, error: null, hasMore: page.hasMore });
    } catch (error) { setState((current) => ({ ...current, status: 'error', error })); }
  }, [auth.isAuthenticated, history]);
  useEffect(() => { if (auth.status === 'ready' && auth.isAuthenticated) void load(); }, [auth.isAuthenticated, auth.status, load]);
  const clear = async () => {
    if (!history || clearing) return;
    setClearing(true);
    try { await clearHistory(); setState((current) => ({ ...current, items: [], hasMore: false })); setClearOpen(false); toast({ body: '浏览历史已清空。', uniqueID: 'history-cleared' }); }
    catch (error) { setState((current) => ({ ...current, error })); toast({ body: error?.message || '清空失败。', type: 'error', uniqueID: 'history-clear-error' }); }
    finally { setClearing(false); }
  };
  const remove = async (item) => {
    const resourceId = String(item.resource_id || '').trim();
    if (!resourceId || removing || history) return;
    setRemoving(resourceId);
    try {
      await removeSavedItem({ resource_type: String(item.resource_type || 'card'), resource_id: resourceId });
      setState((current) => ({ ...current, items: current.items.filter((entry) => !(String(entry.resource_type || 'card') === String(item.resource_type || 'card') && String(entry.resource_id) === resourceId)) }));
      toast({ body: '已取消收藏。', uniqueID: 'library-removed' });
    } catch (error) { toast({ body: error?.message || '取消收藏失败，请稍后重试。', type: 'error', uniqueID: 'library-remove-error' }); }
    finally { setRemoving(''); }
  };
  return <AppShell><main className="profile-page library-page library-assets-page" aria-labelledby="library-assets-title">
    <Link className="detail-back" to="/me">← 返回个人主页</Link>
    <header className="library-header library-header-row"><div><p className="profile-kicker">个人资产</p><h1 id="library-assets-title">{history ? '浏览历史' : '我的收藏'}</h1><p>{history ? '只在当前账号中保留最近浏览的内容。' : '收藏只对你可见，不会改变原内容。'}</p></div>{history && <Button type="button" label="清空历史" variant="secondary" size="lg" isDisabled={!state.items.length || clearing} onClick={() => setClearOpen(true)} />}</header>
    <ProfileContentTabs />
    {auth.status === 'checking' ? <ResourceState status="loading" /> : !auth.isAuthenticated ? <section className="community-boundary"><h2>登录后查看个人资产</h2><p>收藏和历史不会显示给其他用户。</p><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : <ResourceState status={state.status} error={state.error} onRetry={load}>
      {state.items.length ? <section className="community-list asset-list">{state.items.map((item, index) => { const id = item.resource_id; const content = item.resource?.content || item.resource?.title; return <article className="community-row asset-row" key={item.id || `${item.resource_type}-${id}-${index}`}><div className="asset-thumb" aria-hidden="true">{item.deleted ? '×' : String(item.resource_type || 'C').slice(0, 1).toUpperCase()}</div><div className="community-row-main"><strong>{item.deleted ? '原内容已删除' : content || `${item.resource_type || 'card'} #${id || '未知'}`}</strong><span>{item.deleted ? '这条记录仍保留在你的列表中，但原内容已不可访问。' : item.unavailable ? '原内容暂时无法加载。' : item.unsupported ? '当前主题暂不支持预览这种资源。' : '内容可以正常访问。'}</span><small>{history ? item.viewed_at : item.created_at || '时间未提供'}</small></div>{!item.deleted && item.resource_type === 'card' && id ? <Link className="community-row-link" to={`/post/${id}`}>查看</Link> : <span className="community-unavailable">不可访问</span>}{!history && <Button type="button" label={removing === String(id) ? '取消中' : '取消收藏'} variant="ghost" size="sm" isDisabled={Boolean(removing)} isLoading={removing === String(id)} onClick={() => void remove(item)} />}</article>; })}</section> : <section className="community-boundary"><h2>{history ? '还没有浏览记录' : '还没有收藏内容'}</h2><p>{history ? '打开内容详情后，浏览记录会显示在这里。' : '在内容页收藏后，可以从这里快速找回。'}</p><Link className="action-link" to="/explore">去发现</Link></section>}
      {state.hasMore && <p className="community-capability-note">还有更多记录，但当前 SDK 尚未暴露游标参数，暂时无法安全加载下一页。</p>}
    </ResourceState>}
    <ConfirmActionDialog isOpen={clearOpen} onOpenChange={(open) => { if (!clearing) setClearOpen(open); }} title="清空全部浏览历史？" description="此操作只删除你的历史记录，不会影响收藏、喜欢或原内容。" actionLabel="清空历史" isLoading={clearing} onAction={clear} />
  </main></AppShell>;
}
