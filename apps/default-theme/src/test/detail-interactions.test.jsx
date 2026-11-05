import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderRoute } from './renderRoute.jsx';

const state = vi.hoisted(() => ({ uid: 1, pinSupported: false, updatePinnedComment: vi.fn() }));
const detail = vi.hoisted(() => ({
  card: {
    id: '42',
    ownerId: 1,
    author: '内容作者',
    content: '一条可编辑的内容',
    title: '一条可编辑的内容',
    body: '',
    likes: 2,
    replies: 1,
    topics: [{ id: 13, name: '牛的' }, { id: 5, name: '纪念日' }, { id: 4, name: '生日' }],
    mediaItems: [
      { type: 'image', url: '/media/1.webp', alt: '第一张' },
      { type: 'image', url: '/media/2.webp', alt: '第二张' },
      { type: 'image', url: '/media/3.webp', alt: '第三张' },
      { type: 'image', url: '/media/4.webp', alt: '第四张' },
      { type: 'image', url: '/media/5.webp', alt: '第五张' },
    ],
  },
  comments: [{ id: 7, ownerId: 2, author: '评论作者', authorHandle: 'commenter', content: '评论内容', likes: 0, parentId: null, isPinned: false }],
}));

vi.mock('../app/providers.jsx', async (importOriginal) => {
  const actual = await importOriginal();
  return {
    ...actual,
    useAuth: () => ({
      status: 'ready',
      session: { uid: state.uid, capabilities: ['cards.update', 'cards.delete', 'comments.delete'] },
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
  supportsUserCommentPinning: () => state.pinSupported,
  updateDetailCommentLike: vi.fn(),
  updatePinnedComment: state.updatePinnedComment,
}));

describe('detail interaction hierarchy', () => {
  beforeEach(() => { state.uid = 1; state.pinSupported = false; state.updatePinnedComment.mockReset(); detail.comments[0].authorHandle = 'commenter'; });

  it('links a valid comment identity and leaves a missing handle as text', () => {
    expect(renderRoute('/post/42')).toContain('href="/user/commenter"');
    detail.comments[0].authorHandle = null;
    const html = renderRoute('/post/42');
    expect(html).not.toContain('href="/user/commenter"');
    expect(html).toContain('评论作者');
  });

  it('keeps sharing explicit and gates content and comment actions by ownership', () => {
    const html = renderRoute('/post/42');
    expect(html).toContain('detail-share');
    expect(html).toContain('detail-like-control');
    expect(html).toContain('aria-label="内容操作"');
    expect(html).not.toContain('分享或复制链接');
    // 占位文案不得回归：举报入口如今是真实功能（D-8⑤），不再存在"暂未开放"。
    expect(html).not.toContain('举报 · 暂未开放');
    // 登录的非评论作者可对他人评论发起举报；作者本人仍看不到卡片举报。
    expect(html).toContain('aria-label="评论操作"');
    expect(html).toContain('>举报</span>');
    expect(html).not.toContain('编辑正文');
  });

  it('renders every hydrated topic on detail instead of the legacy first topic only', () => {
    const html = renderRoute('/post/42');
    expect(html).toContain('aria-label="内容话题"');
    expect(html).toContain('href="/topic/13-%E7%89%9B%E7%9A%84"');
    expect(html).toContain('href="/topic/5-%E7%BA%AA%E5%BF%B5%E6%97%A5"');
    expect(html).toContain('href="/topic/4-%E7%94%9F%E6%97%A5"');
  });

  it('offers report to non-owners while hiding report on your own comment', () => {
    state.uid = 2;
    const html = renderRoute('/post/42');
    // 非作者登录后可举报卡片，自己的评论保留删除动作但不自举举报。
    expect(html).toContain('aria-label="内容操作"');
    expect(html).toContain('aria-label="评论操作"');
    expect(html).toContain('>删除评论</span>');
    expect(html).not.toContain('>置顶评论</span>');
    expect((html.match(/>举报</g) ?? [])).toHaveLength(1);
  });

  it('reveals the root-comment pin action only when the owner SDK boundary is available', () => {
    state.pinSupported = true;
    const html = renderRoute('/post/42');
    expect(html).toContain('aria-label="评论操作"');
    expect(html).toContain('>置顶评论</span>');
  });

  it('renders normalized media items through the real detail gallery', () => {
    const html = renderRoute('/post/42');
    expect(html).toContain('detail-media-grid is-count-4');
    expect(html.match(/class="detail-media-tile"/g)).toHaveLength(4);
    expect(html).toContain('+1');
  });
});
