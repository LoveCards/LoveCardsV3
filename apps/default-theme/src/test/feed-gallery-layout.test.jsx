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

  it('shows one image and separate navigation buttons in pager mode', () => {
    const html = renderGallery('pager', 3);
    expect(html).toContain('story-media-pager');
    expect((html.match(/class="media-fit-asset"/g) || [])).toHaveLength(1);
    expect(html).toContain('1 / 3');
    expect(html).toContain('aria-label="下一张图片"');
    const mediaLinkEnd = html.indexOf('</a>', html.indexOf('class="story-media-page"'));
    expect(html.slice(0, mediaLinkEnd)).not.toContain('story-media-nav');
  });
});
