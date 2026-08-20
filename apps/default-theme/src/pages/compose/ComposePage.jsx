import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { deleteComposeMedia, getComposeState, loadComposeAuthor, normalizeDraftMedia, publishCard, uploadComposeMedia } from '../../services/compose-service.js';
import { createDraft, deleteDraft, draftSummary, getDraft, normalizeDraftPayload, updateDraft } from '../../services/community-service.js';
import { useAuth } from '../../app/providers.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { useUnsavedChanges } from '../../app/use-unsaved-changes.js';
import { Button, ConfirmActionDialog, Icon, IconButton, InfoPopover, MediaDropzone, SegmentedControl, SegmentedControlItem, TextArea, TextInput, useToast } from '../../ui/index.js';

const composeSteps = [
  { id: 'compose-content', label: '编辑内容', detail: '类型、文字与媒体' },
  { id: 'compose-details', label: '补充信息', detail: '话题与范围' },
  { id: 'compose-check', label: '发布检查', detail: '必填项与权限' },
];

const publishTypes = {
  text: { label: '随笔', icon: 'edit', description: '纯文字，不包含媒体。' },
  image: { label: '图文', icon: 'upload', description: '添加 1–9 张图片或 GIF；一张为单图，多张为图集。' },
  video: { label: '视频', icon: 'play', description: '添加一个 MP4、WebM、MOV 或 M4V 视频。' },
};

function topicsFrom(value) {
  return value.split(/[,，\s]+/).map((tag) => tag.replace(/^#/, '')).filter(Boolean);
}

function resolveDraftType(payload, media) {
  if (['text', 'image', 'video'].includes(payload.publishType)) return payload.publishType;
  if (payload.kind === 'video') return 'video';
  if (payload.kind === 'image' || payload.kind === 'gallery' || media.some((item) => item.type.startsWith('image/'))) return 'image';
  return 'text';
}

function resolveKind(publishType, mediaCount) {
  return publishType === 'image' && mediaCount > 1 ? 'gallery' : publishType;
}

function composeSignature({ content, tags, publishType, media, files }) {
  return JSON.stringify({
    content,
    tags,
    publishType,
    media: media.map((item) => item.id),
    files: files.map((item) => [item.name, item.size, item.type, item.lastModified]),
  });
}

export function ComposePage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const toast = useToast();
  const { isAuthenticated, session, status: authStatus, can, error: authError, refresh: refreshAuth } = useAuth();
  const capability = useMemo(() => getComposeState(), []);
  const designPreview = capability.source === 'preview';
  const canUploadMedia = designPreview || can('files.upload');
  const draftQuery = searchParams.get('draft');
  const requestedDraftId = draftQuery && /^\d+$/.test(draftQuery) ? Number(draftQuery) : null;
  const loadedDraftRef = useRef(null);
  const [content, setContent] = useState('');
  const [tags, setTags] = useState('');
  const [files, setFiles] = useState([]);
  const [mediaPreviews, setMediaPreviews] = useState([]);
  const [persistedMedia, setPersistedMedia] = useState([]);
  const [removedMediaIds, setRemovedMediaIds] = useState([]);
  const [publishType, setPublishType] = useState('text');
  const [draftId, setDraftId] = useState(null);
  const [draftLoadState, setDraftLoadState] = useState('ready');
  const [savedSignature, setSavedSignature] = useState(composeSignature({ content: '', tags: '', publishType: 'text', media: [], files: [] }));
  const [pendingPublishType, setPendingPublishType] = useState(null);
  const [typeSwitchOpen, setTypeSwitchOpen] = useState(false);
  const [activeSection, setActiveSection] = useState('compose-content');
  const [status, setStatus] = useState('idle');
  const [message, setMessage] = useState('');
  const [authorName, setAuthorName] = useState(() => designPreview ? '晚风收藏家' : session?.uid ? `用户 ${session.uid}` : '你的卡片');
  const [discardOpen, setDiscardOpen] = useState(false);
  const [discardDestination, setDiscardDestination] = useState('/');

  useEffect(() => {
    let active = true;
    if (designPreview) {
      setAuthorName('晚风收藏家');
      return () => { active = false; };
    }
    const fallback = session?.uid ? `用户 ${session.uid}` : '你的卡片';
    setAuthorName(fallback);
    if (!isAuthenticated) return () => { active = false; };
    loadComposeAuthor().then((user) => {
      if (active && user?.username) setAuthorName(user.username);
    }).catch(() => {});
    return () => { active = false; };
  }, [designPreview, isAuthenticated, session?.uid]);

  useEffect(() => {
    const next = files.map((selectedFile) => ({ file: selectedFile, url: URL.createObjectURL(selectedFile) }));
    setMediaPreviews(next);
    return () => next.forEach((item) => URL.revokeObjectURL(item.url));
  }, [files]);

  useEffect(() => {
    if (designPreview || authStatus !== 'ready' || !isAuthenticated) return undefined;
    if (!draftQuery) {
      if (loadedDraftRef.current !== null) {
        loadedDraftRef.current = null;
        setDraftId(null);
        setContent('');
        setTags('');
        setFiles([]);
        setPersistedMedia([]);
        setRemovedMediaIds([]);
        setPublishType('text');
        setSavedSignature(composeSignature({ content: '', tags: '', publishType: 'text', media: [], files: [] }));
        setStatus('idle');
        setMessage('');
      }
      setDraftLoadState('ready');
      return undefined;
    }
    if (!requestedDraftId) {
      setDraftLoadState('error');
      setMessage('草稿链接无效，请从草稿箱重新选择。');
      return undefined;
    }
    if (loadedDraftRef.current === requestedDraftId) return undefined;
    let active = true;
    setDraftLoadState('loading');
    setMessage('');
    getDraft(requestedDraftId).then((draft) => {
      if (!active) return;
      if (!draft) {
        setDraftLoadState('not-found');
        return;
      }
      const payload = normalizeDraftPayload(draft.payload);
      const media = normalizeDraftMedia(payload.media);
      const nextType = resolveDraftType(payload, media);
      const nextContent = String(draft.content || '');
      const nextTags = Array.isArray(payload.tags) ? payload.tags.join(' ') : '';
      const nextId = Number(draft.id || requestedDraftId);
      loadedDraftRef.current = nextId;
      setDraftId(nextId);
      setContent(nextContent);
      setTags(nextTags);
      setFiles([]);
      setPersistedMedia(media);
      setRemovedMediaIds([]);
      setPublishType(nextType);
      setSavedSignature(composeSignature({ content: nextContent, tags: nextTags, publishType: nextType, media, files: [] }));
      setDraftLoadState('ready');
      setStatus('success');
      setMessage('草稿已恢复，可以继续完成发布。');
    }).catch((error) => {
      if (!active) return;
      setDraftLoadState(error?.status === 404 ? 'not-found' : 'error');
      setMessage(error?.message || '草稿加载失败，请稍后重试。');
    });
    return () => { active = false; };
  }, [authStatus, designPreview, draftQuery, isAuthenticated, requestedDraftId]);

  const allMediaPreviews = useMemo(() => [
    ...persistedMedia.map((item) => ({ ...item, source: 'persisted' })),
    ...mediaPreviews.map((item) => ({ ...item, name: item.file.name, type: item.file.type, source: 'local' })),
  ], [mediaPreviews, persistedMedia]);
  const mediaCount = persistedMedia.length + files.length;
  const currentSignature = composeSignature({ content, tags, publishType, media: persistedMedia, files });
  const dirty = currentSignature !== savedSignature;
  const contentMode = publishType === 'image' && mediaCount > 1 ? '图文 · 图集' : publishTypes[publishType].label;
  const topicList = topicsFrom(tags);
  const showComposerHeading = designPreview || isAuthenticated;
  const busy = status === 'publishing' || status === 'draft-saving';
  const hasDraftContent = Boolean(content.trim() || tags.trim() || mediaCount || publishType !== 'text');

  const removeMedia = (item, index) => {
    if (item.source === 'persisted') {
      setPersistedMedia((current) => current.filter((value) => value.id !== item.id));
      setRemovedMediaIds((current) => [...new Set([...current, item.id])]);
      return;
    }
    const localIndex = index - persistedMedia.length;
    setFiles((current) => current.filter((_value, itemIndex) => itemIndex !== localIndex));
  };

  const saveDraft = async () => {
    if (designPreview || !isAuthenticated || busy) return;
    if (!hasDraftContent) {
      setStatus('error');
      setMessage('请先填写正文、话题或添加媒体，再存入草稿箱。');
      return;
    }
    setStatus('draft-saving');
    setMessage('正在保存草稿…');
    let uploadedMedia = [];
    try {
      uploadedMedia = await uploadComposeMedia(files);
      const nextMedia = [...persistedMedia, ...uploadedMedia];
      const nextTags = topicsFrom(tags);
      const payload = { publishType, kind: resolveKind(publishType, nextMedia.length), tags: nextTags, media: nextMedia };
      const data = { title: draftSummary({ content, payload }), content, payload };
      let nextId = draftId;
      if (nextId) {
        await updateDraft(nextId, data);
      } else {
        const result = await createDraft(data);
        nextId = Number(result?.id);
        if (!Number.isInteger(nextId) || nextId <= 0) throw new Error('草稿接口没有返回有效编号。');
      }
      await deleteComposeMedia(removedMediaIds);
      const normalizedTags = nextTags.join(' ');
      loadedDraftRef.current = nextId;
      setDraftId(nextId);
      setPersistedMedia(nextMedia);
      setFiles([]);
      setRemovedMediaIds([]);
      setTags(normalizedTags);
      setSavedSignature(composeSignature({ content, tags: normalizedTags, publishType, media: nextMedia, files: [] }));
      const nextSearchParams = new URLSearchParams(searchParams);
      nextSearchParams.set('draft', String(nextId));
      setSearchParams(nextSearchParams, { replace: true });
      setStatus('success');
      setMessage(draftId ? '草稿已更新。' : '已存入草稿箱。');
      toast({ body: draftId ? '草稿已更新' : '已存入草稿箱', uniqueID: 'compose-draft-saved' });
    } catch (error) {
      await deleteComposeMedia(uploadedMedia.map((item) => item.id));
      setStatus('error');
      setMessage(error?.message || '草稿保存失败，当前输入仍保留在页面。');
    }
  };

  const submit = async (event) => {
    event.preventDefault();
    if (!content.trim()) {
      setActiveSection('compose-content');
      setStatus('error');
      setMessage('请先写下要分享的内容。');
      return;
    }
    setStatus('publishing');
    setMessage('');
    try {
      const result = await publishCard({ content, tags: topicsFrom(tags), kind: resolveKind(publishType, mediaCount), files, media: persistedMedia });
      let draftCleanupFailed = false;
      if (draftId) {
        try {
          await deleteDraft(draftId);
        } catch {
          draftCleanupFailed = true;
        }
      }
      await deleteComposeMedia(removedMediaIds);
      setStatus('success');
      setMessage(draftCleanupFailed ? '内容已发布，但原草稿需要稍后手动删除。' : '发布成功。');
      toast({ body: draftCleanupFailed ? '内容已发布，原草稿清理失败' : '内容已发布', type: draftCleanupFailed ? 'error' : undefined, uniqueID: 'compose-success' });
      navigate(result?.id ? `/post/${result.id}` : '/me');
    } catch (error) {
      setStatus('error');
      setMessage(error?.message || '发布失败，请稍后重试。');
    }
  };

  const requestDiscard = useCallback((destination = '/') => {
    setDiscardDestination(destination);
    setDiscardOpen(true);
  }, []);
  const selectPublishType = (nextType) => {
    if (nextType === publishType) return;
    if (mediaCount) {
      setPendingPublishType(nextType);
      setTypeSwitchOpen(true);
      return;
    }
    setPublishType(nextType);
    setMessage('');
  };
  const confirmTypeSwitch = () => {
    setRemovedMediaIds((current) => [...new Set([...current, ...persistedMedia.map((item) => item.id)])]);
    setFiles([]);
    setPersistedMedia([]);
    setPublishType(pendingPublishType || 'text');
    setPendingPublishType(null);
    setTypeSwitchOpen(false);
    setMessage('');
  };
  const showSection = (sectionId) => {
    setActiveSection(sectionId);
    document.getElementById(sectionId)?.scrollIntoView({ behavior: 'auto', block: 'start' });
  };
  useUnsavedChanges(dirty, requestDiscard);

  return (
    <AppShell>
      <main className="compose-page" aria-labelledby="compose-title">
        <header className="compose-heading">
          <div>
            <h1 id="compose-title">{draftId ? '继续完成发布' : '发布新内容'}</h1>
            <p>{showComposerHeading ? '整理内容、媒体与话题，完成发布检查后提交。' : '登录后即可分享文字、图片和话题。'}</p>
          </div>
          {showComposerHeading && <div className="compose-heading-tools">
            <span className="compose-mode" aria-live="polite"><Icon name={publishTypes[publishType].icon} size={15} />{contentMode}</span>
            <InfoPopover label="发布说明"><strong>先选择内容类型</strong><span>随笔发布纯文字；图文支持 1–9 张图片或 GIF；视频支持一个视频文件。切换类型前会确认是否清除已选媒体。</span></InfoPopover>
            <Link className="compose-drafts-link" to="/drafts"><Icon name="edit" size={15} />草稿箱</Link>
          </div>}
        </header>

        {!designPreview && authStatus === 'checking' ? <ResourceState status="loading" /> : !designPreview && authStatus === 'error' ? <ResourceState status="error" error={authError} onRetry={refreshAuth} /> : !designPreview && !isAuthenticated ? <section className="profile-boundary"><h2>登录后发布内容</h2><p>游客可以浏览公开内容；发布、评论和管理个人内容需要正式账号。</p><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : !designPreview && !can('cards.create') ? <section className="profile-boundary"><h2>当前账号不能发布</h2><p>你的账号暂时没有发布内容的权限。</p><Link className="action-link" to="/">返回首页</Link></section> : draftLoadState === 'loading' ? <ResourceState status="loading" /> : draftLoadState === 'error' ? <section className="profile-boundary"><h2>草稿加载失败</h2><p>{message}</p><Link className="action-link action-link-primary" to="/drafts">返回草稿箱</Link></section> : draftLoadState === 'not-found' ? <section className="profile-boundary"><h2>这份草稿不存在</h2><p>它可能已被删除，或不属于当前账号。</p><Link className="action-link" to="/drafts">返回草稿箱</Link></section> : <form className="compose-form" onSubmit={submit}>
          <div className="compose-workbench">
            <nav className="compose-step-rail" aria-label="发布步骤">
              <ol>{composeSteps.map((step, index) => <li key={step.id}>
                <button className={activeSection === step.id ? 'is-current' : ''} type="button" aria-current={activeSection === step.id ? 'step' : undefined} onClick={() => showSection(step.id)}>
                  <span>{index + 1}</span><span><strong>{step.label}</strong><small>{step.detail}</small></span>
                </button>
              </li>)}</ol>
            </nav>

            <section className="compose-editor-surface" id="compose-content">
              <header className="compose-editor-header">
                <div><h2>{draftId ? '继续编辑内容' : '创建内容'}</h2><span>{draftId ? dirty ? '草稿有未保存的修改' : '当前内容已存入草稿箱' : dirty ? '内容仅保留在当前页面' : '尚未开始编辑'}</span></div>
                <div className="compose-author"><span aria-hidden="true">{authorName.slice(0, 1)}</span><strong>{authorName}</strong></div>
              </header>

              <section className="compose-type-selector" aria-labelledby="compose-type-title">
                <header><div><h3 id="compose-type-title">发布类型</h3><p>{publishTypes[publishType].description}</p></div><span>{contentMode}</span></header>
                <SegmentedControl className="compose-type-control" value={publishType} onChange={selectPublishType} label="发布类型" layout="fill" size="lg" isDisabled={busy}>
                  <SegmentedControlItem value="text" label="随笔" icon={<Icon name="edit" size={16} />} />
                  <SegmentedControlItem value="image" label="图文" icon={<Icon name="upload" size={16} />} isDisabled={!canUploadMedia} />
                  <SegmentedControlItem value="video" label="视频" icon={<Icon name="play" size={16} />} isDisabled={!canUploadMedia} />
                </SegmentedControl>
              </section>

              <TextArea className="compose-content-field" label="正文" isRequired value={content} onChange={setContent} maxLength={5000} rows={8} size="lg" width="100%" placeholder="分享此刻的发现、经验或想法…" isDisabled={busy} />

              {publishType !== 'text' && <section className="compose-media-workspace" aria-labelledby="compose-media-title">
                <header><div><h3 id="compose-media-title">{publishType === 'video' ? '视频' : '图片'}</h3><p>{publishType === 'video' ? '选择一个视频，发布后使用原生播放控制。' : '按选择顺序发布，最多添加 9 张。'}</p></div><span>{mediaCount}/{publishType === 'video' ? 1 : 9}</span></header>
                <MediaDropzone
                  label={publishType === 'video' ? '添加视频' : '添加图片或 GIF'}
                  description={publishType === 'video' ? '支持 MP4、WebM、MOV 与 M4V；文件大小以服务端策略为准。' : '支持 JPEG、PNG、WebP 与 GIF；文件大小以服务端策略为准。'}
                  value={publishType === 'video' ? files[0] || null : files}
                  onChange={(value) => {
                    const next = Array.isArray(value) ? value : value ? [value] : [];
                    const available = Math.max(0, (publishType === 'video' ? 1 : 9) - persistedMedia.length);
                    setFiles(next.slice(0, available));
                  }}
                  accept={publishType === 'video' ? 'video/mp4,video/webm,video/quicktime,video/x-m4v,.mov,.m4v' : 'image/jpeg,image/png,image/webp,image/gif'}
                  isMultiple={publishType === 'image'}
                  maxFiles={Math.max(1, (publishType === 'image' ? 9 : 1) - persistedMedia.length)}
                  placeholder={publishType === 'video' ? '拖入视频，或点击选择' : '拖入图片，或点击选择'}
                  isDisabled={busy || !canUploadMedia || mediaCount >= (publishType === 'video' ? 1 : 9)}
                  disabledMessage={!canUploadMedia ? '当前账号没有上传媒体的权限。' : mediaCount >= (publishType === 'video' ? 1 : 9) ? '已达到当前类型的媒体数量上限。' : undefined}
                  isLoading={busy}
                />
                {allMediaPreviews.length ? <div className={`compose-media-preview-grid is-${allMediaPreviews.length > 4 ? 'many' : allMediaPreviews.length}`} aria-label={`${allMediaPreviews.length} 个待发布媒体`}>
                  {allMediaPreviews.map((item, index) => <div className={`compose-media-preview is-${item.source}`} key={item.source === 'persisted' ? `media-${item.id}` : `${item.file.name}-${item.file.lastModified}-${index}`}>{publishType === 'video' ? <video src={item.url} muted playsInline preload="metadata" /> : <img src={item.url} alt={`待发布媒体 ${index + 1}：${item.name || '图片'}`} />}<span>{index + 1}</span><IconButton className="lc-upload-preview-remove" type="button" onClick={() => removeMedia(item, index)} label={publishType === 'video' ? '移除视频' : `移除第 ${index + 1} 张图片`} tooltip="移除媒体" variant="ghost" size="lg" icon={<Icon name="close" size={18} />} isDisabled={busy} /></div>)}
                </div> : <div className="compose-media-empty"><span className="compose-media-add-icon" aria-hidden="true"><Icon name={publishType === 'video' ? 'play' : 'plus'} size={22} /></span><strong>{publishType === 'video' ? '添加一个视频' : '添加 1–9 张图片或 GIF'}</strong><span>{publishType === 'video' ? '视频会在信息流中保持媒体卡片结构。' : '一张显示为单图，多张自动组成图集。'}</span></div>}
              </section>}

              <section className="compose-details" id="compose-details">
                <TextInput className="compose-tags-field" label="话题" description="可选，用空格或逗号分隔多个话题。" value={tags} onChange={setTags} size="lg" width="100%" placeholder="城市漫游  周末记录" isDisabled={busy} />
                <div className="compose-visibility"><span><Icon name="check" size={16} /></span><div><strong>所有人可见</strong><small>发布后会进入公开内容流与个人主页。</small></div></div>
              </section>
            </section>

            <aside className="compose-check-panel" id="compose-check" aria-label="发布检查">
              <section className="compose-checklist" aria-labelledby="compose-checklist-title">
                <h2 id="compose-checklist-title">发布检查</h2>
                <ul>
                  <li className={content.trim() ? 'is-ready' : 'is-pending'}><span><Icon name={content.trim() ? 'check' : 'info'} size={15} /></span><div><strong>{content.trim() ? '正文已填写' : '正文还未填写'}</strong><small>{content.trim() ? `${content.trim().length} 个字符` : '正文是发布所必需的'}</small></div></li>
                  <li className={publishType === 'text' || mediaCount ? 'is-ready' : 'is-pending'}><span><Icon name={publishType === 'text' || mediaCount ? 'check' : 'info'} size={15} /></span><div><strong>发布类型：{contentMode}</strong><small>{publishType === 'text' ? '纯文字，不包含媒体' : mediaCount ? `${mediaCount} 个媒体已就绪` : `还需要添加${publishType === 'video' ? '一个视频' : '至少一张图片'}`}</small></div></li>
                  <li className="is-ready"><span><Icon name="check" size={15} /></span><div><strong>公开可见</strong><small>{topicList.length ? `${topicList.length} 个话题` : '未添加话题'}</small></div></li>
                  <li className={capability.canPublish ? 'is-ready' : 'is-limited'}><span><Icon name={capability.canPublish ? 'check' : 'info'} size={15} /></span><div><strong>{capability.canPublish ? '发布权限已确认' : '当前不可发布'}</strong><small>{capability.message || '发布后会进入你的个人主页'}</small></div></li>
                </ul>
              </section>
            </aside>
          </div>

          <footer className="compose-actions">
            <span className={`compose-message is-${status}`} role="status">{message}</span>
            <Button className="compose-cancel" type="button" label="取消" variant="secondary" size="lg" onClick={() => dirty ? requestDiscard('/') : navigate('/')} isDisabled={busy} />
            <Button className="compose-draft-save" type="button" label={draftId ? '更新草稿' : '存入草稿箱'} variant="secondary" size="lg" onClick={() => void saveDraft()} isDisabled={designPreview || !isAuthenticated || busy || !dirty} isLoading={status === 'draft-saving'} />
            <Button className="compose-submit" type="submit" label="确认发布" variant="primary" size="lg" isDisabled={!capability.canPublish || busy} isLoading={status === 'publishing'} />
          </footer>
        </form>}
        <ConfirmActionDialog isOpen={typeSwitchOpen} onOpenChange={(open) => { setTypeSwitchOpen(open); if (!open) setPendingPublishType(null); }} title={`切换为${publishTypes[pendingPublishType]?.label || '其他类型'}？`} description="当前已选媒体与新类型不兼容，继续切换会移除这些文件，正文和话题会保留。" actionLabel="移除媒体并切换" onAction={confirmTypeSwitch} />
        <ConfirmActionDialog isOpen={discardOpen} onOpenChange={setDiscardOpen} title="放弃这次编辑？" description="尚未发布的文字、话题和媒体将不会保留。" actionLabel="放弃编辑" onAction={() => { setDiscardOpen(false); navigate(discardDestination); }} />
      </main>
    </AppShell>
  );
}
