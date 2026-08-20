import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { loadLikes, loadOwnComments } from '../../services/content-service.js';
import { useAuth } from '../../app/providers.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { ProfileContentTabs } from './ProfileContentTabs.jsx';

export function LibraryPage() {
  const { section = 'likes' } = useParams();
  const { isAuthenticated, status: authStatus, error: authError, refresh: refreshAuth, can } = useAuth();
  const [items, setItems] = useState(null);
  const [error, setError] = useState('');
  const [retryKey, setRetryKey] = useState(0);
  const validSection = section === 'likes' || section === 'comments';
  const comments = section === 'comments';
  const canViewSection = validSection && can(comments ? 'comments.read' : 'likes.read');
  useEffect(() => {
    let active = true;
    setItems(null);
    setError('');
    if (authStatus !== 'ready' || !isAuthenticated || !canViewSection) return () => { active = false; };
    (section === 'comments' ? loadOwnComments() : loadLikes()).then((data) => active && setItems(data)).catch((e) => active && setError(e?.message || '加载失败。'));
    return () => { active = false; };
  }, [authStatus, canViewSection, isAuthenticated, retryKey, section]);
  const records = items || [];
  return <AppShell><main className="profile-page library-page" aria-labelledby="library-title">
    <Link className="detail-back" to="/me">← 返回个人主页</Link>
    <header className="library-header"><p className="profile-kicker">我的空间</p><h1 id="library-title">{comments ? '我的评论' : '我的喜欢'}</h1><p>{comments ? '你参与过的真实讨论。' : '你留下过喜欢的内容。'}</p></header>
    <ProfileContentTabs />
    {authStatus === 'checking' ? <ResourceState status="loading" /> : authStatus === 'error' ? <ResourceState status="error" error={authError} onRetry={refreshAuth} /> : !isAuthenticated ? <section className="profile-boundary"><h2>登录后查看个人记录</h2><p>喜欢和评论记录只属于你的正式账号。</p><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : !validSection ? <section className="profile-boundary"><h2>没有这个资料分类</h2><Link to="/me">返回个人主页</Link></section> : !canViewSection ? <section className="profile-boundary"><h2>当前账号无法查看这项记录</h2><p>你的账号没有读取{comments ? '评论' : '喜欢'}记录的权限。</p><Link className="action-link" to="/me">返回个人主页</Link></section> : error ? <ResourceState status="error" error={new Error(error)} onRetry={() => setRetryKey((value) => value + 1)} /> : items === null ? <ResourceState status="loading" /> : records.length ? <section className="library-list">{records.map((item) => {
      const cardId = item.aid || item.ref_id || item.card_id;
      const label = comments ? item.content || '留下了一条评论' : `内容 #${cardId || item.id}`;
      return <article className="library-item" key={item.id}><div><strong>{label}</strong><small>{item.created_at || '时间未提供'}</small></div>{cardId ? <Link to={`/post/${cardId}`}>查看{comments ? '原文' : '内容'} <span>→</span></Link> : <span className="library-unavailable">原内容暂不可用</span>}</article>;
    })}</section> : <section className="profile-boundary"><h2>这里还没有内容</h2><p>完成一次互动后，它会出现在这里。</p></section>}
  </main></AppShell>;
}
