import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { endCurrentSession } from '../../app/end-current-session.js';
import { useAuth } from '../../app/providers.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { Button } from '../../ui/index.js';

export function GuestMePage() {
  const { logout } = useAuth();
  const navigate = useNavigate();
  const [ending, setEnding] = useState(false);

  const endGuestSession = async () => {
    if (ending) return;
    setEnding(true);
    await endCurrentSession(logout, navigate, '/');
  };

  return <AppShell>
    <main className="guest-me-page" aria-labelledby="guest-me-title">
      <header className="guest-me-heading"><h1 id="guest-me-title">我的</h1><span>游客体验</span></header>
      <section className="guest-me-section" aria-labelledby="guest-me-account-title">
        <h2 id="guest-me-account-title">欢迎来到 LoveCards</h2>
        <p>你可以浏览公开内容。登录后可管理个人内容与互动。</p>
        <div className="guest-me-links">
          <Link className="action-link action-link-primary" to="/auth/login" state={{ from: '/me' }}>登录正式账号</Link>
          <Link className="action-link" to="/auth/register" state={{ from: '/me' }}>创建账号</Link>
        </div>
      </section>
      <section className="guest-me-section" aria-labelledby="guest-me-session-title">
        <h2 id="guest-me-session-title">当前游客会话</h2>
        <p>结束后，此设备保存的游客凭证会清除，无法继续使用当前游客身份；公开内容仍可浏览。</p>
        <Button type="button" label="结束游客会话" variant="secondary" size="lg" isDisabled={ending} isLoading={ending} onClick={() => { void endGuestSession(); }} />
      </section>
      <Link className="guest-me-settings" to="/settings">设置与外观</Link>
    </main>
  </AppShell>;
}
