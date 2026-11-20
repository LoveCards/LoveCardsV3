import { describe, expect, it } from 'vitest';
import { cardPreviewState, previewBackground, readPreviewMediaIndex } from './card-preview-routing.js';

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

  it('carries a safe gallery page only for the matching media preview', () => {
    const state = cardPreviewState(source, 42, 'gallery', 'media', 1);
    expect(state.previewMediaIndex).toBe(1);
    expect(previewBackground({ pathname: '/post/42', state })).toEqual(source);
    expect(readPreviewMediaIndex({ pathname: '/post/42', state }, 42, 3)).toBe(1);
    expect(readPreviewMediaIndex({ pathname: '/post/42', state: { ...state, previewMode: 'full' } }, 42, 3)).toBe(1);
    expect(readPreviewMediaIndex({ pathname: '/post/43', state }, 43, 3)).toBe(0);
    expect(readPreviewMediaIndex({ pathname: '/post/42', state }, 42, 1)).toBe(0);
    expect(readPreviewMediaIndex({ pathname: '/post/42', state: { ...state, previewMediaIndex: -1 } }, 42, 3)).toBe(0);
    expect(readPreviewMediaIndex({ pathname: '/post/42', state: { ...state, previewMediaIndex: 1.5 } }, 42, 3)).toBe(0);
    expect(readPreviewMediaIndex({ pathname: '/post/42', state: { ...state, previewMediaIndex: '1' } }, 42, 3)).toBe(0);
    expect(readPreviewMediaIndex({ pathname: '/post/42', state: { ...state, previewSession: 'other' } }, 42, 3)).toBe(0);
    expect(cardPreviewState(source, 42, 'gallery', 'media', Number.MAX_SAFE_INTEGER + 1)).not.toHaveProperty('previewMediaIndex');
    expect(cardPreviewState(source, 42, 'gallery', 'text', 1)).not.toHaveProperty('previewMediaIndex');
  });
});
