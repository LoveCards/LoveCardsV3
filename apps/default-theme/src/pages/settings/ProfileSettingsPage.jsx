import { useEffect, useMemo, useState } from 'react';
import { AppShell } from '../../layouts/AppShell.jsx';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { getCurrentUser, updateCurrentUser, updateProfileCover } from '../../services/settings-service.js';
import { Button, FileInput } from '../../ui/index.js';
import { ScopedBanner, useToast } from '../../ui/interaction.jsx';
import { SettingsGroup, SettingsLayout, SettingsRowsSkeleton } from './SettingsLayout.jsx';

const countCodePoints = (value) => Array.from(value || '').length;

function normalizeForm(user) {
  return { username: user?.username || '', bio: user?.bio || '', location: user?.location || '', gender: user?.gender || '', birth_date: user?.birth_date || '' };
}

export function ProfileSettingsPage() {
  const { isAuthenticated, status: authStatus, refresh: refreshAuth } = useAuth();
  const toast = useToast();
  const [status, setStatus] = useState('loading');
  const [user, setUser] = useState(null);
  const [form, setForm] = useState({ username: '', bio: '', location: '', gender: '', birth_date: '' });
  const [feedback, setFeedback] = useState(null);
  const [busy, setBusy] = useState(false);
  const [coverFile, setCoverFile] = useState(null);
  const [clearCover, setClearCover] = useState(false);

  const load = async () => {
    setStatus('loading');
    setFeedback(null);
    try {
      const value = await getCurrentUser();
      setUser(value);
      setForm(normalizeForm(value));
      setStatus('success');
    } catch (error) {
      setFeedback(getUserFacingError(error, { fallback: '个人资料暂时无法加载，请稍后重试。' }));
      setStatus('error');
    }
  };

  useEffect(() => {
    if (authStatus === 'ready' && isAuthenticated) void load();
  }, [authStatus, isAuthenticated]);

  const validation = useMemo(() => {
    if (!form.username.trim()) return '账号名称不能为空。';
    if (countCodePoints(form.bio) > 160) return '个人简介最多 160 个字。';
    if (countCodePoints(form.location) > 64) return '所在地最多 64 个字。';
    if (/\r|\n/.test(form.location)) return '所在地请填写单行内容。';
    if (form.birth_date && !/^\d{4}-\d{2}-\d{2}$/.test(form.birth_date)) return '出生日期格式应为 YYYY-MM-DD。';
    return '';
  }, [form]);

  const save = async (event) => {
    event.preventDefault();
    if (validation || busy) return;
    setBusy(true);
    setFeedback(null);
    try {
      let next = await updateCurrentUser({ username: form.username.trim(), bio: form.bio || null, location: form.location || null });
      if (coverFile || clearCover) next = await updateProfileCover(clearCover ? null : coverFile);
      setUser(next);
      setForm(normalizeForm(next));
      setCoverFile(null);
      setClearCover(false);
      refreshAuth();
      setFeedback('资料已保存。主页会同步显示账号和简介。');
      toast({ body: '资料已保存。', uniqueID: 'profile-settings-saved' });
    } catch (error) {
      const message = getUserFacingError(error, { fallback: '资料暂时无法保存，请稍后重试。' });
      setFeedback(message);
      toast({ body: message, type: 'error', uniqueID: 'profile-settings-save-error' });
    } finally {
      setBusy(false);
    }
  };

  return <AppShell mobileMode="task"><main className="profile-settings-page" aria-labelledby="profile-settings-title">
    <SettingsLayout activeKey="profile" title="个人资料" description="管理公开身份与个人介绍；头像可以在我的主页快捷更换。">
      {authStatus === 'checking' || status === 'loading' ? <SettingsRowsSkeleton rows={3} label="个人资料加载中" /> : !isAuthenticated ? <div className="settings-login-note" role="status"><div><h3>登录后管理个人资料</h3><p>个人资料只对当前账号开放。</p></div></div> : status === 'error' ? <ScopedBanner title="个人资料暂时无法加载" description={feedback} actionLabel="重试" onAction={() => void load()} /> : <>
        {feedback && <div className="profile-settings-feedback" role={feedback === '资料已保存。主页会同步显示账号和简介。' ? 'status' : 'alert'} aria-live="polite"><ScopedBanner status={feedback === '资料已保存。主页会同步显示账号和简介。' ? 'success' : 'error'} className="settings-task-feedback" title={feedback === '资料已保存。主页会同步显示账号和简介。' ? '保存成功' : '资料未保存'} description={feedback} /></div>}
        <form id="profile-settings-form" className="profile-settings-form-shell" onSubmit={save} noValidate>
          <SettingsGroup title="公开身份" description="这些信息会出现在你的用户主页和作者信息中。">
            <div className="profile-settings-account"><div><span>账号</span><strong>@{user?.username || '—'}</strong></div><div><span>UID</span><strong>{user?.uid || '—'}</strong></div><small>UID 是账号的稳定标识，用于系统内部关联和安全核验。</small></div>
            <div className="profile-settings-form">
            <label><span>主页背景图</span><FileInput label="选择背景图" description="JPG、PNG 或 WebP，最大 10 MiB" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" maxSize={10 * 1024 * 1024} value={coverFile} onChange={(file) => { setCoverFile(file); setClearCover(false); }} placeholder={user?.cover_url ? '更换背景图' : '选择背景图'} mode="input" width="100%" />{user?.cover_url && <button className="profile-cover-clear" type="button" onClick={() => { setCoverFile(null); setClearCover(true); }}>移除当前背景图</button>}<small>保存资料时一起更新；未选择时继续使用默认渐变。</small></label>
            <label><span>用户名</span><input value={form.username} maxLength={80} onChange={(event) => setForm((value) => ({ ...value, username: event.target.value }))} autoComplete="nickname" aria-describedby="profile-username-help" /><small id="profile-username-help">主页链接使用用户名，修改后旧链接可能失效。</small></label>
            <label><span>个人简介</span><textarea value={form.bio} maxLength={160} rows={4} onChange={(event) => setForm((value) => ({ ...value, bio: event.target.value }))} aria-describedby="profile-bio-help" /><small id="profile-bio-help">{countCodePoints(form.bio)}/160 个字。</small></label>
            <label><span>所在地</span><input value={form.location} maxLength={64} onChange={(event) => setForm((value) => ({ ...value, location: event.target.value.replace(/[\r\n]/g, '') }))} aria-describedby="profile-location-help" /><small id="profile-location-help">单行填写，最多 64 个字。</small></label>
            </div>
          </SettingsGroup>
          <SettingsGroup title="个人资料" description="性别和出生日期默认仅自己可见，可在隐私设置中调整。">
            <div className="profile-settings-form">
            <label><span>性别</span><select value={form.gender} disabled aria-describedby="profile-private-fields-help"><option value="">未设置</option><option value="male">男</option><option value="female">女</option><option value="non_binary">非二元</option><option value="prefer_not_to_say">不愿透露</option></select></label>
            <label><span>出生日期</span><input type="date" min="1900-01-01" max={new Date().toISOString().slice(0, 10)} value={form.birth_date} disabled aria-describedby="profile-private-fields-help" /><small id="profile-private-fields-help">当前生成 SDK 尚未提供这两项的保存接口，暂时只能读取。</small></label>
            </div>
          </SettingsGroup>
          {validation && <p className="profile-settings-validation profile-settings-validation-global" role="alert">{validation}</p>}
          <div className="profile-settings-save-row"><span className="profile-settings-save-status" role="status" aria-live="polite">{busy ? '正在保存资料…' : feedback === '资料已保存。主页会同步显示账号和简介。' ? '已保存' : ''}</span><Button type="submit" label={busy ? '保存中' : '保存资料'} size="lg" isLoading={busy} isDisabled={Boolean(validation) || busy} /></div>
        </form>
      </>}
    </SettingsLayout>
  </main></AppShell>;
}
