import { useCallback, useMemo } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { useResource } from '../../app/use-resource.js';
import { useAuth } from '../../app/providers.jsx';
import { getProfileSnapshot, loadProfile } from '../../services/profile-service.js';
import { ProfileContentTabs } from './ProfileContentTabs.jsx';
import { Icon } from '../../ui/icons.jsx';
import { ActionMenu, useToast } from '../../ui/interaction.jsx';

function ProfileAvatar({ user }) {
  if (user.avatar) return <span className="profile-avatar"><img src={user.avatar} alt={`${user.username}的头像`} /></span>;
  return <span className="profile-avatar" aria-hidden="true">{user.username.slice(0, 1)}</span>;
}

function ProfileCover({ items }) {
  const cover = items.find((item) => item.mediaUrl || item.mediaItems?.length);
  const image = cover?.mediaUrl || cover?.mediaItems?.[0]?.url;
  return <div className="profile-cover" style={image ? { backgroundImage: `url(${image})` } : undefined}>
    <span>{image ? '最近留下的影像' : '分享你的生活，也整理你的收藏。'}</span>
  </div>;
}

function profileCount(value, fallback = '—') {
  if (value === null || value === undefined || value === '') return fallback;
  const number = Number(value);
  if (!Number.isFinite(number)) return String(value);
  return number >= 10000 ? `${(number / 10000).toFixed(number >= 100000 ? 0 : 1).replace('.0', '')}万` : String(number);
}

function ProfileShareAction() {
  const toast = useToast();
  const share = async () => {
    try {
      if (navigator.share) await navigator.share({ title: document.title, url: window.location.href });
      else if (navigator.clipboard) { await navigator.clipboard.writeText(window.location.href); toast({ body: '主页链接已复制。', uniqueID: 'profile-share' }); }
    } catch (error) {
      if (error?.name !== 'AbortError') toast({ body: '暂时无法分享主页。', type: 'error', uniqueID: 'profile-share-error' });
    }
  };
  return <button type="button" onClick={() => { void share(); }}>分享主页</button>;
}

function ProfileSkeleton() {
  return <section className="profile-skeleton" aria-label="个人内容加载中" role="status">
    <span className="sr-only">个人内容加载中</span>
    <div className="profile-skeleton-top" aria-hidden="true"><i /><i /><i /></div>
    <section className="profile-skeleton-hero" aria-hidden="true">
      <i className="profile-skeleton-cover" />
      <div className="profile-skeleton-identity"><i className="profile-skeleton-avatar" /><div><i className="profile-skeleton-name" /><i className="profile-skeleton-handle" /><i className="profile-skeleton-bio" /></div><div className="profile-skeleton-actions"><i /><i /></div></div>
      <div className="profile-skeleton-stats"><i /><i /><i /><i /></div>
    </section>
    <div className="profile-skeleton-body"><div className="profile-skeleton-tabs"><i /><i /><i /><i /><i /></div><div className="profile-skeleton-content"><i /><div><i className="profile-skeleton-post-title" /><i className="profile-skeleton-post-copy" /><i className="profile-skeleton-post-meta" /></div></div><div className="profile-skeleton-content is-media"><i /><div><i className="profile-skeleton-post-title" /><i className="profile-skeleton-post-meta" /></div></div><aside className="profile-skeleton-rail"><i /><i /><i /></aside></div>
  </section>;
}

function ProfilePostList({ items }) {
  return <section className="profile-post-list" aria-label="我的动态">
    {items.map((item) => {
      const media = item.mediaUrl || item.mediaItems?.[0]?.url;
      const isVideo = item.kind === 'video' || item.mediaItems?.some((entry) => entry.type === 'video');
      return <article className={`profile-post profile-post-${item.kind || 'text'}`} key={item.id}>
        <Link className="profile-post-visual" to={`/post/${item.id}`} aria-label={`查看内容：${item.title}`}>
          {media ? <img src={media} alt={item.mediaAlt || item.title} /> : <span className="profile-post-mark" aria-hidden="true">“</span>}
          {isVideo && <span className="profile-post-type"><Icon name="play" size={13} /> 视频</span>}
          {item.mediaCount > 1 && <span className="profile-post-count">{item.mediaCount}</span>}
        </Link>
        <div className="profile-post-copy">
          <h2><Link to={`/post/${item.id}`}>{item.title}</Link></h2>
          {item.body && <p>{item.body}</p>}
          <footer><span>{item.meta || '时间未提供'}</span><span aria-label={`${profileCount(item.likes, 0)} 个喜欢`}><Icon name="heart" size={13} /> {profileCount(item.likes, 0)}</span><span aria-label={`${profileCount(item.replies, 0)} 条评论`}><Icon name="comment" size={13} /> {profileCount(item.replies, 0)}</span></footer>
        </div>
      </article>;
    })}
  </section>;
}

export function ProfilePage() {
  const navigate = useNavigate();
  const { isAuthenticated, status: authStatus, error: authError, refresh: refreshAuth, can } = useAuth();
  const initial = useMemo(() => getProfileSnapshot(), []);
  const designPreview = initial.source === 'preview';
  const canViewProfile = designPreview || (can('users.read') && can('cards.read'));
  const loader = useCallback((signal) => isAuthenticated && canViewProfile || designPreview ? loadProfile({ signal }) : Promise.resolve({ source: 'unavailable', user: null, items: [] }), [canViewProfile, designPreview, isAuthenticated]);
  const resource = useResource(loader, initial);
  const data = resource.data || initial;
  const likes = data.items.reduce((sum, item) => sum + (Number(item.likes) || 0), 0);
  const relations = data.user ? { following: data.user.following, followers: data.user.followers } : {};

  if (!designPreview && authStatus === 'checking') return <AppShell><main className="profile-page"><ProfileSkeleton /></main></AppShell>;

  return (
    <AppShell>
      <main className="profile-page" aria-labelledby="profile-title">
        {!designPreview && authStatus === 'error' ? <ResourceState status="error" error={authError} onRetry={refreshAuth} /> : !designPreview && !isAuthenticated ? <section className="profile-boundary" role="status"><h1 id="profile-title">登录后查看你的空间</h1><p>登录后才能加载个人资料、发布内容和互动记录。</p><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : !canViewProfile ? <section className="profile-boundary" role="status"><h1 id="profile-title">当前账号无法查看个人空间</h1><p>账号已登录，但尚未获得个人资料与个人内容的读取权限。</p><Link className="action-link" to="/">返回首页</Link></section> : resource.status === 'loading' ? <ProfileSkeleton /> : <ResourceState status={resource.status} error={resource.error} onRetry={resource.reload} loadingLabel="个人内容加载中">
          {data.source === 'configuration-error' ? (
            <section className="profile-boundary" role="status">
              <h1 id="profile-title">个人主页暂不可用</h1><p>{data.detail}</p>
            </section>
          ) : data.user && (
            <>
              <div className="profile-mobile-top"><Link to="/" aria-label="返回首页"><Icon name="arrow" size={19} /></Link><strong>我的</strong><ActionMenu items={[{ label: '编辑资料', icon: <Icon name="edit" size={17} />, onClick: () => navigate('/me/edit') }, { label: '设置', icon: <Icon name="settings" size={17} />, onClick: () => navigate('/settings') }]} label="更多个人操作" icon={<Icon name="more" size={19} />} /></div>
              <section className="profile-hero">
                <ProfileCover items={data.items} />
                <div className="profile-identity">
                  <ProfileAvatar user={data.user} />
                  <div className="profile-copy"><h1 id="profile-title">{data.user.username}</h1><p className="profile-handle">@{data.user.handle || data.user.username}{data.user.location ? ` · ${data.user.location}` : ''}</p><p className="profile-bio">{data.user.bio || '这个人正在认真生活，也在慢慢填写自己的介绍。'}</p></div>
                  <div className="profile-actions"><ProfileShareAction /><Link to="/me/edit">编辑资料</Link></div>
                </div>
                <div className="profile-stats"><Link to="/me"><b>{data.items.length}</b><small>动态</small></Link><Link to={`/user/${data.user.handle || data.user.username}/following`}><b>{profileCount(relations.following)}</b><small>关注</small></Link><Link to={`/user/${data.user.handle || data.user.username}/followers`}><b>{profileCount(relations.followers)}</b><small>粉丝</small></Link><span><b>{profileCount(data.user.likesAndSaves ?? likes)}</b><small>获赞与收藏</small></span></div>
              </section>
              <div className="profile-layout">
                <div className="profile-content"><ProfileContentTabs />{data.items.length ? <ProfilePostList items={data.items} /> : <section className="profile-boundary"><h2>还没有发布内容</h2><p>完成第一篇分享后，它会出现在这里。</p><Link className="action-link action-link-primary" to="/compose">开始发布</Link></section>}</div>
                <aside className="profile-rail" aria-label="我的空间快捷入口"><section><h2>我的空间</h2><Link to="/drafts">草稿箱 <span>›</span></Link><Link to="/me/library/saved">收藏夹 <span>›</span></Link><Link to="/me/library/history">浏览记录 <span>›</span></Link></section><section><h2>内容记录</h2><Link to="/me/library/likes">喜欢 <span>›</span></Link><Link to="/me/library/comments">评论 <span>›</span></Link></section><section><h2>关系摘要</h2><p>{relations.followers === null || relations.followers === undefined ? '关注与粉丝关系将在数据可用后显示。' : `你有 ${profileCount(relations.followers, 0)} 位粉丝，最近的互动会在这里汇聚。`}</p><Link className="rail-action" to="/me/edit">完善资料</Link></section></aside>
              </div>
            </>
          )}
        </ResourceState>}
      </main>
    </AppShell>
  );
}
