import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const sdk = vi.hoisted(() => ({
  getSdkClient: vi.fn(),
  sdkConfigured: vi.fn(),
}));

vi.mock('../app/sdk-client.js', () => sdk);

import { publishCard } from '../services/compose-service.js';

beforeEach(() => {
  vi.stubEnv('VITE_DATA_MODE', 'api');
  vi.stubEnv('VITE_API_URL', '/api');
  sdk.sdkConfigured.mockReturnValue(true);
});

afterEach(() => {
  vi.clearAllMocks();
  vi.unstubAllEnvs();
});

describe('compose media publishing', () => {
  it('creates an explicit text card without uploading media', async () => {
    const create = vi.fn().mockResolvedValue({ id: 7, status: 0 });
    const client = { getToken: vi.fn().mockReturnValue('token'), files: { upload: vi.fn(), delete: vi.fn() }, cards: { create } };
    sdk.getSdkClient.mockReturnValue(client);

    await publishCard({ content: '  今天的随笔  ', tags: [], kind: 'text' });

    expect(client.files.upload).not.toHaveBeenCalled();
    expect(create).toHaveBeenCalledWith({ content: '今天的随笔', tags: [], data: { kind: 'text' } });
  });

  it('creates a single image card with an owned cover', async () => {
    const upload = vi.fn().mockResolvedValue({ id: 10, url: '/one.jpg' });
    const create = vi.fn().mockResolvedValue({ id: 8, status: 0 });
    sdk.getSdkClient.mockReturnValue({ getToken: vi.fn().mockReturnValue('token'), files: { upload, delete: vi.fn() }, cards: { create } });

    await publishCard({ content: '单图正文', tags: [], kind: 'image', files: [new Blob(['one'], { type: 'image/jpeg' })] });

    expect(create).toHaveBeenCalledWith({
      content: '单图正文', tags: [], data: { kind: 'image' }, media_file_ids: [10], cover_file_id: 10,
    });
  });

  it('uploads ordered card media and creates a gallery with owned file ids', async () => {
    const upload = vi.fn()
      .mockResolvedValueOnce({ id: 11, url: '/one.jpg' })
      .mockResolvedValueOnce({ id: 12, url: '/two.gif' });
    const create = vi.fn().mockResolvedValue({ id: 88, status: 0 });
    const client = { getToken: vi.fn().mockReturnValue('token'), files: { upload, delete: vi.fn() }, cards: { create } };
    sdk.getSdkClient.mockReturnValue(client);

    const files = [new Blob(['one'], { type: 'image/jpeg' }), new Blob(['two'], { type: 'image/gif' })];
    await expect(publishCard({ content: '  多图正文  ', tags: ['城市'], kind: 'gallery', files })).resolves.toEqual({ id: 88, status: 0 });

    expect(upload).toHaveBeenCalledTimes(2);
    expect(upload.mock.calls[0][0].get('scene')).toBe('card');
    expect(upload.mock.calls[1][0].get('scene')).toBe('card');
    expect(create).toHaveBeenCalledWith({
      content: '多图正文',
      tags: ['城市'],
      data: { kind: 'gallery' },
      cover_file_id: 11,
      media_file_ids: [11, 12],
    });
  });

  it('creates one video without treating it as an image cover', async () => {
    const upload = vi.fn().mockResolvedValue({ id: 31, url: '/storage/opaque-video' });
    const create = vi.fn().mockResolvedValue({ id: 89, status: 0 });
    sdk.getSdkClient.mockReturnValue({ getToken: vi.fn().mockReturnValue('token'), files: { upload, delete: vi.fn() }, cards: { create } });

    await publishCard({ content: '视频正文', tags: ['晨跑'], kind: 'video', files: [new Blob(['clip'], { type: 'video/mp4' })] });

    expect(create).toHaveBeenCalledWith({
      content: '视频正文', tags: ['晨跑'], data: { kind: 'video' }, media_file_ids: [31],
    });
  });

  it('publishes restored draft media without uploading it again', async () => {
    const upload = vi.fn();
    const create = vi.fn().mockResolvedValue({ id: 90, status: 0 });
    const client = { getToken: vi.fn().mockReturnValue('token'), files: { upload, delete: vi.fn() }, cards: { create } };
    sdk.getSdkClient.mockReturnValue(client);

    await publishCard({
      content: '恢复的图集',
      tags: ['旅行'],
      kind: 'gallery',
      media: [{ id: 41, url: '/draft-one.jpg', type: 'image/jpeg' }, { id: 42, url: '/draft-two.jpg', type: 'image/jpeg' }],
    });

    expect(upload).not.toHaveBeenCalled();
    expect(create).toHaveBeenCalledWith({
      content: '恢复的图集', tags: ['旅行'], data: { kind: 'gallery' },
      media_file_ids: [41, 42], cover_file_id: 41,
    });
  });

  it.each([
    ['image', [], '图文发布需要选择 1–9 张图片或 GIF。'],
    ['video', [], '视频发布需要选择一个视频文件。'],
    ['video', [new Blob(['image'], { type: 'image/jpeg' })], '视频仅支持 MP4、WebM、MOV 或 M4V。'],
    ['image', [new Blob(['video'], { type: 'video/mp4' })], '图文仅支持 JPEG、PNG、WebP 或 GIF。'],
  ])('rejects invalid %s media before upload', async (kind, files, message) => {
    const client = { getToken: vi.fn().mockReturnValue('token'), files: { upload: vi.fn(), delete: vi.fn() }, cards: { create: vi.fn() } };
    sdk.getSdkClient.mockReturnValue(client);

    await expect(publishCard({ content: '正文', tags: [], kind, files })).rejects.toThrow(message);
    expect(client.files.upload).not.toHaveBeenCalled();
  });

  it('removes uploaded orphans when card creation fails', async () => {
    const client = {
      getToken: vi.fn().mockReturnValue('token'),
      files: { upload: vi.fn().mockResolvedValue({ id: 21, url: '/one.jpg' }), delete: vi.fn().mockResolvedValue(undefined) },
      cards: { create: vi.fn().mockRejectedValue(new Error('create failed')) },
    };
    sdk.getSdkClient.mockReturnValue(client);

    await expect(publishCard({ content: '正文', tags: [], files: [new Blob(['one'], { type: 'image/jpeg' })] })).rejects.toThrow('create failed');
    expect(client.files.delete).toHaveBeenCalledWith(21);
  });
});
