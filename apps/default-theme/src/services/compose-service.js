import { getSdkClient, sdkConfigured } from '../app/sdk-client.js';
import { getRuntimeDataMode } from '../app/runtime-context.js';
import { CARD_CONTENT_MAX_LENGTH } from '../features/feed/card-constraints.js';

export const COMPOSE_IMAGE_MIME_TYPES = Object.freeze(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
export const COMPOSE_VIDEO_MIME_TYPES = Object.freeze(['video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v']);
export const COMPOSE_IMAGE_MAX_BYTES = 10 * 1024 * 1024;
const CARD_VISIBILITIES = Object.freeze(['public', 'followers', 'private']);

export function normalizeCardVisibility(value) {
  return CARD_VISIBILITIES.includes(value) ? value : 'public';
}

function mediaType(value) {
  return String(value?.type || value?.mime_type || '').trim().toLowerCase();
}

function isVideoMedia(value) {
  const type = mediaType(value);
  return type === 'video' || COMPOSE_VIDEO_MIME_TYPES.includes(type);
}

function isImageMedia(value) {
  const type = mediaType(value);
  return type === 'image' || type === 'gif' || COMPOSE_IMAGE_MIME_TYPES.includes(type);
}

function resolvePublishKind(kind, files) {
  if (kind) return kind;
  if (files.length > 1) return 'gallery';
  if (files.length === 1 && isVideoMedia(files[0])) return 'video';
  if (files.length === 1) return 'image';
  return 'text';
}

function validatePublishMedia(kind, files) {
  if (!['text', 'image', 'gallery', 'video'].includes(kind)) throw new Error('请选择有效的发布类型。');
  if (kind === 'text') {
    if (files.length) throw new Error('随笔不能包含媒体，请先移除已选文件。');
    return;
  }
  if (kind === 'video') {
    if (files.length !== 1) throw new Error('视频发布需要选择一个视频文件。');
    if (!isVideoMedia(files[0])) throw new Error('视频仅支持 MP4、WebM、MOV 或 M4V。');
    return;
  }
  if (files.length < 1 || files.length > 9) throw new Error('图文发布需要选择 1–9 张图片或 GIF。');
  if (files.some((file) => !isImageMedia(file))) throw new Error('图文仅支持 JPEG、PNG、WebP 或 GIF。');
  if (files.some((file) => Number(file?.size) > COMPOSE_IMAGE_MAX_BYTES)) throw new Error('每张图片不能超过 10MB，请选择更小的图片。');
}

function emptyContentMessage(kind) {
  if (kind === 'video') return '视频必须先填写正文。';
  if (kind === 'image' || kind === 'gallery') return '图文必须先填写正文。';
  return '请先写下要分享的内容。';
}

function visibilityPayload(visibility) {
  if (visibility === undefined) return {};
  if (!CARD_VISIBILITIES.includes(visibility)) throw new Error('请选择有效的内容可见范围。');
  return { visibility };
}

function normalizeMediaReference(value) {
  const id = Number(value?.id ?? value?.file_id ?? value?.fileId);
  const url = String(value?.url || '').trim();
  if (!Number.isInteger(id) || id <= 0 || !url) return null;
  return {
    id,
    url,
    type: String(value?.mime_type || value?.type || '').trim(),
    name: String(value?.name || value?.original_name || '').trim(),
    size: Number(value?.size) || 0,
  };
}

export function normalizeDraftMedia(value) {
  return Array.isArray(value) ? value.map(normalizeMediaReference).filter(Boolean) : [];
}

export async function uploadComposeMedia(files = []) {
  const client = getSdkClient();
  if (!client?.getToken()) throw new Error('请先登录后再保存媒体。');
  const uploads = [];
  try {
    for (const selectedFile of files) {
      const formData = new FormData();
      formData.append('file', selectedFile);
      formData.append('scene', 'card');
      const upload = await client.files.upload(formData);
      uploads.push({ id: upload.id, url: upload.url, type: selectedFile.type || upload.mime_type || '', name: selectedFile.name || upload.original_name || '' });
    }
    return uploads;
  } catch (error) {
    await Promise.allSettled(uploads.map((upload) => client.files.delete(upload.id)));
    throw error;
  }
}

export async function deleteComposeMedia(ids = []) {
  const client = getSdkClient();
  if (!client?.getToken()) return;
  const uniqueIds = [...new Set(ids.map(Number).filter((id) => Number.isInteger(id) && id > 0))];
  await Promise.allSettled(uniqueIds.map((id) => client.files.delete(id)));
}

export function getComposeState() {
  const mode = getRuntimeDataMode();
  if (mode === 'design-preview') return { source: 'preview', canPublish: false, message: '设计预览为只读，内容不会发布。' };
  if (mode !== 'api') return { source: 'configuration-error', canPublish: false, message: 'VITE_DATA_MODE 配置无效。' };
  if (!sdkConfigured()) return { source: 'configuration-error', canPublish: false, message: '尚未配置 API，内容无法发布。' };
  return { source: 'live', canPublish: true, message: '' };
}

export async function loadComposeAuthor() {
  const client = getSdkClient();
  if (!client?.getToken()) return null;
  const user = await client.users.me();
  if (!user || !Number.isInteger(user.id)) throw new Error('SDK 契约错误：users.me() 未返回有效用户。');
  return { id: user.id, username: user.username || `用户 ${user.id}`, avatar: user.avatar || '' };
}

export async function loadComposeTopics() {
  const state = getComposeState();
  if (!state.canPublish) return [];
  const tags = await getSdkClient().tags.list();
  return Array.isArray(tags)
    ? tags.filter((tag) => Number.isInteger(Number(tag?.id)) && String(tag?.name || '').trim()).map((tag) => ({ id: Number(tag.id), name: String(tag.name).trim() }))
    : [];
}

// 读取标签创建规则（社区规则页「标签」组的公开投影）。
// 读取失败时由调用方回落到宽松默认（允许创建、不审核），最终裁决始终在后端提交时执行。
export async function loadTopicPolicy() {
  const policy = await getSdkClient().tags.policy();
  return {
    allowUserCreate: policy?.allow_user_create !== false,
    reviewNew: policy?.review_new_tags === true,
  };
}

function readCardData(value) {
  if (value && typeof value === 'object' && !Array.isArray(value)) return { ...value };
  if (typeof value !== 'string' || !value.trim()) return {};
  try {
    const parsed = JSON.parse(value);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch {
    return {};
  }
}

function normalizedTopicNames(card) {
  const topics = Array.isArray(card?.topics) ? card.topics : [];
  const names = topics.map((topic) => String(topic?.name || '').trim()).filter(Boolean);
  if (names.length) return [...new Set(names)];
  return [...new Set((Array.isArray(card?.tags) ? card.tags : []).map((tag) => String(tag || '').trim()).filter((tag) => tag && !/^\d+$/.test(tag)))];
}

export async function loadPublishedCardForEdit(id) {
  const state = getComposeState();
  if (!state.canPublish) throw new Error(state.message);
  const client = getSdkClient();
  if (!client?.getToken()) throw new Error('请先登录后再编辑内容。');
  const numericId = Number(id);
  if (!Number.isInteger(numericId) || numericId <= 0) throw new Error('编辑链接无效，请返回自己的内容重新进入。');
  if (typeof client.cards?.getOwn !== 'function') throw new Error('当前 SDK 暂不支持读取自己的卡片。');
  const card = await client.cards.getOwn(numericId);
  if (!card || Number(card.id) !== numericId) throw new Error('SDK 契约错误：cards.getOwn() 未返回目标卡片。');
  const rawMedia = Array.isArray(card.media)
    ? card.media.filter((item) => String(item?.url || '').trim()).map((item, index) => ({ item, index })).sort((left, right) => {
      const leftPosition = Number.isInteger(Number(left.item?.position)) ? Number(left.item.position) : left.index;
      const rightPosition = Number.isInteger(Number(right.item?.position)) ? Number(right.item.position) : right.index;
      return leftPosition - rightPosition || left.index - right.index;
    }).map(({ item }) => item)
    : [];
  const media = normalizeDraftMedia(rawMedia);
  const hasLegacyMedia = media.length !== rawMedia.length || (!rawMedia.length && ((Array.isArray(card.pictures) && card.pictures.length > 0) || String(card.cover || '').trim()));
  if (hasLegacyMedia) throw new Error('这张历史卡片的媒体缺少可编辑引用，暂时无法安全保存修改。');
  const data = readCardData(card.data);
  const requestedKind = String(data.kind || '').trim();
  const kind = ['text', 'image', 'gallery', 'video'].includes(requestedKind)
    ? requestedKind
    : resolvePublishKind(null, media);
  const publishType = kind === 'video' ? 'video' : kind === 'image' || kind === 'gallery' || media.length ? 'image' : 'text';
  return {
    id: numericId,
    content: String(card.content || ''),
    tags: normalizedTopicNames(card),
    data,
    media,
    publishType,
    visibility: normalizeCardVisibility(card.visibility),
  };
}

export async function updatePublishedCard({ id, content, tags = [], kind, files = [], media = [], data = {}, mediaChanged = false, visibility, verification = {} }) {
  const state = getComposeState();
  if (!state.canPublish) throw new Error(state.message);
  const client = getSdkClient();
  if (!client?.getToken()) throw new Error('请先登录后再编辑内容。');
  const numericId = Number(id);
  if (!Number.isInteger(numericId) || numericId <= 0) throw new Error('当前内容无法编辑。');
  const existingMedia = normalizeDraftMedia(media);
  const publishKind = resolvePublishKind(kind, [...existingMedia, ...files]);
  const normalizedContent = String(content || '').trim();
  if (!normalizedContent) throw new Error(emptyContentMessage(publishKind));
  if (normalizedContent.length > CARD_CONTENT_MAX_LENGTH) throw new Error(`正文最多 ${CARD_CONTENT_MAX_LENGTH} 字，请精简后再保存。`);
  const normalizedTags = [...new Set(tags.map((tag) => String(tag || '').trim().replace(/^#/, '')).filter(Boolean))];
  if (normalizedTags.length > 3) throw new Error('最多添加 3 个话题，请移除多余话题。');
  const cardVisibilityPayload = visibilityPayload(visibility);
  validatePublishMedia(publishKind, [...existingMedia, ...files]);
  let uploads = [];
  try {
    uploads = await uploadComposeMedia(files);
    const allMedia = [...existingMedia, ...uploads];
    const mediaPayload = mediaChanged ? {
      media_file_ids: allMedia.map((item) => item.id),
      cover_file_id: publishKind !== 'video' && allMedia.length ? allMedia[0].id : 0,
    } : {};
    await client.cards.update(numericId, {
      content: normalizedContent,
      tags: normalizedTags,
      ...cardVisibilityPayload,
      data: { ...readCardData(data), kind: publishKind },
      ...mediaPayload,
      ...verification,
    });
    return { id: numericId };
  } catch (error) {
    await deleteComposeMedia(uploads.map((item) => item.id));
    throw error;
  }
}

export async function publishCard({ content, tags = [], kind, files = [], file = null, media = [], visibility = 'public', verification = {} }) {
  const state = getComposeState();
  if (!state.canPublish) throw new Error(state.message);
  const client = getSdkClient();
  if (!client?.getToken()) throw new Error('请先登录后再发布内容。');
  const selectedFiles = files.length ? files : file ? [file] : [];
  const existingMedia = normalizeDraftMedia(media);
  const publishKind = resolvePublishKind(kind, [...existingMedia, ...selectedFiles]);
  const normalizedContent = String(content || '').trim();
  if (!normalizedContent) throw new Error(emptyContentMessage(publishKind));
  if (normalizedContent.length > CARD_CONTENT_MAX_LENGTH) throw new Error(`正文最多 ${CARD_CONTENT_MAX_LENGTH} 字，请精简后再发布。`);
  const cardVisibilityPayload = visibilityPayload(visibility);
  validatePublishMedia(publishKind, [...existingMedia.map((item) => ({ type: item.type })), ...selectedFiles]);
  const uploads = [];
  try {
    for (const selectedFile of selectedFiles) {
      const formData = new FormData();
      formData.append('file', selectedFile);
      formData.append('scene', 'card');
      uploads.push(await client.files.upload(formData));
    }
    const allMedia = [...existingMedia, ...uploads];
    const mediaPayload = allMedia.length ? {
      media_file_ids: allMedia.map((upload) => upload.id),
      ...(publishKind !== 'video' ? { cover_file_id: allMedia[0].id } : {}),
    } : {};
    return await client.cards.create({
      content: normalizedContent,
      tags,
      ...cardVisibilityPayload,
      data: { kind: publishKind },
      ...mediaPayload,
      ...verification,
    });
  } catch (error) {
    await Promise.allSettled(uploads.map((upload) => client.files.delete(upload.id)));
    throw error;
  }
}
