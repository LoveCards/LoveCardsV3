import { describe, expect, it, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';
import { MediaGallery, MediaRouteViewer, MediaViewer } from '../features/media/MediaGallery.jsx';

function imageItems(count) {
  return Array.from({ length: count }, (_, index) => ({
    type: 'image',
    url: `/media/photo-${index + 1}.webp`,
    alt: `第 ${index + 1} 张照片`,
  }));
}

function countTiles(markup) {
  return (markup.match(/class="detail-media-tile"/g) || []).length;
}

describe('detail media gallery', () => {
  for (const count of [1, 2, 3, 4]) {
    it(`renders the ${count}-item grid without placeholder tiles`, () => {
      const html = renderToStaticMarkup(<MediaGallery items={imageItems(count)} title="真实图集" />);
      expect(html).toContain(`is-count-${count}`);
      expect(countTiles(html)).toBe(count);
      expect(html).toContain(`媒体，共 ${count} 项`);
    });
  }

  it('shows four real tiles and an overflow count for longer galleries', () => {
    const html = renderToStaticMarkup(<MediaGallery items={imageItems(7)} title="七张照片" />);
    expect(html).toContain('is-count-4');
    expect(countTiles(html)).toBe(4);
    expect(html).toContain('+3');
    expect(html).toContain('查看第 4 项媒体，共 7 项');
  });

  it('renders video in the viewer with native controls and no autoplay', () => {
    const html = renderToStaticMarkup(
      <MediaViewer
        items={[{ type: 'video', url: '/media/run.mp4', posterUrl: '/media/run.webp' }]}
        title="晨跑记录"
        activeIndex={0}
        onIndexChange={vi.fn()}
        onClose={vi.fn()}
      />,
    );
    expect(html).toContain('<video');
    expect(html).toContain('controls=""');
    expect(html).toContain('playsInline=""');
    expect(html).toContain('preload="metadata"');
    expect(html).not.toContain('autoplay');
  });

  it('keeps GIF media as an image and exposes viewer navigation names', () => {
    const html = renderToStaticMarkup(
      <MediaViewer
        items={[{ type: 'gif', url: '/media/one.gif' }, { type: 'image', url: '/media/two.webp' }]}
        title="动态记录"
        activeIndex={0}
        onIndexChange={vi.fn()}
        onClose={vi.fn()}
      />,
    );
    expect(html).toContain('lc-media-viewer-media is-gif');
    expect(html).toContain('aria-label="上一项媒体"');
    expect(html).toContain('aria-label="下一项媒体"');
    expect(html).toContain('aria-label="关闭媒体查看器"');
  });

  it('renders the media deep link as an immersive viewer instead of redirect copy', () => {
    const html = renderToStaticMarkup(
      <MediaRouteViewer items={imageItems(2)} title="可分享图集" activeIndex={1} onIndexChange={vi.fn()} onClose={vi.fn()} />,
    );
    expect(html).toContain('lc-media-route-viewer');
    expect(html).toContain('2 / 2');
    expect(html).toContain('aria-label="返回内容详情"');
    expect(html).not.toContain('查看正文');
  });
});
