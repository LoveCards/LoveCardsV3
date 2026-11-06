import { beforeEach, describe, expect, it, vi } from 'vitest';

const sdk = vi.hoisted(() => ({ getSdkClient: vi.fn() }));
vi.mock('../app/sdk-client.js', () => sdk);

import {
  classifyCommunityError,
  dedupeByStableId,
  ensureClientMessageId,
  getUnreadNotificationCount,
  listNotifications,
  listPublicCardsByUser,
  listDrafts,
  draftSummary,
  markNotificationRead,
  markAllNotificationsRead,
  normalizeCollection,
  normalizeDraftPayload,
  recordHistoryItem,
  removeSavedItem,
  sendMessage,
} from '../services/community-service.js';

describe('community SDK adapter', () => {
  beforeEach(() => vi.clearAllMocks());

  it('normalizes arrays and cursor collections', () => {
    expect(normalizeCollection([{ id: 1 }, { id: 2 }])).toEqual({
      items: [{ id: 1 }, { id: 2 }],
      nextCursor: null,
      hasMore: false,
    });
    expect(normalizeCollection({ items: [{ id: 3 }], next_cursor: 42, has_more: true })).toEqual({
      items: [{ id: 3 }],
      nextCursor: '42',
      hasMore: true,
    });
    expect(normalizeCollection({ data: [{ id: 4 }], next_cursor: '3', has_more: true })).toEqual({
      items: [{ id: 4 }],
      nextCursor: '3',
      hasMore: true,
    });
    expect(normalizeCollection({ data: { items: [{ id: 5 }], next_cursor: null, has_more: false } })).toEqual({
      items: [{ id: 5 }],
      nextCursor: null,
      hasMore: false,
    });
  });

  it('deduplicates records by their first stable identifier', () => {
    const withoutId = { body: 'kept because it has no stable id' };
    expect(dedupeByStableId([
      { id: 1, body: 'first' },
      { id: 1, body: 'duplicate' },
      { client_message_id: 'retry-1' },
      { client_message_id: 'retry-1' },
      withoutId,
    ])).toEqual([{ id: 1, body: 'first' }, { client_message_id: 'retry-1' }, withoutId]);
  });

  it.each([
    [401, 'unauthenticated'],
    [403, 'forbidden'],
    [404, 'not-found'],
    [0, 'network'],
    [500, 'error'],
  ])('classifies status %s as %s', (status, kind) => {
    expect(classifyCommunityError({ status, message: 'failure' })).toMatchObject({ status, kind, message: 'failure' });
  });

  it('keeps an existing client message id stable across retries', () => {
    const createId = vi.fn(() => 'generated');
    expect(ensureClientMessageId(' retry-id ', createId)).toBe('retry-id');
    expect(createId).not.toHaveBeenCalled();
    expect(ensureClientMessageId('', createId)).toBe('lc-message-generated');
  });

  it('normalizes SDK collections without bypassing the generated resource', async () => {
    const list = vi.fn().mockResolvedValue([{ id: 1 }, { id: 1 }, { id: 2 }]);
    sdk.getSdkClient.mockReturnValue({ drafts: { list } });
    await expect(listDrafts()).resolves.toEqual({
      items: [{ id: 1 }, { id: 2 }],
      nextCursor: null,
      hasMore: false,
    });
    expect(list).toHaveBeenCalledOnce();
  });

  it('normalizes public card pagination and clamps page size to the backend limit', async () => {
    const listPublicByUser = vi.fn().mockResolvedValue({ data: [] });
    sdk.getSdkClient.mockReturnValue({ cards: { listPublicByUser } });

    await listPublicCardsByUser('123456789012', { page: '0', list_rows: 999 });
    expect(listPublicByUser).toHaveBeenCalledWith('123456789012', { page: 1, list_rows: 100 });

    await listPublicCardsByUser('123456789012', { page: '-2', list_rows: 0 });
    expect(listPublicByUser).toHaveBeenLastCalledWith('123456789012', { page: 1, list_rows: 1 });
  });

  it('passes engagement filters and opaque cursors to the generated SDK resource', async () => {
    const list = vi.fn().mockResolvedValue({ data: [], next_cursor: null, has_more: false });
    sdk.getSdkClient.mockReturnValue({ notifications: { list } });
    const cursor = 'opaque:created=2026-08-22T10%3A00%3A00Z&id=91';
    await listNotifications({ cursor, limit: 20, category: 'engagement' });
    expect(list).toHaveBeenCalledWith({ cursor, limit: 20, category: 'engagement' });
  });

  it('scopes unread and read-all operations to the active notification category', async () => {
    const unread = vi.fn().mockResolvedValue({ unread: 3 });
    const readAll = vi.fn().mockResolvedValue(undefined);
    sdk.getSdkClient.mockReturnValue({ notifications: { unread, readAll } });

    await expect(getUnreadNotificationCount({ category: 'engagement' })).resolves.toEqual({ unread: 3 });
    await expect(markAllNotificationsRead({ category: 'system' })).resolves.toBeUndefined();
    expect(unread).toHaveBeenCalledWith({ category: 'engagement' });
    expect(readAll).toHaveBeenCalledWith({ category: 'system' });
  });

  it('loads public cards by canonical handle and normalizes each CardResource', async () => {
    const listPublicByUser = vi.fn().mockResolvedValue({
      data: [{ id: 24, user_id: 7, username: 'forest', content: '公开内容', pictures: [], goods: 3, comments: 2 }],
      pagination: { currentPage: 1, totalItems: 1 },
    });
    sdk.getSdkClient.mockReturnValue({ cards: { listPublicByUser } });
    const result = await listPublicCardsByUser('123456789012', { page: 2, list_rows: 12 });
    expect(listPublicByUser).toHaveBeenCalledWith('123456789012', { page: 2, list_rows: 12 });
    expect(result.data[0]).toMatchObject({ id: '24', author: 'forest', authorHandle: 'forest', likes: 3, replies: 2 });
  });

  it('does not invent a public-card request when the SDK capability is absent', async () => {
    sdk.getSdkClient.mockReturnValue({ cards: {} });
    await expect(listPublicCardsByUser('123456789012')).rejects.toMatchObject({ code: 'community_capability_unavailable', capability: 'cards.listPublicByUser' });
  });

  it('derives draft labels from body content and payload type', () => {
    expect(normalizeDraftPayload('{"publishType":"video"}')).toEqual({ publishType: 'video' });
    expect(normalizeDraftPayload('not json')).toEqual({});
    expect(draftSummary({ content: '  第一行标题\n第二行正文', payload: '{}' })).toBe('第一行标题');
    expect(draftSummary({ content: '', payload: { publishType: 'video' } })).toBe('视频草稿');
    expect(draftSummary({ content: '   ', payload: { publishType: 'image' } })).toBe('图文草稿');
    expect(draftSummary({ content: '', payload: { kind: 'gallery' } })).toBe('图文草稿');
  });

  it('sends the message idempotency fields required by the SDK contract', async () => {
    const send = vi.fn().mockResolvedValue({ id: 8 });
    sdk.getSdkClient.mockReturnValue({ messages: { send } });
    await sendMessage({ recipientUid: '123456789012', body: ' hello ', clientMessageId: 'stable-id' });
    expect(send).toHaveBeenCalledWith({
      recipient_uid: '123456789012',
      body: 'hello',
      client_message_id: 'stable-id',
    });
  });

  it('rejects invalid message parameters before calling the SDK', async () => {
    const send = vi.fn();
    sdk.getSdkClient.mockReturnValue({ messages: { send } });
    expect(() => sendMessage({ recipientUid: 'invalid', body: 'hello' })).toThrow('收件人');
    expect(() => sendMessage({ recipientUid: '123456789012', body: '   ' })).toThrow('消息内容不能为空');
    expect(send).not.toHaveBeenCalled();
  });

  it('marks one or more notification ids through the generated SDK resource', async () => {
    const read = vi.fn().mockResolvedValue(undefined);
    sdk.getSdkClient.mockReturnValue({ notifications: { read } });
    await markNotificationRead([9, '9', 10, 0]);
    expect(read).toHaveBeenCalledWith({ ids: [9, 10] });
  });

  it('removes one saved resource through the typed SDK contract', async () => {
    const remove = vi.fn().mockResolvedValue(undefined);
    sdk.getSdkClient.mockReturnValue({ library: { remove } });
    await removeSavedItem(12);
    expect(remove).toHaveBeenCalledWith({ resource_type: 'card', resource_id: '12' });
  });

  it('supports non-numeric stable resource identifiers', async () => {
    const remove = vi.fn().mockResolvedValue(undefined);
    sdk.getSdkClient.mockReturnValue({ library: { remove } });
    await removeSavedItem({ resource_type: 'media', resource_id: 'deep:abc-1' });
    expect(remove).toHaveBeenCalledWith({ resource_type: 'media', resource_id: 'deep:abc-1' });
  });

  it('records a viewed card through the generated library resource', async () => {
    const historyRecord = vi.fn().mockResolvedValue(undefined);
    sdk.getSdkClient.mockReturnValue({ library: { history_record: historyRecord } });
    await recordHistoryItem(24);
    expect(historyRecord).toHaveBeenCalledWith({ resource_type: 'card', resource_id: '24' });
  });
});
