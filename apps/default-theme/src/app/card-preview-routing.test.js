import { describe, expect, it } from 'vitest';
import { cardPreviewState, previewBackground } from './card-preview-routing.js';

describe('card preview route state', () => {
  const source = { pathname: '/search/posts', search: '?q=coast', hash: '', state: null, key: 'source' };

  it('preserves the source route and filter for a card link', () => {
    const state = cardPreviewState(source, 42);
    expect(previewBackground({ pathname: '/post/42', state })).toEqual(source);
  });

  it('falls back to the full detail for mismatched or explicit full routes', () => {
    const state = cardPreviewState(source, 42);
    expect(previewBackground({ pathname: '/post/43', state })).toBeNull();
    expect(previewBackground({ pathname: '/post/42', state: { ...state, previewMode: 'full' } })).toBeNull();
    expect(previewBackground({ pathname: '/post/%', state })).toBeNull();
  });

  it('rejects background routes that would create stacked previews', () => {
    const state = cardPreviewState({ ...source, pathname: '/post/41' }, 42);
    expect(previewBackground({ pathname: '/post/42', state })).toBeNull();
  });
});
