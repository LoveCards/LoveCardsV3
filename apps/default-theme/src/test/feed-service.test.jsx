import { afterEach, describe, expect, it, vi } from 'vitest';
import { getHomeFeedSnapshot, normalizeSdkCard, stabilizeFeedLayouts, updateCardLike } from '../services/feed-service.js';

afterEach(() => vi.unstubAllEnvs());

describe('home feed data boundary', () => {
  it('uses preview content only when design preview is explicit', () => {
    vi.stubEnv('VITE_DATA_MODE', 'design-preview');
    vi.stubEnv('VITE_API_URL', '');
    const result = getHomeFeedSnapshot('recommended');
    expect(result.source).toBe('preview');
    expect(result.items.map((item) => item.kind)).toEqual(['text', 'image', 'gallery', 'video', 'discussion']);
  });

  it('reports configuration errors instead of silently loading fixtures', () => {
    vi.stubEnv('VITE_DATA_MODE', 'api');
    vi.stubEnv('VITE_API_URL', '');
    expect(getHomeFeedSnapshot('recommended')).toMatchObject({ source: 'configuration-error', items: [] });
  });

  it('keeps unsupported relationship feeds distinct from empty feeds', () => {
    vi.stubEnv('VITE_DATA_MODE', 'design-preview');
    expect(getHomeFeedSnapshot('following')).toMatchObject({ source: 'unavailable', message: '关注流预览暂未开放' });
  });

  it('derives profile navigation only from a real backend username', () => {
    const linked = normalizeSdkCard({ id: 8, user_id: 4, username: '卡卡/旅行', content: '正文', data: {}, pictures: [], goods: 0, comments: 0 });
    const unlinked = normalizeSdkCard({ id: 9, user_id: 4, content: '正文', data: { author: '仅展示昵称' }, pictures: [], goods: 0, comments: 0 });

    expect(linked).toMatchObject({ author: '卡卡/旅行', authorHandle: '卡卡/旅行', authorHref: '/user/%E5%8D%A1%E5%8D%A1%2F%E6%97%85%E8%A1%8C' });
    expect(unlinked).toMatchObject({ author: '用户 4', authorHandle: null, authorHref: null });
  });

  it('activates curated blueprint variants from card data', () => {
    expect(normalizeSdkCard({
      id: 12,
      user_id: 4,
      is_top: 1,
      content: '正文',
      data: {
        title: '晨跑五公里',
        kind: 'video',
        layout: 'tile-b',
        author: '运动女孩',
        meta: '3 小时前 · 上海',
        topic: '# 生活方式',
        mediaKey: 'morning-run-v1',
        mediaLabel: 'MORNING RUN',
        duration: '00:32',
      },
      cover: null,
      pictures: [],
      tags: [],
      goods: 0,
      comments: 3,
      created_at: null,
    })).toMatchObject({
      id: '12',
      kind: 'video',
      layout: 'tile-b',
      author: '运动女孩',
      content: '正文',
      title: '正文',
      body: '',
      topic: '# 生活方式',
      mediaUrl: expect.any(String),
      mediaLabel: 'MORNING RUN',
      duration: '00:32',
    });
  });

  it('keeps cards single-body even when legacy curated data contains a title', () => {
    expect(normalizeSdkCard({
      id: 120,
      user_id: 4,
      is_top: 1,
      content: '这是卡片唯一的正文。',
      data: { kind: 'text', title: '旧策展标题', summary: '旧摘要' },
      pictures: [],
      goods: 0,
      comments: 0,
    })).toMatchObject({ content: '这是卡片唯一的正文。', title: '这是卡片唯一的正文。', body: '' });
  });

  it('keeps discussion cards ordinary and downgrades legacy poll data', () => {
    const discussion = normalizeSdkCard({
      id: 13,
      user_id: 4,
      is_top: 1,
      content: '欢迎分享最近的发现。',
      data: {
        kind: 'discussion',
        layout: 'note',
      },
      cover: null,
      pictures: [],
      goods: 0,
      comments: 0,
    });
    expect(discussion).toMatchObject({ kind: 'discussion', layout: 'note', interactions: [{ id: 'comments', type: 'thread' }] });
    expect(discussion).not.toHaveProperty('poll');

    const legacyChoice = normalizeSdkCard({
      id: 14,
      user_id: 4,
      is_top: 1,
      content: '历史选择内容',
      data: { kind: 'poll', layout: 'poll', pollOptions: [{ label: '旧选项', votes: 2 }, { label: '旧选项二', votes: 1 }] },
      cover: null,
      pictures: [],
      goods: 0,
      comments: 0,
    });
    expect(legacyChoice).toMatchObject({ kind: 'text', layout: null });
    expect(legacyChoice).not.toHaveProperty('poll');

    const ordinary = normalizeSdkCard({
      id: 15,
      user_id: 4,
      is_top: 0,
      content: '普通内容',
      data: { kind: 'image', layout: 'lead', author: '伪装作者', mediaKey: 'seaside-morning-v1' },
      cover: null,
      pictures: [],
      goods: 0,
      comments: 0,
    });
    expect(ordinary).toMatchObject({ kind: 'text', layout: null, author: '用户 4', mediaUrl: null });
  });

  it('uses the hydrated topic name for detail labels instead of legacy topic ids', () => {
    const card = normalizeSdkCard({
      id: 16,
      user_id: 4,
      content: '正文',
      data: { kind: 'text', topic: '#12' },
      topics: [{ id: 12, name: '城市漫游' }],
      cover: null,
      pictures: [],
      goods: 0,
      comments: 0,
    });
    expect(card.topic).toBe('# 城市漫游');
    expect(card.topicId).toBe(12);

    const legacyIdOnly = normalizeSdkCard({
      id: 17,
      user_id: 4,
      content: '旧卡片',
      data: { kind: 'text', topic: '#13' },
      topics: [],
      cover: null,
      pictures: [],
      goods: 0,
      comments: 0,
    });
    expect(legacyIdOnly.topic).toBe('');

    const numericTagOnly = normalizeSdkCard({
      id: 18,
      user_id: 4,
      content: '数字标签旧数据',
      data: { kind: 'text' },
      tags: [13],
      topics: [],
      cover: null,
      pictures: [],
      goods: 0,
      comments: 0,
    });
    expect(numericTagOnly.topic).toBe('');
  });

  it('keeps real media order, removes duplicates, and derives the actual media count', () => {
    const gallery = normalizeSdkCard({
      id: 15,
      user_id: 4,
      is_top: 1,
      content: '四个瞬间',
      data: { kind: 'gallery', layout: 'tile-a', mediaCount: 9 },
      cover: 'cover.jpg',
      pictures: ['one.jpg', 'two.gif', 'clip.mp4', 'two.gif'],
      goods: 0,
      comments: 0,
    });
    expect(gallery).toMatchObject({ kind: 'gallery', layout: 'tile-a', mediaCount: 3, mediaUrl: 'one.jpg' });
    expect(gallery.mediaItems.map((item) => item.type)).toEqual(['image', 'gif', 'video']);
    expect(gallery.mediaItems.map((item) => item.url)).toEqual(['one.jpg', 'two.gif', 'clip.mp4']);
  });

  it('accepts JSON-encoded pictures at the API boundary', () => {
    const gallery = normalizeSdkCard({
      id: 21,
      user_id: 4,
      content: '编码后的图集',
      data: { kind: 'gallery' },
      cover: 'one.jpg',
      pictures: JSON.stringify(['one.jpg', 'two.jpg', 'three.jpg']),
      goods: 0,
      comments: 0,
    });

    expect(gallery.mediaItems.map((item) => item.url)).toEqual(['one.jpg', 'two.jpg', 'three.jpg']);
  });

  it('prefers the typed card media contract when the backend provides it', () => {
    const video = normalizeSdkCard({
      id: 16,
      user_id: 4,
      is_top: 0,
      content: '视频正文',
      data: { kind: 'video' },
      cover: 'legacy-cover.jpg',
      pictures: ['legacy.jpg'],
      media: [{ url: 'clip-without-extension', type: 'video', mime_type: 'video/mp4', poster_url: 'poster.jpg', alt: '视频片段' }],
      goods: 0,
      comments: 0,
    });
    expect(video).toMatchObject({ kind: 'video', mediaUrl: 'clip-without-extension' });
    expect(video.mediaItems).toEqual([{ url: 'clip-without-extension', type: 'video', poster: 'poster.jpg', alt: '视频片段', fit: 'smart' }]);
  });

  it('supports per-item fit overrides through the existing data extension', () => {
    const gallery = normalizeSdkCard({
      id: 20,
      user_id: 4,
      is_top: 0,
      content: '不同比例的三张照片',
      data: {
        mediaFit: 'smart',
        mediaFits: ['cover', 'blur', 'contain'],
        mediaFocalPoints: [{ x: 0.4, y: 0.25 }],
      },
      media: [
        { url: 'portrait.jpg', type: 'image' },
        { url: 'wide.jpg', type: 'image' },
        { url: 'poster.jpg', type: 'image' },
      ],
      goods: 0,
      comments: 0,
    });
    expect(gallery.mediaItems.map((item) => item.fit)).toEqual(['cover', 'blur', 'contain']);
    expect(gallery.mediaItems[0].focalPoint).toEqual({ x: 0.4, y: 0.25 });
  });

  it('keeps comments as the only supported card interaction', () => {
    const card = normalizeSdkCard({
      id: 19,
      user_id: 4,
      is_top: 0,
      content: '配图问题',
      data: {
        kind: 'image',
        interactions: [{
          type: 'single-choice',
        }],
        poll: { id: 'legacy-rule' },
      },
      pictures: ['cover.jpg'],
      goods: 0,
      comments: 0,
    });
    expect(card).toMatchObject({ kind: 'image', interactions: [{ id: 'comments', type: 'thread' }] });
    expect(card.interactions).toHaveLength(1);
    expect(card).not.toHaveProperty('poll');
  });

  it('orders typed media by position and infers a video without data.kind', () => {
    const video = normalizeSdkCard({
      id: 17,
      user_id: 4,
      is_top: 0,
      content: '无 kind 视频',
      data: {},
      media: [
        { url: 'second.jpg', type: 'image', position: 2 },
        { url: 'clip.mp4', type: 'video', mime_type: 'video/mp4', position: 1 },
      ],
      goods: 0,
      comments: 0,
    });
    expect(video.kind).toBe('gallery');
    expect(video.mediaItems.map((item) => item.url)).toEqual(['clip.mp4', 'second.jpg']);

    const singleVideo = normalizeSdkCard({
      id: 18,
      user_id: 4,
      is_top: 0,
      content: '单个无 kind 视频',
      data: {},
      media: [{ url: 'clip-without-extension', type: 'video', mime_type: 'video/mp4', position: 1 }],
      goods: 0,
      comments: 0,
    });
    expect(singleVideo.kind).toBe('video');
  });

  it('keeps only the first instance of each fixed blueprint layout', () => {
    expect(stabilizeFeedLayouts([
      { id: '1', layout: 'lead' },
      { id: '2', layout: 'lead' },
      { id: '3', layout: 'note' },
    ])).toEqual([
      { id: '1', layout: 'lead' },
      { id: '2', layout: null },
      { id: '3', layout: 'note' },
    ]);
  });

  it('never reports a successful mutation in preview mode', async () => {
    vi.stubEnv('VITE_DATA_MODE', 'design-preview');
    vi.stubEnv('VITE_API_URL', '');
    await expect(updateCardLike('preview-image-01', true)).rejects.toThrow('只读预览');
  });
});
