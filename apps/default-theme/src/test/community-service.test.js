import { beforeEach, describe, expect, it, vi } from 'vitest';

const sdk = vi.hoisted(() => ({ getSdkClient: vi.fn() }));
vi.mock('../app/sdk-client.js', () => sdk);

import {
  classifyCommunityError,
  dedupeByStableId,
  ensureClientMessageId,
  listDrafts,
  draftSummary,
  markNotificationRead,
  normalizeCollection,
  normalizeDraftPayload,
  parseNotificationSseSnapshot,
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

  it('parses one SSE snapshot including multiline JSON data', () => {
    const snapshot = parseNotificationSseSnapshot([
      'id: notification-9',
      'event: snapshot',
      'retry: 5000',
      'data: {"items":[',
      'data: {"id":9,"type":"mention"}]}',
      '',
    ].join('\n'));
    expect(snapshot).toEqual({
      id: 'notification-9',
      event: 'snapshot',
      retry: 5000,
      data: { items: [{ id: 9, type: 'mention' }] },
    });
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

  it('derives draft labels from body content and payload type', () => {
    expect(normalizeDraftPayload('{"publishType":"video"}')).toEqual({ publishType: 'video' });
    expect(normalizeDraftPayload('not json')).toEqual({});
    expect(draftSummary({ content: '  第一行标题\n第二行正文', payload: '{}' })).toBe('第一行标题');
    expect(draftSummary({ content: '', payload: { publishType: 'video' } })).toBe('视频草稿');
    expect(draftSummary({ content: '', payload: { kind: 'gallery' } })).toBe('图文草稿');
  });

  it('sends the message idempotency fields required by the SDK contract', async () => {
    const send = vi.fn().mockResolvedValue({ id: 8 });
    sdk.getSdkClient.mockReturnValue({ messages: { send } });
    await sendMessage({ recipientId: 12, body: ' hello ', clientMessageId: 'stable-id' });
    expect(send).toHaveBeenCalledWith({
      recipient_id: 12,
      body: 'hello',
      client_message_id: 'stable-id',
    });
  });

  it('rejects invalid message parameters before calling the SDK', async () => {
    const send = vi.fn();
    sdk.getSdkClient.mockReturnValue({ messages: { send } });
    expect(() => sendMessage({ recipientId: 'invalid', body: 'hello' })).toThrow('收件人');
    expect(() => sendMessage({ recipientId: 12, body: '   ' })).toThrow('消息内容不能为空');
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
