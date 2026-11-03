import { Link } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';

export function NotFoundPage() {
  return (
    <AppShell>
      <main className="not-found-page">
        <p>404</p>
        <h1>这里还没有页面</h1>
        <span>这个地址可能已经失效，或页面尚未开放。</span>
        <Link className="action-link action-link-primary" to="/">返回首页</Link>
      </main>
    </AppShell>
  );
}
