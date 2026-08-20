import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { useAuth, useThemeMode } from '../../app/providers.jsx';
import { sendEmailCaptcha, updateEmail, updatePassword } from '../../services/content-service.js';
import { ResourceState } from '../../components/ResourceState.jsx';
import { Button, FormLayout, Selector, Switch, TextInput, useToast } from '../../ui/index.js';

export function SettingsPage() {
  const toast = useToast();
  const { isAuthenticated, logout, status: authStatus, error: authError, refresh: refreshAuth, can } = useAuth();
  const { preference, setPreference, reduceMotion, setReduceMotion } = useThemeMode();
  const navigate = useNavigate();
  const [password, setPassword] = useState('');
  const [email, setEmail] = useState('');
  const [captcha, setCaptcha] = useState('');
  const [feedback, setFeedback] = useState({ scope: '', message: '' });
  const [busy, setBusy] = useState('');
  const run = async (scope, task, success) => {
    setBusy(scope);
    setFeedback({ scope: '', message: '' });
    try {
      await task();
      toast({ body: success, uniqueID: `settings-${scope}` });
    } catch (error) {
      setFeedback({ scope, message: error?.message || '操作失败，请稍后重试。' });
    } finally {
      setBusy('');
    }
  };
  const visitorView = authStatus === 'ready' && !isAuthenticated;
  const canManageAccount = can('users.update');
  return <AppShell>
    <main className="settings-page" aria-labelledby="settings-title">
      <Link className="detail-back" to={visitorView ? '/' : '/me'}>← {visitorView ? '返回首页' : '返回个人主页'}</Link>
      <header><p className="profile-kicker">设置</p><h1 id="settings-title">外观与账户</h1><p>{visitorView ? '调整当前设备的外观；登录后可管理账户。' : '管理外观偏好、登录安全与联系方式。'}</p></header>
      <section className="settings-section"><h2>外观与动态</h2><p>这些偏好只保存在当前设备，并立即应用。</p><div className="settings-preferences"><Selector label="主题模式" description="跟随系统会在设备主题变化时自动切换。" options={[{ value: 'system', label: '跟随系统' }, { value: 'light', label: '浅色' }, { value: 'dark', label: '深色' }]} value={preference} onChange={(value) => { setPreference(value); toast({ body: '主题偏好已更新', uniqueID: 'theme-preference' }); }} width="100%" /><Switch label="减少动态效果" description="关闭位移、缩放和非必要过渡。" value={reduceMotion} onChange={(value) => { setReduceMotion(value); toast({ body: value ? '已减少动态效果' : '已恢复标准动态效果', uniqueID: 'motion-preference' }); }} labelPosition="start" labelSpacing="spread" width="100%" /></div></section>
      {authStatus === 'checking' ? <ResourceState status="loading" /> : authStatus === 'error' ? <ResourceState status="error" error={authError} onRetry={refreshAuth} /> : !isAuthenticated ? <section className="settings-login-note" role="status"><div><h2>账户设置需要登录</h2><p>登录后可修改密码、验证邮箱和管理会话。</p></div><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : <>
        {canManageAccount ? <>
          <section className="settings-section"><h2>登录安全</h2><form onSubmit={(event) => { event.preventDefault(); void run('password', () => updatePassword(password), '密码已更新。'); }}><FormLayout><TextInput label="新密码" type="password" value={password} onChange={setPassword} minLength={6} placeholder="至少 6 位" size="lg" width="100%" status={feedback.scope === 'password' ? { type: 'error', message: feedback.message } : undefined} statusVariant="detached" /></FormLayout><Button type="submit" label="更新密码" variant="primary" size="lg" isDisabled={Boolean(busy)} isLoading={busy === 'password'} /></form></section>
          <section className="settings-section"><h2>邮箱验证</h2><div className="settings-inline"><TextInput label="邮箱" type="email" value={email} onChange={setEmail} placeholder="name@example.com" size="lg" width="100%" status={feedback.scope === 'email-captcha' ? { type: 'error', message: feedback.message } : undefined} statusVariant="detached" /><Button type="button" label="发送验证码" variant="secondary" size="lg" isDisabled={Boolean(busy)} isLoading={busy === 'email-captcha'} clickAction={() => run('email-captcha', () => sendEmailCaptcha(email), '验证码已发送。')} /></div><form onSubmit={(event) => { event.preventDefault(); void run('email-update', () => updateEmail(email, captcha), '邮箱已更新。'); }}><FormLayout><TextInput label="验证码" value={captcha} onChange={setCaptcha} size="lg" width="100%" status={feedback.scope === 'email-update' ? { type: 'error', message: feedback.message } : undefined} statusVariant="detached" /></FormLayout><Button type="submit" label="保存邮箱" variant="primary" size="lg" isDisabled={Boolean(busy)} isLoading={busy === 'email-update'} /></form></section>
        </> : <section className="settings-login-note" role="status"><div><h2>账户资料当前为只读</h2><p>你的账号没有修改密码或邮箱的权限；外观设置和退出登录仍可使用。</p></div></section>}
        <section className="settings-section settings-danger"><h2>会话</h2><Button type="button" label="退出登录" variant="destructive" size="lg" isDisabled={Boolean(busy)} isLoading={busy === 'logout'} clickAction={async () => { setBusy('logout'); try { await logout(); } catch { toast({ body: '本地登录状态已清除，但服务器退出确认失败。', type: 'error', uniqueID: 'logout-error' }); } finally { navigate('/auth/login', { replace: true }); } }} /></section>
      </>}
    </main>
  </AppShell>;
}
