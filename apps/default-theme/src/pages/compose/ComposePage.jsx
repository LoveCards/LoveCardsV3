import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { deleteComposeMedia, getComposeState, loadComposeTopics, loadPublishedCardForEdit, normalizeDraftMedia, publishCard, updatePublishedCard, uploadComposeMedia } from '../../services/compose-service.js';
import { createDraft, deleteDraft, draftSummary, getDraft, normalizeDraftPayload, updateDraft } from '../../services/community-service.js';
import { useAuth } from '../../app/providers.jsx';
import { ResourceState } from '../../components/ResourceState.jsx';
import { useUnsavedChanges } from '../../app/use-unsaved-changes.js';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { getSdkClient } from '../../app/sdk-client.js';
import { createMentionTrigger, createPreviewMentionAdapter, createSdkMentionAdapter, normalizeMentionCaret } from '../../services/mentions-service.js';
import { previewCreators } from '../../fixtures/feed-preview.js';
import { renderMentionCandidateItem } from '../../features/mentions/MentionCandidateItem.jsx';
import { Button, ChatComposerInput, ConfirmActionDialog, Icon, IconButton, InfoPopover, MediaDropzone, ScopedBanner, SegmentedControl, SegmentedControlItem, TextInput, useToast } from '../../ui/index.js';
import { CARD_CONTENT_MAX_LENGTH } from '../../features/feed/card-constraints.js';

const publishTypes = {
  text: { label: '随笔', description: '纯文字，不包含媒体。' },
  image: { label: '图文', description: '先添加 1–9 张图片或 GIF，再填写必填正文。' },
  video: { label: '视频', description: '先添加一个视频，再填写必填正文。' },
};

function topicsFrom(value) {
  const seen = new Set();
  return value.split(/[,，\s]+/).map((tag) => tag.replace(/^#/, '').trim()).filter((tag) => {
    const key = tag.toLowerCase();
    if (!tag || seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

function focusComposeIssue(sectionId, selector) {
  window.requestAnimationFrame(() => {
    const section = document.getElementById(sectionId);
    section?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    section?.querySelector(selector)?.focus({ preventScroll: true });
  });
}

function ComposeSkeleton() {
  return <section className="compose-skeleton" aria-label="内容编辑器加载中" aria-busy="true">
    <div className="compose-skeleton-segments"><i /><i /><i /></div>
    <div className="compose-skeleton-field"><i /><span /><span /><span /></div>
    <div className="compose-skeleton-field is-short"><i /><span /><span /></div>
    <footer><i /><i /></footer>
  </section>;
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
  const { isAuthenticated, status: authStatus, can, error: authError, refresh: refreshAuth } = useAuth();
  const capability = useMemo(() => getComposeState(), []);
  const designPreview = capability.source === 'preview';
  const canUploadMedia = designPreview || can('files.upload');
  const draftQuery = searchParams.get('draft');
  const requestedDraftId = draftQuery && /^\d+$/.test(draftQuery) ? Number(draftQuery) : null;
  const editQuery = searchParams.get('edit');
  const requestedEditId = editQuery && /^\d+$/.test(editQuery) ? Number(editQuery) : null;
  const editMode = editQuery !== null;
  const hasConflictingQuery = editMode && draftQuery !== null;
  const loadedDraftRef = useRef(null);
  const loadedEditRef = useRef(null);
  const [content, setContent] = useState('');
  const [tags, setTags] = useState('');
  const [topicOptions, setTopicOptions] = useState([]);
  const [topicOptionsError, setTopicOptionsError] = useState('');
  const [files, setFiles] = useState([]);
  const [mediaPreviews, setMediaPreviews] = useState([]);
  const [persistedMedia, setPersistedMedia] = useState([]);
  const [removedMediaIds, setRemovedMediaIds] = useState([]);
  const [publishType, setPublishType] = useState('text');
  const [draftId, setDraftId] = useState(null);
  const [publishedCardId, setPublishedCardId] = useState(null);
  const [publishedData, setPublishedData] = useState({});
  const [loadState, setLoadState] = useState('ready');
  const [savedSignature, setSavedSignature] = useState(composeSignature({ content: '', tags: '', publishType: 'text', media: [], files: [] }));
  const [pendingPublishType, setPendingPublishType] = useState(null);
  const [typeSwitchOpen, setTypeSwitchOpen] = useState(false);
  const [validationAttempted, setValidationAttempted] = useState(false);
  const [topicLimitHit, setTopicLimitHit] = useState(false);
  const [status, setStatus] = useState('idle');
  const [message, setMessage] = useState('');
  const [discardOpen, setDiscardOpen] = useState(false);
  const [discardDestination, setDiscardDestination] = useState('/');
  const composeInputHandle = useRef(null);
  const composeInputRoot = useRef(null);
  const mentionAdapter = useMemo(() => {
    if (designPreview) {
      return createPreviewMentionAdapter(previewCreators.map((creator) => ({
        id: creator.id,
        username: creator.name,
        display_name: creator.name,
        avatar: null,
      })));
    }
    return createSdkMentionAdapter(getSdkClient());
  }, [designPreview]);
  const mentionTrigger = useMemo(() => mentionAdapter ? createMentionTrigger(mentionAdapter, {
    limit: 8,
    renderItem: renderMentionCandidateItem,
    menuLabel: '提及用户',
    loadingText: '正在查找用户…',
    emptySearchResultsText: '没有匹配的用户',
  }) : null, [mentionAdapter]);
  const handleComposeInputKeyDown = useCallback((event) => {
    normalizeMentionCaret(event.currentTarget);
    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent?.isComposing) {
      event.preventDefault();
      composeInputHandle.current?.insertText('\n');
    }
  }, []);
  const handleComposeInputChange = useCallback((value) => {
    setContent(value.length > CARD_CONTENT_MAX_LENGTH ? value.slice(0, CARD_CONTENT_MAX_LENGTH) : value);
    // Controlled contenteditables may expose the caret as an element boundary
    // immediately after onChange; normalise it before Astryx parses the @.
    normalizeMentionCaret(composeInputRoot.current);
  }, []);

  useEffect(() => {
    if (designPreview || !isAuthenticated) return undefined;
    let active = true;
    loadComposeTopics().then((items) => { if (active) setTopicOptions(items); }).catch((error) => { if (active) setTopicOptionsError(getUserFacingError(error, { fallback: '话题建议暂时无法加载，你仍可直接发布内容。' })); });
    return () => { active = false; };
  }, [designPreview, isAuthenticated]);

  useEffect(() => {
    const next = files.map((selectedFile) => ({ file: selectedFile, url: URL.createObjectURL(selectedFile) }));
    setMediaPreviews(next);
    return () => next.forEach((item) => URL.revokeObjectURL(item.url));
  }, [files]);

  useEffect(() => {
    if (designPreview || authStatus !== 'ready' || !isAuthenticated) return undefined;
    if (editMode) return undefined;
    if (!draftQuery) {
      if (loadedDraftRef.current !== null || loadedEditRef.current !== null) {
        loadedDraftRef.current = null;
        loadedEditRef.current = null;
        setDraftId(null);
        setPublishedCardId(null);
        setPublishedData({});
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
      setLoadState('ready');
      return undefined;
    }
    if (!requestedDraftId) {
      setLoadState('error');
      setMessage('草稿链接无效，请从草稿箱重新选择。');
      return undefined;
    }
    if (loadedDraftRef.current === requestedDraftId) return undefined;
    let active = true;
    setLoadState('loading');
    setMessage('');
    getDraft(requestedDraftId).then((draft) => {
      if (!active) return;
      if (!draft) {
        setLoadState('not-found');
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
      setLoadState('ready');
      setStatus('success');
      setMessage('草稿已恢复，可以继续完成发布。');
    }).catch((error) => {
      if (!active) return;
      setLoadState(error?.status === 404 ? 'not-found' : error?.status === 403 ? 'forbidden' : 'error');
      setMessage(getUserFacingError(error, { fallback: '草稿暂时无法加载，请稍后重试。' }));
    });
    return () => { active = false; };
  }, [authStatus, designPreview, draftQuery, editMode, isAuthenticated, requestedDraftId]);

  useEffect(() => {
    if (designPreview || authStatus !== 'ready' || !isAuthenticated || !editMode) return undefined;
    if (hasConflictingQuery) {
      setLoadState('error');
      setMessage('编辑链接不能同时包含草稿和已发布内容，请重新进入。');
      return undefined;
    }
    if (!requestedEditId) {
      setLoadState('error');
      setMessage('编辑链接无效，请从自己的内容重新进入。');
      return undefined;
    }
    if (loadedEditRef.current === requestedEditId) return undefined;
    let active = true;
    setLoadState('loading');
    setMessage('');
    loadPublishedCardForEdit(requestedEditId).then((card) => {
      if (!active) return;
      const nextTags = card.tags.join(' ');
      loadedDraftRef.current = null;
      loadedEditRef.current = card.id;
      setDraftId(null);
      setPublishedCardId(card.id);
      setPublishedData(card.data);
      setContent(card.content);
      setTags(nextTags);
      setFiles([]);
      setPersistedMedia(card.media);
      setRemovedMediaIds([]);
      setPublishType(card.publishType);
      setSavedSignature(composeSignature({ content: card.content, tags: nextTags, publishType: card.publishType, media: card.media, files: [] }));
      setLoadState('ready');
      setStatus('success');
      setMessage('已载入当前内容。');
    }).catch((error) => {
      if (!active) return;
      setLoadState(error?.status === 404 ? 'not-found' : error?.status === 403 ? 'forbidden' : 'error');
      setMessage(getUserFacingError(error, { fallback: '内容暂时无法加载，请稍后重试。' }));
    });
    return () => { active = false; };
  }, [authStatus, designPreview, editMode, hasConflictingQuery, isAuthenticated, requestedEditId]);

  const allMediaPreviews = useMemo(() => [
    ...persistedMedia.map((item) => ({ ...item, source: 'persisted' })),
    ...mediaPreviews.map((item) => ({ ...item, name: item.file.name, type: item.file.type, source: 'local' })),
  ], [mediaPreviews, persistedMedia]);
  const mediaCount = persistedMedia.length + files.length;
  const currentSignature = composeSignature({ content, tags, publishType, media: persistedMedia, files });
  const dirty = currentSignature !== savedSignature;
  const topicList = topicsFrom(tags);
  const contentError = validationAttempted && (!content.trim() || content.length > CARD_CONTENT_MAX_LENGTH);
  const contentErrorMessage = !content.trim()
    ? publishType === 'text' ? '请先写下要分享的内容。' : `${publishTypes[publishType].label}必须填写正文，请补充后再发布。`
    : `正文最多 ${CARD_CONTENT_MAX_LENGTH} 字，请精简后再发布。`;
  const contentDescription = content.length >= 800
    ? `还可输入 ${Math.max(0, CARD_CONTENT_MAX_LENGTH - content.length)} 字。`
    : `最多 ${CARD_CONTENT_MAX_LENGTH} 字。`;
  const mediaError = validationAttempted && publishType !== 'text' && mediaCount === 0;
  const topicError = topicList.length > 3 || topicLimitHit;
  const topicSuggestions = topicOptions.filter((topic) => !topicList.some((selected) => selected.toLowerCase() === topic.name.toLowerCase())).slice(0, 6);
  const showComposerHeading = designPreview || isAuthenticated;
  const busy = status === 'publishing' || status === 'draft-saving' || status === 'saving-edit';
  const hasDraftContent = Boolean(content.trim() || tags.trim() || mediaCount || publishType !== 'text');
  const saveState = editMode
    ? status === 'saving-edit'
      ? { tone: 'saving', label: '正在保存修改' }
      : dirty
        ? { tone: 'dirty', label: '有未保存的修改' }
        : loadState === 'ready'
          ? { tone: 'saved', label: '已是最新版本' }
          : null
    : status === 'draft-saving'
      ? { tone: 'saving', label: '正在保存草稿' }
      : draftId && !dirty
        ? { tone: 'saved', label: '已保存到草稿箱' }
        : dirty
          ? { tone: 'dirty', label: draftId ? '有未保存的修改' : '尚未存入草稿箱' }
          : null;

  const removeMedia = (item, index) => {
    if (item.source === 'persisted') {
      setPersistedMedia((current) => current.filter((value) => value.id !== item.id));
      setRemovedMediaIds((current) => [...new Set([...current, item.id])]);
      return;
    }
    const localIndex = index - persistedMedia.length;
    setFiles((current) => current.filter((_value, itemIndex) => itemIndex !== localIndex));
  };

  const addTopic = (name) => {
    const normalized = String(name || '').trim().replace(/^#/, '');
    if (!normalized || topicList.some((topic) => topic.toLowerCase() === normalized.toLowerCase())) return;
    if (topicList.length >= 3) {
      setTopicLimitHit(true);
      return;
    }
    setTags([...topicList, normalized].join(' '));
    setTopicLimitHit(false);
    setMessage('');
  };

  const removeTopic = (name) => {
    setTags(topicList.filter((topic) => topic !== name).join(' '));
    setTopicLimitHit(false);
  };

  const saveDraft = async () => {
    if (editMode || designPreview || !isAuthenticated || busy) return;
    if (!hasDraftContent) {
      setStatus('error');
      setMessage('请先填写正文、话题或添加媒体，再存入草稿箱。');
      return;
    }
    if (topicList.length > 3) {
      setValidationAttempted(true);
      setStatus('idle');
      setMessage('');
      focusComposeIssue('compose-details', 'input');
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
    } catch (error) {
      await deleteComposeMedia(uploadedMedia.map((item) => item.id));
      setStatus('error');
      setMessage(getUserFacingError(error, { fallback: '草稿暂时没有保存，当前输入仍保留在页面。', validationFallback: '草稿内容不符合要求，请检查后重试。' }));
    }
  };

  const submit = async (event) => {
    event.preventDefault();
    setValidationAttempted(true);
    if (topicList.length <= 3) setTopicLimitHit(false);
    if (!content.trim()) {
      setStatus('idle');
      setMessage('');
      focusComposeIssue('compose-content', '[contenteditable="true"]');
      return;
    }
    if (content.length > CARD_CONTENT_MAX_LENGTH) {
      setStatus('idle');
      setMessage('');
      focusComposeIssue('compose-content', '[contenteditable="true"]');
      return;
    }
    if (publishType !== 'text' && mediaCount === 0) {
      setStatus('idle');
      setMessage('');
      focusComposeIssue('compose-media', 'button');
      return;
    }
    if (topicList.length > 3) {
      setStatus('idle');
      setMessage('');
      focusComposeIssue('compose-details', 'input');
      return;
    }
    setStatus(editMode ? 'saving-edit' : 'publishing');
    setMessage('');
    try {
      if (editMode) {
        const result = await updatePublishedCard({
          id: publishedCardId || requestedEditId,
          content,
          tags: topicsFrom(tags),
          kind: resolveKind(publishType, mediaCount),
          files,
          media: persistedMedia,
          data: publishedData,
          mediaChanged: files.length > 0 || removedMediaIds.length > 0,
        });
        await deleteComposeMedia(removedMediaIds);
        setRemovedMediaIds([]);
        setStatus('success');
        setMessage('修改已保存。');
        toast({ body: '修改已保存', uniqueID: 'compose-edit-success' });
        navigate(`/post/${result.id}`);
        return;
      }
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
      setMessage(getUserFacingError(error, editMode
        ? { fallback: '修改暂时没有保存，当前输入仍保留在页面。', validationFallback: '修改内容不符合要求，请检查文字与媒体后重试。' }
        : { fallback: '内容暂时没有发布，当前输入仍保留在页面。', validationFallback: '发布内容不符合要求，请检查文字与媒体后重试。' }));
    }
  };

  const returnDestination = editMode && (publishedCardId || requestedEditId) ? `/post/${publishedCardId || requestedEditId}` : '/';
  const requestDiscard = useCallback((destination = returnDestination) => {
    setDiscardDestination(destination);
    setDiscardOpen(true);
  }, [returnDestination]);
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
  const canWriteCard = editMode ? can('cards.update') : can('cards.create');
  useUnsavedChanges(dirty, requestDiscard);

  return (
    <AppShell mobileMode="immersive">
      <main className="compose-page" aria-labelledby="compose-title">
        <header className="compose-heading">
          <button className="compose-back" type="button" onClick={() => dirty ? requestDiscard(returnDestination) : navigate(returnDestination)} aria-label={editMode ? '取消编辑并返回内容详情' : '取消发布并返回首页'}><span aria-hidden="true">←</span><span>返回</span></button>
          <div className="compose-heading-copy">
            <h1 id="compose-title">{editMode ? '编辑内容' : draftId ? '继续完成发布' : '发布新内容'}</h1>
            <p>{showComposerHeading ? editMode ? '修改会直接更新这张卡片。' : '把想法整理成一张卡片。' : '登录后即可分享文字、图片和话题。'}</p>
          </div>
          {showComposerHeading && <div className="compose-heading-tools">
          <InfoPopover label={editMode ? '编辑说明' : '发布说明'}><strong>先选择内容类型</strong><span>随笔为纯文字；图文和视频先添加媒体，再填写正文；图文支持 1–9 张图片或 GIF，视频支持一个视频文件。切换类型前会确认是否清除已选媒体。</span></InfoPopover>
            {!editMode && <Link className="compose-drafts-link" to="/drafts"><Icon name="edit" size={15} />草稿箱</Link>}
          </div>}
        </header>

        {!designPreview && authStatus === 'checking' ? <ComposeSkeleton /> : !designPreview && authStatus === 'error' ? <ResourceState status="error" error={authError} onRetry={refreshAuth} /> : !designPreview && !isAuthenticated ? <section className="profile-boundary"><h2>登录后{editMode ? '编辑' : '发布'}内容</h2><p>游客可以浏览公开内容；发布、评论和管理个人内容需要正式账号。</p><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : !designPreview && !canWriteCard ? <section className="profile-boundary"><h2>当前账号不能{editMode ? '编辑' : '发布'}</h2><p>你的账号暂时没有{editMode ? '修改这张卡片' : '发布内容'}的权限。</p><Link className="action-link" to={returnDestination}>返回</Link></section> : loadState === 'loading' ? <ComposeSkeleton /> : loadState === 'error' ? <section className="profile-boundary"><h2>{editMode ? '内容加载失败' : '草稿加载失败'}</h2><p>{message}</p><Link className="action-link action-link-primary" to={editMode ? returnDestination : '/drafts'}>返回</Link></section> : loadState === 'forbidden' ? <section className="profile-boundary"><h2>不能编辑这张卡片</h2><p>它不属于当前账号，或当前账号没有修改权限。</p><Link className="action-link" to="/me">返回我的主页</Link></section> : loadState === 'not-found' ? <section className="profile-boundary"><h2>{editMode ? '这张卡片不存在' : '这份草稿不存在'}</h2><p>它可能已被删除，或不属于当前账号。</p><Link className="action-link" to={editMode ? '/me' : '/drafts'}>返回</Link></section> : <form className="compose-form" onSubmit={submit}>
          <section className="compose-editor-surface" id="compose-content">
              <section className="compose-type-selector" aria-labelledby="compose-type-title">
                <header><div><h3 id="compose-type-title">发布类型</h3><p>{publishTypes[publishType].description}</p></div></header>
                <SegmentedControl className="compose-type-control" value={publishType} onChange={selectPublishType} label="发布类型" layout="fill" size="lg" isDisabled={busy}>
                  <SegmentedControlItem value="text" label="随笔" icon={<Icon name="edit" size={16} />} />
                  <SegmentedControlItem value="image" label="图文" icon={<Icon name="upload" size={16} />} isDisabled={!canUploadMedia} />
                  <SegmentedControlItem value="video" label="视频" icon={<Icon name="play" size={16} />} isDisabled={!canUploadMedia} />
                </SegmentedControl>
              </section>

              {publishType !== 'text' && <section className="compose-media-workspace" id="compose-media" aria-labelledby="compose-media-title">
                <header><div><h3 id="compose-media-title">{publishType === 'video' ? '视频' : '图片'}<span aria-hidden="true"> *</span></h3><p>{publishType === 'video' ? '必选：选择一个视频，发布后使用原生播放控制。' : '必选：按选择顺序发布，最多添加 9 张。'}</p></div><span>{mediaCount}/{publishType === 'video' ? 1 : 9}</span></header>
                <MediaDropzone
                  label={publishType === 'video' ? mediaCount ? '更换视频' : '添加视频' : mediaCount ? '继续添加图片或 GIF' : '添加图片或 GIF'}
                  description={publishType === 'video' ? '支持 MP4、WebM、MOV 与 M4V；文件大小以服务端策略为准。' : '支持 JPEG、PNG、WebP 与 GIF；文件大小以服务端策略为准。'}
                  value={publishType === 'video' ? files[0] || null : files}
                  onChange={(value) => {
                    const next = Array.isArray(value) ? value : value ? [value] : [];
                    const available = Math.max(0, (publishType === 'video' ? 1 : 9) - persistedMedia.length);
                    setFiles(next.slice(0, available));
                  }}
                  accept={publishType === 'video' ? 'video/mp4,video/webm,video/quicktime,video/x-m4v,.mov,.m4v' : 'image/jpeg,image/png,image/webp,image/gif'}
                  maxSize={publishType === 'image' ? 10 * 1024 * 1024 : undefined}
                  isMultiple={publishType === 'image'}
                  maxFiles={Math.max(1, (publishType === 'image' ? 9 : 1) - persistedMedia.length)}
                  placeholder={publishType === 'video' ? '拖入视频，或点击选择' : '拖入图片，或点击选择'}
                  isDisabled={busy || !canUploadMedia || mediaCount >= (publishType === 'video' ? 1 : 9)}
                  disabledMessage={!canUploadMedia ? '当前账号没有上传媒体的权限。' : mediaCount >= (publishType === 'video' ? 1 : 9) ? '已达到当前类型的媒体数量上限。' : undefined}
                  isLoading={busy}
                  status={mediaError ? { type: 'error', message: publishType === 'video' ? '请添加一个视频后再发布。' : '请至少添加一张图片后再发布。' } : undefined}
                />
                {allMediaPreviews.length ? <div className={`compose-media-preview-grid is-${allMediaPreviews.length > 4 ? 'many' : allMediaPreviews.length}`} aria-label={`${allMediaPreviews.length} 个待发布媒体`}>
                  {allMediaPreviews.map((item, index) => <div className={`compose-media-preview is-${item.source}`} key={item.source === 'persisted' ? `media-${item.id}` : `${item.file.name}-${item.file.lastModified}-${index}`}>{publishType === 'video' ? <video src={item.url} muted playsInline preload="metadata" /> : <img src={item.url} alt={`待发布媒体 ${index + 1}：${item.name || '图片'}`} />}<span>{index + 1}</span><IconButton className="lc-upload-preview-remove" type="button" onClick={() => removeMedia(item, index)} label={publishType === 'video' ? '移除视频' : `移除第 ${index + 1} 张图片`} tooltip="移除媒体" variant="ghost" size="lg" icon={<Icon name="close" size={18} />} isDisabled={busy} /></div>)}
                </div> : null}
              </section>}

              <section className="compose-content-field" aria-label="正文编辑">
                <div className="compose-content-label-row"><div><label className="compose-content-label" htmlFor="compose-content-input">正文<span aria-hidden="true"> *</span></label><p className="compose-content-description" id="compose-content-description">{contentDescription}</p></div><span className={`compose-content-count${content.length >= CARD_CONTENT_MAX_LENGTH ? ' is-limit' : ''}`} aria-live="polite">{content.length} / {CARD_CONTENT_MAX_LENGTH}</span></div>
                <div className={`compose-content-control${contentError ? ' is-error' : ''}`}>
                  <ChatComposerInput
                    id="compose-content-input"
                    className="compose-mention-input"
                    label="正文"
                    ref={composeInputRoot}
                    handleRef={composeInputHandle}
                    value={content}
                    onChange={handleComposeInputChange}
                    triggers={mentionTrigger ? [mentionTrigger] : undefined}
                    debounceMs={180}
                    hasHistory={false}
                    pasteAsToken={false}
                    maxRows={8}
                    placeholder="写下你的发现或想法…"
                    isDisabled={busy}
                    onKeyDown={handleComposeInputKeyDown}
                  />
                </div>
                {contentError && <p className="compose-content-error" id="compose-content-error" role="alert">{contentErrorMessage}</p>}
              </section>

              <section className="compose-details" id="compose-details">
                <TextInput className="compose-tags-field" label="话题" description="可选，最多 3 个；用空格或逗号分隔。" value={tags} onChange={(value) => { setTags(value); setTopicLimitHit(false); }} size="lg" width="100%" placeholder="城市漫游  周末记录" isDisabled={busy} status={topicError ? { type: 'error', message: '最多添加 3 个话题，请移除多余话题。' } : undefined} statusVariant="detached" />
                {topicList.length > 0 && <div className="compose-topic-chips" aria-label="已选话题">{topicList.map((topic) => <button key={topic} type="button" className="compose-topic-chip" onClick={() => removeTopic(topic)} disabled={busy}>#{topic}<span aria-hidden="true">×</span></button>)}</div>}
                {topicSuggestions.length > 0 && <div className="compose-topic-suggestions" aria-label="话题建议"><span>已有话题</span>{topicSuggestions.map((topic) => <button key={topic.id} type="button" onClick={() => addTopic(topic.name)} disabled={busy}>#{topic.name}</button>)}</div>}
                {topicOptionsError && <p className="compose-topic-hint" role="status">{topicOptionsError}</p>}
                <div className="compose-visibility"><span><Icon name="check" size={16} /></span><div><strong>所有人可见</strong><small>发布后会进入公开内容流与个人主页。</small></div></div>
              </section>

              {status === 'error' && message && <ScopedBanner className="compose-task-feedback" title={editMode ? '修改没有保存' : message.includes('草稿') ? '草稿没有保存' : '内容没有发布'} description={message} />}
              {!capability.canPublish && capability.message && <ScopedBanner className="compose-capability-feedback" status="warning" title="当前为只读状态" description={capability.message} />}

              <footer className={`compose-actions${saveState ? ' has-save-state' : ''}${editMode ? ' is-edit' : ''}`}>
                <span className="sr-only" role="status" aria-live="polite">{status === 'saving-edit' ? '正在保存修改' : status === 'publishing' ? '正在发布内容' : status === 'success' ? message : ''}</span>
                <Button className="compose-cancel" type="button" label="取消" variant="secondary" size="lg" onClick={() => dirty ? requestDiscard(returnDestination) : navigate(returnDestination)} isDisabled={busy} />
                {saveState ? <span className={`compose-save-state is-${saveState.tone}`} role="status" aria-live="polite"><Icon name={saveState.tone === 'saved' ? 'check' : 'info'} size={14} />{saveState.label}</span> : <span className="compose-actions-spacer" aria-hidden="true" />}
                {!editMode && <Button className="compose-draft-save" type="button" label={draftId ? '更新草稿' : '存入草稿箱'} variant="secondary" size="lg" onClick={() => void saveDraft()} isDisabled={designPreview || !isAuthenticated || busy || !dirty} isLoading={status === 'draft-saving'} />}
                <Button className="compose-submit" type="submit" label={editMode ? '保存修改' : '确认发布'} variant="primary" size="lg" isDisabled={!capability.canPublish || busy || editMode && !dirty} isLoading={editMode ? status === 'saving-edit' : status === 'publishing'} />
              </footer>
            </section>
        </form>}
        <ConfirmActionDialog isOpen={typeSwitchOpen} onOpenChange={(open) => { setTypeSwitchOpen(open); if (!open) setPendingPublishType(null); }} title={`切换为${publishTypes[pendingPublishType]?.label || '其他类型'}？`} description="当前已选媒体与新类型不兼容，继续切换会移除这些文件，正文和话题会保留。" actionLabel="移除媒体并切换" onAction={confirmTypeSwitch} />
        <ConfirmActionDialog isOpen={discardOpen} onOpenChange={setDiscardOpen} title="放弃这次编辑？" description={editMode ? '尚未保存的修改将不会保留。' : '尚未发布的文字、话题和媒体将不会保留。'} actionLabel="放弃编辑" onAction={() => { setDiscardOpen(false); navigate(discardDestination); }} />
      </main>
    </AppShell>
  );
}
