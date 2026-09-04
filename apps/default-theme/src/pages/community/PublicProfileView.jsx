import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { ProfileRelationList, ProfileRelationSkeleton } from '../../components/ProfileRelationList.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { ProfileCardFeed, ProfileCardFeedSkeleton } from '../../components/ProfileCardFeed.jsx';
import { ProfileShell } from '../../components/ProfileShell.jsx';
import { buildProfileMetadata, formatProfileCount, normalizeProfileUid, ProfileIdentitySkeleton, ProfileIdentitySurface } from '../../components/ProfileIdentitySurface.jsx';
import { ReportResourceDialog } from '../../features/moderation/ReportResourceDialog.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { blockUser, followUser, getPublicProfile, getUserRelation, listFollowers, listFollowing, listPublicCardsByUser, unblockUser, unfollowUser } from '../../services/community-service.js';
import { Avatar, Button, ConfirmActionDialog, Icon, useToast } from '../../ui/index.js';
import { ProfileContentTabs } from '../profile/ProfileContentTabs.jsx';
import { ActionMenu, ScopedBanner } from '../../ui/interaction.jsx';

function PublicProfileAvatar({ profile }) {
  return <Avatar label={profile?.username || '用户'} src={profile?.avatar} tone="rose" size="md" className="profile-identity-avatar-node" />;
}

function canonicalHandle(profile, fallback = '') {
  const value = normalizeProfileUid(profile?.uid);
  const fallbackValue = normalizeProfileUid(fallback);
  return value || fallbackValue || null;
}

function profilePath(profile, suffix = '', fallbackHandle = '') {
  const handle = canonicalHandle(profile, fallbackHandle);
  return handle ? `/user/${encodeURIComponent(handle)}${suffix ? `/${suffix}` : ''}` : null;
}

function relationLink(profile, suffix, children) {
  if (profile?.can_view_relations === false) return <span className="public-profile-stat-disabled" title="该用户不公开关系列表">{children}</span>;
  const to = profilePath(profile, suffix);
  return to ? <Link to={to}>{children}</Link> : <span className="public-profile-stat-disabled">{children}</span>;
}

function paginationHasMore(result, fallbackPage, pageSize = 30) {
  if (typeof result?.has_more === 'boolean') return result.has_more;
  const pagination = result?.pagination || {};
  if (typeof pagination.has_more === 'boolean') return pagination.has_more;
  const currentPage = Number(pagination.currentPage ?? pagination.current_page ?? fallbackPage);
  const totalPages = Number(pagination.totalPages ?? pagination.total_pages);
  if (Number.isFinite(totalPages)) return totalPages > currentPage;
  return Array.isArray(result?.data) && result.data.length >= pageSize;
}

function errorStatus(error) {
  return Number(error?.status ?? error?.response?.status ?? 0);
}

function isNetworkError(error) {
  return Number(error?.code) === -2 || Number(error?.code) === -3 || error?.reason === 'network' || error?.reason === 'timeout';
}

function PublicProfileError({ error, onRetry, className = 'public-profile-error' }) {
  const status = errorStatus(error);
  const title = status === 404 ? '这个用户不存在' : status === 403 ? '这个主页暂不可见' : isNetworkError(error) ? '主页暂时无法加载' : '主页加载失败';
  const detail = status === 404
    ? '用户可能已注销，或主页地址已经失效。'
    : status === 403
      ? '该用户限制了主页访问，请稍后再试。'
      : isNetworkError(error)
        ? '请检查网络连接后重试。'
        : getUserFacingError(error, { fallback: '用户主页暂时无法加载，请稍后重试。' });
  return <ResourceState status="error" className={className} title={title} description={detail} onRetry={onRetry} actionLabel="重试" />;
}

function PublicRelationError({ error, title, onRetry }) {
  const status = errorStatus(error);
  const heading = status === 404 ? '这个用户不存在' : status === 403 ? `${title}列表不可见` : `${title}暂时无法加载`;
  const detail = status === 404
    ? '用户可能已注销，或主页地址已经失效。'
    : status === 403
      ? '该用户限制了关系列表访问。'
      : getUserFacingError(error, { fallback: `${title}暂时无法加载，请稍后重试。` });
  return <ResourceState status="error" title={heading} description={detail} onRetry={onRetry} actionLabel="重试" />;
}

function PublicProfileSkeleton({ handle, relationPage = false }) {
  return <section className="public-profile-skeleton" aria-label="用户主页加载中" role="status">
    <span className="sr-only">用户主页加载中</span>
    <ProfileIdentitySkeleton ariaLabel="用户资料加载中" />
    {relationPage ? <div className="public-profile-skeleton-rows"><i /><i /><i /></div> : <><ProfileContentTabs mode="public" handle={handle} label="用户内容分类" /><ProfileCardFeedSkeleton count={4} ariaLabel="公开内容加载中" /></>}
  </section>;
}

function PublicProfileCards({ status, error, items, profile, hasMore, loadingMore, loadMoreError, onRetry, onLoadMore, onRetryMore }) {
  if (status === 'loading') return <ProfileCardFeedSkeleton count={4} className="public-profile-post-list" ariaLabel="公开内容加载中" />;
  if (status === 'error') {
    const code = errorStatus(error);
    const title = code === 404 ? '这个用户不存在' : code === 403 ? '公开内容不可见' : isNetworkError(error) ? '公开内容加载失败' : '公开内容暂不可用';
    const detail = code === 404 ? '用户可能已注销，或主页地址已经失效。' : code === 403 ? '该用户限制了公开内容访问。' : getUserFacingError(error, { fallback: '公开内容暂时无法加载，请稍后重试。' });
    return <ResourceState status="error" className="public-profile-card-state" title={title} description={detail} onRetry={onRetry} actionLabel="重试" />;
  }
  if (!items.length) return <ResourceState status="empty" className="public-profile-content-boundary is-empty" emptyTitle="还没有公开内容" emptyDescription="当这个用户发布公开内容后，会显示在这里。" />;
  const loadMoreCode = errorStatus(loadMoreError);
  const loadMoreTitle = loadMoreCode === 404 ? '这个用户不存在' : loadMoreCode === 403 ? '公开内容不可见' : isNetworkError(loadMoreError) ? '更多内容加载失败' : '更多内容暂不可用';
  const loadMoreDetail = loadMoreCode === 404 ? '用户可能已注销，或主页地址已经失效。' : loadMoreCode === 403 ? '该用户限制了公开内容访问。' : getUserFacingError(loadMoreError, { fallback: '更多公开内容暂时无法加载，请稍后重试。' });
  return <>
    <ProfileCardFeed items={items} profile={profile} className="public-profile-post-list" ariaLabel="公开内容" />
    {loadMoreError && <ScopedBanner className="public-profile-load-more-error" title={loadMoreTitle} description={loadMoreDetail} actionLabel="重试" onAction={onRetryMore} />}
    {hasMore && <div className="public-profile-load-more"><Button type="button" label="加载更多" variant="secondary" size="lg" isDisabled={loadingMore} isLoading={loadingMore} onClick={onLoadMore} /></div>}
  </>;
}

export function PublicProfileView({ relation: relationProp }) {
  const { uid: handle, relation: routeRelation } = useParams();
  const relation = relationProp || routeRelation;
  const navigate = useNavigate();
  const auth = useAuth();
  const toast = useToast();
  const relationPage = relation === 'followers' || relation === 'following';
  const [state, setState] = useState({ status: 'loading', profile: null, rows: [], error: null, cardsStatus: relationPage ? 'idle' : 'loading', cards: [], cardsError: null, cardsLoadMoreError: null, cardsPage: 1, cardsHasMore: false, cardsLoadingMore: false });
  const [following, setFollowing] = useState(false);
  const [busy, setBusy] = useState('');
  const [blocked, setBlocked] = useState(false);
  const [blockOpen, setBlockOpen] = useState(false);
  const [reportOpen, setReportOpen] = useState(false);
  const requestRef = useRef(0);

  const shareProfile = useCallback(async () => {
    const url = window.location.href;
    try {
      if (navigator.share) await navigator.share({ title: document.title, url });
      else if (navigator.clipboard) { await navigator.clipboard.writeText(url); toast({ body: '主页链接已复制。', uniqueID: 'public-profile-share' }); }
    } catch (error) {
      if (error?.name !== 'AbortError') toast({ body: '暂时无法分享主页。', type: 'error', uniqueID: 'public-profile-share-error' });
    }
  }, [toast]);

  const load = useCallback(async () => {
    const requestId = ++requestRef.current;
    setState((current) => ({ ...current, status: 'loading', error: null, cardsStatus: relationPage ? 'idle' : 'loading', cardsError: null, cardsLoadMoreError: null, cardsPage: 1, cardsHasMore: false, cardsLoadingMore: false }));
    try {
      const profile = await getPublicProfile(handle);
      if (requestId !== requestRef.current) return;
      const rows = relationPage && auth.isAuthenticated && (profile.can_view_relations !== false || auth.session?.uid === profile.uid)
        ? (relation === 'followers' ? await listFollowers(profile.uid) : await listFollowing(profile.uid)).items
        : [];
      let cards = [];
      let cardsStatus = 'idle';
      let cardsError = null;
      let cardsHasMore = false;
      if (!relationPage) {
        try {
          const result = await listPublicCardsByUser(handle, { page: 1, list_rows: 30 });
          if (requestId !== requestRef.current) return;
          cards = result.data;
          cardsHasMore = paginationHasMore(result, 1);
          cardsStatus = 'success';
        } catch (error) {
          if (requestId !== requestRef.current) return;
          cardsStatus = 'error';
          cardsError = error;
        }
      }
      if (requestId !== requestRef.current) return;
      setState({ status: 'success', profile, rows, error: null, cardsStatus, cards, cardsError, cardsLoadMoreError: null, cardsPage: 1, cardsHasMore, cardsLoadingMore: false });
      if (!relationPage && auth.isAuthenticated && auth.session?.uid !== profile.uid) {
        try { const result = await getUserRelation(profile.uid); if (requestId !== requestRef.current) return; setFollowing(Boolean(result?.following)); setBlocked(Boolean(result?.blocked_by_me)); }
        catch { /* Relation state remains unknown until the next successful read. */ }
      }
    } catch (error) {
      if (requestId !== requestRef.current) return;
      setState((current) => ({ ...current, status: 'error', error }));
    }
  }, [auth.isAuthenticated, auth.session?.uid, handle, relation, relationPage]);
  useEffect(() => { void load(); }, [load]);

  const loadMoreCards = async () => {
    if (relationPage || state.cardsStatus !== 'success' || !state.cardsHasMore || state.cardsLoadingMore) return;
    const requestId = requestRef.current;
    const nextPage = state.cardsPage + 1;
    setState((current) => ({ ...current, cardsLoadingMore: true, cardsLoadMoreError: null }));
    try {
      const result = await listPublicCardsByUser(handle, { page: nextPage, list_rows: 30 });
      if (requestId !== requestRef.current) return;
      const incoming = Array.isArray(result.data) ? result.data : [];
      const existing = new Set(state.cards.map((item) => String(item.id)));
      const cards = [...state.cards, ...incoming.filter((item) => !existing.has(String(item.id)))];
      setState((current) => ({ ...current, cards, cardsPage: nextPage, cardsHasMore: paginationHasMore(result, nextPage), cardsLoadingMore: false, cardsLoadMoreError: null }));
    } catch (error) {
      if (requestId !== requestRef.current) return;
      setState((current) => ({ ...current, cardsLoadingMore: false, cardsLoadMoreError: error }));
    }
  };

  const toggleFollow = async () => {
    const profile = state.profile;
    if (!profile || busy) return;
    if (!auth.isAuthenticated) { toast({ body: '登录后才能关注用户。', uniqueID: 'follow-login' }); navigate('/auth/login', { state: { from: profilePath(profile, '', handle) || `/user/${handle}` } }); return; }
    const previous = following;
    const previousCount = Number(profile.followers ?? profile.counts?.followers ?? 0);
    setBusy('follow'); setFollowing(!previous);
    setState((current) => ({ ...current, profile: { ...current.profile, followers: Math.max(0, previousCount + (previous ? -1 : 1)) } }));
    try { if (blocked) throw Object.assign(new Error('关系受到限制'), { reason_code: 'blocked_relationship' }); if (previous) await unfollowUser(profile.uid); else await followUser(profile.uid); }
    catch (error) { setFollowing(previous); setState((current) => ({ ...current, profile: { ...current.profile, followers: previousCount } })); toast({ body: getUserFacingError(error, { fallback: previous ? '暂时无法取消关注，请稍后重试。' : '暂时无法关注该用户，请稍后重试。' }), type: 'error', uniqueID: 'follow-error' }); }
    finally { setBusy(''); }
  };
  const startMessage = () => {
    if (blocked) { toast({ body: '你与该用户的关系受到限制，暂时无法发送私信。', type: 'error', uniqueID: 'message-blocked' }); return; }
    const targetHandle = canonicalHandle(state.profile, handle);
    if (!auth.isAuthenticated) {
      toast({ body: '登录后才能发送私信。', uniqueID: 'message-login' });
      navigate('/auth/login', { state: { from: profilePath(state.profile, '', handle) || `/user/${handle}` } });
      return;
    }
    if (!targetHandle) {
      toast({ body: '该用户没有可用的主页身份。', type: 'error', uniqueID: 'message-invalid-profile' });
      return;
    }
    navigate(`/messages/new/${encodeURIComponent(targetHandle)}`);
  };
  const toggleBlock = async () => {
    if (!state.profile || busy) return;
    setBusy('block');
    try { if (blocked) await unblockUser(state.profile.uid); else await blockUser(state.profile.uid); setBlocked(!blocked); setBlockOpen(false); toast({ body: blocked ? '已解除拉黑。' : '已拉黑该用户。', uniqueID: 'block-updated' }); }
    catch (error) { toast({ body: getUserFacingError(error, { fallback: blocked ? '暂时无法解除拉黑，请稍后重试。' : '暂时无法拉黑该用户，请稍后重试。' }), type: 'error', uniqueID: 'block-error' }); }
    finally { setBusy(''); }
  };
  const profile = state.profile;
  const profileHandle = canonicalHandle(profile, handle);
  const relationBackPath = profilePath(profile, '', handle);
  const ownProfile = Boolean(profile && normalizeProfileUid(auth.session?.uid) === normalizeProfileUid(profile.uid));
  const followers = Number(profile?.followers ?? profile?.counts?.followers ?? 0);
  const followingCount = Number(profile?.following ?? profile?.counts?.following ?? 0);
  const profileMenu = <ActionMenu items={[{ label: '分享主页', icon: <Icon name="share" size={17} />, onClick: () => { void shareProfile(); } }, ...(!ownProfile && auth.isAuthenticated ? [{ label: '举报', icon: <Icon name="flag" size={17} />, onClick: () => { if (!busy) setReportOpen(true); } }, { label: blocked ? '解除拉黑' : '拉黑', icon: <Icon name="close" size={17} />, onClick: () => { if (!busy) setBlockOpen(true); } }] : [])]} label="更多主页操作" icon={<Icon name="more" size={19} />} />;
  if (ownProfile) {
    const ownPath = relation === 'followers' ? '/me/followers' : relation === 'following' ? '/me/following' : '/me';
    return <Navigate to={ownPath} replace />;
  }
  if (relationPage) {
    const title = relation === 'followers' ? '粉丝' : '关注';
    const count = relation === 'followers' ? followers : followingCount;
    const backPath = relationBackPath || `/user/${encodeURIComponent(handle || '')}`;
    const followersPath = profilePath(profile, 'followers', handle) || backPath;
    const followingPath = profilePath(profile, 'following', handle) || backPath;
    const ownerLabel = profile?.username || (profile?.uid ? `UID ${profile.uid}` : '该用户');
    const description = relation === 'followers' ? `${ownerLabel} 的粉丝会显示在这里。` : `${ownerLabel} 关注的用户会显示在这里。`;
    const relationContent = state.status === 'loading'
      ? <ProfileRelationSkeleton title={title} />
      : state.status === 'error'
        ? <PublicRelationError error={state.error} title={title} onRetry={load} />
        : !auth.isAuthenticated
          ? <ResourceState status="empty" emptyTitle="登录后查看关系列表" emptyDescription="登录后才能查看完整的关系列表。" emptyAction={<Link className="action-link action-link-primary" to="/auth/login">去登录</Link>} />
          : state.rows.length
            ? <ProfileRelationList items={state.rows} emptyLabel="用户" />
            : <ResourceState status="empty" emptyTitle={`还没有${title}`} emptyDescription={description} />;

    return <AppShell mobileMode="task"><main className="profile-secondary-page public-profile-relation-page" aria-labelledby="public-profile-relation-title">
      <div className="profile-secondary-mobile-top"><Link to={backPath} aria-label="返回用户主页"><Icon name="arrow" size={19} /></Link><strong>{title}</strong><span aria-hidden="true" /></div>
      <Link className="profile-secondary-back" to={backPath}>← 返回用户主页</Link>
      <header className="profile-secondary-header"><p>用户关系</p><h1 id="public-profile-relation-title">{title}</h1><span>{state.status === 'success' ? `${count} 人` : '—'} · {description}</span></header>
      <nav className="profile-secondary-switcher" aria-label="关系类型">
        <Link className={relation === 'following' ? 'is-active' : ''} aria-current={relation === 'following' ? 'page' : undefined} to={followingPath}>关注</Link>
        <Link className={relation === 'followers' ? 'is-active' : ''} aria-current={relation === 'followers' ? 'page' : undefined} to={followersPath}>粉丝</Link>
      </nav>
      <section className="profile-secondary-content" aria-label={`${title}列表`}>{relationContent}</section>
    </main></AppShell>;
  }

  return <AppShell mobileMode="standard"><ProfileShell mode="public" title={profile?.username || '用户主页'} ariaLabel={profile?.username || '用户主页'} mobileActions={profileMenu}>
    {state.status === 'loading' ? <PublicProfileSkeleton handle={handle} /> : state.status === 'error' ? <PublicProfileError error={state.error} onRetry={load} /> : profile && <>
       <ProfileIdentitySurface
         mode="public"
         ariaLabel={`${profile.username || '用户'}的公开资料`}
         contextLabel="用户主页"
         avatar={<PublicProfileAvatar profile={profile} />}
         name={<h1 id="public-profile-title">{profile.username || '用户主页'}</h1>}
         uid={profile?.uid}
         location={profile?.location}
         bio={profile?.bio}
         metadata={buildProfileMetadata(profile)}
         coverUrl={profile?.cover_url || profile?.cover || ''}
         menu={profileMenu}
         actions={<><Button type="button" label={following ? '已关注' : '关注'} variant={following ? 'secondary' : 'primary'} size="lg" isDisabled={Boolean(busy)} isLoading={busy === 'follow'} onClick={toggleFollow} /><Button type="button" label="发私信" variant="secondary" size="lg" isDisabled={Boolean(busy) || !profileHandle} onClick={startMessage} /></>}
         stats={<><span aria-label={`获赞，${formatProfileCount(profile?.stats?.received_likes, '暂未统计')} 条`}><b>{formatProfileCount(profile?.stats?.received_likes)}</b><small>获赞</small></span>{relationLink(profile, 'following', <><b>{formatProfileCount(followingCount)}</b><small>关注</small></>)}{relationLink(profile, 'followers', <><b>{formatProfileCount(followers)}</b><small>粉丝</small></>)}</>}
       />
      <ProfileContentTabs mode="public" handle={profileHandle || handle} label="用户内容分类" />
      <PublicProfileCards status={state.cardsStatus} error={state.cardsError} items={state.cards} profile={profile} hasMore={state.cardsHasMore} loadingMore={state.cardsLoadingMore} loadMoreError={state.cardsLoadMoreError} onRetry={load} onLoadMore={loadMoreCards} onRetryMore={loadMoreCards} />
    </>}
    <ConfirmActionDialog isOpen={blockOpen} onOpenChange={(open) => { if (!busy) setBlockOpen(open); }} title={blocked ? '解除拉黑这个用户？' : '拉黑这个用户？'} description={blocked ? '解除后仍受隐私设置控制。' : '拉黑后，对方的私信和部分互动会被服务器阻止。'} actionLabel={blocked ? '解除拉黑' : '确认拉黑'} isLoading={busy === 'block'} onAction={toggleBlock} />
    <ReportResourceDialog isOpen={reportOpen} onOpenChange={setReportOpen} resourceType="user" resourceId={profile?.uid} title="举报用户" onFeedback={(body, type) => toast({ body, type: type === 'error' ? 'error' : undefined, uniqueID: type === 'error' ? 'report-error' : 'report-sent', collisionBehavior: 'overwrite' })} />
  </ProfileShell></AppShell>;
}
