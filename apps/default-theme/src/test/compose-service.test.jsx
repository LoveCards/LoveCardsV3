import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const sdk = vi.hoisted(() => ({
  getSdkClient: vi.fn(),
  sdkConfigured: vi.fn(),
}));

vi.mock('../app/sdk-client.js', () => sdk);

import { loadPublishedCardForEdit, normalizeCardVisibility, publishCard, updatePublishedCard } from '../services/compose-service.js';

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
  it('restores legacy or invalid draft visibility as public', () => {
    expect(normalizeCardVisibility(undefined)).toBe('public');
    expect(normalizeCardVisibility('legacy')).toBe('public');
    expect(normalizeCardVisibility('followers')).toBe('followers');
  });

  it('creates an explicit text card without uploading media', async () => {
    const create = vi.fn().mockResolvedValue({ id: 7, status: 0 });
    const client = { getToken: vi.fn().mockReturnValue('token'), files: { upload: vi.fn(), delete: vi.fn() }, cards: { create } };
    sdk.getSdkClient.mockReturnValue(client);

    await publishCard({ content: '  今天的随笔  ', tags: [], kind: 'text' });

    expect(client.files.upload).not.toHaveBeenCalled();
    expect(create).toHaveBeenCalledWith({ content: '今天的随笔', tags: [], visibility: 'public', data: { kind: 'text' } });
  });

  it('passes human verification to the card write without changing the media flow', async () => {
    const create = vi.fn().mockResolvedValue({ id: 7 });
    const update = vi.fn().mockResolvedValue(undefined);
    const client = { getToken: vi.fn().mockReturnValue('token'), files: { upload: vi.fn(), delete: vi.fn() }, cards: { create, update } };
    sdk.getSdkClient.mockReturnValue(client);
    const verification = { captcha_id: 'image-id', captcha_code: 'ABCD' };
    await publishCard({ content: '正文', kind: 'text', verification });
    await updatePublishedCard({ id: 7, content: '改后正文', kind: 'text', verification });
    expect(create).toHaveBeenCalledWith(expect.objectContaining(verification));
    expect(update).toHaveBeenCalledWith(7, expect.objectContaining(verification));
    expect(client.files.upload).not.toHaveBeenCalled();
  });

  it('rejects card content longer than 1000 characters before creating it', async () => {
    const create = vi.fn();
    const client = { getToken: vi.fn().mockReturnValue('token'), files: { upload: vi.fn(), delete: vi.fn() }, cards: { create } };
    sdk.getSdkClient.mockReturnValue(client);

    await expect(publishCard({ content: '字'.repeat(1001), tags: [], kind: 'text' })).rejects.toThrow('正文最多 1000 字');
    expect(create).not.toHaveBeenCalled();
  });

  it('creates a single image card with an owned cover', async () => {
    const upload = vi.fn().mockResolvedValue({ id: 10, url: '/one.jpg' });
    const create = vi.fn().mockResolvedValue({ id: 8, status: 0 });
    sdk.getSdkClient.mockReturnValue({ getToken: vi.fn().mockReturnValue('token'), files: { upload, delete: vi.fn() }, cards: { create } });

    await publishCard({ content: '单图正文', tags: [], kind: 'image', files: [new Blob(['one'], { type: 'image/jpeg' })] });

    expect(create).toHaveBeenCalledWith({
      content: '单图正文', tags: [], visibility: 'public', data: { kind: 'image' }, media_file_ids: [10], cover_file_id: 10,
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
      visibility: 'public',
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
      content: '视频正文', tags: ['晨跑'], visibility: 'public', data: { kind: 'video' }, media_file_ids: [31],
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
      content: '恢复的图集', tags: ['旅行'], visibility: 'public', data: { kind: 'gallery' },
      media_file_ids: [41, 42], cover_file_id: 41,
    });
  });

  it.each(['followers', 'private'])('publishes the selected %s visibility through the generated SDK', async (visibility) => {
    const create = vi.fn().mockResolvedValue({ id: 91, status: 0 });
    sdk.getSdkClient.mockReturnValue({ getToken: vi.fn().mockReturnValue('token'), files: { upload: vi.fn(), delete: vi.fn() }, cards: { create } });

    await publishCard({ content: '范围测试', tags: [], kind: 'text', visibility });

    expect(create).toHaveBeenCalledWith({ content: '范围测试', tags: [], visibility, data: { kind: 'text' } });
  });

  it('rejects an invalid visibility before creating a card', async () => {
    const create = vi.fn();
    sdk.getSdkClient.mockReturnValue({ getToken: vi.fn().mockReturnValue('token'), files: { upload: vi.fn(), delete: vi.fn() }, cards: { create } });

    await expect(publishCard({ content: '范围测试', tags: [], kind: 'text', visibility: 'friends' })).rejects.toThrow('请选择有效的内容可见范围');
    expect(create).not.toHaveBeenCalled();
  });

  it.each([
    ['image', [], '图文发布需要选择 1–9 张图片或 GIF。'],
    ['video', [], '视频发布需要选择一个视频文件。'],
    ['video', [new Blob(['image'], { type: 'image/jpeg' })], '视频仅支持 MP4、WebM、MOV 或 M4V。'],
    ['image', [new Blob(['video'], { type: 'video/mp4' })], '图文仅支持 JPEG、PNG、WebP 或 GIF。'],
    ['image', [{ type: 'image/jpeg', size: 10 * 1024 * 1024 + 1 }], '每张图片不能超过 10MB，请选择更小的图片。'],
  ])('rejects invalid %s media before upload', async (kind, files, message) => {
    const client = { getToken: vi.fn().mockReturnValue('token'), files: { upload: vi.fn(), delete: vi.fn() }, cards: { create: vi.fn() } };
    sdk.getSdkClient.mockReturnValue(client);

    await expect(publishCard({ content: '正文', tags: [], kind, files })).rejects.toThrow(message);
    expect(client.files.upload).not.toHaveBeenCalled();
  });

  it.each([
    ['image', new Blob(['one'], { type: 'image/jpeg' }), '图文必须先填写正文。'],
    ['video', new Blob(['clip'], { type: 'video/mp4' }), '视频必须先填写正文。'],
  ])('requires正文 for %s cards before uploading media', async (kind, file, message) => {
    const client = { getToken: vi.fn().mockReturnValue('token'), files: { upload: vi.fn(), delete: vi.fn() }, cards: { create: vi.fn() } };
    sdk.getSdkClient.mockReturnValue(client);

    await expect(publishCard({ content: '   ', tags: [], kind, files: [file] })).rejects.toThrow(message);
    expect(client.files.upload).not.toHaveBeenCalled();
    expect(client.cards.create).not.toHaveBeenCalled();
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

describe('published card editing', () => {
  it('loads the owned card with all topic names and ordered editable media', async () => {
    const getOwn = vi.fn().mockResolvedValue({
      id: 42,
      content: '原正文',
      visibility: 'followers',
      data: { kind: 'gallery', accent: 'rose' },
      tags: [13, 5, 4],
      topics: [{ id: 13, name: '牛的' }, { id: 5, name: '纪念日' }, { id: 4, name: '生日' }],
      pictures: ['/two.jpg', '/one.jpg'],
      cover: '/one.jpg',
      media: [
        { file_id: 12, url: '/two.jpg', type: 'image', mime_type: 'image/jpeg', position: 1 },
        { file_id: 11, url: '/one.jpg', type: 'image', mime_type: 'image/jpeg', position: 0 },
      ],
    });
    sdk.getSdkClient.mockReturnValue({ getToken: vi.fn().mockReturnValue('token'), cards: { getOwn } });

    await expect(loadPublishedCardForEdit(42)).resolves.toMatchObject({
      id: 42,
      content: '原正文',
      tags: ['牛的', '纪念日', '生日'],
      data: { kind: 'gallery', accent: 'rose' },
      publishType: 'image',
      visibility: 'followers',
      media: [{ id: 11, url: '/one.jpg' }, { id: 12, url: '/two.jpg' }],
    });
    expect(getOwn).toHaveBeenCalledWith(42);
  });

  it('updates content and every topic without rebinding unchanged media', async () => {
    const update = vi.fn().mockResolvedValue(undefined);
    sdk.getSdkClient.mockReturnValue({
      getToken: vi.fn().mockReturnValue('token'),
      cards: { update },
      files: { upload: vi.fn(), delete: vi.fn() },
    });

    await updatePublishedCard({
      id: 42,
      content: '  修改后的正文  ',
      tags: ['牛的', '纪念日', '生日'],
      kind: 'gallery',
      data: { kind: 'gallery', accent: 'rose' },
      media: [{ id: 11, url: '/one.jpg', type: 'image/jpeg' }, { id: 12, url: '/two.jpg', type: 'image/jpeg' }],
      mediaChanged: false,
    });

    expect(update).toHaveBeenCalledWith(42, {
      content: '修改后的正文',
      tags: ['牛的', '纪念日', '生日'],
      data: { kind: 'gallery', accent: 'rose' },
    });
  });

  it('submits explicit empty media and cover references when switching to text', async () => {
    const update = vi.fn().mockResolvedValue(undefined);
    sdk.getSdkClient.mockReturnValue({
      getToken: vi.fn().mockReturnValue('token'),
      cards: { update },
      files: { upload: vi.fn(), delete: vi.fn() },
    });

    await updatePublishedCard({ id: 42, content: '纯文字', tags: [], kind: 'text', media: [], mediaChanged: true });

    expect(update).toHaveBeenCalledWith(42, {
      content: '纯文字', tags: [], data: { kind: 'text' }, media_file_ids: [], cover_file_id: 0,
    });
  });

  it('omits unchanged visibility and can explicitly change it back to public', async () => {
    const update = vi.fn().mockResolvedValue(undefined);
    sdk.getSdkClient.mockReturnValue({
      getToken: vi.fn().mockReturnValue('token'),
      cards: { update },
      files: { upload: vi.fn(), delete: vi.fn() },
    });

    await updatePublishedCard({ id: 42, content: '不改范围', tags: [], kind: 'text' });
    expect(update.mock.calls[0][1]).not.toHaveProperty('visibility');

    await updatePublishedCard({ id: 42, content: '改回公开', tags: [], kind: 'text', visibility: 'public' });
    expect(update.mock.calls[1][1]).toMatchObject({ visibility: 'public' });
  });

  it('rejects an invalid visibility before updating a card', async () => {
    const update = vi.fn();
    sdk.getSdkClient.mockReturnValue({
      getToken: vi.fn().mockReturnValue('token'),
      cards: { update },
      files: { upload: vi.fn(), delete: vi.fn() },
    });

    await expect(updatePublishedCard({ id: 42, content: '范围测试', tags: [], kind: 'text', visibility: 'friends' })).rejects.toThrow('请选择有效的内容可见范围');
    expect(update).not.toHaveBeenCalled();
  });

  it('rejects an overlong edit before calling the SDK', async () => {
    const update = vi.fn();
    sdk.getSdkClient.mockReturnValue({
      getToken: vi.fn().mockReturnValue('token'),
      cards: { update },
      files: { upload: vi.fn(), delete: vi.fn() },
    });

    await expect(updatePublishedCard({ id: 42, content: '字'.repeat(1001), tags: [], kind: 'text' })).rejects.toThrow('正文最多 1000 字');
    expect(update).not.toHaveBeenCalled();
  });

  it.each([
    ['image', [{ id: 11, url: '/one.jpg', type: 'image/jpeg' }], '图文必须先填写正文。'],
    ['video', [{ id: 12, url: '/clip.mp4', type: 'video/mp4' }], '视频必须先填写正文。'],
  ])('requires正文 for %s edits', async (kind, media, message) => {
    const update = vi.fn();
    sdk.getSdkClient.mockReturnValue({
      getToken: vi.fn().mockReturnValue('token'),
      cards: { update },
      files: { upload: vi.fn(), delete: vi.fn() },
    });

    await expect(updatePublishedCard({ id: 42, content: '   ', tags: [], kind, media })).rejects.toThrow(message);
    expect(update).not.toHaveBeenCalled();
  });

  it('deletes only newly uploaded media when the card update fails', async () => {
    const client = {
      getToken: vi.fn().mockReturnValue('token'),
      files: { upload: vi.fn().mockResolvedValue({ id: 31, url: '/new.jpg' }), delete: vi.fn().mockResolvedValue(undefined) },
      cards: { update: vi.fn().mockRejectedValue(new Error('update failed')) },
    };
    sdk.getSdkClient.mockReturnValue(client);

    await expect(updatePublishedCard({ id: 42, content: '正文', tags: [], kind: 'image', files: [new Blob(['new'], { type: 'image/jpeg' })], mediaChanged: true })).rejects.toThrow('update failed');
    expect(client.files.delete).toHaveBeenCalledTimes(1);
    expect(client.files.delete).toHaveBeenCalledWith(31);
  });
});
