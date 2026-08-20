import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { useUnsavedChanges } from '../../app/use-unsaved-changes.js';
import { ResourceState } from '../../components/ResourceState.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { getPrivacyPreferences, updatePrivacyPreferences } from '../../services/community-service.js';
import { Button, ConfirmActionDialog, Selector, Switch, useToast } from '../../ui/index.js';

const defaults = { profile_visibility: 'public', messages_from: 'everyone', show_relations: true };

export function PrivacyPage() {
  const auth = useAuth();
  const navigate = useNavigate();
  const toast = useToast();
  const [state, setState] = useState({ status: 'loading', value: defaults, initial: defaults, error: null });
  const [saving, setSaving] = useState(false);
  const [discardDestination, setDiscardDestination] = useState('');
  const dirty = useMemo(() => JSON.stringify(state.value) !== JSON.stringify(state.initial), [state.initial, state.value]);
  const load = useCallback(async () => {
    if (!auth.isAuthenticated) return;
    setState((current) => ({ ...current, status: 'loading', error: null }));
    try { const result = await getPrivacyPreferences(); const value = { profile_visibility: result?.profile_visibility || 'public', messages_from: result?.messages_from || 'everyone', show_relations: result?.show_relations !== false }; setState({ status: 'success', value, initial: value, error: null }); }
    catch (error) { setState((current) => ({ ...current, status: 'error', error })); }
  }, [auth.isAuthenticated]);
  useEffect(() => { if (auth.status === 'ready' && auth.isAuthenticated) void load(); }, [auth.isAuthenticated, auth.status, load]);
  useUnsavedChanges(dirty, setDiscardDestination);
  const update = (key, value) => setState((current) => ({ ...current, value: { ...current.value, [key]: value }, error: null }));
  const save = async () => {
    if (!dirty || saving) return;
    setSaving(true);
    try { await updatePrivacyPreferences(state.value); setState((current) => ({ ...current, initial: current.value, error: null })); toast({ body: '隐私设置已保存。', uniqueID: 'privacy-saved' }); }
    catch (error) { setState((current) => ({ ...current, error })); }
    finally { setSaving(false); }
  };
  return <AppShell><main className="community-page privacy-page" aria-labelledby="privacy-title">
    <Link className="detail-back" to="/settings">← 返回设置</Link>
    <header className="community-page-header"><div><p className="community-context">设置</p><h1 id="privacy-title">隐私与黑名单</h1><p>控制主页可见性、私信来源和关系展示。</p></div><Button type="button" label="保存更改" variant="primary" size="lg" isDisabled={!dirty || saving} isLoading={saving} onClick={save} /></header>
    <div className="settings-community-layout"><aside className="community-rail"><Link to="/settings">账户与外观</Link><Link className="is-active" to="/settings/privacy">隐私与黑名单</Link><span>通知偏好 · 暂未开放</span></aside><section className="settings-community-panel">
      {auth.status === 'checking' ? <ResourceState status="loading" /> : !auth.isAuthenticated ? <section className="community-boundary"><h2>登录后管理隐私</h2><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : <ResourceState status={state.status} error={state.error} onRetry={load}>
        <div className="privacy-fields"><Selector label="主页可见范围" description="决定谁可以打开你的公开主页。" options={[{ value: 'public', label: '所有人' }, { value: 'followers', label: '仅关注者' }, { value: 'private', label: '仅自己' }]} value={state.value.profile_visibility} onChange={(value) => update('profile_visibility', value)} width="100%" /><Selector label="谁可以给我发私信" description="不符合条件的发送请求会被服务器拒绝。" options={[{ value: 'everyone', label: '所有人' }, { value: 'following', label: '仅我关注的人' }, { value: 'none', label: '任何人都不可以' }]} value={state.value.messages_from} onChange={(value) => update('messages_from', value)} width="100%" /><Switch label="展示关注与粉丝列表" description="关闭后，关系数量和列表会按照后端隐私规则隐藏。" value={state.value.show_relations} onChange={(value) => update('show_relations', value)} labelPosition="start" labelSpacing="spread" width="100%" /></div>
        {state.error && <p className="community-inline-error" role="alert">{state.error.message || '保存失败，请稍后重试。'}</p>}
        <section className="privacy-blocked-note"><h2>黑名单</h2><p>你可以在其他用户主页中拉黑或解除拉黑。当前 SDK 尚未提供黑名单列表读取接口，因此这里不会伪造名单。</p></section>
      </ResourceState>}
    </section></div>
    <ConfirmActionDialog isOpen={Boolean(discardDestination)} onOpenChange={(open) => { if (!open) setDiscardDestination(''); }} title="放弃隐私设置修改？" description="尚未保存的可见性和私信偏好将不会生效。" actionLabel="放弃修改" onAction={() => { const destination = discardDestination; setState((current) => ({ ...current, value: current.initial })); setDiscardDestination(''); navigate(destination); }} />
  </main></AppShell>;
}
