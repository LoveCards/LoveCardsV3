import { describe, expect, it, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';
import { CommentThread } from '../features/comments/CommentThread.jsx';

const comments = [
  { id: 1, ownerId: 2, author: '林间有风', content: '根评论', createdAt: '刚刚', likes: 2, parentId: null },
  { id: 2, ownerId: 3, author: '摄影小白', content: '回复内容', createdAt: '刚刚', likes: 1, parentId: 1 },
];

describe('comment thread surface', () => {
  it('renders replies as a grouped thread and uses the native chat composer', () => {
    const html = renderToStaticMarkup(<CommentThread comments={comments} source="live" isAuthenticated canComment viewerId={9} onSubmitComment={vi.fn()} onLogin={vi.fn()} onOpenEdit={vi.fn()} onRequestDelete={vi.fn()} onCopy={vi.fn()} />);
    expect(html).toContain('comment-thread-replies');
    expect(html).toContain('回复');
    expect(html).toContain('contentEditable="true"');
    expect(html).toContain('astryx-chat-composer');
    expect(html).toContain('comment-thread-composer-wrap');
    expect(html).toContain('comment-composer-input-shell');
    expect(html).toContain('comment-composer-count');
    expect(html).toContain('comment-composer-hint');
    expect(html).toContain('>发送</span>');
  });

  it('keeps preview comments read-only', () => {
    const html = renderToStaticMarkup(<CommentThread comments={[]} source="preview" isAuthenticated={false} canComment={false} onSubmitComment={vi.fn()} onLogin={vi.fn()} />);
    expect(html).toContain('设计预览不会发送评论');
    expect(html).not.toContain('contenteditable');
  });

  it('marks a deep-linked reply as the current discussion target', () => {
    const html = renderToStaticMarkup(<CommentThread comments={comments} focusedCommentId="2" source="live" isAuthenticated canComment viewerId={9} onSubmitComment={vi.fn()} onLogin={vi.fn()} onOpenEdit={vi.fn()} onRequestDelete={vi.fn()} onCopy={vi.fn()} />);
    expect(html).toContain('id="comment-2"');
    expect(html).toContain('comment-thread-row is-reply is-focused');
    expect(html).toContain('aria-current="true"');
  });

});
