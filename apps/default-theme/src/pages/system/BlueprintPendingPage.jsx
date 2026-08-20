import { Link } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';

export function BlueprintPendingPage({ title, description, capability, backTo, backLabel }) {
  return (
    <AppShell>
      <main className="pending-page" aria-labelledby="pending-title">
        {backTo && <Link className="detail-back pending-back" to={backTo}>← {backLabel || '返回上一级'}</Link>}
        <p className="pending-context">功能状态</p>
        <h1 id="pending-title">{title}</h1>
        <p>{description}</p>
        <section className="pending-boundary" role="status">
          <strong>{capability ? '暂未开放' : '正在建设'}</strong>
          <span>{capability || '这一入口已经保留，完成真实数据与交互接入后会在这里开放。'}</span>
        </section>
        <div className="pending-actions">
          <Link className="action-link action-link-primary" to="/explore">继续发现内容</Link>
          <Link className="action-link" to="/">返回首页</Link>
        </div>
      </main>
    </AppShell>
  );
}
