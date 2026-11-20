import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { FeedStory } from '../features/feed/FeedStory.jsx';

function renderGallery(galleryLayout, count = 9) {
  const mediaItems = Array.from({ length: count }, (_, index) => ({ url: `/photo-${index + 1}.webp`, type: 'image' }));
  return renderToStaticMarkup(<MemoryRouter><FeedStory card={{ id: '42', kind: 'gallery', title: '一组照片', author: '作者', galleryLayout, mediaItems }} source="recommended" /></MemoryRouter>);
}

describe('feed gallery layouts', () => {
  it('shows all nine images in grid mode', () => {
    const html = renderGallery('grid');
    expect(html).toContain('story-media-grid-9');
    expect((html.match(/class="media-fit-asset"/g) || [])).toHaveLength(9);
    expect(html).not.toContain('story-media-more');
  });

  it('keeps pager cards as a static grid in the feed', () => {
    const html = renderGallery('pager', 3);
    expect(html).toBe(renderGallery('grid', 3));
    expect(html).toContain('story-media-grid-3');
    expect((html.match(/class="media-fit-asset"/g) || [])).toHaveLength(3);
    expect(html).toContain('href="/post/42"');
    expect(html).not.toContain('story-media-nav');
    expect(html).not.toContain('story-media-pager');
  });
});
