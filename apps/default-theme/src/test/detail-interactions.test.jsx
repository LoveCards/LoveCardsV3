import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderRoute } from './renderRoute.jsx';

const state = vi.hoisted(() => ({ uid: 1 }));
const detail = vi.hoisted(() => ({
  card: {
    id: '42',
    ownerId: 1,
    author: '内容作者',
    title: '一条可编辑的内容',
    body: '正文',
    likes: 2,
    replies: 1,
    mediaItems: [
      { type: 'image', url: '/media/1.webp', alt: '第一张' },
      { type: 'image', url: '/media/2.webp', alt: '第二张' },
      { type: 'image', url: '/media/3.webp', alt: '第三张' },
      { type: 'image', url: '/media/4.webp', alt: '第四张' },
      { type: 'image', url: '/media/5.webp', alt: '第五张' },
    ],
  },
  comments: [{ id: 7, ownerId: 2, author: '评论作者', content: '评论内容', likes: 0 }],
}));

vi.mock('../app/providers.jsx', async (importOriginal) => {
  const actual = await importOriginal();
  return {
    ...actual,
    useAuth: () => ({
      status: 'ready',
      session: { uid: state.uid, capabilities: ['cards.update', 'cards.delete', 'comments.update', 'comments.delete'] },
      isAuthenticated: true,
      isVisitor: false,
      can: () => true,
      logout: vi.fn(),
    }),
  };
});

vi.mock('../services/detail-service.js', () => ({
  createDetailComment: vi.fn(),
  getDetailSnapshot: () => ({ source: 'live', card: detail.card, comments: detail.comments }),
  loadDetail: async () => ({ source: 'live', card: detail.card, comments: detail.comments }),
}));

describe('detail interaction hierarchy', () => {
  beforeEach(() => { state.uid = 1; });

  it('keeps sharing explicit and reserves the content menu for owner actions', () => {
    const html = renderRoute('/post/42');
    expect(html).toContain('detail-share');
    expect(html).toContain('detail-like-control');
    expect(html).toContain('aria-label="内容操作"');
    expect(html).not.toContain('分享或复制链接');
    expect(html).not.toContain('举报 · 暂未开放');
    expect(html).not.toContain('aria-label="评论操作"');
  });

  it('shows the comment menu only to the comment owner', () => {
    state.uid = 2;
    const html = renderRoute('/post/42');
    expect(html).not.toContain('aria-label="内容操作"');
    expect(html).toContain('aria-label="评论操作"');
  });

  it('renders normalized media items through the real detail gallery', () => {
    const html = renderRoute('/post/42');
    expect(html).toContain('detail-media-grid is-count-4');
    expect(html.match(/class="detail-media-tile"/g)).toHaveLength(4);
    expect(html).toContain('+1');
  });
});
