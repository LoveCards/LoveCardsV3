import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { AppShell } from '../../layouts/AppShell.jsx';
import { unblockUser } from '../../services/community-service.js';
import { listBlockedUsers } from '../../services/settings-service.js';
import { Avatar, Button, TextInput, useToast } from '../../ui/index.js';
import { ScopedBanner } from '../../ui/interaction.jsx';
import { SettingsGroup, SettingsLayout, SettingsRowsSkeleton } from './SettingsLayout.jsx';

function blockedDate(value) {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(date);
}

export function BlockedUsersPage() {
  const auth = useAuth();
  const toast = useToast();
  const [query, setQuery] = useState('');
  const [activeQuery, setActiveQuery] = useState('');
  const [busy, setBusy] = useState('');
  const [state, setState] = useState({ status: 'loading', data: [], nextCursor: null, hasMore: false, error: null, loadMoreError: null });
  const load = useCallback(async ({ cursor, search = activeQuery } = {}) => {
    if (!auth.isAuthenticated) return;
    setState((current) => ({ ...current, status: cursor ? current.status : 'loading', error: null, loadMoreError: null }));
    try {
      const page = await listBlockedUsers({ cursor, limit: 20, query: search });
      setState((current) => ({ status: 'success', data: cursor ? [...current.data, ...page.data] : page.data, nextCursor: page.next_cursor, hasMore: page.has_more, error: null, loadMoreError: null }));
    } catch (error) {
      setState((current) => cursor ? { ...current, loadMoreError: error } : { ...current, status: 'error', error });
    }
  }, [activeQuery, auth.isAuthenticated]);
  useEffect(() => {
    if (auth.status !== 'ready' || !auth.isAuthenticated) return;
    let active = true;
    setState((current) => ({ ...current, status: 'loading', error: null }));
    listBlockedUsers({ limit: 20 }).then((page) => { if (active) setState({ status: 'success', data: page.data, nextCursor: page.next_cursor, hasMore: page.has_more, error: null, loadMoreError: null }); }).catch((error) => { if (active) setState((current) => ({ ...current, status: 'error', error })); });
    return () => { active = false; };
  }, [auth.status, auth.isAuthenticated]);

  const submitSearch = (event) => {
    event.preventDefault();
    const search = query.trim();
    setActiveQuery(search);
    void load({ search });
  };
  const remove = async (item) => {
    const id = String(item.user.id);
    setBusy(id);
    try {
      await unblockUser(item.user.id);
      setState((current) => ({ ...current, data: current.data.filter((row) => row.user.id !== item.user.id) }));
      toast({ body: `已解除对 ${item.user.username} 的拉黑。`, uniqueID: `unblocked-${id}` });
    } catch (error) {
      setState((current) => ({ ...current, error }));
    } finally {
      setBusy('');
    }
  };

  return <AppShell mobileMode="task"><SettingsLayout activeKey="blocked" description="管理被你拉黑的用户和关系限制。"><SettingsGroup title="黑名单" description="拉黑后，双方无法关注、私信或通过提及互动。解除后不会自动恢复原有关注关系。">
    {auth.status === 'checking' ? <SettingsRowsSkeleton rows={3} label="黑名单加载中" /> : !auth.isAuthenticated ? <div className="settings-login-note" role="status"><div><h3>登录后管理黑名单</h3><p>登录后可以搜索和解除已拉黑的用户。</p></div><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></div> : <>
      <form className="blocked-users-search" role="search" onSubmit={submitSearch}><TextInput label="搜索黑名单" hideLabel placeholder="输入用户名" value={query} onChange={setQuery} width="100%" /><Button type="submit" label="搜索" variant="secondary" size="lg" /></form>
      {state.error && state.status === 'success' && <ScopedBanner title="操作没有完成" description={getUserFacingError(state.error, { fallback: '请稍后重试。' })} />}
      {state.status === 'loading' ? <SettingsRowsSkeleton rows={3} label="黑名单加载中" /> : state.status === 'error' ? <ScopedBanner title="黑名单暂时无法加载" description={getUserFacingError(state.error, { fallback: '请稍后重试。' })} actionLabel="重试" onAction={() => void load()} /> : !state.data.length ? <div className="blocked-users-empty" role="status"><h3>{activeQuery ? '没有匹配的用户' : '黑名单是空的'}</h3><p>{activeQuery ? '请检查用户名，或清空搜索条件后重试。' : '你拉黑的用户会显示在这里。'}</p>{activeQuery && <Button type="button" label="清空搜索" variant="secondary" onClick={() => { setQuery(''); setActiveQuery(''); void load({ search: '' }); }} />}</div> : <div className="blocked-users-list" role="list" aria-label="已拉黑用户">{state.data.map((item) => <article className="blocked-users-row" role="listitem" key={item.user.id}><Avatar label={item.user.username} src={item.user.avatar} tone="rose" size="md" /><div><strong>{item.user.username}</strong>{item.blocked_at && <span>拉黑于 {blockedDate(item.blocked_at)}</span>}</div><Button type="button" label="解除拉黑" variant="secondary" size="sm" isDisabled={Boolean(busy)} isLoading={busy === String(item.user.id)} onClick={() => void remove(item)} /></article>)}</div>}
      {state.loadMoreError && <ScopedBanner title="更多用户没有加载" description={getUserFacingError(state.loadMoreError, { fallback: '请稍后重试。' })} actionLabel="重试" onAction={() => void load({ cursor: state.nextCursor })} />}
      {state.status === 'success' && state.hasMore && <div className="blocked-users-more"><Button type="button" label="加载更多" variant="secondary" size="lg" onClick={() => void load({ cursor: state.nextCursor })} /></div>}
    </>}
  </SettingsGroup></SettingsLayout></AppShell>;
}
