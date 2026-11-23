import { useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { Link, UNSAFE_DataRouterContext, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { composeMediaKey, deleteComposeMedia, getComposeState, loadComposeTopics, loadPublishedCardForEdit, loadTopicPolicy, normalizeCardVisibility, normalizeDraftMedia, orderComposeMedia, publishCard, updatePublishedCard, uploadComposeMedia } from '../../services/compose-service.js';
import { createDraft, deleteDraft, draftSummary, getDraft, normalizeDraftPayload, updateDraft } from '../../services/community-service.js';
import { useAuth } from '../../app/providers.jsx';
import { ResourceErrorBanner, ResourceState } from '../../components/ResourceState.jsx';
import { useUnsavedChanges } from '../../app/use-unsaved-changes.js';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { getSdkClient } from '../../app/sdk-client.js';
import { loadAuthPolicy } from '../../services/auth-service.js';
import { useHumanVerification } from '../../features/human-verification/useHumanVerification.jsx';
import { createMentionTrigger, createPreviewMentionAdapter, createSdkMentionAdapter, normalizeMentionCaret } from '../../services/mentions-service.js';
import { previewCreators } from '../../fixtures/feed-preview.js';
import { renderMentionCandidateItem } from '../../features/mentions/MentionCandidateItem.jsx';
import { Button, ChatComposerInput, ConfirmActionDialog, Icon, IconButton, MediaDropzone, ScopedBanner, SegmentedControl, SegmentedControlItem, TextInput, useToast } from '../../ui/index.js';
import { CARD_CONTENT_MAX_LENGTH } from '../../features/feed/card-constraints.js';
import { restoreComposeGalleryLayout } from '../../features/media/gallery-layout.js';
import { gallerySwipeDirection } from '../../features/media/useGalleryPager.js';
import { ComposeDialogShell } from './ComposeDialogShell.jsx';
import { ComposePageNavigationGuard } from './ComposePageNavigationGuard.jsx';

const publishTypes = {
  text: { label: '随笔', description: '纯文字，不包含媒体。' },
  image: { label: '图文', description: '先添加 1–9 张图片或 GIF，再填写必填正文。' },
  video: { label: '视频', description: '先添加一个视频，再填写必填正文。' },
};

const visibilityOptions = [
  { value: 'public', label: '公开', description: '按账号当前隐私设置公开展示。' },
  { value: 'followers', label: '仅关注者', description: '限制为你和关注你的人查看。' },
  { value: 'private', label: '仅自己', description: '只有你自己可以查看。' },
];

function topicsFrom(value) {
  const seen = new Set();
  return value.split(/[,，\s]+/).map((tag) => tag.replace(/^#/, '').trim()).filter((tag) => {
    const key = tag.toLowerCase();
    if (!tag || seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

async function cleanupRemovedMedia(ids) {
  try {
    return (await deleteComposeMedia(ids)).length === 0;
  } catch {
    return false;
  }
}

function focusComposeIssue(sectionId, selector) {
  window.requestAnimationFrame(() => {
    const section = document.getElementById(sectionId);
    const reduceMotion = document.documentElement.dataset.reduceMotion === 'true'
      || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    section?.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
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

export function selectComposeFiles(current, selected, { multiple = false, limit = 1 } = {}) {
  if (selected == null) return [];
  const incoming = Array.isArray(selected) ? selected : [selected];
  if (!multiple) return incoming.slice(0, limit);
  const seen = new Set(current.map((file) => composeMediaKey(file, 'local')));
  const next = [...current];
  for (const file of incoming) {
    const identity = composeMediaKey(file, 'local');
    if (seen.has(identity)) continue;
    seen.add(identity);
    if (next.length >= limit) break;
    next.push(file);
  }
  return next;
}

function composeSignature({ content, tags, publishType, media, files, mediaOrder = [], galleryLayout = 'pager', visibility = 'public' }) {
  return JSON.stringify({
    content,
    tags,
    publishType,
    galleryLayout,
    visibility,
    mediaOrder: orderComposeMedia(media, files, mediaOrder).map((entry) => entry.key),
  });
}

export function ComposePage({ surface = 'page' }) {
  const navigate = useNavigate();
  const location = useLocation();
  const dataRouter = useContext(UNSAFE_DataRouterContext);
  const isDialog = surface === 'dialog';
  const [dialogOpen, setDialogOpen] = useState(true);
  const allowLeaveRef = useRef(false);
  const blockedNavigationRef = useRef(null);
  const discardActionRef = useRef('leave');
  const [searchParams, setSearchParams] = useSearchParams();
  const toast = useToast();
  const { verify, dialog: humanVerificationDialog, step: verificationStep } = useHumanVerification();
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
  const loadedEditVisibilityRef = useRef('public');
  const writeInFlightRef = useRef(false);
  const [content, setContent] = useState('');
  const [tags, setTags] = useState('');
  const [topicOptions, setTopicOptions] = useState([]);
  const [topicOptionsError, setTopicOptionsError] = useState(null);
  // null 表示规则尚未加载或加载失败：按现行宽松行为渲染，提交仍由后端裁决。
  const [topicPolicy, setTopicPolicy] = useState(null);
  const [files, setFiles] = useState([]);
  const [mediaPreviews, setMediaPreviews] = useState([]);
  const [persistedMedia, setPersistedMedia] = useState([]);
  const [mediaOrder, setMediaOrder] = useState([]);
  const [dragTarget, setDragTarget] = useState(null);
  const dragSourceRef = useRef(null);
  const previewTouchRef = useRef(null);
  const [previewKey, setPreviewKey] = useState(null);
  const [removedMediaIds, setRemovedMediaIds] = useState([]);
  const [publishType, setPublishType] = useState('text');
  const [galleryLayout, setGalleryLayout] = useState('pager');
  const [visibility, setVisibility] = useState('public');
  const [draftId, setDraftId] = useState(null);
  const [publishedCardId, setPublishedCardId] = useState(null);
  const [publishedData, setPublishedData] = useState({});
  const [loadState, setLoadState] = useState('ready');
  const [loadError, setLoadError] = useState(null);
  const [savedSignature, setSavedSignature] = useState(composeSignature({ content: '', tags: '', publishType: 'text', media: [], files: [] }));
  const [pendingPublishType, setPendingPublishType] = useState(null);
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
    loadComposeTopics().then((items) => { if (active) { setTopicOptions(items); setTopicOptionsError(null); } }).catch((error) => { if (active) setTopicOptionsError(error); });
    loadTopicPolicy().then((policy) => { if (active) setTopicPolicy(policy); }).catch(() => {});
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
        loadedEditVisibilityRef.current = 'public';
        setDraftId(null);
        setPublishedCardId(null);
        setPublishedData({});
        setContent('');
        setTags('');
        setFiles([]);
        setPersistedMedia([]);
        setMediaOrder([]);
        setPreviewKey(null);
        setRemovedMediaIds([]);
        setPublishType('text');
        setGalleryLayout('pager');
        setVisibility('public');
        setSavedSignature(composeSignature({ content: '', tags: '', publishType: 'text', media: [], files: [] }));
        setStatus('idle');
        setMessage('');
      }
      setLoadState('ready');
      setLoadError(null);
      return undefined;
    }
    if (!requestedDraftId) {
      setLoadState('error');
      setLoadError(null);
      setMessage('草稿链接无效，请从草稿箱重新选择。');
      return undefined;
    }
    if (loadedDraftRef.current === requestedDraftId) return undefined;
    let active = true;
    setLoadState('loading');
    setLoadError(null);
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
      setMediaOrder(media.map((item) => composeMediaKey(item, 'persisted')));
      setPreviewKey(null);
      setRemovedMediaIds([]);
      setPublishType(nextType);
      const nextGalleryLayout = restoreComposeGalleryLayout(payload.galleryLayout, media.length, nextType);
      setGalleryLayout(nextGalleryLayout);
      const nextVisibility = normalizeCardVisibility(payload.visibility);
      setVisibility(nextVisibility);
      setSavedSignature(composeSignature({ content: nextContent, tags: nextTags, publishType: nextType, galleryLayout: nextGalleryLayout, media, files: [], visibility: nextVisibility }));
      setLoadState('ready');
      setLoadError(null);
      setStatus('success');
      setMessage('草稿已恢复，可以继续完成发布。');
    }).catch((error) => {
      if (!active) return;
      setLoadState('error');
      setLoadError(error);
    });
    return () => { active = false; };
  }, [authStatus, designPreview, draftQuery, editMode, isAuthenticated, requestedDraftId]);

  useEffect(() => {
    if (designPreview || authStatus !== 'ready' || !isAuthenticated || !editMode) return undefined;
    if (hasConflictingQuery) {
      setLoadState('error');
      setLoadError(null);
      setMessage('编辑链接不能同时包含草稿和已发布内容，请重新进入。');
      return undefined;
    }
    if (!requestedEditId) {
      setLoadState('error');
      setLoadError(null);
      setMessage('编辑链接无效，请从自己的内容重新进入。');
      return undefined;
    }
    if (loadedEditRef.current === requestedEditId) return undefined;
    let active = true;
    setLoadState('loading');
    setLoadError(null);
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
      setMediaOrder(card.media.map((item) => composeMediaKey(item, 'persisted')));
      setPreviewKey(null);
      setRemovedMediaIds([]);
      setPublishType(card.publishType);
      const nextGalleryLayout = restoreComposeGalleryLayout(card.data.galleryLayout, card.media.length, card.publishType);
      setGalleryLayout(nextGalleryLayout);
      loadedEditVisibilityRef.current = card.visibility;
      setVisibility(card.visibility);
      setSavedSignature(composeSignature({ content: card.content, tags: nextTags, publishType: card.publishType, galleryLayout: nextGalleryLayout, media: card.media, files: [], visibility: card.visibility }));
      setLoadState('ready');
      setLoadError(null);
      setStatus('success');
      setMessage('已载入当前内容。');
    }).catch((error) => {
      if (!active) return;
      setLoadState('error');
      setLoadError(error);
    });
    return () => { active = false; };
  }, [authStatus, designPreview, editMode, hasConflictingQuery, isAuthenticated, requestedEditId]);

  const orderedMedia = useMemo(() => orderComposeMedia(persistedMedia, files, mediaOrder), [persistedMedia, files, mediaOrder]);
  const allMediaPreviews = useMemo(() => orderedMedia.map((entry) => entry.source === 'persisted'
    ? { ...entry.item, key: entry.key, source: entry.source }
    : { ...mediaPreviews[entry.index], key: entry.key, file: entry.item, name: entry.item.name, type: entry.item.type, source: entry.source }), [mediaPreviews, orderedMedia]);
  const previewIndex = Math.max(0, allMediaPreviews.findIndex((item) => item.key === previewKey));
  const activePreview = allMediaPreviews[previewIndex];
  const isPagerPreview = publishType === 'image' && galleryLayout === 'pager';
  const mediaCount = persistedMedia.length + files.length;
  const currentSignature = composeSignature({ content, tags, publishType, galleryLayout, media: persistedMedia, files, mediaOrder, visibility });
  const mediaOrderChanged = JSON.stringify(orderedMedia.map((entry) => entry.key)) !== JSON.stringify(JSON.parse(savedSignature).mediaOrder);
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
  // 标签创建规则门控：关闭创建时，未知话题在提交前就地拦截（后端仍是最终裁决方）。
  const topicCreationBlocked = topicPolicy !== null && topicPolicy.allowUserCreate === false;
  const knownTopicNames = new Set(topicOptions.map((topic) => String(topic.name || '').toLowerCase()));
  // 卡片上已保存的话题（编辑/草稿场景）视为已知，避免用户编辑自己的卡片时被误拦。
  const savedTopicNames = new Set((Array.isArray(savedSignature.tags) ? savedSignature.tags : topicsFrom(String(savedSignature.tags ?? ''))).map((topic) => String(topic).toLowerCase()));
  const unknownTopics = topicCreationBlocked
    ? topicList.filter((topic) => !knownTopicNames.has(topic.toLowerCase()) && !savedTopicNames.has(topic.toLowerCase()))
    : [];
  const topicError = topicList.length > 3 || topicLimitHit || unknownTopics.length > 0;
  const topicErrorMessage = unknownTopics.length > 0
    ? `当前不支持创建新话题：#${unknownTopics.join('、#')} 尚不存在，请从已有话题中选择。`
    : '最多添加 3 个话题，请移除多余话题。';
  const topicSuggestions = topicOptions.filter((topic) => !topicList.some((selected) => selected.toLowerCase() === topic.name.toLowerCase())).slice(0, 6);
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

  const removeMedia = (item) => {
    if (item.key === previewKey) setPreviewKey(null);
    setMediaOrder((current) => current.filter((key) => key !== item.key));
    if (item.source === 'persisted') {
      setPersistedMedia((current) => current.filter((value) => value.id !== item.id));
      setRemovedMediaIds((current) => [...new Set([...current, item.id])]);
      return;
    }
    setFiles((current) => current.filter((value) => composeMediaKey(value, 'local') !== item.key));
  };

  const moveMedia = (sourceKey, targetKey) => {
    if (busy || sourceKey === targetKey) return;
    const keys = orderedMedia.map((entry) => entry.key);
    const sourceIndex = keys.indexOf(sourceKey);
    const targetIndex = keys.indexOf(targetKey);
    if (sourceIndex < 0 || targetIndex < 0) return;
    keys.splice(sourceIndex, 1);
    keys.splice(targetIndex, 0, sourceKey);
    setMediaOrder(keys);
  };

  const movePreview = (offset) => {
    const nextIndex = Math.max(0, Math.min(allMediaPreviews.length - 1, previewIndex + offset));
    if (allMediaPreviews[nextIndex]) setPreviewKey(allMediaPreviews[nextIndex].key);
  };

  const finishMediaDrag = (event) => {
    const sourceKey = dragSourceRef.current;
    if (sourceKey) {
      const targetKey = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-compose-media-key]')?.dataset.composeMediaKey;
      if (targetKey) moveMedia(sourceKey, targetKey);
    }
    dragSourceRef.current = null;
    setDragTarget(null);
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
    if (editMode || designPreview || !isAuthenticated || busy || writeInFlightRef.current) return;
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
    writeInFlightRef.current = true;
    setStatus('draft-saving');
    setMessage('正在保存草稿…');
    let uploadedMedia = [];
    try {
      uploadedMedia = await uploadComposeMedia(files);
      const nextMedia = orderComposeMedia(persistedMedia, files, mediaOrder).map((entry) => entry.source === 'persisted' ? entry.item : uploadedMedia[entry.index]);
      const nextTags = topicsFrom(tags);
      const payload = { publishType, kind: resolveKind(publishType, nextMedia.length), galleryLayout, tags: nextTags, media: nextMedia, visibility };
      const data = { title: draftSummary({ content, payload }), content, payload };
      let nextId = draftId;
      if (nextId) {
        await updateDraft(nextId, data);
      } else {
        const result = await createDraft(data);
        nextId = Number(result?.id);
        if (!Number.isInteger(nextId) || nextId <= 0) throw new Error('草稿接口没有返回有效编号。');
      }
      const mediaCleanupSucceeded = await cleanupRemovedMedia(removedMediaIds);
      const normalizedTags = nextTags.join(' ');
      loadedDraftRef.current = nextId;
      setDraftId(nextId);
      setPersistedMedia(nextMedia);
      setMediaOrder(nextMedia.map((item) => composeMediaKey(item, 'persisted')));
      setFiles([]);
      setRemovedMediaIds(mediaCleanupSucceeded ? [] : removedMediaIds);
      setTags(normalizedTags);
      setSavedSignature(composeSignature({ content, tags: normalizedTags, publishType, galleryLayout, media: nextMedia, files: [], visibility }));
      const nextSearchParams = new URLSearchParams(searchParams);
      nextSearchParams.set('draft', String(nextId));
      allowLeaveRef.current = true;
      setSearchParams(nextSearchParams, { replace: true, state: isDialog ? location.state : undefined });
      setStatus('success');
      setMessage(mediaCleanupSucceeded
        ? (draftId ? '草稿已更新。' : '已存入草稿箱。')
        : '草稿已保存，但旧媒体清理失败；本页再次保存时会重试。');
    } catch (error) {
      await deleteComposeMedia(uploadedMedia.map((item) => item.id));
      setStatus('error');
      setMessage(getUserFacingError(error, { fallback: '草稿暂时没有保存，当前输入仍保留在页面。', validationFallback: '草稿内容不符合要求，请检查后重试。' }));
    } finally {
      writeInFlightRef.current = false;
    }
  };

  const submit = async (event) => {
    event.preventDefault();
    if (busy || writeInFlightRef.current) return;
    setValidationAttempted(true);
    if (topicList.length <= 3) setTopicLimitHit(false);
    if (publishType !== 'text' && mediaCount === 0) {
      setStatus('idle');
      setMessage('');
      focusComposeIssue('compose-media', 'button');
      return;
    }
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
    if (topicList.length > 3) {
      setStatus('idle');
      setMessage('');
      focusComposeIssue('compose-details', 'input');
      return;
    }
    if (unknownTopics.length > 0) {
      setStatus('idle');
      setMessage('');
      focusComposeIssue('compose-details', 'input');
      return;
    }
    writeInFlightRef.current = true;
    setStatus(editMode ? 'saving-edit' : 'publishing');
    setMessage('');
    try {
      const verification = await verify(await loadAuthPolicy());
      if (editMode) {
        const result = await updatePublishedCard({
          id: publishedCardId || requestedEditId,
          content,
          tags: topicsFrom(tags),
          kind: resolveKind(publishType, mediaCount),
          files,
          media: persistedMedia,
          mediaOrder,
          data: publishedData,
          ...(publishType === 'image' ? { galleryLayout } : {}),
          mediaChanged: files.length > 0 || removedMediaIds.length > 0 || mediaOrderChanged,
          ...(visibility !== loadedEditVisibilityRef.current ? { visibility } : {}),
          verification,
        });
        const mediaCleanupSucceeded = await cleanupRemovedMedia(removedMediaIds);
        setStatus('success');
        setMessage(mediaCleanupSucceeded ? '修改已保存。' : '修改已保存，但旧媒体清理失败。');
        toast({ body: mediaCleanupSucceeded ? '修改已保存' : '修改已保存，旧媒体清理失败', type: mediaCleanupSucceeded ? undefined : 'error', uniqueID: 'compose-edit-success' });
        allowLeaveRef.current = true;
        navigate(`/post/${result.id}`, { replace: isDialog, state: null });
        return;
      }
      const result = await publishCard({ content, tags: topicsFrom(tags), kind: resolveKind(publishType, mediaCount), files, media: persistedMedia, mediaOrder, ...(publishType === 'image' ? { galleryLayout } : {}), visibility, verification });
      let draftCleanupFailed = false;
      if (draftId) {
        try {
          await deleteDraft(draftId);
        } catch {
          draftCleanupFailed = true;
        }
      }
      const mediaCleanupSucceeded = await cleanupRemovedMedia(removedMediaIds);
      const cleanupNotes = [
        ...(draftCleanupFailed ? ['原草稿'] : []),
        ...(!mediaCleanupSucceeded ? ['旧媒体'] : []),
      ];
      setStatus('success');
      setMessage(cleanupNotes.length ? `内容已发布，但${cleanupNotes.join('和')}清理失败。` : '发布成功。');
      toast({ body: cleanupNotes.length ? `内容已发布，${cleanupNotes.join('和')}清理失败` : '内容已发布', type: cleanupNotes.length ? 'error' : undefined, uniqueID: 'compose-success' });
      allowLeaveRef.current = true;
      navigate(result?.id ? `/post/${result.id}` : '/me', { replace: isDialog, state: null });
    } catch (error) {
      setStatus('error');
      setMessage(getUserFacingError(error, editMode
        ? { fallback: '修改暂时没有保存，当前输入仍保留在页面。', validationFallback: '修改内容不符合要求，请检查文字与媒体后重试。' }
        : { fallback: '内容暂时没有发布，当前输入仍保留在页面。', validationFallback: '发布内容不符合要求，请检查文字与媒体后重试。' }));
    } finally {
      writeInFlightRef.current = false;
    }
  };

  const returnDestination = editMode && (publishedCardId || requestedEditId) ? `/post/${publishedCardId || requestedEditId}` : '/';
  const requestDiscard = useCallback((destination = returnDestination, action = 'leave') => {
    discardActionRef.current = action;
    setDiscardDestination(destination);
    setDiscardOpen(true);
  }, [returnDestination]);
  const onNavigationBlocked = useCallback((blocker) => {
    setPendingPublishType(null);
    discardActionRef.current = 'leave';
    blockedNavigationRef.current = blocker;
    setDiscardOpen(true);
  }, []);
  useEffect(() => {
    if (dirty) allowLeaveRef.current = false;
  }, [dirty]);
  const requestClose = useCallback(() => {
    if (busy) return;
    if (dirty) requestDiscard(returnDestination, 'close');
    else setDialogOpen(false);
  }, [busy, dirty, requestDiscard, returnDestination]);
  const closeOrReturn = () => {
    if (isDialog) requestClose();
    else if (dirty) requestDiscard(returnDestination);
    else navigate(returnDestination);
  };
  const onDialogExited = useCallback(() => navigate(-1), [navigate]);
  const selectPublishType = (nextType) => {
    if (nextType === publishType) return;
    if (mediaCount) {
      setPendingPublishType(nextType);
      return;
    }
    setPublishType(nextType);
    setGalleryLayout('pager');
    setMessage('');
  };
  const confirmTypeSwitch = () => {
    setRemovedMediaIds((current) => [...new Set([...current, ...persistedMedia.map((item) => item.id)])]);
    setFiles([]);
    setPersistedMedia([]);
    setMediaOrder([]);
    setPreviewKey(null);
    setPublishType(pendingPublishType || 'text');
    setGalleryLayout('pager');
    setPendingPublishType(null);
    setMessage('');
  };
  const cancelDiscard = () => {
    setDiscardOpen(false);
    const blocker = blockedNavigationRef.current;
    if (blocker?.state === 'blocked') blocker.reset();
    blockedNavigationRef.current = null;
  };
  const confirmDiscard = () => {
    setDiscardOpen(false);
    allowLeaveRef.current = true;
    if (isDialog && discardActionRef.current === 'close') setDialogOpen(false);
    else if (blockedNavigationRef.current?.state === 'blocked') {
      blockedNavigationRef.current.proceed();
      blockedNavigationRef.current = null;
    } else navigate(discardDestination);
  };
  const confirmation = discardOpen ? {
    title: '放弃这次编辑？',
    description: editMode ? '尚未保存的修改将不会保留。' : '尚未发布的文字、话题和媒体将不会保留。',
    actionLabel: '放弃编辑',
    onCancel: cancelDiscard,
    onAction: confirmDiscard,
  } : pendingPublishType ? {
    title: `切换为${publishTypes[pendingPublishType].label}？`,
    description: '当前已选媒体与新类型不兼容，继续切换会移除这些文件，正文和话题会保留。',
    actionLabel: '移除媒体并切换',
    onCancel: () => setPendingPublishType(null),
    onAction: confirmTypeSwitch,
  } : null;
  const step = verificationStep || (confirmation && {
    ...confirmation,
    footer: <><Button type="button" label="取消" variant="secondary" size="lg" onClick={confirmation.onCancel} /><Button type="button" label={confirmation.actionLabel} variant="primary" size="lg" onClick={confirmation.onAction} /></>,
  });
  const canWriteCard = editMode ? can('cards.update') : can('cards.create');
  const showDraftsLink = !editMode && (designPreview || (authStatus === 'ready' && isAuthenticated && canWriteCard));
  useUnsavedChanges(dirty, requestDiscard, { interceptLinks: !dataRouter && !isDialog });

  const editorContent = (
      <main className="compose-page" aria-labelledby="compose-title">
        {!isDialog && dataRouter && <ComposePageNavigationGuard dirty={dirty} allowLeaveRef={allowLeaveRef} onBlocked={onNavigationBlocked} />}
        <header className="compose-heading">
          <button className="compose-back" type="button" onClick={closeOrReturn} aria-label={isDialog ? '关闭发布窗口' : editMode ? '取消编辑并返回内容详情' : '取消发布并返回首页'}><Icon name={isDialog ? 'close' : 'arrowLeft'} size={18} /><span>{isDialog ? '关闭' : '返回'}</span></button>
          <div className="compose-heading-copy">
            <h1 id="compose-title">{editMode ? '编辑内容' : draftId ? '继续完成发布' : '发布新内容'}</h1>
            <p>{editMode ? '修改会直接更新这张卡片。' : '把想法整理成一张卡片。'}</p>
          </div>
          {showDraftsLink && <Link className="compose-drafts-link" to="/drafts" aria-disabled={busy} tabIndex={busy ? -1 : undefined} onClick={(event) => { if (busy) event.preventDefault(); }}><Icon name="edit" size={15} />草稿箱</Link>}
        </header>

        {!designPreview && authStatus === 'checking' ? <ComposeSkeleton /> : !designPreview && authStatus === 'error' ? <ResourceState status="error" error={authError} onRetry={refreshAuth} /> : !designPreview && !isAuthenticated ? <section className="profile-boundary"><h2>登录后{editMode ? '编辑' : '发布'}内容</h2><p>游客可以浏览公开内容；发布、评论和管理个人内容需要正式账号。</p><Link className="action-link action-link-primary" to="/auth/login">去登录</Link></section> : !designPreview && !canWriteCard ? <section className="profile-boundary"><h2>当前账号不能{editMode ? '编辑' : '发布'}</h2><p>你的账号暂时没有{editMode ? '修改这张卡片' : '发布内容'}的权限。</p><Link className="action-link" to={returnDestination}>返回</Link></section> : loadState === 'loading' ? <ComposeSkeleton /> : loadState === 'error' ? <ResourceState className="profile-boundary" status="error" error={loadError} title={editMode ? '编辑链接无效' : '草稿链接无效'} description={message} actionLabel="返回" onRetry={loadError ? () => window.location.reload() : () => navigate(editMode ? returnDestination : '/drafts')} /> : loadState === 'forbidden' ? <section className="profile-boundary"><h2>不能编辑这张卡片</h2><p>它不属于当前账号，或当前账号没有修改权限。</p><Link className="action-link" to="/me">返回我的主页</Link></section> : loadState === 'not-found' ? <section className="profile-boundary"><h2>{editMode ? '这张卡片不存在' : '这份草稿不存在'}</h2><p>它可能已被删除，或不属于当前账号。</p><Link className="action-link" to={editMode ? '/me' : '/drafts'}>返回</Link></section> : <form className="compose-form" onSubmit={submit}>
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
                <header><div><h3 id="compose-media-title">{publishType === 'video' ? '视频' : '图片'}<span aria-hidden="true"> *</span></h3><p>{publishType === 'video' ? '必选：选择一个视频，发布后使用原生播放控制。' : '必选：最多 9 张。拖动图片调整顺序，第一张为封面；也可用前移、后移按钮。'}</p></div><span>{mediaCount}/{publishType === 'video' ? 1 : 9}</span></header>
                <MediaDropzone
                  label={publishType === 'video' ? mediaCount ? '更换视频' : '添加视频' : mediaCount ? '继续添加图片或 GIF' : '添加图片或 GIF'}
                  description={publishType === 'video' ? '支持 MP4、WebM、MOV 与 M4V；文件大小以服务端策略为准。' : '支持 JPEG、PNG、WebP 与 GIF；文件大小以服务端策略为准。'}
                  value={publishType === 'video' ? files[0] || null : files}
                  onChange={(value) => {
                    const available = Math.max(0, (publishType === 'video' ? 1 : 9) - persistedMedia.length);
                    const nextFiles = selectComposeFiles(files, value, { multiple: publishType === 'image', limit: available });
                    setFiles(nextFiles);
                    const nextKeys = nextFiles.map((file) => composeMediaKey(file, 'local'));
                    setMediaOrder((current) => publishType === 'video'
                      ? nextKeys
                      : [...current.filter((key) => !key.startsWith('file:') || nextKeys.includes(key)), ...nextKeys.filter((key) => !current.includes(key))]);
                  }}
                  accept={publishType === 'video' ? 'video/mp4,video/webm,video/quicktime,video/x-m4v,.mov,.m4v' : 'image/jpeg,image/png,image/webp,image/gif'}
                  maxSize={publishType === 'image' ? 10 * 1024 * 1024 : undefined}
                  isMultiple={publishType === 'image'}
                  maxFiles={Math.max(1, (publishType === 'image' ? 9 : 1) - mediaCount)}
                  placeholder={publishType === 'video' ? '拖入视频，或点击选择' : '拖入图片，或点击选择'}
                  isDisabled={busy || !canUploadMedia || mediaCount >= (publishType === 'video' ? 1 : 9)}
                  disabledMessage={!canUploadMedia ? '当前账号没有上传媒体的权限。' : mediaCount >= (publishType === 'video' ? 1 : 9) ? '已达到当前类型的媒体数量上限。' : undefined}
                  isLoading={busy}
                  status={mediaError ? { type: 'error', message: publishType === 'video' ? '请添加一个视频后再发布。' : '请至少添加一张图片后再发布。' } : undefined}
                />
                {publishType === 'image' && <div className="compose-gallery-layout" aria-labelledby="compose-gallery-layout-title">
                  <div><h4 id="compose-gallery-layout-title">图片排布</h4><p>默认翻页；只有一张时只显示这一页。宫格和翻页均可在发布前查看，图片顺序以缩略图为准。</p></div>
                  <SegmentedControl value={galleryLayout} onChange={setGalleryLayout} label="图片排布" layout="fill" size="md" isDisabled={busy}>
                    <SegmentedControlItem value="pager" label="翻页" />
                    <SegmentedControlItem value="grid" label="宫格" />
                  </SegmentedControl>
                </div>}
                {isPagerPreview && activePreview && <div className="compose-media-pager" role="group" aria-label={`图片翻页预览，共 ${allMediaPreviews.length} 张`} tabIndex={allMediaPreviews.length > 1 ? 0 : undefined}
                  onKeyDown={(event) => { if (event.target === event.currentTarget && ['ArrowLeft', 'ArrowRight'].includes(event.key)) { event.preventDefault(); movePreview(event.key === 'ArrowLeft' ? -1 : 1); } }}
                  onTouchStart={(event) => { const touch = event.touches.length === 1 ? event.touches[0] : null; previewTouchRef.current = touch ? { id: touch.identifier, x: touch.clientX, y: touch.clientY } : null; }}
                  onTouchMove={(event) => { if (event.touches.length !== 1) previewTouchRef.current = null; }}
                  onTouchEnd={(event) => { const start = previewTouchRef.current; previewTouchRef.current = null; const touch = event.changedTouches.length === 1 ? event.changedTouches[0] : null; if (start && touch?.identifier === start.id) movePreview(gallerySwipeDirection(start, { x: touch.clientX, y: touch.clientY })); }}
                  onTouchCancel={() => { previewTouchRef.current = null; }}>
                  <img key={activePreview.key} src={activePreview.url} alt={`排布预览：第 ${previewIndex + 1} 张，${activePreview.name || '图片'}`} draggable="false" />
                  {allMediaPreviews.length > 1 && <>
                    <span className="compose-media-pager-count" aria-live="polite">{previewIndex + 1} / {allMediaPreviews.length}</span>
                    <button className="compose-media-pager-previous" type="button" aria-label="上一张图片" disabled={previewIndex === 0} onClick={() => movePreview(-1)}><Icon name="arrow" size={18} /></button>
                    <button className="compose-media-pager-next" type="button" aria-label="下一张图片" disabled={previewIndex === allMediaPreviews.length - 1} onClick={() => movePreview(1)}><Icon name="arrow" size={18} /></button>
                  </>}
                </div>}
                {allMediaPreviews.length ? <div className={`compose-media-preview-grid is-${allMediaPreviews.length > 4 ? 'many' : allMediaPreviews.length}${isPagerPreview ? ' is-pager-strip' : ''}`} role="group" aria-label={isPagerPreview ? `${allMediaPreviews.length} 张可排序图片` : `${allMediaPreviews.length} 个待发布媒体`}>
                  {allMediaPreviews.map((item, index) => <div className={`compose-media-preview is-${item.source}${dragTarget === item.key ? ' is-drag-target' : ''}`} key={item.key} data-compose-media-key={item.key}>
                    {publishType === 'video' ? <video src={item.url} muted playsInline preload="metadata" /> : isPagerPreview ? <button className={`compose-media-thumbnail-select${previewIndex === index ? ' is-active' : ''}`} type="button" aria-label={`预览第 ${index + 1} 张图片`} aria-current={previewIndex === index ? 'true' : undefined} onClick={() => setPreviewKey(item.key)}><img src={item.url} alt="" draggable="false" /></button> : <img src={item.url} alt={`待发布媒体 ${index + 1}：${item.name || '图片'}`} draggable="false" />}
                    <span className="compose-media-position">{publishType === 'image' && index === 0 ? '封面 · 1' : index + 1}</span>
                    {publishType === 'image' && allMediaPreviews.length > 1 && <div className="compose-media-sort-actions">
                      <button type="button" className="compose-media-drag-handle" aria-label={`拖动第 ${index + 1} 张图片排序`} title="拖动排序" disabled={busy}
                        onPointerDown={(event) => { dragSourceRef.current = item.key; event.currentTarget.setPointerCapture(event.pointerId); }}
                        onPointerMove={(event) => { if (dragSourceRef.current) setDragTarget(document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-compose-media-key]')?.dataset.composeMediaKey || null); }}
                        onPointerUp={finishMediaDrag}
                        onPointerCancel={() => { dragSourceRef.current = null; setDragTarget(null); }}><Icon name="grip" size={16} /></button>
                      <button type="button" className="compose-media-sort-previous" aria-label={`第 ${index + 1} 张图片前移`} title="前移" disabled={busy || index === 0} onClick={() => moveMedia(item.key, allMediaPreviews[index - 1].key)}><Icon name="arrow" size={16} /></button>
                      <button type="button" aria-label={`第 ${index + 1} 张图片后移`} title="后移" disabled={busy || index === allMediaPreviews.length - 1} onClick={() => moveMedia(item.key, allMediaPreviews[index + 1].key)}><Icon name="arrow" size={16} /></button>
                    </div>}
                    <IconButton className="lc-upload-preview-remove" type="button" onClick={() => removeMedia(item)} label={publishType === 'video' ? '移除视频' : `移除第 ${index + 1} 张图片`} tooltip="移除媒体" variant="ghost" size="lg" icon={<Icon name="close" size={18} />} isDisabled={busy} />
                  </div>)}
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
                <div className="compose-topic-field">
                  <TextInput className="compose-tags-field" label="话题" description={topicCreationBlocked ? '可选，最多 3 个；当前只能使用已有话题。' : '可选，最多 3 个；用空格或逗号分隔。'} value={tags} onChange={(value) => { setTags(value); setTopicLimitHit(false); }} size="lg" width="100%" placeholder="城市漫游  周末记录" isDisabled={busy} status={topicError ? { type: 'error', message: topicErrorMessage } : undefined} statusVariant="detached" />
                  {topicList.length > 0 && <div className="compose-topic-chips" aria-label="已选话题">{topicList.map((topic) => <button key={topic} type="button" className="compose-topic-chip" onClick={() => removeTopic(topic)} disabled={busy}>#{topic}<span aria-hidden="true">×</span></button>)}</div>}
                  {topicSuggestions.length > 0 && <div className="compose-topic-suggestions" aria-label="话题建议"><span>已有话题</span>{topicSuggestions.map((topic) => <button key={topic.id} type="button" onClick={() => addTopic(topic.name)} disabled={busy}>#{topic.name}</button>)}</div>}
                  {topicOptionsError && <ResourceErrorBanner className="compose-topic-hint" error={topicOptionsError} />}
                  {topicCreationBlocked && <p className="compose-topic-hint">新话题创建已关闭，请从“已有话题”中选择。</p>}
                  {!topicCreationBlocked && topicPolicy !== null && topicPolicy.reviewNew && <p className="compose-topic-hint">新话题需管理员审核通过后才会公开展示。</p>}
                </div>
                <section className="compose-visibility" aria-labelledby="compose-visibility-title">
                  <header><h3 id="compose-visibility-title">谁可以查看</h3><p>卡片范围会与账号隐私设置共同生效。</p></header>
                  <SegmentedControl className="compose-visibility-control" value={visibility} onChange={setVisibility} label="卡片可见范围" layout="fill" size="lg" isDisabled={busy}>
                    {visibilityOptions.map((option) => <SegmentedControlItem key={option.value} value={option.value} label={option.label} />)}
                  </SegmentedControl>
                </section>
              </section>

              {status === 'error' && message && <ScopedBanner className="compose-task-feedback" title={editMode ? '修改没有保存' : message.includes('草稿') ? '草稿没有保存' : '内容没有发布'} description={message} />}
              {!capability.canPublish && capability.message && <ScopedBanner className="compose-capability-feedback" status="warning" title="当前为只读状态" description={capability.message} />}

              <footer className={`compose-actions${saveState ? ' has-save-state' : ''}${editMode ? ' is-edit' : ''}`}>
                <span className="sr-only" role="status" aria-live="polite">{status === 'saving-edit' ? '正在保存修改' : status === 'publishing' ? '正在发布内容' : status === 'success' ? message : ''}</span>
                {saveState ? <span className={`compose-save-state is-${saveState.tone}`} role="status" aria-live="polite"><Icon name={saveState.tone === 'saved' ? 'check' : 'info'} size={14} />{saveState.label}</span> : <span className="compose-actions-spacer" aria-hidden="true" />}
                {!editMode && <Button className="compose-draft-save" type="button" label={draftId ? '更新草稿' : '存入草稿箱'} variant="secondary" size="lg" onClick={() => void saveDraft()} isDisabled={designPreview || !isAuthenticated || busy || !dirty} isLoading={status === 'draft-saving'} />}
                <Button className="compose-submit" type="submit" label={editMode ? '保存修改' : '确认发布'} variant="primary" size="lg" isDisabled={!capability.canPublish || busy || editMode && !dirty} isLoading={editMode ? status === 'saving-edit' : status === 'publishing'} />
              </footer>
            </section>
        </form>}
        {!isDialog && <ConfirmActionDialog isOpen={Boolean(confirmation)} onOpenChange={(open) => { if (!open) confirmation?.onCancel(); }} title={confirmation?.title} description={confirmation?.description} actionLabel={confirmation?.actionLabel} onAction={confirmation?.onAction} />}
        {!isDialog && humanVerificationDialog}
      </main>
  );
  return isDialog
    ? <ComposeDialogShell isOpen={dialogOpen} step={step} dirty={dirty} busy={busy} allowLeaveRef={allowLeaveRef} onRequestClose={requestClose} onNavigationBlocked={onNavigationBlocked} onExited={onDialogExited}>{editorContent}</ComposeDialogShell>
    : <AppShell mobileMode="immersive">{editorContent}</AppShell>;
}
