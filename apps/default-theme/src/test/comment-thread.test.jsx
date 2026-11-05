import { describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { buildCommentActionMenuItems, CommentThread } from '../features/comments/CommentThread.jsx';

const comments = [
  { id: 1, ownerId: 2, author: '林间有风', authorHandle: 'forest', content: '根评论', createdAt: '刚刚', likes: 2, parentId: null, isPinned: true },
  { id: 2, ownerId: 3, author: '摄影小白', content: '回复内容', createdAt: '刚刚', likes: 1, parentId: 1 },
];

const renderThread = (props) => renderToStaticMarkup(<MemoryRouter><CommentThread {...props} /></MemoryRouter>);

describe('comment thread surface', () => {
  it('renders replies as a grouped thread and uses the native chat composer', () => {
    const html = renderThread({ comments, source: 'live', isAuthenticated: true, canComment: true, canLike: true, viewerId: 9, onSubmitComment: vi.fn(), onLogin: vi.fn(), onToggleLike: vi.fn(), onOpenEdit: vi.fn(), onRequestDelete: vi.fn(), onCopy: vi.fn() });
    expect(html).toContain('comment-thread-replies');
    expect(html).toContain('回复');
    expect(html).toContain('contentEditable="true"');
    expect(html).toContain('astryx-chat-composer');
    expect(html).toContain('comment-thread-composer-wrap');
    expect(html).toContain('comment-composer-input-shell');
    expect(html).toContain('comment-composer-count');
    expect(html).toContain('comment-composer-hint');
    expect(html).toContain('>发送</span>');
    expect(html).toContain('comment-thread-row-header');
    expect(html).toContain('author-identity-comment');
    expect(html).toContain('href="/user/forest"');
    expect(html).toContain('林间有风</b><small>刚刚</small>');
    expect(html).toContain('comment-like-button');
    expect(html).toContain('aria-pressed="false"');
    expect(html).toContain('comment-pinned-badge');
    expect(html).toContain('置顶优先');
  });

  it('wires the same raw-text @ mention trigger into comments and replies', () => {
    const source = readFileSync(fileURLToPath(new URL('../features/comments/CommentThread.jsx', import.meta.url)), 'utf8');
    expect(source).toContain('triggers={mentionTrigger ? [mentionTrigger] : undefined}');
    expect(source).toContain('pasteAsToken={false}');
    expect(source).not.toContain('mentioned_user_ids');
  });

  it('keeps preview comments read-only', () => {
    const html = renderThread({ comments: [], source: 'preview', isAuthenticated: false, canComment: false, onSubmitComment: vi.fn(), onLogin: vi.fn() });
    expect(html).toContain('设计预览不会发送评论');
    expect(html).not.toContain('contenteditable');
  });

  it('does not expose comment body editing and gates deletion by capability', () => {
    const html = renderThread({ comments, source: 'live', isAuthenticated: true, canComment: true, canDeleteComment: true, viewerId: 2, onSubmitComment: vi.fn(), onLogin: vi.fn(), onRequestDelete: vi.fn(), onCopy: vi.fn() });
    expect(html).not.toContain('编辑评论');
    expect(html).toContain('删除评论');
    const readOnly = renderThread({ comments, source: 'live', isAuthenticated: true, canComment: true, canDeleteComment: false, viewerId: 2, onSubmitComment: vi.fn(), onLogin: vi.fn(), onRequestDelete: vi.fn(), onCopy: vi.fn() });
    expect(readOnly).not.toContain('删除评论');
  });

  it('marks a deep-linked reply as the current discussion target', () => {
    const html = renderThread({ comments, focusedCommentId: '2', source: 'live', isAuthenticated: true, canComment: true, viewerId: 9, onSubmitComment: vi.fn(), onLogin: vi.fn(), onOpenEdit: vi.fn(), onRequestDelete: vi.fn(), onCopy: vi.fn() });
    expect(html).toContain('id="comment-2"');
    expect(html).toContain('comment-thread-row is-reply is-focused');
    expect(html).toContain('aria-current="true"');
  });

});

describe('comment pin action boundary', () => {
  it('offers pinning for another author\'s top-level comment and emits intent only', () => {
    const onRequestPinChange = vi.fn();
    const item = { ...comments[0], isPinned: false };
    const items = buildCommentActionMenuItems({ item, source: 'live', viewerId: 9, canPinComment: true, onRequestPinChange, onCopy: vi.fn() });

    expect(items.filter((entry) => entry.label).map((entry) => entry.label)).toEqual(['复制评论', '置顶评论']);
    items.find((entry) => entry.label === '置顶评论').onClick();
    expect(onRequestPinChange).toHaveBeenCalledWith(item, true);
  });

  it('offers unpin for the pinned root and disables the mutation while busy', () => {
    const onRequestPinChange = vi.fn();
    const item = { ...comments[0], isPinned: true };
    const items = buildCommentActionMenuItems({ item, source: 'live', viewerId: 9, canPinComment: true, pinBusy: true, onRequestPinChange, onCopy: vi.fn() });
    const unpin = items.find((entry) => entry.label === '取消置顶');

    expect(unpin.isDisabled).toBe(true);
    unpin.onClick();
    expect(onRequestPinChange).toHaveBeenCalledWith(item, false);
  });

  it('never treats a reply or orphan reply as a pinnable root', () => {
    const onRequestPinChange = vi.fn();
    const regularReply = buildCommentActionMenuItems({ item: comments[1], source: 'live', viewerId: 9, canPinComment: true, onRequestPinChange, onCopy: vi.fn() });
    const orphanReply = buildCommentActionMenuItems({ item: { ...comments[1], parentId: 999 }, source: 'live', viewerId: 9, canPinComment: true, onRequestPinChange, onCopy: vi.fn() });

    expect(regularReply).toEqual([]);
    expect(orphanReply).toEqual([]);
  });

  it('keeps owner pin and delete actions ordered without duplicate dividers', () => {
    const items = buildCommentActionMenuItems({
      item: { ...comments[0], isPinned: false },
      source: 'live',
      viewerId: 2,
      canDeleteComment: true,
      canPinComment: true,
      onRequestPinChange: vi.fn(),
      onRequestDelete: vi.fn(),
      onCopy: vi.fn(),
    });

    expect(items.map((entry) => entry.type || entry.label)).toEqual(['复制评论', '置顶评论', 'divider', '删除评论']);
  });

  it('offers report only to non-owners and keeps it last with a divider', () => {
    const onReport = vi.fn();
    const foreign = buildCommentActionMenuItems({ item: comments[0], source: 'live', viewerId: 9, canReport: true, onReport, onCopy: vi.fn() });
    expect(foreign.map((entry) => entry.type || entry.label)).toEqual(['复制评论', 'divider', '举报']);
    foreign[2].onClick();
    expect(onReport).toHaveBeenCalledWith(comments[0]);
    const own = buildCommentActionMenuItems({ item: comments[0], source: 'live', viewerId: 2, canReport: true, canDeleteComment: true, onRequestDelete: vi.fn(), onCopy: vi.fn(), onReport });
    expect(own.map((entry) => entry.type || entry.label)).toEqual(['复制评论', 'divider', '删除评论']);
    const silent = buildCommentActionMenuItems({ item: comments[0], source: 'live', viewerId: 9, canReport: true, onCopy: vi.fn() });
    expect(silent).toEqual([]);
  });

  it('does not expose a fake pin action before the user-facing SDK capability exists', () => {
    expect(buildCommentActionMenuItems({ item: comments[0], source: 'live', viewerId: 9, canPinComment: false, onCopy: vi.fn() })).toEqual([]);
    expect(buildCommentActionMenuItems({ item: comments[0], source: 'live', viewerId: 9, canPinComment: true, onCopy: vi.fn() })).toEqual([]);
  });
});
