import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { AuthorIdentity } from '../components/AuthorIdentity.jsx';
import { ProfileCardFeed } from '../components/ProfileCardFeed.jsx';
import { FeedStory } from '../features/feed/FeedStory.jsx';

function render(element) {
  return renderToStaticMarkup(<MemoryRouter>{element}</MemoryRouter>);
}

describe('card author identity', () => {
  it('uses one semantic profile link for a valid username', () => {
    const html = render(<AuthorIdentity author="卡卡旅行记" handle=" travel/name " meta="5 小时前" />);

    expect(html).toContain('href="/user/travel%2Fname"');
    expect(html.match(/<a\b/g)).toHaveLength(1);
    expect(html).toContain('查看 卡卡旅行记 的主页');
    expect(html).toContain('5 小时前');
  });

  it.each([undefined, '', '已注销', 'deleted'])('keeps missing or invalid username %s non-interactive', (handle) => {
    const html = render(<AuthorIdentity author="未知用户" handle={handle} />);

    expect(html).not.toContain('<a');
    expect(html).toContain('author-identity');
    expect(html).toContain('未知用户');
  });

  it('keeps real avatars inside the same profile link', () => {
    const html = render(<AuthorIdentity author="林间有风" handle="forest" avatarUrl="/avatar/forest.webp" variant="list" />);

    expect(html).toContain('author-identity-list');
    expect(html).toContain('src="/avatar/forest.webp"');
    expect(html.match(/<a\b/g)).toHaveLength(1);
  });

  it('keeps author and card detail as independent sibling links', () => {
    const html = render(<FeedStory card={{ id: '42', kind: 'image', title: '海边的风', author: '卡卡旅行记', authorHandle: 'kaka', likes: 2, replies: 1, mediaUrl: 'https://example.test/coast.jpg' }} source="recommended" isStaticPreview={false} />);

    expect(html).toContain('href="/user/kaka"');
    expect(html).toContain('href="/post/42"');
    const authorStart = html.indexOf('href="/user/kaka"');
    const authorEnd = html.indexOf('</a>', authorStart);
    expect(html.slice(authorStart, authorEnd)).not.toContain('href="/post/42"');
  });

  it('reuses the profile card feed without nesting author and detail links', () => {
    const html = render(<ProfileCardFeed items={[{ id: 9, kind: 'image', title: '海边', author: '卡卡旅行记', authorHandle: 'kaka', mediaUrl: '/coast.webp', likes: 4, replies: 2 }]} />);
    expect(html).toContain('href="/user/kaka"');
    expect(html).toContain('href="/post/9"');
    const detailStart = html.indexOf('href="/post/9"');
    const detailEnd = html.indexOf('</a>', detailStart);
    expect(html.slice(detailStart, detailEnd)).not.toContain('href="/user/kaka"');
  });

  it('keeps profile cards in the shared responsive masonry structure', () => {
    const html = render(<ProfileCardFeed items={[
      { id: 9, kind: 'image', title: '海边', author: '卡卡旅行记', authorHandle: 'kaka', mediaUrl: '/coast.webp' },
      { id: 10, kind: 'text', title: '日常记录', author: '卡卡旅行记', authorHandle: 'kaka' },
    ]} />);

    expect(html).toContain('profile-feed feed-masonry');
    expect(html).toContain('class="masonry-column-sizer"');
    expect(html).toContain('class="masonry-gutter-sizer"');
    expect(html.match(/class="feed-story/g)).toHaveLength(2);
  });

  it('does not assign the profile owner handle to another card author', () => {
    const html = render(<ProfileCardFeed profile={{ username: '本人', handle: 'self' }} items={[{ id: 10, kind: 'text', title: '收藏的内容', author: '另一位作者' }]} />);
    expect(html).not.toContain('href="/user/self"');
  });

  it('keeps deleted records in the shared feed shape without a dead detail link', () => {
    const html = render(<ProfileCardFeed items={[{ id: null, kind: 'text', unavailable: true, title: '原内容已删除', body: '这条记录仍保留。', author: '原内容', meta: '刚刚' }]} />);
    expect(html).toContain('feed-story-unavailable');
    expect(html).toContain('原内容已删除');
    expect(html).not.toContain('/post/null');
    expect(html).not.toContain('profile-unavailable-list');
  });
});
