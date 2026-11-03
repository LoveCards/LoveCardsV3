import { describe, expect, it } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';
import { MediaFrame } from '../features/media/MediaFrame.jsx';
import { normalizeMediaFit, resolveMediaFit } from '../features/media/media-fit.js';

describe('media fit policy', () => {
  it('normalizes supported modes and safely falls back to smart', () => {
    expect(normalizeMediaFit('aspect-fill')).toBe('cover');
    expect(normalizeMediaFit('center_crop')).toBe('center-crop');
    expect(normalizeMediaFit('unknown')).toBe('smart');
  });

  it('uses cover when the media and frame ratios are similar', () => {
    expect(resolveMediaFit({ item: { width: 1080, height: 1350, type: 'image', url: '/photo.webp' }, frameRatio: 4 / 5 })).toBe('cover');
  });

  it('uses a blurred background for a large ratio mismatch', () => {
    expect(resolveMediaFit({ item: { width: 1920, height: 1080, type: 'image', url: '/wide.webp' }, frameRatio: 4 / 5 })).toBe('blur');
  });

  it('keeps posterless video complete and always contains media in the viewer', () => {
    expect(resolveMediaFit({ item: { type: 'video', url: '/clip.mp4' }, frameRatio: 4 / 5 })).toBe('contain');
    expect(resolveMediaFit({ item: { type: 'image', url: '/photo.webp', fit: 'cover' }, context: 'viewer' })).toBe('contain');
  });

  it('renders blur and focal crop modes through the shared frame', () => {
    const blurred = renderToStaticMarkup(<MediaFrame item={{ type: 'image', url: '/wide.webp', width: 1920, height: 1080 }} frameRatio={4 / 5} alt="横向照片" />);
    const cropped = renderToStaticMarkup(<MediaFrame item={{ type: 'image', url: '/portrait.webp', fit: 'cover', focal_x: 0.4, focal_y: 0.2 }} alt="人像" />);
    expect(blurred).toContain('data-media-fit="blur"');
    expect(blurred).toContain('media-fit-backdrop');
    expect(cropped).toContain('data-media-fit="cover"');
    expect(cropped).toContain('--lc-media-position:40% 20%');
  });
});
