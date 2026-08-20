import { getSdkClient, sdkConfigured } from '../app/sdk-client.js';
import { getRuntimeDataMode } from '../app/runtime-context.js';

export const COMPOSE_IMAGE_MIME_TYPES = Object.freeze(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
export const COMPOSE_VIDEO_MIME_TYPES = Object.freeze(['video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v']);

function resolvePublishKind(kind, files) {
  if (kind) return kind;
  if (files.length > 1) return 'gallery';
  if (files.length === 1 && COMPOSE_VIDEO_MIME_TYPES.includes(files[0]?.type)) return 'video';
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
    if (!COMPOSE_VIDEO_MIME_TYPES.includes(files[0]?.type)) throw new Error('视频仅支持 MP4、WebM、MOV 或 M4V。');
    return;
  }
  if (files.length < 1 || files.length > 9) throw new Error('图文发布需要选择 1–9 张图片或 GIF。');
  if (files.some((file) => !COMPOSE_IMAGE_MIME_TYPES.includes(file?.type))) throw new Error('图文仅支持 JPEG、PNG、WebP 或 GIF。');
}

function normalizeMediaReference(value) {
  const id = Number(value?.id);
  const url = String(value?.url || '').trim();
  if (!Number.isInteger(id) || id <= 0 || !url) return null;
  return {
    id,
    url,
    type: String(value?.type || value?.mime_type || '').trim(),
    name: String(value?.name || value?.original_name || '').trim(),
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
  return { id: user.id, username: user.username || `用户 ${user.id}` };
}

export async function publishCard({ content, tags = [], kind, files = [], file = null, media = [] }) {
  const state = getComposeState();
  if (!state.canPublish) throw new Error(state.message);
  const client = getSdkClient();
  if (!client?.getToken()) throw new Error('请先登录后再发布内容。');
  const normalizedContent = String(content || '').trim();
  if (!normalizedContent) throw new Error('请先写下要分享的内容。');
  const selectedFiles = files.length ? files : file ? [file] : [];
  const existingMedia = normalizeDraftMedia(media);
  const publishKind = resolvePublishKind(kind, [...existingMedia, ...selectedFiles]);
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
      data: { kind: publishKind },
      ...mediaPayload,
    });
  } catch (error) {
    await Promise.allSettled(uploads.map((upload) => client.files.delete(upload.id)));
    throw error;
  }
}
