import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const sdk = vi.hoisted(() => ({ getSdkClient: vi.fn() }));
vi.mock('../app/sdk-client.js', () => ({ ...sdk, sdkConfigured: vi.fn(() => true) }));

import { createDetailComment, loadDetail, supportsUserCommentPinning, updateDetailCommentLike, updatePinnedComment } from '../services/detail-service.js';

describe('detail comment service', () => {
  beforeEach(() => {
    vi.stubEnv('VITE_DATA_MODE', 'api');
  });
  afterEach(() => { vi.clearAllMocks(); vi.unstubAllEnvs(); });

  it('sends parent_id for a reply without changing the card resource', async () => {
    const client = { getToken: vi.fn(() => 'token'), comments: { create: vi.fn().mockResolvedValue({ data: { id: 3 } }) } };
    sdk.getSdkClient.mockReturnValue(client);
    await createDetailComment(24, '回复内容', 7);
    expect(client.comments.create).toHaveBeenCalledWith(24, { content: '回复内容', parent_id: 7 });
  });

  it('omits parent_id for a root comment', async () => {
    const client = { getToken: vi.fn(() => 'token'), comments: { create: vi.fn().mockResolvedValue({ data: { id: 3 } }) } };
    sdk.getSdkClient.mockReturnValue(client);
    await createDetailComment(24, '根评论');
    expect(client.comments.create).toHaveBeenCalledWith(24, { content: '根评论' });
  });

  it('uses the typed comment endpoints for like toggles', async () => {
    const client = { getToken: vi.fn(() => 'token'), comments: { like: vi.fn().mockResolvedValue({ likes: 3 }), unlike: vi.fn().mockResolvedValue(undefined) } };
    sdk.getSdkClient.mockReturnValue(client);

    await expect(updateDetailCommentLike(8, true)).resolves.toEqual({ likes: 3 });
    await expect(updateDetailCommentLike(8, false)).resolves.toBeUndefined();

    expect(client.comments.like).toHaveBeenCalledWith(8);
    expect(client.comments.unlike).toHaveBeenCalledWith(8);
  });

  it('uses only the generated user-facing SDK methods for pin and unpin', async () => {
    const client = {
      getToken: vi.fn(() => 'token'),
      comments: { pin: vi.fn().mockResolvedValue(undefined), unpin: vi.fn().mockResolvedValue(undefined) },
    };
    sdk.getSdkClient.mockReturnValue(client);

    expect(supportsUserCommentPinning()).toBe(true);
    await updatePinnedComment(24, 7, true);
    await updatePinnedComment(24, 7, false);

    expect(client.comments.pin).toHaveBeenCalledWith(24, { comment_id: 7 });
    expect(client.comments.unpin).toHaveBeenCalledWith(24);
  });

  it('keeps comment pinning unavailable without the generated user SDK', async () => {
    const client = { getToken: vi.fn(() => 'token'), comments: { pinManaged: vi.fn(), unpinManaged: vi.fn() } };
    sdk.getSdkClient.mockReturnValue(client);

    expect(supportsUserCommentPinning()).toBe(false);
    await expect(updatePinnedComment(24, 7, true)).rejects.toThrow('当前 SDK 暂不支持作者置顶评论');
    expect(client.comments.pinManaged).not.toHaveBeenCalled();
  });

  it('loads a deep-linked reply and its parent when they are outside the first comment page', async () => {
    const client = {
      getToken: vi.fn(() => 'token'),
      cards: { get: vi.fn().mockResolvedValue({ id: 24, user_id: 1, data: {}, content: '内容', pictures: [], tags: [] }) },
      library: { history_record: vi.fn().mockResolvedValue(undefined) },
      comments: {
        cardList: vi.fn().mockResolvedValue({ data: [{ id: 7, card_id: 24, user_id: 4, parent_id: null, content: '置顶评论', is_pinned: true }], pagination: { totalItems: 60 } }),
        get: vi.fn()
          .mockResolvedValueOnce({ id: 9, card_id: 24, user_id: 3, username: 'reply-user', parent_id: 8, content: '深层回复' })
          .mockResolvedValueOnce({ id: 8, card_id: 24, user_id: 2, parent_id: null, content: '根评论' }),
      },
    };
    sdk.getSdkClient.mockReturnValue(client);

    const result = await loadDetail({ id: '24', commentId: '9' });

    expect(client.comments.get).toHaveBeenNthCalledWith(1, 9);
    expect(client.comments.get).toHaveBeenNthCalledWith(2, 8);
    expect(client.comments.cardList).toHaveBeenCalledWith(24, { page: 1, list_rows: 50 });
    expect(client.library.history_record).toHaveBeenCalledWith({ resource_type: 'card', resource_id: '24' });
    expect(result.comments.map((comment) => comment.id)).toEqual([7, 8, 9]);
    expect(result.comments[0]).toMatchObject({ isPinned: true });
    expect(result.comments[2]).toMatchObject({ author: 'reply-user', authorHandle: 'reply-user' });
    expect(result.comments[1]).toMatchObject({ author: '用户 2', authorHandle: null });
    expect(result.commentCoverage.complete).toBe(false);
  });

  it('keeps the card visible when the comments endpoint fails', async () => {
    const error = Object.assign(new Error('服务器繁忙'), { status: 500 });
    const client = {
      getToken: vi.fn(() => 'token'),
      cards: { get: vi.fn().mockResolvedValue({ id: 50, user_id: 1, data: {}, content: '卡片正文', pictures: [], tags: [] }) },
      comments: { cardList: vi.fn().mockRejectedValue(error) },
      library: { history_record: vi.fn().mockResolvedValue(undefined) },
    };
    sdk.getSdkClient.mockReturnValue(client);

    const result = await loadDetail({ id: '50' });

    expect(result.source).toBe('live');
    expect(result.card).toMatchObject({ id: '50' });
    expect(result.comments).toEqual([]);
    expect(result.commentsError).toBe(error);
  });
});
