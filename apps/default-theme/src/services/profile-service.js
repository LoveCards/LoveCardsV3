import { getSdkClient, sdkConfigured } from '../app/sdk-client.js';
import { getRuntimeDataMode } from '../app/runtime-context.js';
import { previewFeed } from '../fixtures/feed-preview.js';
import { normalizeSdkCard } from './feed-service.js';
import { listHistory, listSavedItems } from './community-service.js';
import { hydrateCardReference } from './profile-card-service.js';

export function getProfileMode() {
  return getRuntimeDataMode();
}

export function isCardReference(item) {
  if (!item || typeof item !== 'object') return false;
  const type = String(item.ref_type ?? item.resource_type ?? '').trim().toLowerCase();
  return !type || type === 'card';
}

function collectionTotal(collection, fallback) {
  const candidates = [
    collection?.total,
    collection?.total_count,
    collection?.pagination?.total,
    collection?.pagination?.total_count,
    collection?.pagination?.totalItems,
    collection?.meta?.total,
  ];
  const total = candidates.map(Number).find((value) => Number.isFinite(value) && value >= 0);
  return total === undefined ? fallback : total;
}

function normalizeProfileUser(user, relation) {
  return {
    id: user.id,
    uid: user.uid,
    username: user.username || `用户 ${user.id}`,
    // A profile URL is an identity route, so only a server-provided handle
    // or the real username may be used. Numeric account numbers and ids are
    // deliberately not promoted to handles.
    handle: user.handle || user.username || null,
    location: user.location || '',
    avatar: user.avatar || '',
    bio: user.bio || '',
    gender: user.gender || '',
    birth_date: user.birth_date || '',
    following: relation?.counts?.following ?? user.following ?? user.following_count ?? null,
    followers: relation?.counts?.followers ?? user.followers ?? user.followers_count ?? null,
    receivedLikes: Number.isFinite(Number(user.stats?.received_likes)) ? Number(user.stats.received_likes) : 0,
  };
}

export function getProfileSnapshot() {
  const mode = getProfileMode();
  if (mode === 'design-preview') {
    return {
      source: 'preview',
      user: { id: 'preview-user', uid: '100000000000', username: '晚风收藏家', handle: 'wanfeng', location: '上海', avatar: '', bio: '记录生活里值得留下的细节，也看看别人正在分享什么。', gender: 'female', birth_date: '1998-05-18', following: 864, followers: 24000, receivedLikes: 186000, followingUsers: [{ id: 11, uid: '100000000011', username: '城市散步者', handle: 'citywalk', avatar: '' }, { id: 12, uid: '100000000012', username: '北岸来信', handle: 'northletter', avatar: '' }], followerUsers: [{ id: 21, uid: '100000000021', username: '一页晴天', handle: 'sunnyday', avatar: '' }, { id: 22, uid: '100000000022', username: '山野放映室', handle: 'wildscreen', avatar: '' }] },
      items: previewFeed.slice(0, 4),
      contentCount: 128,
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
  if (!user || typeof user.uid !== 'string' || !/^\d{12}$/.test(user.uid)) throw new Error('SDK 契约错误：users.me() 未返回有效 UID。');
  if (!cards || !Array.isArray(cards.data)) throw new Error('SDK 契约错误：cards.listOwn() 应返回 ListResult。');
  const relation = client.social?.relation ? await client.social.relation(user.uid).catch(() => null) : null;
  return {
    source: 'live',
    user: normalizeProfileUser(user, relation),
    items: cards.data.map(normalizeSdkCard),
    // A page length is not a content total. If the SDK omits pagination
    // metadata, leave the count unknown instead of presenting a false number.
    contentCount: collectionTotal(cards, null),
  };
}

// Relationship and summary pages only need the signed-in identity. Keeping
// this read separate prevents those pages from coupling their first render to
// the (potentially larger) own-card collection.
export async function loadProfileIdentity({ signal } = {}) {
  const snapshot = getProfileSnapshot();
  if (snapshot.source !== 'loading') return { source: snapshot.source, user: snapshot.user };
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
  const client = getSdkClient();
  const user = await client.users.me();
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
  if (!user || typeof user.uid !== 'string' || !/^\d{12}$/.test(user.uid)) throw new Error('SDK 契约错误：users.me() 未返回有效 UID。');
  const relation = client.social?.relation ? await client.social.relation(user.uid).catch(() => null) : null;
  return { source: 'live', user: normalizeProfileUser(user, relation) };
}

export function getProfileTabSnapshot(section) {
  const mode = getProfileMode();
  if (mode === 'design-preview') return { source: 'preview', status: 'success', items: previewFeed.slice(0, 4), unavailable: [], hasMore: false };
  if (mode !== 'api') return { source: 'configuration-error', status: 'error', items: [], unavailable: [], hasMore: false, detail: 'VITE_DATA_MODE 只能是 api 或 design-preview。' };
  if (!sdkConfigured()) return { source: 'configuration-error', status: 'error', items: [], unavailable: [], hasMore: false, detail: '请配置 VITE_API_URL，或显式启用 design-preview。' };
  return { source: 'loading', status: 'loading', section, items: [], unavailable: [], hasMore: false };
}

export async function loadProfileTab(section, { signal } = {}) {
  const snapshot = getProfileTabSnapshot(section);
  if (snapshot.source !== 'loading') return snapshot;
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
  const client = getSdkClient();
  let page;
  if (section === 'likes') {
    const likes = await client.likes.list();
    const items = Array.isArray(likes) ? likes : Array.isArray(likes?.data) ? likes.data : [];
    page = { items: items.filter(isCardReference), hasMore: false };
  } else if (section === 'saved') {
    page = await listSavedItems();
  } else if (section === 'history') {
    page = await listHistory();
  } else {
    return { source: 'live', status: 'success', items: [], unavailable: [], hasMore: false };
  }
  if (signal?.aborted) throw new DOMException('Request aborted', 'AbortError');
  const cardItems = (Array.isArray(page.items) ? page.items : []).filter(isCardReference);
  const records = await Promise.all(cardItems.map(hydrateCardReference));
  return {
    source: 'live',
    status: 'success',
    section,
    records,
    items: records.filter((item) => item.resource).map((item) => ({
      ...item.resource,
      meta: item.viewed_at || item.created_at || item.resource.meta,
      libraryRecord: item,
    })),
    unavailable: records.filter((item) => !item.resource),
    hasMore: Boolean(page.hasMore),
  };
}

export async function updateProfile({ username, avatar, bio, location, file }) {
  if (getProfileMode() !== 'api') throw new Error('设计预览为只读，资料不会保存。');
  const client = getSdkClient();
  if (!client) throw new Error('尚未配置 API，资料未保存。');
  if (!client.getToken()) throw new Error('请先登录后再修改资料。');
  const payload = {};
  if (username !== undefined) payload.username = String(username).trim();
  if (bio !== undefined) payload.bio = String(bio).trim() || null;
  if (location !== undefined) payload.location = String(location).replace(/[\r\n]+/g, ' ').trim() || null;
  let avatarUrl = avatar === undefined ? undefined : String(avatar).trim();
  let uploaded;
  if (file) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('scene', 'avatar');
    formData.append('is_public', '1');
    uploaded = await client.files.upload(formData);
    avatarUrl = uploaded.url;
  }
  if (avatarUrl !== undefined) payload.avatar = avatarUrl || undefined;
  if (!Object.keys(payload).length) return {};
  try {
    const updated = await client.users.updateMe(payload);
    return updated && typeof updated === 'object' ? updated : { ...payload, avatar: avatarUrl };
  } catch (error) {
    if (uploaded?.id && client.files?.delete) await client.files.delete(uploaded.id).catch(() => {});
    throw error;
  }
}
