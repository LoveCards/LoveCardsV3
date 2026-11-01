import { describe, expect, it, vi } from 'vitest';
import { installCardMediaAccess } from '../services/card-media-access.js';

describe('card media grants', () => {
  it('leaves public cards untouched and preserves pagination', async () => {
    const card = { id: 1, visibility: 'public', media: [] };
    const client = { cards: { list: vi.fn(async () => ({ data: [card], pagination: { totalItems: 1 } })), mediaAccess: vi.fn() } };
    installCardMediaAccess(client, 'http://localhost/api');
    expect(await client.cards.list()).toEqual({ data: [card], pagination: { totalItems: 1 } });
    expect(client.cards.mediaAccess).not.toHaveBeenCalled();
  });
  it('hydrates and renews restricted media only through the generated SDK', async () => {
    vi.stubGlobal('window', { location: { origin: 'http://localhost' } });
    const card = { id: 2, visibility: 'private', media: [{ file_id: 7, url: '/old', type: 'image' }] };
    const mediaAccess = vi.fn(async () => ({ media: [{ file_id: 7, url: '/media/issued?grant=first' }], cover_url: null, expires_in: 300 }));
    const client = { cards: { get: vi.fn(async () => card), mediaAccess } };
    installCardMediaAccess(client, 'http://localhost:6001/api');
    try {
      const result = await client.cards.get(2);
      expect(result.media[0].url).toBe('http://localhost:6001/media/issued?grant=first');
      mediaAccess.mockResolvedValue({ media: [{ file_id: 7, url: '/media/issued?grant=renewed' }], cover_url: null, expires_in: 300 });
      expect(await result.media[0].renewUrl()).toBe('http://localhost:6001/media/issued?grant=renewed');
      expect(mediaAccess).toHaveBeenLastCalledWith(2);
      mediaAccess.mockRejectedValue(new Error('revoked'));
      await expect(result.media[0].renewUrl()).rejects.toThrow('revoked');
    } finally { vi.unstubAllGlobals(); }
  });
});
