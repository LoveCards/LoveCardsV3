import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const sdk = vi.hoisted(() => ({ getSdkClient: vi.fn() }));
vi.mock('../app/sdk-client.js', () => ({ ...sdk, sdkConfigured: vi.fn(() => true) }));

import { createDetailComment, loadDetail } from '../services/detail-service.js';

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

  it('loads a deep-linked reply and its parent when they are outside the first comment page', async () => {
    const client = {
      getToken: vi.fn(() => 'token'),
      cards: { get: vi.fn().mockResolvedValue({ id: 24, user_id: 1, data: {}, content: '内容', pictures: [], tags: [] }) },
      library: { history_record: vi.fn().mockResolvedValue(undefined) },
      comments: {
        cardList: vi.fn().mockResolvedValue({ data: [], pagination: { totalItems: 60 } }),
        get: vi.fn()
          .mockResolvedValueOnce({ id: 9, card_id: 24, user_id: 3, parent_id: 8, content: '深层回复' })
          .mockResolvedValueOnce({ id: 8, card_id: 24, user_id: 2, parent_id: null, content: '根评论' }),
      },
    };
    sdk.getSdkClient.mockReturnValue(client);

    const result = await loadDetail({ id: '24', commentId: '9' });

    expect(client.comments.get).toHaveBeenNthCalledWith(1, 9);
    expect(client.comments.get).toHaveBeenNthCalledWith(2, 8);
    expect(client.library.history_record).toHaveBeenCalledWith({ resource_type: 'card', resource_id: '24' });
    expect(result.comments.map((comment) => comment.id)).toEqual([8, 9]);
    expect(result.commentCoverage.complete).toBe(false);
  });
});
