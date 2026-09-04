import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { useResource } from '../../app/use-resource.js';
import { useAuth } from '../../app/providers.jsx';
import { getProfileSnapshot, getProfileTabSnapshot, loadProfile, loadProfileTab, updateProfile } from '../../services/profile-service.js';
import { updateProfileCover } from '../../services/settings-service.js';
import { clearHistory, removeSavedItem } from '../../services/community-service.js';
import { getAvatarUploadFeedback, getUserFacingError } from '../../app/user-facing-error.js';
import { ProfileContentTabs } from './ProfileContentTabs.jsx';
import { Avatar, Icon } from '../../ui/icons.jsx';
import { ActionMenu, FileInput, FormDialog, ScopedBanner, useToast } from '../../ui/interaction.jsx';
import { ProfileCardFeed, ProfileCardFeedSkeleton } from '../../components/ProfileCardFeed.jsx';
import { ProfileShell } from '../../components/ProfileShell.jsx';
import { buildProfileMetadata, formatProfileCount, ProfileIdentitySkeleton, ProfileIdentitySurface } from '../../components/ProfileIdentitySurface.jsx';
import { Button, ConfirmActionDialog, FieldStatus } from '../../ui/index.js';

const PROFILE_TABS = new Set(['content', 'likes', 'saved', 'history']);

// Keep client-side validation aligned with the active default storage policy.
const avatarMimeTypes = new Set(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
const avatarExtensions = new Set(['.jpg', '.jpeg', '.png', '.gif', '.webp']);
const avatarMaxBytes = 10 * 1024 * 1024;

function InlineProfileCover({ onSaved }) {
  const toast = useToast();
  const [isOpen, setIsOpen] = useState(false);
  const [file, setFile] = useState(null);
  const [busy, setBusy] = useState(false);
  const save = async () => {
    if (!file || busy) return;
    const valid = ['image/jpeg', 'image/png', 'image/webp'].includes(String(file.type).toLowerCase()) && file.size <= 10 * 1024 * 1024;
    if (!valid) { toast({ body: '背景图需为 JPG、PNG 或 WebP，且不超过 10 MiB。', type: 'error', uniqueID: 'profile-cover-invalid' }); return; }
    setBusy(true);
    try { const next = await updateProfileCover(file); onSaved(next); setFile(null); setIsOpen(false); toast({ body: '主页背景已更新。', uniqueID: 'profile-cover-updated' }); }
    catch (error) { toast({ body: getUserFacingError(error, { fallback: '背景图暂时无法更新，请稍后重试。' }), type: 'error', uniqueID: 'profile-cover-error' }); }
    finally { setBusy(false); }
  };
  return <><button className="profile-cover-edit" type="button" disabled={busy} onClick={() => setIsOpen(true)}><Icon name="upload" size={16} />更换背景</button><FormDialog className="profile-cover-dialog" isOpen={isOpen} onOpenChange={(open) => { if (!busy) { setIsOpen(open); if (!open) setFile(null); } }} title="编辑主页背景" subtitle="选择一张图片作为资料卡顶部背景。" isBusy={busy} footer={<><Button type="button" label="取消" variant="secondary" size="lg" isDisabled={busy} onClick={() => setIsOpen(false)} /><Button type="button" label="保存背景" variant="primary" size="lg" isDisabled={!file || busy} isLoading={busy} onClick={() => void save()} /></>}><FileInput label="选择背景图" description="JPG、PNG 或 WebP，最大 10 MiB" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" maxSize={10 * 1024 * 1024} value={file} onChange={setFile} placeholder="选择一张图片" mode="input" width="100%" /></FormDialog></>;
}

function getAvatarFileStatus(file) {
  if (!file) return null;
  const mime = String(file.type || '').toLowerCase();
  const name = String(file.name || '').toLowerCase();
  const extension = name.includes('.') ? name.slice(name.lastIndexOf('.')) : '';
  // Some browsers/Windows providers leave File.type empty. In that case the
  // filename extension is the reliable client-side signal; the server remains
  // authoritative and will still reject a spoofed payload during upload.
  if (!avatarMimeTypes.has(mime) && !avatarExtensions.has(extension)) {
    return { type: 'error', message: '图片格式不支持。请选择 JPG、PNG、GIF 或 WebP。' };
  }
  if (file.size > avatarMaxBytes) {
    return { type: 'error', message: '图片太大。请选择不超过 10MB 的图片。' };
  }
  return null;
}

function InlineProfileAvatar({ user, canEdit, onSaved }) {
  const navigate = useNavigate();
  const toast = useToast();
  const previewUrlRef = useRef('');
  const inspectedStatusRef = useRef(null);
  const [isOpen, setIsOpen] = useState(false);
  const [file, setFile] = useState(null);
  const [busy, setBusy] = useState(false);
  const [preview, setPreview] = useState('');
  const [fieldStatus, setFieldStatus] = useState(null);
  const [taskError, setTaskError] = useState(null);

  const releasePreview = () => {
    if (previewUrlRef.current) URL.revokeObjectURL(previewUrlRef.current);
    previewUrlRef.current = '';
    setPreview('');
  };

  useEffect(() => () => {
    if (previewUrlRef.current) URL.revokeObjectURL(previewUrlRef.current);
  }, []);

  const resetTask = () => {
    releasePreview();
    inspectedStatusRef.current = null;
    setFile(null);
    setFieldStatus(null);
    setTaskError(null);
  };

  const requestOpenChange = (open) => {
    if (busy) return;
    setIsOpen(open);
    if (!open) resetTask();
  };

  const inspectNativeFile = (event) => {
    inspectedStatusRef.current = getAvatarFileStatus(event.currentTarget.files?.[0]);
  };

  const chooseAvatar = (nextFile) => {
    const inspectedStatus = inspectedStatusRef.current;
    inspectedStatusRef.current = null;
    if (!nextFile) {
      releasePreview();
      setFile(null);
      setTaskError(null);
      setFieldStatus(inspectedStatus);
      return;
    }
    const nextStatus = inspectedStatus || getAvatarFileStatus(nextFile);
    if (nextStatus) {
      releasePreview();
      setFile(null);
      setTaskError(null);
      setFieldStatus(nextStatus);
      return;
    }
    releasePreview();
    try {
      const localUrl = URL.createObjectURL(nextFile);
      previewUrlRef.current = localUrl;
      setPreview(localUrl);
      setFile(nextFile);
      setFieldStatus(null);
      setTaskError(null);
    } catch {
      setFile(null);
      setFieldStatus({ type: 'error', message: '图片无法读取。请重新导出或选择其他图片。' });
    }
  };

  const saveAvatar = async () => {
    if (!file || busy) return;
    setTaskError(null);
    setBusy(true);
    try {
      const result = await updateProfile({ file });
      const nextAvatar = String(result.avatar || '').trim();
      if (!nextAvatar) throw new Error('头像上传成功，但服务端没有返回图片地址。');
      onSaved({ avatar: nextAvatar });
      setIsOpen(false);
      resetTask();
      toast({ body: '头像已更新。', uniqueID: 'profile-avatar-updated' });
    } catch (saveError) {
      const feedback = getAvatarUploadFeedback(saveError);
      if (feedback.scope === 'field') {
        setTaskError(null);
        setFieldStatus({ type: 'error', message: feedback.fieldStatus });
      } else {
        setTaskError(feedback);
      }
    } finally {
      setBusy(false);
    }
  };

  const handleTaskAction = () => {
    if (!taskError) return;
    if (taskError.action === 'retry') {
      void saveAvatar();
      return;
    }
    setIsOpen(false);
    resetTask();
    if (taskError.action === 'login') navigate('/auth/login');
  };

  if (!canEdit) return <Avatar label={user.username} src={user.avatar} tone="rose" size="md" className="profile-avatar" />;

  const bannerActionLabel = taskError?.action === 'retry' ? '重试上传' : taskError?.action === 'login' ? '重新登录' : '关闭';

  return <div className="profile-avatar-editor">
    <button className="profile-avatar-trigger" type="button" aria-label="更换头像" onClick={() => setIsOpen(true)}>
      <Avatar label={user.username} src={user.avatar} tone="rose" size="md" className="profile-avatar" />
      <span className="profile-avatar-edit-badge" aria-hidden="true"><Icon name="upload" size={16} /></span>
    </button>
    <FormDialog
      className="avatar-upload-dialog"
      isOpen={isOpen}
      onOpenChange={requestOpenChange}
      title="更换头像"
      subtitle="更新后会同步到主页、评论和消息。"
      isBusy={busy}
      width="min(480px, calc(100vw - 32px))"
      footer={<><Button type="button" label="取消" variant="secondary" size="lg" isDisabled={busy} onClick={() => requestOpenChange(false)} /><Button type="button" label={busy ? '上传中' : '保存头像'} variant="primary" size="lg" isDisabled={!file || busy} isLoading={busy} onClick={() => { void saveAvatar(); }} /></>}
    >
      {taskError && <ScopedBanner className="avatar-upload-banner" title={taskError.title} description={taskError.description} actionLabel={bannerActionLabel} onAction={handleTaskAction} />}
      <div className="avatar-upload-preview">
        <div className="avatar-upload-preview-image" role="img" aria-label={file ? '新头像预览' : '当前头像'}><Avatar label={user.username} src={preview || user.avatar} tone="rose" size="lg" className="avatar-upload-preview-avatar" /></div>
        <div><strong>{file ? '新头像预览' : '当前头像'}</strong><span>{file ? '确认图片清晰后保存；也可以重新选择。' : '选择清晰的正方形图片，系统会生成圆形展示。'}</span></div>
      </div>
      <FileInput
        className="avatar-upload-file"
        label="选择图片"
        description="JPG、PNG、GIF 或 WebP，最大 10MB"
        value={file}
        onChange={chooseAvatar}
        onInputCapture={inspectNativeFile}
          accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp"
        maxSize={avatarMaxBytes}
        isLoading={busy}
        isDisabled={busy}
        disabledMessage="头像正在上传，请等待完成。"
        status={fieldStatus}
        statusVariant="detached"
        placeholder="选择一张图片"
        mode="input"
        width="100%"
      />
      {busy && <div className="avatar-upload-progress" role="status"><span className="avatar-upload-spinner" aria-hidden="true" /><span><strong>正在上传头像</strong><small>请保持页面打开</small></span></div>}
    </FormDialog>
  </div>;
}

function InlineProfileName({ user, canEdit, onSaved }) {
  const inputRef = useRef(null);
  const [editing, setEditing] = useState(false);
  const [value, setValue] = useState(user.username || '');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!editing) setValue(user.username || '');
  }, [editing, user.username]);

  useEffect(() => {
    if (editing) inputRef.current?.focus();
  }, [editing]);

  const openEditor = () => {
    setValue(user.username || '');
    setError('');
    setEditing(true);
  };
  const closeEditor = () => {
    if (busy) return;
    setEditing(false);
    setError('');
  };
  const saveName = async (event) => {
    event.preventDefault();
    const nextName = value.trim();
    if (nextName.length < 3 || nextName.length > 12) {
      setError('用户名需要 3–12 个字符。');
      return;
    }
    if (nextName === user.username) {
      closeEditor();
      return;
    }
    setBusy(true);
    setError('');
    try {
      const result = await updateProfile({ username: nextName });
      onSaved({ username: result.username || nextName });
      setEditing(false);
    } catch (saveError) {
      setError(getUserFacingError(saveError, { fallback: '用户名暂时没有保存，请稍后重试。', validationFallback: '用户名不符合要求，请输入 3–12 个字符。' }));
    } finally {
      setBusy(false);
    }
  };

  if (editing) return <form className="profile-name-editor" onSubmit={saveName}>
    <h1 id="profile-title" className="sr-only">编辑用户名</h1>
    <label className="sr-only" htmlFor="profile-name-input">用户名</label>
    <input ref={inputRef} id="profile-name-input" value={value} maxLength={12} disabled={busy} onChange={(event) => setValue(event.target.value)} onKeyDown={(event) => { if (event.key === 'Escape') { event.preventDefault(); closeEditor(); } }} />
    <button type="submit" className="profile-name-save" disabled={busy}>{busy ? '保存中' : '保存'}</button>
    <button type="button" className="profile-name-cancel" disabled={busy} onClick={closeEditor}>取消</button>
    <p className="profile-inline-note">当前用户名也用于主页地址。</p>
    {error && <FieldStatus type="error" message={error} variant="detached" />}
  </form>;

  return <h1 id="profile-title"><button className={`profile-name-trigger${canEdit ? '' : ' is-readonly'}`} type="button" disabled={!canEdit} aria-label={canEdit ? `编辑用户名，当前为 ${user.username}` : user.username} onClick={openEditor}>{user.username}<Icon name="edit" size={15} aria-hidden="true" /></button></h1>;
}

function InlineProfileBio({ user, canEdit, onSaved }) {
  const inputRef = useRef(null);
  const [editing, setEditing] = useState(false);
  const [value, setValue] = useState(user.bio || '');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const characterCount = Array.from(value).length;

  useEffect(() => {
    if (!editing) setValue(user.bio || '');
  }, [editing, user.bio]);

  useEffect(() => {
    if (editing) inputRef.current?.focus();
  }, [editing]);

  const closeEditor = () => {
    if (busy) return;
    setEditing(false);
    setError('');
  };

  const saveBio = async (event) => {
    event.preventDefault();
    const nextBio = value.trim();
    if (Array.from(nextBio).length > 160) {
      setError('个人简介最多 160 个字。');
      return;
    }
    if (nextBio === String(user.bio || '').trim()) {
      closeEditor();
      return;
    }
    setBusy(true);
    setError('');
    try {
      const result = await updateProfile({ bio: nextBio });
      onSaved({ bio: result.bio == null ? '' : String(result.bio) });
      setEditing(false);
    } catch (saveError) {
      setError(getUserFacingError(saveError, { fallback: '个人简介暂时没有保存，请稍后重试。', validationFallback: '个人简介不符合要求，请检查后重试。' }));
    } finally {
      setBusy(false);
    }
  };

  if (editing) return <form className="profile-bio-editor" onSubmit={saveBio}>
    <label htmlFor="profile-bio-input">个人简介</label>
    <textarea ref={inputRef} id="profile-bio-input" rows={4} value={value} disabled={busy} onChange={(event) => setValue(Array.from(event.target.value).slice(0, 160).join(''))} onKeyDown={(event) => { if (event.key === 'Escape') { event.preventDefault(); closeEditor(); } }} aria-describedby="profile-bio-count" />
    <div className="profile-bio-editor-footer"><small id="profile-bio-count">{characterCount}/160</small><button type="button" className="profile-name-cancel" disabled={busy} onClick={closeEditor}>取消</button><button type="submit" className="profile-name-save" disabled={busy}>{busy ? '保存中' : '保存'}</button></div>
    {error && <FieldStatus type="error" message={error} variant="detached" />}
  </form>;

  return <button className={`profile-bio-trigger${user.bio ? '' : ' is-empty'}${canEdit ? '' : ' is-readonly'}`} type="button" disabled={!canEdit} aria-label={canEdit ? `编辑个人简介${user.bio ? `，当前为 ${user.bio}` : ''}` : '个人简介'} onClick={() => { setValue(user.bio || ''); setError(''); setEditing(true); }}><span>{user.bio || '添加个人简介'}</span>{canEdit && <Icon name="edit" size={14} aria-hidden="true" />}</button>;
}

function profileCountLabel(value, unit) {
  const display = formatProfileCount(value);
  return display === '—' ? '暂未统计' : `${display} ${unit}`;
}

function ProfileSkeleton() {
  return <section className="profile-skeleton" aria-label="个人内容加载中" role="status">
    <span className="sr-only">个人内容加载中</span>
    <div className="profile-skeleton-top" aria-hidden="true"><i /><i /><i /></div>
    <ProfileIdentitySkeleton ariaLabel="个人资料加载中" />
    <div className="profile-skeleton-body"><ProfileContentTabs mode="self" contentCount="—" /><ProfileCardFeedSkeleton count={4} ariaLabel="个人内容加载中" /></div>
  </section>;
}

function unavailableRecordCard(item, section, index) {
  const deleted = Boolean(item?.deleted);
  const timestamp = section === 'history' ? item?.viewed_at : item?.created_at;
  const reason = deleted ? '原内容已删除' : '原内容暂不可用';
  const body = section === 'history'
    ? '这条浏览记录仍保留，但原内容已经无法访问。'
    : '这条互动记录仍保留，但原内容已经无法访问。';
  return {
    id: null,
    kind: 'text',
    unavailable: true,
    unavailableKey: item?.id || index,
    author: '原内容',
    authorHandle: null,
    title: reason,
    body,
    meta: timestamp || '时间未提供',
    likes: 0,
    replies: 0,
  };
}

function tabCopy(section) {
  if (section === 'likes') return { loading: '喜欢内容加载中', emptyTitle: '还没有喜欢的内容', emptyBody: '你留下喜欢的内容会集中显示在这里。' };
  if (section === 'saved') return { loading: '收藏内容加载中', emptyTitle: '还没有收藏内容', emptyBody: '在内容详情里收藏后，可以从这里快速找回。' };
  if (section === 'history') return { loading: '浏览历史加载中', emptyTitle: '还没有浏览记录', emptyBody: '打开内容详情后，最近浏览的卡片会出现在这里。' };
  return { loading: '个人内容加载中', emptyTitle: '还没有发布内容', emptyBody: '完成第一篇分享后，它会出现在这里。' };
}

function ProfileTabContent({ section, state, profile, onRetry, onRemoveSaved }) {
  const copy = tabCopy(section);
  if (!state || state.status === 'loading') return <ProfileCardFeedSkeleton count={4} ariaLabel={copy.loading} />;
  if (state.status === 'error') return <ResourceState className="profile-boundary profile-tab-error" status="error" title={copy.loading.replace('加载中', '暂时无法加载')} description={getUserFacingError(state.error, { fallback: `${copy.loading.replace('加载中', '')}暂时无法加载，请稍后重试。` })} onRetry={onRetry} />;
  const unavailableCards = (state.unavailable || []).map((item, index) => unavailableRecordCard(item, section, index));
  const feedItems = [...(state.items || []), ...unavailableCards];
  if (!feedItems.length) return <ResourceState className="profile-boundary profile-tab-empty" status="success" empty emptyTitle={copy.emptyTitle} emptyDescription={copy.emptyBody} emptyAction={section === 'content' ? <Link className="action-link action-link-primary" to="/compose">开始发布</Link> : undefined} />;
  return <ProfileCardFeed items={feedItems} profile={profile} ariaLabel={`${copy.emptyTitle.replace('还没有', '')}内容`} renderAction={section === 'saved' ? onRemoveSaved : undefined} />;
}

function resolveProfileTab(search, initialSection) {
  const query = new URLSearchParams(search).get('tab');
  const value = query || initialSection || 'content';
  return PROFILE_TABS.has(value) ? value : 'content';
}

export function ProfilePage({ initialSection = 'content' }) {
  const navigate = useNavigate();
  const location = useLocation();
  const toast = useToast();
  const { isAuthenticated, status: authStatus, error: authError, refresh: refreshAuth, can } = useAuth();
  const initial = useMemo(() => getProfileSnapshot(), []);
  const designPreview = initial.source === 'preview';
  const canViewProfile = designPreview || (can('users.read') && can('cards.read'));
  const activeTab = resolveProfileTab(location.search, initialSection);
  const loader = useCallback((signal) => (isAuthenticated && canViewProfile) || designPreview ? loadProfile({ signal }) : Promise.resolve({ source: 'unavailable', user: null, items: [] }), [canViewProfile, designPreview, isAuthenticated]);
  const resource = useResource(loader, initial);
  const data = resource.data || initial;
  const [tabStates, setTabStates] = useState(() => designPreview ? {
    likes: getProfileTabSnapshot('likes'),
    saved: getProfileTabSnapshot('saved'),
    history: getProfileTabSnapshot('history'),
  } : {});
  const [tabRetry, setTabRetry] = useState(0);
  const [identityPatch, setIdentityPatch] = useState({});
  const [assetBusy, setAssetBusy] = useState('');
  const [clearOpen, setClearOpen] = useState(false);
  const [clearing, setClearing] = useState(false);
  const profileUser = data.user ? { ...data.user, ...identityPatch } : data.user;
  const relations = profileUser ? { following: profileUser.following, followers: profileUser.followers } : {};
  const contentCount = data.contentCount;
  const canEditIdentity = data.source === 'live' && isAuthenticated && can('users.update');
  const canUploadAvatar = canEditIdentity && can('files.upload');

  useEffect(() => {
    setIdentityPatch({});
  }, [data.user?.id]);

  const saveIdentity = (patch) => {
    setIdentityPatch((current) => {
      const next = { ...current, ...patch };
      if (patch.username !== undefined && (!data.user?.handle || data.user.handle === data.user.username)) next.handle = patch.username;
      return next;
    });
    refreshAuth();
  };

  const shareProfile = useCallback(async () => {
    try {
      if (navigator.share) await navigator.share({ title: document.title, url: window.location.href });
      else if (navigator.clipboard) { await navigator.clipboard.writeText(window.location.href); toast({ body: '主页链接已复制。', uniqueID: 'profile-share' }); }
    } catch (error) {
      if (error?.name !== 'AbortError') toast({ body: '暂时无法分享主页。', type: 'error', uniqueID: 'profile-share-error' });
    }
  }, [toast]);

  useEffect(() => {
    if (activeTab === 'content' || !data.user || (!designPreview && (!isAuthenticated || !canViewProfile)) || (designPreview && tabRetry === 0)) return undefined;
    let active = true;
    setTabStates((current) => ({ ...current, [activeTab]: { ...getProfileTabSnapshot(activeTab), status: 'loading' } }));
    loadProfileTab(activeTab).then((result) => {
      if (active) setTabStates((current) => ({ ...current, [activeTab]: result }));
    }).catch((error) => {
      if (active) setTabStates((current) => ({ ...current, [activeTab]: { status: 'error', items: [], unavailable: [], error } }));
    });
    return () => { active = false; };
  }, [activeTab, canViewProfile, data.user, designPreview, isAuthenticated, tabRetry]);

  const currentTabState = activeTab === 'content'
    ? { status: resource.status, items: data.items, unavailable: [], error: resource.error }
    : tabStates[activeTab];

  const retryTab = () => setTabRetry((value) => value + 1);
  const removeSaved = async (card) => {
    const item = card?.libraryRecord;
    const resourceId = String(item?.resource_id || '').trim();
    if (!item || !resourceId || assetBusy) return;
    setAssetBusy(resourceId);
    try {
      await removeSavedItem({ resource_type: String(item.resource_type || 'card'), resource_id: resourceId });
      setTabStates((current) => {
        const saved = current.saved;
        if (!saved) return current;
        return { ...current, saved: { ...saved, items: saved.items.filter((entry) => String(entry.libraryRecord?.resource_id) !== resourceId), records: saved.records?.filter((entry) => String(entry.resource_id) !== resourceId) } };
      });
      toast({ body: '已取消收藏。', uniqueID: 'profile-saved-removed' });
    } catch (error) { toast({ body: getUserFacingError(error, { fallback: '暂时无法取消收藏，请稍后重试。' }), type: 'error', uniqueID: 'profile-saved-remove-error' }); }
    finally { setAssetBusy(''); }
  };
  const renderSavedAction = (card) => {
    const resourceId = String(card?.libraryRecord?.resource_id || '').trim();
    if (!resourceId) return null;
    const busy = assetBusy === resourceId;
    return <button className="profile-card-action" type="button" disabled={busy} aria-label="取消收藏" onClick={(event) => { event.stopPropagation(); void removeSaved(card); }}>{busy ? '处理中' : '取消收藏'}</button>;
  };
  const clearHistoryAction = async () => {
    if (clearing) return;
    setClearing(true);
    try {
      await clearHistory();
      setTabStates((current) => ({ ...current, history: { ...(current.history || { status: 'success' }), status: 'success', items: [], records: [], unavailable: [], hasMore: false } }));
      setClearOpen(false);
      toast({ body: '浏览历史已清空。', uniqueID: 'profile-history-cleared' });
    } catch (error) { toast({ body: getUserFacingError(error, { fallback: '浏览历史暂时没有清空，请稍后重试。' }), type: 'error', uniqueID: 'profile-history-clear-error' }); }
    finally { setClearing(false); }
  };

  if (!designPreview && authStatus === 'checking') return <AppShell><ProfileShell title="我的" ariaLabel="个人主页加载中"><ProfileSkeleton /></ProfileShell></AppShell>;

  const mobileActions = <ActionMenu items={[{ label: '完整资料', icon: <Icon name="edit" size={17} />, onClick: () => navigate('/settings/profile') }, { label: '分享主页', icon: <Icon name="share" size={17} />, onClick: () => { void shareProfile(); } }, { label: '设置', icon: <Icon name="settings" size={17} />, onClick: () => navigate('/settings/appearance') }]} label="更多个人操作" icon={<Icon name="more" size={19} />} />;

  return <AppShell>
    <ProfileShell title="我的" ariaLabel={profileUser?.username || '个人主页'} mobileActions={mobileActions}>
      {!designPreview && authStatus === 'error' ? <ResourceState status="error" error={authError} onRetry={refreshAuth} /> : !designPreview && !isAuthenticated ? <section className="profile-boundary" role="status"><h1 id="profile-title">登录后查看你的空间</h1><p>登录后才能加载个人资料、发布内容和互动记录。</p><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : !canViewProfile ? <section className="profile-boundary" role="status"><h1 id="profile-title">当前账号无法查看个人空间</h1><p>账号已登录，但尚未获得个人资料与个人内容的读取权限。</p><Link className="action-link" to="/">返回首页</Link></section> : resource.status === 'loading' ? <ProfileSkeleton /> : <ResourceState status={resource.status} error={resource.error} onRetry={resource.reload} loadingLabel="个人内容加载中">
        {data.source === 'configuration-error' ? <section className="profile-boundary" role="status"><h1 id="profile-title">个人主页暂不可用</h1><p>{data.detail}</p></section> : data.user && <>
          <ProfileIdentitySurface
            mode="owner"
            ariaLabel="个人资料卡"
            contextLabel="我的"
            avatar={<InlineProfileAvatar user={profileUser} canEdit={canUploadAvatar} onSaved={saveIdentity} />}
            name={<InlineProfileName user={profileUser} canEdit={canEditIdentity} onSaved={saveIdentity} />}
            uid={profileUser?.uid}
            location={profileUser?.location}
            bio={profileUser?.bio}
            bioContent={<InlineProfileBio user={profileUser} canEdit={canEditIdentity} onSaved={saveIdentity} />}
            metadata={buildProfileMetadata(profileUser)}
            coverUrl={profileUser?.cover_url || profileUser?.cover || ''}
            coverAction={canEditIdentity ? <InlineProfileCover onSaved={(next) => saveIdentity({ cover_url: next?.cover_url || null })} /> : null}
            menu={mobileActions}
            actions={<Link className="profile-edit-profile-action" to="/settings/profile">编辑资料</Link>}
            stats={<><span className="profile-stat" aria-label={`累计获赞，${profileCountLabel(profileUser.receivedLikes, '条')}`}><b>{formatProfileCount(profileUser.receivedLikes)}</b><small>获赞</small></span><Link className="profile-stat-link" to="/me/following" aria-label={`查看关注，${profileCountLabel(relations.following, '人')}`}><b>{formatProfileCount(relations.following)}</b><small>关注</small></Link><Link className="profile-stat-link" to="/me/followers" aria-label={`查看粉丝，${profileCountLabel(relations.followers, '人')}`}><b>{formatProfileCount(relations.followers)}</b><small>粉丝</small></Link></>}
          />
          <div className="profile-layout"><div className="profile-content">
            <ProfileContentTabs mode="self" contentCount={contentCount} />
            {activeTab === 'history' && currentTabState?.status === 'success' && currentTabState.items?.length > 0 && <div className="profile-tab-toolbar"><span>最近浏览的内容</span><button className="profile-clear-history" type="button" disabled={clearing} onClick={() => setClearOpen(true)}>清空历史</button></div>}
            <ProfileTabContent section={activeTab} state={currentTabState} profile={profileUser} onRetry={retryTab} onRemoveSaved={activeTab === 'saved' ? renderSavedAction : undefined} />
          </div></div>
          {clearOpen && <ConfirmActionDialog isOpen onOpenChange={(open) => { if (!clearing) setClearOpen(open); }} title="清空全部浏览历史？" description="此操作只删除你的历史记录，不会影响收藏、喜欢或原内容。" actionLabel="清空历史" isLoading={clearing} onAction={clearHistoryAction} />}
        </>}
      </ResourceState>}
    </ProfileShell>
  </AppShell>;
}
