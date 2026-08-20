import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const sdk = vi.hoisted(() => ({
  getSdkClient: vi.fn(),
  sdkConfigured: vi.fn(),
}));

vi.mock('../app/sdk-client.js', () => sdk);

import { loadHomeFeed, updateCardLike } from '../services/feed-service.js';

const card = {
  id: 12,
  user_id: 4,
  username: '卡卡旅行记',
  content: '海边的风，治愈一切。',
  data: {},
  cover: 'coast.webp',
  pictures: null,
  tags: ['周末去哪儿'],
  goods: 96,
  comments: 18,
  created_at: '2026-08-15 10:00:00',
};

beforeEach(() => {
  vi.stubEnv('VITE_DATA_MODE', 'api');
  vi.stubEnv('VITE_API_URL', '/api');
  sdk.sdkConfigured.mockReturnValue(true);
});

afterEach(() => {
  vi.clearAllMocks();
  vi.unstubAllEnvs();
});

describe('live home feed SDK contract', () => {
  it('maps recommended to cards.hot and keeps the response shape strict', async () => {
    const client = { cards: { hot: vi.fn().mockResolvedValue([card]), list: vi.fn() } };
    sdk.getSdkClient.mockReturnValue(client);
    await expect(loadHomeFeed({ channel: 'recommended' })).resolves.toMatchObject({ source: 'live', strategy: 'hot', items: [{ id: '12' }] });
    expect(client.cards.hot).toHaveBeenCalledOnce();

    client.cards.hot.mockResolvedValue({ data: [card] });
    await expect(loadHomeFeed({ channel: 'recommended' })).rejects.toThrow('cards.hot() 应返回数组');
  });

  it('maps latest to the paginated list contract', async () => {
    const client = { cards: { hot: vi.fn(), list: vi.fn().mockResolvedValue({ data: [card], pagination: { currentPage: 1 } }) } };
    sdk.getSdkClient.mockReturnValue(client);
    const result = await loadHomeFeed({ channel: 'latest' });
    expect(result).toMatchObject({ source: 'live', strategy: 'latest', items: [{ id: '12' }] });
    expect(client.cards.list).toHaveBeenCalledWith(expect.objectContaining({ page: 1, list_rows: 30, order_key: 'created_at', order_desc: true }));
  });

  it('uses cards.like and cards.unlike with the card id', async () => {
    const client = { getToken: vi.fn().mockReturnValue('token'), cards: { like: vi.fn().mockResolvedValue({ likes: 97 }), unlike: vi.fn().mockResolvedValue(undefined) } };
    sdk.getSdkClient.mockReturnValue(client);
    await expect(updateCardLike('12', true)).resolves.toEqual({ likes: 97 });
    await updateCardLike('12', false);
    expect(client.cards.like).toHaveBeenCalledWith(12);
    expect(client.cards.unlike).toHaveBeenCalledWith(12);
  });
});
