import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { ResourceErrorBanner } from '../../components/ResourceState.jsx';
import { useUnsavedChanges } from '../../app/use-unsaved-changes.js';
import { AppShell } from '../../layouts/AppShell.jsx';
import { getPrivacyPreferences, unblockUser, updatePrivacyPreferences } from '../../services/community-service.js';
import { Button, ConfirmActionDialog, Selector, Switch, TextInput, useToast } from '../../ui/index.js';
import { ScopedBanner } from '../../ui/interaction.jsx';
import { SettingsGroup, SettingsLayout, SettingsRowsSkeleton } from '../settings/SettingsLayout.jsx';
import { listBlockedUsers } from '../../services/settings-service.js';

const defaults = { profile_visibility: 'public', messages_from: 'everyone', show_relations: true, gender_visibility: 'private', birth_date_visibility: 'private' };

export function PrivacyPage() {
  const auth = useAuth();
  const navigate = useNavigate();
  const toast = useToast();
  const [state, setState] = useState({ status: 'loading', value: defaults, initial: defaults, error: null });
  const [saving, setSaving] = useState(false);
  const [discardDestination, setDiscardDestination] = useState('');
  const [blocked, setBlocked] = useState({ status: 'loading', data: [], next_cursor: null, has_more: false, error: null });
  const [query, setQuery] = useState('');
  const [blockedBusy, setBlockedBusy] = useState('');
  const dirty = useMemo(() => JSON.stringify(state.value) !== JSON.stringify(state.initial), [state.initial, state.value]);
  const load = useCallback(async () => {
    if (!auth.isAuthenticated) return;
    setState((current) => ({ ...current, status: 'loading', error: null }));
    try { const result = await getPrivacyPreferences(); const value = { profile_visibility: result?.profile_visibility || 'public', messages_from: result?.messages_from || 'everyone', show_relations: result?.show_relations !== false, gender_visibility: result?.gender_visibility || 'private', birth_date_visibility: result?.birth_date_visibility || 'private' }; setState({ status: 'success', value, initial: value, error: null }); }
    catch (error) { setState((current) => ({ ...current, status: 'error', error })); }
  }, [auth.isAuthenticated]);
  useEffect(() => { if (auth.status === 'ready' && auth.isAuthenticated) void load(); }, [auth.isAuthenticated, auth.status, load]);
  const loadBlocked = useCallback(async (cursor = undefined, search = query) => {
    setBlocked((current) => ({ ...current, status: cursor ? current.status : 'loading', error: null }));
    try { const page = await listBlockedUsers({ cursor, query: search }); setBlocked((current) => ({ status: 'success', data: cursor ? [...current.data, ...page.data] : page.data, next_cursor: page.next_cursor, has_more: page.has_more, error: null })); }
    catch (error) { setBlocked((current) => ({ ...current, status: 'error', error })); }
  }, [query]);
  useUnsavedChanges(dirty, setDiscardDestination);
  const update = (key, value) => setState((current) => ({ ...current, value: { ...current.value, [key]: value }, error: null }));
  const save = async () => {
    if (!dirty || saving) return;
    setSaving(true);
    try { const saved = await updatePrivacyPreferences(state.value); const value = { profile_visibility: saved?.profile_visibility || 'public', messages_from: saved?.messages_from || 'everyone', show_relations: saved?.show_relations !== false, gender_visibility: saved?.gender_visibility || 'private', birth_date_visibility: saved?.birth_date_visibility || 'private' }; setState((current) => ({ ...current, value, initial: value, error: null })); toast({ body: '隐私设置已保存。', uniqueID: 'privacy-saved' }); }
    catch (error) { setState((current) => ({ ...current, error })); }
    finally { setSaving(false); }
  };
  const visitorView = auth.status === 'ready' && !auth.isAuthenticated;
  return <AppShell mobileMode="task"><SettingsLayout
    activeKey="privacy"
    description={visitorView ? '调整此设备上的显示偏好；登录后可以继续管理账户与隐私。' : '管理账户、隐私与此设备上的显示偏好。'}
  >
    <SettingsGroup id="settings-privacy" title="隐私偏好" description="控制主页可见性、私信来源和关系展示。" action={auth.status === 'ready' && auth.isAuthenticated ? <Button type="button" label="保存更改" variant="primary" size="lg" isDisabled={!dirty || saving} isLoading={saving} onClick={save} /> : null}>
      {auth.status === 'checking' || (auth.isAuthenticated && state.status === 'loading') ? <SettingsRowsSkeleton rows={3} label="隐私设置加载中" /> : auth.status === 'error' ? <ResourceErrorBanner error={auth.error} onRetry={auth.refresh} /> : !auth.isAuthenticated ? <div className="settings-login-note" role="status"><div><h3>登录后管理隐私</h3><p>登录后才能读取和保存隐私设置。</p></div><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></div> : state.status === 'error' ? <ResourceErrorBanner error={state.error} onRetry={() => void load()} /> : <>
        {state.error && <ScopedBanner title="隐私设置未保存" description={getUserFacingError(state.error, { fallback: '隐私设置暂时没有保存，请稍后重试。', validationFallback: '隐私设置不符合要求，请重新选择后保存。' })} />}
        <div className="privacy-fields settings-privacy-fields"><Selector label="主页可见范围" description="决定谁可以打开你的公开主页。" options={[{ value: 'public', label: '所有人' }, { value: 'followers', label: '仅关注者' }, { value: 'private', label: '仅自己' }]} value={state.value.profile_visibility} onChange={(value) => update('profile_visibility', value)} width="100%" /><Selector label="谁可以给我发私信" description="不符合条件的发送请求会被服务器拒绝。" options={[{ value: 'everyone', label: '所有人' }, { value: 'following', label: '仅我关注的人' }, { value: 'none', label: '任何人都不可以' }]} value={state.value.messages_from} onChange={(value) => update('messages_from', value)} width="100%" /><Switch label="展示关注与粉丝列表" description="关闭后，关系数量和列表会按照后端隐私规则隐藏。" value={state.value.show_relations} onChange={(value) => update('show_relations', value)} labelPosition="start" labelSpacing="spread" width="100%" /><Selector label="性别可见范围" options={[{ value: 'public', label: '所有人' }, { value: 'followers', label: '仅关注者' }, { value: 'private', label: '仅自己' }]} value={state.value.gender_visibility} onChange={(value) => update('gender_visibility', value)} width="100%" /><Selector label="出生日期可见范围" options={[{ value: 'public', label: '所有人' }, { value: 'followers', label: '仅关注者' }, { value: 'private', label: '仅自己' }]} value={state.value.birth_date_visibility} onChange={(value) => update('birth_date_visibility', value)} width="100%" /></div>
        <section className="privacy-blocked-note settings-privacy-note"><h3>黑名单</h3><p>被你拉黑的用户无法关注、私信或通过提及互动。</p><div className="settings-inline"><TextInput aria-label="搜索黑名单" placeholder="搜索用户名" value={query} onChange={setQuery} width="100%" /><Button type="button" label="搜索" variant="secondary" onClick={() => void loadBlocked(undefined, query)} /></div>{blocked.status === 'loading' && <SettingsRowsSkeleton rows={2} label="黑名单加载中" />}{blocked.status === 'error' && <ResourceErrorBanner error={blocked.error} onRetry={() => void loadBlocked(undefined, query)} />}{blocked.status === 'success' && !blocked.data.length && <p role="status">暂时没有拉黑用户。</p>}{blocked.status === 'success' && blocked.data.map((item) => <div className="settings-session-row" key={item.user.uid}><div><strong>{item.user.username}</strong><p>{item.blocked_at ? `拉黑于 ${new Date(item.blocked_at).toLocaleDateString()}` : ''}</p></div><Button type="button" label="解除拉黑" variant="secondary" size="sm" isDisabled={Boolean(blockedBusy)} isLoading={blockedBusy === String(item.user.uid)} onClick={async () => { setBlockedBusy(String(item.user.uid)); try { await unblockUser(item.user.uid); setBlocked((value) => ({ ...value, data: value.data.filter((row) => row.user.uid !== item.user.uid) })); } catch (error) { setState((value) => ({ ...value, error })); } finally { setBlockedBusy(''); } }} /></div>)}{blocked.status === 'success' && blocked.has_more && <Button type="button" label="加载更多" variant="secondary" onClick={() => void loadBlocked(blocked.next_cursor, query)} />}</section>
      </>}
    </SettingsGroup>
  </SettingsLayout>
    <ConfirmActionDialog isOpen={Boolean(discardDestination)} onOpenChange={(open) => { if (!open) setDiscardDestination(''); }} title="放弃隐私设置修改？" description="尚未保存的可见性和私信偏好将不会生效。" actionLabel="放弃修改" onAction={() => { const destination = discardDestination; setState((current) => ({ ...current, value: current.initial })); setDiscardDestination(''); navigate(destination); }} />
  </AppShell>;
}
