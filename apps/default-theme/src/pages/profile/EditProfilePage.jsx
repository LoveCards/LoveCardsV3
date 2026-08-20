import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { useResource } from '../../app/use-resource.js';
import { useUnsavedChanges } from '../../app/use-unsaved-changes.js';
import { useAuth } from '../../app/providers.jsx';
import { getProfileSnapshot, loadProfile, updateProfile } from '../../services/profile-service.js';
import { Button, ConfirmActionDialog, ImageDropzone, TextInput, useToast } from '../../ui/index.js';

export function EditProfilePage() {
  const toast = useToast();
  const navigate = useNavigate();
  const { isAuthenticated, status: authStatus, error: authError, refresh: refreshAuth, can } = useAuth();
  const initial = useMemo(() => getProfileSnapshot(), []);
  const designPreview = initial.source === 'preview';
  const canEditProfile = designPreview || (can('users.read') && can('users.update'));
  const canUploadAvatar = designPreview || can('files.upload');
  const loader = useCallback((signal) => isAuthenticated && canEditProfile || designPreview ? loadProfile({ signal }) : Promise.resolve({ source: 'unavailable', user: null, items: [] }), [canEditProfile, designPreview, isAuthenticated]);
  const resource = useResource(loader, initial);
  const data = resource.data || initial;
  const [username, setUsername] = useState('');
  const [avatar, setAvatar] = useState('');
  const [avatarFile, setAvatarFile] = useState(null);
  const [status, setStatus] = useState('idle');
  const [message, setMessage] = useState('');
  const [discardOpen, setDiscardOpen] = useState(false);
  const [discardDestination, setDiscardDestination] = useState('/me');

  useEffect(() => {
    if (data.user) {
      setUsername(data.user.username || '');
      setAvatar(data.user.avatar || '');
    }
  }, [data.user]);

  const submit = async (event) => {
    event.preventDefault();
    if (!username.trim()) { setStatus('error'); setMessage('用户名不能为空。'); return; }
    setStatus('saving');
    setMessage('');
    try {
      await updateProfile({ username, avatar, file: avatarFile });
      await resource.reload();
      setStatus('success');
      setMessage('资料已保存。');
      setAvatarFile(null);
      toast({ body: '个人资料已更新', uniqueID: 'profile-saved' });
    } catch (error) {
      setStatus('error');
      setMessage(error?.message || '保存失败，请稍后重试。');
    }
  };

  const readOnly = data.source !== 'live' || !canEditProfile;
  const dirty = Boolean(data.user && (username !== (data.user.username || '') || avatar !== (data.user.avatar || '') || avatarFile));
  const requestDiscard = useCallback((destination = '/me') => {
    setDiscardDestination(destination);
    setDiscardOpen(true);
  }, []);
  useUnsavedChanges(dirty, requestDiscard);
  return (
    <AppShell>
      <main className="edit-profile-page" aria-labelledby="edit-profile-title">
        <Link className="detail-back" to="/me">← 返回个人主页</Link>
        {!designPreview && authStatus === 'checking' ? <><h1 id="edit-profile-title" className="sr-only">编辑资料</h1><ResourceState status="loading" /></> : !designPreview && authStatus === 'error' ? <><h1 id="edit-profile-title" className="sr-only">编辑资料</h1><ResourceState status="error" error={authError} onRetry={refreshAuth} /></> : !designPreview && !isAuthenticated ? <section className="profile-boundary"><h1 id="edit-profile-title">登录后编辑资料</h1><p>请先登录，再修改你的公开资料。</p><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : !canEditProfile ? <section className="profile-boundary"><h1 id="edit-profile-title">当前账号不能编辑资料</h1><p>账号已登录，但尚未获得资料读取与编辑权限。</p><Link className="action-link" to="/me">返回个人空间</Link></section> : <ResourceState status={resource.status} error={resource.error} onRetry={resource.reload}>
          <section className="edit-profile-panel">
            <header><h1 id="edit-profile-title">编辑资料</h1><p>修改公开显示的用户名和头像。</p></header>
            {data.source === 'configuration-error' ? <p className="form-message is-error">{data.detail}</p> : (
              <form onSubmit={submit}>
                <TextInput label="用户名" value={username} onChange={setUsername} isDisabled={readOnly} disabledMessage="设计预览为只读，接入 API 后可修改用户名。" maxLength={40} size="lg" width="100%" status={status === 'error' && !username.trim() ? { type: 'error', message: '用户名不能为空。' } : undefined} statusVariant="detached" />
                <TextInput label="头像地址" description="也可以在下方直接上传新头像。" value={avatar} onChange={setAvatar} isDisabled={readOnly} disabledMessage="设计预览为只读，接入 API 后可修改头像。" placeholder="https://…" size="lg" width="100%" />
                <ImageDropzone label="或上传新头像" description={canUploadAvatar ? '选择文件后将优先使用上传图片，并清除上方头像地址。' : '当前账号没有文件上传权限，可继续使用头像地址。'} value={avatarFile} onChange={(value) => { const next = Array.isArray(value) ? value[0] || null : value; setAvatarFile(next); if (next) setAvatar(''); }} isDisabled={readOnly || !canUploadAvatar} isLoading={status === 'saving'} />
                {readOnly && <p className="form-message">设计预览为只读；接入 API 后可保存这些字段。</p>}
                {message && <p className={`form-message is-${status}`} role="status">{message}</p>}
                <div className="edit-profile-actions"><Button type="submit" label="保存资料" variant="primary" size="lg" isDisabled={readOnly || status === 'saving'} isLoading={status === 'saving'} /><Button type="button" label="取消" variant="secondary" size="lg" onClick={() => dirty ? requestDiscard('/me') : navigate('/me')} /></div>
              </form>
            )}
          </section>
        </ResourceState>}
        <ConfirmActionDialog isOpen={discardOpen} onOpenChange={setDiscardOpen} title="放弃资料修改？" description="尚未保存的用户名、头像地址和上传文件将不会保留。" actionLabel="放弃修改" onAction={() => { setDiscardOpen(false); navigate(discardDestination); }} />
      </main>
    </AppShell>
  );
}
