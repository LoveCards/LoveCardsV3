import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { MentionText } from '../components/MentionText.jsx';
import { FeedStory } from '../features/feed/FeedStory.jsx';

function render(element) {
  return renderToStaticMarkup(<MemoryRouter>{element}</MemoryRouter>);
}

describe('structured mention rendering', () => {
  it('renders an active server-authenticated mention as a profile link', () => {
    const html = render(<MentionText text="你好 @forest" mentions={[{ start: 3, end: 10, state: 'active', action_url: '/user/100000000001' }]} />);
    expect(html).toContain('class="mention-text-link"');
    expect(html).toContain('href="/user/100000000001"');
    expect(html).toContain('@forest');
  });

  it('keeps unavailable mentions as plain text and rejects external paths', () => {
    const html = render(<MentionText text="@gone @other" mentions={[
      { start: 0, end: 5, state: 'unavailable', action_url: '/user/gone' },
      { start: 6, end: 12, state: 'active', action_url: '//evil.example' },
    ]} />);
    expect(html).toContain('mention-text-unavailable');
    expect(html).not.toContain('evil.example');
    expect(html).not.toContain('mention-text-link');
  });

  it('keeps card detail and mention links as sibling paths', () => {
    const html = render(<FeedStory card={{ id: '42', kind: 'text', content: '你好 @forest', title: '你好 @forest', author: '作者', mentions: [{ start: 3, end: 10, state: 'active', action_url: '/user/100000000001' }] }} source="recommended" />);
    expect(html).toContain('href="/post/42"');
    expect(html).toContain('href="/user/100000000001"');
    const detailStart = html.indexOf('href="/post/42"');
    const detailEnd = html.indexOf('</a>', detailStart);
    expect(html.slice(detailStart, detailEnd)).not.toContain('user/100000000001');
  });
});
