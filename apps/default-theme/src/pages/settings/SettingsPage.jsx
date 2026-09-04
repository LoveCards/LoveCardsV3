import { useEffect, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { useAuth, useThemeMode } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { sendEmailCaptcha, updateEmail, updatePassword } from '../../services/content-service.js';
import { listSessions, revokeOtherSessions, revokeSession } from '../../services/settings-service.js';
import { Button, FormLayout, Icon, Selector, Switch, TextInput, useToast } from '../../ui/index.js';
import { ScopedBanner } from '../../ui/interaction.jsx';
import { settingsNavigationItems, SettingsGroup, SettingsLayout, SettingsRowsSkeleton } from './SettingsLayout.jsx';

const settingsOverviewItems = [
  ...settingsNavigationItems,
];

export function settingsPathFromLegacyHash(hash = '') {
  if (hash === '#settings-account') return '/settings/security';
  if (hash === '#settings-session') return '/settings/session';
  return '/settings/appearance';
}

export function SettingsIndexRedirect() {
  const { hash } = useLocation();
  return hash ? <Navigate to={settingsPathFromLegacyHash(hash)} replace /> : <SettingsOverviewPage />;
}

export function SettingsOverviewPage() {
  return <AppShell mobileMode="task">
    <SettingsLayout
      activeKey="overview"
      description="管理账号、隐私与使用偏好。"
    >
      <SettingsGroup title="设置总览" description="选择一项继续。设备外观无需登录，账号与隐私设置需要登录。" className="settings-overview">
        <nav className="settings-overview-list" aria-label="设置项目">
          {settingsOverviewItems.map((item) => <Link className="settings-overview-link" key={item.key} to={item.href}>
            <span><strong>{item.label}</strong><small>{item.description}</small></span>
            <Icon name="arrow" size={17} />
          </Link>)}
        </nav>
      </SettingsGroup>
    </SettingsLayout>
  </AppShell>;
}

function formatSessionTime(value) {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }).format(date);
}

function SessionSettingsSection({ authStatus, authError, isAuthenticated, refreshAuth, sessions, feedback, busy, logout, navigate, setBusy, setSessions, setFeedback }) {
  const activeSessions = sessions.data
    .filter((item) => item.status === 'active')
    .sort((left, right) => Number(right.current) - Number(left.current) || new Date(right.created_at).getTime() - new Date(left.created_at).getTime());
  const revokeOne = async (session) => {
    setBusy(`session-${session.id}`);
    try {
      await revokeSession(session.id);
      if (session.current) {
        await logout();
        navigate('/auth/login', { replace: true });
        return;
      }
      setSessions((value) => ({ ...value, data: value.data.map((item) => item.id === session.id ? { ...item, status: 'revoked' } : item) }));
    } catch (error) {
      setFeedback({ scope: 'session', message: getUserFacingError(error, { fallback: '会话暂时无法撤销，请稍后重试。' }) });
    } finally {
      setBusy('');
    }
  };
  return <SettingsGroup title="登录设备" description="查看正在使用账号的设备与登录记录；没有返回的设备信息不会被猜测或补写。">
    {authStatus === 'checking' ? <SettingsRowsSkeleton rows={3} label="登录设备加载中" /> : authStatus === 'error' ? <ScopedBanner title="登录设备暂时无法加载" description={getUserFacingError(authError, { fallback: '登录设备暂时无法加载，请稍后重试。' })} actionLabel="重试" onAction={refreshAuth} /> : !isAuthenticated ? <div className="settings-login-note" role="status"><div><h3>当前没有登录会话</h3><p>登录后可以查看和管理登录设备。</p></div><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></div> : <>
      {feedback.scope === 'session' && <ScopedBanner className="settings-task-feedback" title="会话操作没有完成" description={feedback.message} />}
      {sessions.status === 'loading' && <SettingsRowsSkeleton rows={3} label="登录设备加载中" />}
      {sessions.status === 'error' && <ScopedBanner title="登录设备暂时无法加载" description={getUserFacingError(sessions.error, { fallback: '请稍后重试。' })} />}
      {sessions.status === 'success' && !activeSessions.length && <div className="settings-login-note" role="status"><div><h3>没有活动会话</h3><p>重新登录后，设备记录会显示在这里。</p></div></div>}
      {sessions.status === 'success' && activeSessions.length > 0 && <div className="settings-session-list" role="list" aria-label="登录设备列表">{activeSessions.map((session) => {
        const createdAt = formatSessionTime(session.created_at);
        const lastSeenAt = formatSessionTime(session.last_seen_at);
        const address = [session.location, session.ip_address].filter(Boolean).join(' · ');
        return <article className={`settings-session-row${session.current ? ' is-current' : ''}`} role="listitem" key={session.id}>
          <div className="settings-session-identity"><span className="settings-session-indicator" aria-hidden="true" /><div><strong>{session.device_name || (session.current ? '当前设备' : '登录设备')}</strong>{session.current && <small>当前会话</small>}</div></div>
          <dl className="settings-session-details">
            {createdAt && <div><dt>登录时间</dt><dd><time dateTime={session.created_at}>{createdAt}</time></dd></div>}
            {lastSeenAt && <div><dt>最近活动</dt><dd><time dateTime={session.last_seen_at}>{lastSeenAt}</time></dd></div>}
            {address && <div><dt>登录地址</dt><dd>{address}</dd></div>}
          </dl>
          <Button type="button" label={session.current ? '退出此设备' : '撤销会话'} variant="secondary" size="sm" isDisabled={Boolean(busy)} isLoading={busy === `session-${session.id}`} clickAction={() => void revokeOne(session)} />
        </article>;
      })}</div>}
      {sessions.status === 'success' && activeSessions.some((session) => !session.current) && <div className="settings-session-toolbar"><span>发现其他活动会话时，可以一次全部退出。</span><Button type="button" label="退出其他设备" variant="secondary" size="lg" isDisabled={Boolean(busy)} isLoading={busy === 'revoke-others'} clickAction={async () => { setBusy('revoke-others'); try { await revokeOtherSessions(); const data = await listSessions(); setSessions({ status: 'success', data, error: null }); } catch (error) { setFeedback({ scope: 'session', message: getUserFacingError(error, { fallback: '其他设备暂时无法退出，请稍后重试。' }) }); } finally { setBusy(''); } }} /></div>}
    </>}
  </SettingsGroup>;
}

export function SettingsPage({ section }) {
  const toast = useToast();
  const { isAuthenticated, logout, status: authStatus, error: authError, refresh: refreshAuth, can } = useAuth();
  const { preference, setPreference, reduceMotion, setReduceMotion } = useThemeMode();
  const navigate = useNavigate();
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [emailPassword, setEmailPassword] = useState('');
  const [email, setEmail] = useState('');
  const [captcha, setCaptcha] = useState('');
  const [feedback, setFeedback] = useState({ scope: '', message: '' });
  const [busy, setBusy] = useState('');
  const [sessions, setSessions] = useState({ status: 'idle', data: [], error: null });
  const [captchaExpires, setCaptchaExpires] = useState(0);
  useEffect(() => { if (!captchaExpires) return undefined; const timer = window.setInterval(() => setCaptchaExpires((value) => Math.max(0, value - 1)), 1000); return () => window.clearInterval(timer); }, [captchaExpires]);
  useEffect(() => { if (section !== 'session' || !isAuthenticated) return; let active = true; setSessions({ status: 'loading', data: [], error: null }); listSessions().then((data) => { if (active) setSessions({ status: 'success', data: Array.isArray(data) ? data : [], error: null }); }).catch((error) => { if (active) setSessions({ status: 'error', data: [], error }); }); return () => { active = false; }; }, [section, isAuthenticated]);
  const run = async (scope, task, success) => {
    setBusy(scope);
    setFeedback({ scope: '', message: '' });
    try {
      await task();
      toast({ body: success, uniqueID: `settings-${scope}` });
    } catch (error) {
      setFeedback({ scope, message: getUserFacingError(error, { fallback: '账户信息暂时没有保存，请稍后重试。', validationFallback: '输入内容不符合要求，请检查后重试。' }) });
    } finally {
      setBusy('');
    }
  };
  const visitorView = authStatus === 'ready' && !isAuthenticated;
  const canManageAccount = can('users.update');

  return <AppShell mobileMode="task">
    <SettingsLayout
      activeKey={section}
      description={visitorView ? '调整此设备上的显示偏好；登录后可以继续管理账户与隐私。' : '管理账户、隐私与此设备上的显示偏好。'}
    >
      {section === 'appearance' && <SettingsGroup title="外观与无障碍" description="这些偏好只保存在当前设备，并在修改后立即生效。">
        <div className="settings-preferences">
          <Selector label="主题模式" description="跟随系统会在设备主题变化时自动切换。" options={[{ value: 'system', label: '跟随系统' }, { value: 'light', label: '浅色' }, { value: 'dark', label: '深色' }]} value={preference} onChange={(value) => { setPreference(value); toast({ body: '主题偏好已更新', uniqueID: 'theme-preference' }); }} width="100%" />
          <Switch label="减少动态效果" description="关闭位移、缩放和非必要过渡。" value={reduceMotion} onChange={(value) => { setReduceMotion(value); toast({ body: value ? '已减少动态效果' : '已恢复标准动态效果', uniqueID: 'motion-preference' }); }} labelPosition="start" labelSpacing="spread" width="100%" />
        </div>
      </SettingsGroup>}

      {section === 'security' && <SettingsGroup title="账号与安全" description="管理登录凭证、联系方式和账号权限状态。">
        {authStatus === 'checking' ? <SettingsRowsSkeleton rows={2} label="账号与安全设置加载中" /> : authStatus === 'error' ? <ScopedBanner title="账号设置暂时无法加载" description={getUserFacingError(authError, { fallback: '账号设置暂时无法加载，请稍后重试。' })} actionLabel="重试" onAction={refreshAuth} /> : !isAuthenticated ? <div className="settings-login-note" role="status"><div><h3>账户设置需要登录</h3><p>登录后可修改密码、验证邮箱和管理会话。</p></div><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></div> : canManageAccount ? <>
          <div className="settings-task">
            <div className="settings-task-copy"><h3>登录密码</h3><p>更新当前账号用于登录的密码。</p></div>
            <div className="settings-task-content">
              {feedback.scope === 'password' && <ScopedBanner className="settings-task-feedback" title="密码没有更新" description={feedback.message} />}
              <form onSubmit={(event) => { event.preventDefault(); void run('password', async () => { await updatePassword({ current_password: currentPassword, new_password: password }); toast({ body: '安全信息已更新，需要重新登录。', uniqueID: 'password-updated' }); await logout(); navigate('/auth/login', { replace: true }); }, ''); }}>
                <FormLayout><TextInput label="当前密码" type="password" value={currentPassword} onChange={setCurrentPassword} required size="lg" width="100%" /><TextInput label="新密码" type="password" value={password} onChange={setPassword} minLength={6} required placeholder="至少 6 位" size="lg" width="100%" /></FormLayout>
                <Button type="submit" label="更新密码" variant="primary" size="lg" isDisabled={Boolean(busy)} isLoading={busy === 'password'} />
              </form>
            </div>
          </div>
          <div className="settings-task">
            <div className="settings-task-copy"><h3>验证邮箱</h3><p>验证码会发送到新的邮箱地址。</p></div>
            <div className="settings-task-content">
              {(feedback.scope === 'email-captcha' || feedback.scope === 'email-update') && <ScopedBanner className="settings-task-feedback" title={feedback.scope === 'email-captcha' ? '验证码没有发送' : '邮箱没有更新'} description={feedback.message} />}
              <FormLayout><TextInput label="当前密码" type="password" value={emailPassword} onChange={setEmailPassword} required size="lg" width="100%" /><div className="settings-inline"><TextInput label="新邮箱" type="email" value={email} onChange={setEmail} placeholder="name@example.com" size="lg" width="100%" /><Button type="button" label={captchaExpires ? `${captchaExpires}s 后重发` : '发送验证码'} variant="secondary" size="lg" isDisabled={Boolean(busy) || Boolean(captchaExpires)} isLoading={busy === 'email-captcha'} clickAction={() => run('email-captcha', async () => { const result = await sendEmailCaptcha(email); setCaptchaExpires(Number(result?.expires_in) || 0); }, '验证码已发送。')} /></div><TextInput label="验证码" value={captcha} onChange={setCaptcha} required size="lg" width="100%" /></FormLayout>
              <form onSubmit={(event) => { event.preventDefault(); void run('email-update', async () => { await updateEmail({ current_password: emailPassword, email, captcha }); toast({ body: '安全信息已更新，需要重新登录。', uniqueID: 'email-updated' }); await logout(); navigate('/auth/login', { replace: true }); }, ''); }}>
                <Button type="submit" label="保存邮箱" variant="primary" size="lg" isDisabled={Boolean(busy)} isLoading={busy === 'email-update'} />
              </form>
            </div>
          </div>
        </> : <div className="settings-login-note" role="status"><div><h3>账户资料当前为只读</h3><p>当前账号没有修改密码或邮箱的权限；外观设置和退出登录仍可使用。</p></div></div>}
      </SettingsGroup>}

      {section === '__legacy-session' && <SettingsGroup title="登录状态" description="管理当前设备上的登录状态；退出不会删除账号或内容。">
        {authStatus === 'checking' ? <SettingsRowsSkeleton rows={1} label="登录状态加载中" /> : authStatus === 'error' ? <ScopedBanner title="登录状态暂时无法加载" description={getUserFacingError(authError, { fallback: '登录状态暂时无法加载，请稍后重试。' })} actionLabel="重试" onAction={refreshAuth} /> : isAuthenticated ? <><div className="settings-action-row"><div><h3>退出当前账号</h3><p>退出后，此设备需要重新登录才能访问个人内容。</p></div><Button type="button" label="退出登录" variant="secondary" size="lg" isDisabled={Boolean(busy)} isLoading={busy === 'logout'} clickAction={async () => { setBusy('logout'); try { await logout(); } finally { navigate('/auth/login', { replace: true }); } }} /></div>{sessions.status === 'loading' && <SettingsRowsSkeleton rows={3} label="会话列表加载中" />}{sessions.status === 'error' && <ScopedBanner title="会话列表暂时无法加载" description={getUserFacingError(sessions.error, { fallback: '请稍后重试。' })} actionLabel="重试" onAction={() => window.dispatchEvent(new Event('lc-auth-changed'))} />}{sessions.status === 'success' && <div className="settings-session-list">{sessions.data.filter((item) => item.status === 'active').map((session) => <div className="settings-session-row" key={session.id}><div><strong>{session.device_name || '当前登录会话'} {session.current && <small>当前会话</small>}</strong><p>{session.location || ''}{session.ip_address ? `${session.location ? ' · ' : ''}${session.ip_address}` : ''}</p></div><Button type="button" label="撤销" variant="secondary" size="sm" isDisabled={session.current || Boolean(busy)} clickAction={async () => { setBusy(`session-${session.id}`); try { await revokeSession(session.id); setSessions((value) => ({ ...value, data: value.data.map((item) => item.id === session.id ? { ...item, status: 'revoked' } : item) })); } catch (error) { setFeedback({ scope: 'session', message: getUserFacingError(error, { fallback: '会话暂时无法撤销，请稍后重试。' }) }); } finally { setBusy(''); } }} /></div>)}</div>}{sessions.status === 'success' && sessions.data.some((item) => item.status === 'active' && !item.current) && <Button type="button" label="撤销其他会话" variant="secondary" size="lg" isDisabled={Boolean(busy)} clickAction={async () => { setBusy('revoke-others'); try { await revokeOtherSessions(); const next = await listSessions(); setSessions({ status: 'success', data: next, error: null }); } catch (error) { setFeedback({ scope: 'session', message: getUserFacingError(error, { fallback: '其他会话暂时无法撤销，请稍后重试。' }) }); } finally { setBusy(''); } }} />}</> : <div className="settings-login-note" role="status"><div><h3>当前没有登录会话</h3><p>登录后可以在这里退出当前账号。</p></div><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></div>}
      </SettingsGroup>}
      {section === 'session' && <SessionSettingsSection authStatus={authStatus} authError={authError} isAuthenticated={isAuthenticated} refreshAuth={refreshAuth} sessions={sessions} feedback={feedback} busy={busy} logout={logout} navigate={navigate} setBusy={setBusy} setSessions={setSessions} setFeedback={setFeedback} />}
    </SettingsLayout>
  </AppShell>;
}
