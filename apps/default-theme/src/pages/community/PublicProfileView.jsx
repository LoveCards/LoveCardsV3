import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { ReportResourceDialog } from '../../features/moderation/ReportResourceDialog.jsx';
import { AppShell } from '../../layouts/AppShell.jsx';
import { blockUser, followUser, getPublicProfile, getUserRelation, listFollowers, listFollowing, unblockUser, unfollowUser } from '../../services/community-service.js';
import { Button, ConfirmActionDialog, useToast } from '../../ui/index.js';

const avatarLabel = (profile) => String(profile?.username || profile?.number || '?').slice(0, 1);

export function PublicProfileView({ relation: relationProp }) {
  const { handle, relation: routeRelation } = useParams();
  const relation = relationProp || routeRelation;
  const navigate = useNavigate();
  const auth = useAuth();
  const toast = useToast();
  const relationPage = relation === 'followers' || relation === 'following';
  const [state, setState] = useState({ status: 'loading', profile: null, rows: [], error: null });
  const [following, setFollowing] = useState(false);
  const [relationKnown, setRelationKnown] = useState(false);
  const [busy, setBusy] = useState('');
  const [blocked, setBlocked] = useState(false);
  const [blockOpen, setBlockOpen] = useState(false);
  const [reportOpen, setReportOpen] = useState(false);

  const load = useCallback(async () => {
    setState((current) => ({ ...current, status: 'loading', error: null }));
    try {
      const profile = await getPublicProfile(handle);
      const rows = relationPage && auth.isAuthenticated
        ? (relation === 'followers' ? await listFollowers(Number(profile.id)) : await listFollowing(Number(profile.id))).items
        : [];
      setState({ status: 'success', profile, rows, error: null });
      if (auth.isAuthenticated && Number(auth.session?.uid) !== Number(profile.id)) {
        try { const result = await getUserRelation(Number(profile.id)); setFollowing(Boolean(result?.following)); setRelationKnown(true); }
        catch { setRelationKnown(false); }
      }
    } catch (error) { setState((current) => ({ ...current, status: 'error', error })); }
  }, [auth.isAuthenticated, auth.session?.uid, handle, relation, relationPage]);
  useEffect(() => { void load(); }, [load]);

  const toggleFollow = async () => {
    const profile = state.profile;
    if (!profile || busy) return;
    if (!auth.isAuthenticated) { toast({ body: '登录后才能关注用户。', uniqueID: 'follow-login' }); navigate('/auth/login', { state: { from: `/user/${handle}` } }); return; }
    const previous = following;
    const previousCount = Number(profile.followers ?? profile.counts?.followers ?? 0);
    setBusy('follow'); setFollowing(!previous); setRelationKnown(true);
    setState((current) => ({ ...current, profile: { ...current.profile, followers: Math.max(0, previousCount + (previous ? -1 : 1)) } }));
    try { if (previous) await unfollowUser(Number(profile.id)); else await followUser(Number(profile.id)); }
    catch (error) { setFollowing(previous); setState((current) => ({ ...current, profile: { ...current.profile, followers: previousCount } })); toast({ body: error?.message || '关注操作失败。', type: 'error', uniqueID: 'follow-error' }); }
    finally { setBusy(''); }
  };
  const toggleBlock = async () => {
    if (!state.profile || busy) return;
    setBusy('block');
    try { if (blocked) await unblockUser(Number(state.profile.id)); else await blockUser(Number(state.profile.id)); setBlocked(!blocked); setBlockOpen(false); toast({ body: blocked ? '已解除拉黑。' : '已拉黑该用户。', uniqueID: 'block-updated' }); }
    catch (error) { toast({ body: error?.message || '操作失败。', type: 'error', uniqueID: 'block-error' }); }
    finally { setBusy(''); }
  };
  const profile = state.profile;
  const ownProfile = profile && Number(auth.session?.uid) === Number(profile.id);
  const followers = Number(profile?.followers ?? profile?.counts?.followers ?? 0);
  const followingCount = Number(profile?.following ?? profile?.counts?.following ?? 0);
  return <AppShell><main className="community-page public-profile-page" aria-labelledby="public-profile-title">
    {relationPage && <Link className="detail-back" to={`/user/${handle}`}>← 返回主页</Link>}
    <ResourceState status={state.status} error={state.error} onRetry={load}>{profile && <>
      <section className="public-profile-hero"><div className="public-profile-cover" /><div className="public-profile-identity"><span className="public-profile-avatar">{profile.avatar ? <img src={profile.avatar} alt={`${profile.username}的头像`} /> : avatarLabel(profile)}</span><div className="public-profile-copy"><p className="community-context">社区成员</p><h1 id="public-profile-title">{profile.username || handle}</h1><p>@{profile.number || profile.username || handle}</p></div><div className="public-profile-actions">{ownProfile ? <Link className="action-link" to="/me/edit">编辑资料</Link> : <><Button type="button" label={following ? '已关注' : '关注'} variant={following ? 'secondary' : 'primary'} size="lg" isDisabled={Boolean(busy)} isLoading={busy === 'follow'} onClick={toggleFollow} /><Button type="button" label="举报" variant="ghost" size="lg" isDisabled={!auth.isAuthenticated || Boolean(busy)} onClick={() => setReportOpen(true)} /><Button type="button" label={blocked ? '解除拉黑' : '拉黑'} variant="ghost" size="lg" isDisabled={!auth.isAuthenticated || Boolean(busy)} onClick={() => setBlockOpen(true)} /></>}</div></div><div className="public-profile-stats"><Link to={`/user/${handle}/followers`}><strong>{followers}</strong><span>粉丝</span></Link><Link to={`/user/${handle}/following`}><strong>{followingCount}</strong><span>关注</span></Link><span><strong>{relationKnown ? (following ? '已关注' : '未关注') : '—'}</strong><small>{auth.isAuthenticated ? '你们的关系' : '登录后查看关系'}</small></span></div></section>
      {relationPage ? <section className="relationship-section"><header><div><h2>{relation === 'followers' ? '粉丝' : '关注'}</h2><p>{profile.username} 的关系列表。</p></div></header>{!auth.isAuthenticated ? <div className="community-boundary"><h3>登录后查看关系列表</h3><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></div> : state.rows.length ? <div className="community-list">{state.rows.map((row, index) => <article className="community-row relationship-row" key={row.id || index}><span className="conversation-avatar">{avatarLabel(row)}</span><div className="community-row-main"><strong>{row.username || row.number || `用户 ${index + 1}`}</strong><small>@{row.number || row.username || row.id}</small></div>{row.username ? <Link className="community-row-link" to={`/user/${row.username}`}>查看主页</Link> : <span className="community-unavailable">主页地址不可用</span>}</article>)}</div> : <div className="community-boundary"><h3>这里还没有记录</h3><p>新的关系建立后会显示在这里。</p></div>}</section> : <section className="public-profile-content-boundary"><h2>公开内容</h2><p>当前 SDK 只提供公开资料与关系数据，尚未提供按公开用户查询内容的接口，因此这里不会展示伪造动态。</p></section>}
    </>}</ResourceState>
    <ConfirmActionDialog isOpen={blockOpen} onOpenChange={(open) => { if (!busy) setBlockOpen(open); }} title={blocked ? '解除拉黑这个用户？' : '拉黑这个用户？'} description={blocked ? '解除后仍受隐私设置控制。' : '拉黑后，对方的私信和部分互动会被服务器阻止。'} actionLabel={blocked ? '解除拉黑' : '确认拉黑'} isLoading={busy === 'block'} onAction={toggleBlock} />
    <ReportResourceDialog isOpen={reportOpen} onOpenChange={setReportOpen} resourceType="user" resourceId={profile?.id} title="举报用户" onFeedback={(body, type) => toast({ body, type: type === 'error' ? 'error' : undefined, uniqueID: type === 'error' ? 'report-error' : 'report-sent', collisionBehavior: 'overwrite' })} />
  </main></AppShell>;
}
