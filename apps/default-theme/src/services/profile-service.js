import { getSdkClient, sdkConfigured } from '../app/sdk-client.js';
import { getRuntimeDataMode } from '../app/runtime-context.js';
import { previewFeed } from '../fixtures/feed-preview.js';
import { normalizeSdkCard } from './feed-service.js';

export function getProfileMode() {
  return getRuntimeDataMode();
}

export function getProfileSnapshot() {
  const mode = getProfileMode();
  if (mode === 'design-preview') {
    return {
      source: 'preview',
      user: { id: 'preview-user', username: '晚风收藏家', handle: 'wanfeng', location: '上海', avatar: '', bio: '记录生活里值得留下的细节，也看看别人正在分享什么。', following: 864, followers: 24000, likesAndSaves: 186000 },
      items: previewFeed.slice(0, 4),
    };
  }
  if (mode !== 'api') return { source: 'configuration-error', user: null, items: [], detail: 'VITE_DATA_MODE 只能是 api 或 design-preview。' };
  if (!sdkConfigured()) return { source: 'configuration-error', user: null, items: [], detail: '请配置 VITE_API_URL，或显式启用 design-preview。' };
  return { source: 'loading', user: null, items: [] };
}

export async function loadProfile({ signal } = {}) {
  const snapshot = getProfileSnapshot();
  if (snapshot.source !== 'loading') return snapshot;
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
  const client = getSdkClient();
  const [user, cards] = await Promise.all([
    client.users.me(),
    client.cards.listOwn({ page: 1, list_rows: 30, order_key: 'created_at', order_desc: true }),
  ]);
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
  if (!user || !Number.isInteger(user.id)) throw new Error('SDK 契约错误：users.me() 未返回有效用户。');
  if (!cards || !Array.isArray(cards.data)) throw new Error('SDK 契约错误：cards.listOwn() 应返回 ListResult。');
  const relation = client.social?.relation ? await client.social.relation(user.id).catch(() => null) : null;
  return {
    source: 'live',
    user: {
      id: user.id,
      username: user.username || `用户 ${user.id}`,
      handle: user.handle || user.number || user.username || `user-${user.id}`,
      location: user.location || '',
      avatar: user.avatar || '',
      bio: user.bio || '',
      following: relation?.counts?.following ?? user.following ?? user.following_count ?? null,
      followers: relation?.counts?.followers ?? user.followers ?? user.followers_count ?? null,
      likesAndSaves: user.likes_and_saves ?? user.likesAndSaves ?? null,
    },
    items: cards.data.map(normalizeSdkCard),
  };
}

export async function updateProfile({ username, avatar, file }) {
  if (getProfileMode() !== 'api') throw new Error('设计预览为只读，资料不会保存。');
  const client = getSdkClient();
  if (!client) throw new Error('尚未配置 API，资料未保存。');
  if (!client.getToken()) throw new Error('请先登录后再修改资料。');
  let avatarUrl = avatar.trim();
  if (file) {
    const formData = new FormData();
    formData.append('file', file);
    const uploaded = await client.files.upload(formData);
    avatarUrl = uploaded.url;
  }
  await client.users.updateMe({ username: username.trim(), avatar: avatarUrl || undefined });
}
