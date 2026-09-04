import { getSdkClient } from '../app/sdk-client.js';

function sdk() {
  const value = getSdkClient();
  if (!value?.getToken()) throw new Error('请先登录后再管理设置。');
  return value;
}

export const getCurrentUser = () => sdk().users.me();
export const updateCurrentUser = (payload) => sdk().users.updateMe(payload);
export async function updateProfileCover(file) {
  const value = sdk();
  if (!file) return value.users.updateMe({ cover_file_id: null });
  const formData = new FormData();
  formData.append('file', file);
  formData.append('scene', 'profile_cover');
  formData.append('is_public', '1');
  const uploaded = await value.files.upload(formData);
  const fileId = Number(uploaded?.id);
  if (!Number.isInteger(fileId) || fileId <= 0) throw new Error('背景图上传成功，但没有返回有效文件编号。');
  return value.users.updateMe({ cover_file_id: fileId });
}
export const listSessions = () => sdk().session.listSessions();
export const revokeSession = (id) => sdk().session.revokeSession(String(id));
export const revokeOtherSessions = () => sdk().session.revokeOthers();
export const getNotificationPreferences = () => sdk().notifications.preferences();
export const updateNotificationPreferences = (in_app) => sdk().notifications.updatePreferences({ in_app });

export async function listBlockedUsers(params = {}) {
  const page = await sdk().safety.listBlocked({ cursor: params.cursor || undefined, limit: params.limit || 20, query: params.query?.trim() || undefined });
  if (!page || !Array.isArray(page.data)) throw new Error('SDK 契约错误：黑名单接口应返回 data 列表。');
  return page;
}
