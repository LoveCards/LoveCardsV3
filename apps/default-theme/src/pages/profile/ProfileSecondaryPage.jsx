import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { ProfileRelationList, ProfileRelationSkeleton } from '../../components/ProfileRelationList.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { getProfileSnapshot, loadProfileIdentity } from '../../services/profile-service.js';
import { listFollowers, listFollowing } from '../../services/community-service.js';
import { Button, Icon } from '../../ui/index.js';
import { ScopedBanner } from '../../ui/interaction.jsx';
import { normalizeProfileUid } from '../../components/ProfileIdentitySurface.jsx';

const relationConfig = {
  following: { title: '关注', description: '你关注的用户会在这里显示。', countKey: 'following' },
  followers: { title: '粉丝', description: '关注你的用户会在这里显示。', countKey: 'followers' },
};

function profileCount(value) {
  if (value === null || value === undefined || value === '') return '—';
  const number = Number(value);
  if (!Number.isFinite(number)) return String(value);
  return number >= 10000 ? `${(number / 10000).toFixed(number >= 100000 ? 0 : 1).replace('.0', '')}万` : String(number);
}

function SecondaryTop({ title }) {
  return <>
    <div className="profile-secondary-mobile-top"><Link to="/me" aria-label="返回我的主页"><Icon name="arrow" size={19} /></Link><strong>{title}</strong><span aria-hidden="true" /></div>
    <Link className="profile-secondary-back" to="/me">← 返回我的主页</Link>
  </>;
}

function RelationSwitcher({ relation }) {
  return <nav className="profile-secondary-switcher" aria-label="关系类型">
    <Link className={relation === 'following' ? 'is-active' : ''} aria-current={relation === 'following' ? 'page' : undefined} to="/me/following">关注</Link>
    <Link className={relation === 'followers' ? 'is-active' : ''} aria-current={relation === 'followers' ? 'page' : undefined} to="/me/followers">粉丝</Link>
  </nav>;
}

export function ProfileRelationPage({ relation }) {
  const auth = useAuth();
  const initial = useMemo(() => getProfileSnapshot(), []);
  const designPreview = initial.source === 'preview';
  const config = relationConfig[relation] || relationConfig.following;
  const [profile, setProfile] = useState(initial.user);
  const [state, setState] = useState({ status: 'loading', items: [], error: null, nextCursor: null, hasMore: false, loadingMore: false, loadMoreError: null });
  const requestRef = useRef(0);
  const profileRef = useRef(initial.user);

  const load = useCallback(async (cursor = null, append = false) => {
    const requestId = append ? requestRef.current : requestRef.current + 1;
    if (!append) requestRef.current = requestId;
    if (designPreview) {
      const rows = relation === 'followers' ? (initial.user?.followerUsers || []) : (initial.user?.followingUsers || []);
      profileRef.current = initial.user;
      setProfile(initial.user);
      setState({ status: 'success', items: append ? rows : rows, error: null, nextCursor: null, hasMore: false, loadingMore: false, loadMoreError: null });
      return;
    }
    if (!auth.isAuthenticated) return;
    setState((current) => append
      ? { ...current, loadingMore: true, loadMoreError: null }
      : { ...current, status: 'loading', items: [], error: null, nextCursor: null, hasMore: false, loadingMore: false, loadMoreError: null });
    try {
      const loadedProfile = profileRef.current?.uid ? { user: profileRef.current } : await loadProfileIdentity();
      if (requestId !== requestRef.current) return;
      if (!loadedProfile?.user || !normalizeProfileUid(loadedProfile.user.uid)) throw new Error('当前账号没有有效的关系标识。');
      const loader = relation === 'followers' ? listFollowers : listFollowing;
      const result = await loader(loadedProfile.user.uid, { cursor, limit: 20 });
      if (requestId !== requestRef.current) return;
      profileRef.current = loadedProfile.user;
      setProfile(loadedProfile.user);
      setState((current) => ({ status: 'success', items: append ? [...current.items, ...(result.items || [])] : (result.items || []), error: null, nextCursor: result.nextCursor || null, hasMore: Boolean(result.hasMore), loadingMore: false, loadMoreError: null }));
    } catch (error) {
      if (requestId !== requestRef.current) return;
      setState((current) => append ? { ...current, loadingMore: false, loadMoreError: error } : { ...current, status: 'error', items: [], error });
    }
  }, [auth.isAuthenticated, designPreview, initial, relation]);

  useEffect(() => {
    if (designPreview || (auth.status === 'ready' && auth.isAuthenticated)) void load();
  }, [auth.isAuthenticated, auth.status, designPreview, load]);

  const count = profileCount(profile?.[config.countKey]);
  const content = !designPreview && auth.status === 'checking'
    ? <ProfileRelationSkeleton title={config.title} />
    : !designPreview && !auth.isAuthenticated
      ? <ResourceState status="empty" emptyTitle="登录后查看关系列表" emptyDescription="登录后才能查看你的关注与粉丝。" emptyAction={<Link className="action-link action-link-primary" to="/auth/login">去登录</Link>} />
      : state.status === 'loading'
        ? <ProfileRelationSkeleton title={config.title} />
        : state.status === 'error'
          ? <ResourceState status="error" error={state.error} title={`${config.title}暂时无法加载`} description={getUserFacingError(state.error, { fallback: `${config.title}暂时无法加载，请稍后重试。` })} onRetry={() => void load()} />
          : !state.items.length
            ? <ResourceState status="success" empty emptyTitle={`还没有${config.title}`} emptyDescription={config.description} />
            : <>
              <ProfileRelationList items={state.items} emptyLabel="用户" />
              {state.loadMoreError && <ScopedBanner className="profile-secondary-load-more-error" title={`更多${config.title}加载失败`} description={getUserFacingError(state.loadMoreError, { fallback: `更多${config.title}暂时无法加载，请稍后重试。` })} actionLabel="重试" onAction={() => void load(state.nextCursor, true)} />}
              {state.hasMore && !state.loadMoreError && <div className="profile-secondary-load-more"><Button type="button" label="加载更多" variant="secondary" size="lg" isDisabled={state.loadingMore} isLoading={state.loadingMore} onClick={() => void load(state.nextCursor, true)} /></div>}
            </>;

  return <AppShell mobileMode="task">
    <main className="profile-secondary-page" aria-labelledby="profile-secondary-title">
      <SecondaryTop title={config.title} />
      <header className="profile-secondary-header"><p>我的关系</p><h1 id="profile-secondary-title">{config.title}</h1><span>{count} 人 · {config.description}</span></header>
      <RelationSwitcher relation={relation} />
      <section className="profile-secondary-content" aria-label={`${config.title}列表`}>{content}</section>
    </main>
  </AppShell>;
}
