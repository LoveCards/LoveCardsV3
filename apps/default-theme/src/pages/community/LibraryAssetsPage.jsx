import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { ResourceState } from '../../components/ResourceState.jsx';
import { ProfileCardFeed } from '../../components/ProfileCardFeed.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { clearHistory, listHistory, listSavedItems, removeSavedItem } from '../../services/community-service.js';
import { hydrateCardReference } from '../../services/profile-card-service.js';
import { Button, ConfirmActionDialog, useToast } from '../../ui/index.js';
import { ProfileContentTabs } from '../profile/ProfileContentTabs.jsx';

function cardItems(items, history) {
  return items.filter((item) => item.resource).map((item) => ({
    ...item.resource,
    meta: history ? item.viewed_at || item.resource.meta : item.created_at || item.resource.meta,
    libraryRecord: item,
  }));
}

function UnavailableAssets({ items, history }) {
  if (!items.length) return null;
  return <section className="community-list asset-list asset-unavailable-list" aria-label="不可访问的记录">
    {items.map((item, index) => {
      const id = item.resource_id || item.card_id || item.aid || item.ref_id;
      const title = item.deleted ? '原内容已删除' : item.unsupported ? '暂不支持的资源' : '原内容暂时不可用';
      const detail = item.deleted
        ? '这条记录仍保留在你的列表中，但原内容已经不可访问。'
        : item.unsupported
          ? '当前主题只把卡片内容统一呈现为信息流。'
          : '暂时无法读取原内容，可以稍后重试。';
      return <article className="community-row asset-row asset-row-unavailable" key={item.id || `${item.resource_type}-${id}-${index}`}>
        <div className="asset-thumb" aria-hidden="true">×</div>
        <div className="community-row-main"><strong>{title}</strong><span>{detail}</span><small>{history ? item.viewed_at : item.created_at || '时间未提供'}</small></div>
        <span className="community-unavailable">不可访问</span>
      </article>;
    })}
  </section>;
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
      const items = await Promise.all(page.items.map(hydrateCardReference));
      setState({ status: 'success', items, error: null, hasMore: page.hasMore });
    } catch (error) { setState((current) => ({ ...current, status: 'error', error })); }
  }, [auth.isAuthenticated, history]);
  useEffect(() => { if (auth.status === 'ready' && auth.isAuthenticated) void load(); }, [auth.isAuthenticated, auth.status, load]);
  const clear = async () => {
    if (!history || clearing) return;
    setClearing(true);
    try { await clearHistory(); setState((current) => ({ ...current, items: [], hasMore: false })); setClearOpen(false); toast({ body: '浏览历史已清空。', uniqueID: 'history-cleared' }); }
    catch (error) { setState((current) => ({ ...current, error })); toast({ body: getUserFacingError(error, { fallback: '浏览历史暂时没有清空，请稍后重试。' }), type: 'error', uniqueID: 'history-clear-error' }); }
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
    } catch (error) { toast({ body: getUserFacingError(error, { fallback: '暂时无法取消收藏，请稍后重试。' }), type: 'error', uniqueID: 'library-remove-error' }); }
    finally { setRemoving(''); }
  };
  const cards = useMemo(() => cardItems(state.items, history), [history, state.items]);
  const unavailable = useMemo(() => state.items.filter((item) => !item.resource), [state.items]);
  return <AppShell><main className="profile-page library-page library-assets-page" aria-labelledby="library-assets-title">
    <Link className="detail-back" to="/me">← 返回个人主页</Link>
    <header className="library-header library-header-row"><div><p className="profile-kicker">个人资产</p><h1 id="library-assets-title">{history ? '浏览历史' : '我的收藏'}</h1><p>{history ? '只在当前账号中保留最近浏览的内容。' : '收藏只对你可见，不会改变原内容。'}</p></div>{history && <Button type="button" label="清空历史" variant="secondary" size="lg" isDisabled={!state.items.length || clearing} onClick={() => setClearOpen(true)} />}</header>
    <ProfileContentTabs />
    {auth.status === 'checking' ? <ResourceState status="loading" /> : auth.status === 'error' ? <ResourceState status="error" error={auth.error} onRetry={auth.refresh} /> : !auth.isAuthenticated ? <ResourceState status="empty" emptyTitle="登录后查看个人资产" emptyDescription="收藏和历史不会显示给其他用户。" emptyAction={<Link className="action-link action-link-primary" to="/auth/login">去登录</Link>} /> : <ResourceState status={state.status} error={state.error} onRetry={load}>
      {state.items.length ? <>
        {cards.length > 0 && <ProfileCardFeed items={cards} ariaLabel={history ? '浏览历史内容' : '收藏内容'} renderAction={(card) => {
          const item = card.libraryRecord;
          if (history || !item) return null;
          const id = String(item.resource_id || '');
          return <Button type="button" label={removing === id ? '取消中' : '取消收藏'} variant="ghost" size="sm" isDisabled={Boolean(removing)} isLoading={removing === id} onClick={() => void remove(item)} />;
        }} />}
        <UnavailableAssets items={unavailable} history={history} />
      </> : <ResourceState status="empty" emptyTitle={history ? '还没有浏览记录' : '还没有收藏内容'} emptyDescription={history ? '打开内容详情后，浏览记录会显示在这里。' : '在内容页收藏后，可以从这里快速找回。'} emptyAction={<Link className="action-link" to="/explore">去发现</Link>} />}
      {state.hasMore && <p className="community-capability-note">还有更多记录，但当前 SDK 尚未暴露游标参数，暂时无法安全加载下一页。</p>}
    </ResourceState>}
    <ConfirmActionDialog isOpen={clearOpen} onOpenChange={(open) => { if (!clearing) setClearOpen(open); }} title="清空全部浏览历史？" description="此操作只删除你的历史记录，不会影响收藏、喜欢或原内容。" actionLabel="清空历史" isLoading={clearing} onAction={clear} />
  </main></AppShell>;
}
