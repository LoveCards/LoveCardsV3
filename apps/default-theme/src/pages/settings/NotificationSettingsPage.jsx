import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { ResourceErrorBanner } from '../../components/ResourceState.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { getNotificationPreferences, updateNotificationPreferences } from '../../services/settings-service.js';
import { Button, Switch } from '../../ui/index.js';
import { ScopedBanner } from '../../ui/interaction.jsx';
import { SettingsGroup, SettingsLayout, SettingsRowsSkeleton } from './SettingsLayout.jsx';

const labels = {
  'social.followed': '有人关注我', message: '私信通知', 'content.card_liked': '有人赞了我的卡片',
  'content.comment_liked': '有人赞了我的评论', 'library.saved': '有人收藏了我的卡片',
  'content.commented': '有人评论我的卡片', 'content.replied': '有人回复我的评论', 'content.mentioned': '有人提及我',
};

export function NotificationSettingsPage() {
  const auth = useAuth();
  const canEdit = auth.status === 'ready' && auth.isAuthenticated;
  const [state, setState] = useState({ status: 'loading', value: null, error: null });
  const [saving, setSaving] = useState(false);
  const load = useCallback(async () => { setState((value) => ({ ...value, status: 'loading', error: null })); try { const value = await getNotificationPreferences(); setState({ status: 'success', value, error: null }); } catch (error) { setState({ status: 'error', value: null, error }); } }, []);
  useEffect(() => { if (auth.status === 'ready' && auth.isAuthenticated) void load(); }, [auth.status, auth.isAuthenticated, load]);
  if (auth.status === 'checking') return <AppShell mobileMode="task"><SettingsLayout activeKey="notifications" description="控制互动消息的接收方式。"><SettingsRowsSkeleton rows={4} label="通知偏好加载中" /></SettingsLayout></AppShell>;
  return <AppShell mobileMode="task"><SettingsLayout activeKey="notifications" description="控制互动消息的接收方式。"><SettingsGroup title="通知偏好" description="关闭某项只影响未来通知，不会删除历史消息或改变未读记录。" action={canEdit ? <Button type="button" label="保存更改" variant="primary" size="lg" isDisabled={saving || state.status !== 'success'} isLoading={saving} onClick={async () => { setSaving(true); try { const result = await updateNotificationPreferences(state.value.in_app); setState({ status: 'success', value: result, error: null }); } catch (error) { setState((value) => ({ ...value, error })); } finally { setSaving(false); } }} /> : null}>{auth.status === 'error' ? <ResourceErrorBanner error={auth.error} onRetry={auth.refresh} /> : !auth.isAuthenticated ? <div className="settings-login-note"><h3>登录后管理通知</h3><p>登录后可以选择接收哪些互动消息。</p><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></div> : state.status === 'loading' ? <SettingsRowsSkeleton rows={4} label="通知偏好加载中" /> : state.status === 'error' ? <ResourceErrorBanner error={state.error} onRetry={() => void load()} /> : <>{state.error && <ScopedBanner title="通知偏好没有保存" description={getUserFacingError(state.error, { fallback: '请稍后重试。' })} />}<div className="settings-preferences">{Object.entries(state.value?.in_app || {}).map(([type, enabled]) => <Switch key={type} label={labels[type] || type} value={Boolean(enabled)} onChange={(value) => setState((current) => ({ ...current, value: { ...current.value, in_app: { ...current.value.in_app, [type]: value } } }))} labelPosition="start" labelSpacing="spread" width="100%" />)}{(state.value?.locked_types || []).map((type) => <div className="settings-action-row" key={type}><div><h3>{labels[type] || '系统通知'}</h3><p>系统通知始终开启</p></div></div>)}</div></>}</SettingsGroup></SettingsLayout></AppShell>;
}
