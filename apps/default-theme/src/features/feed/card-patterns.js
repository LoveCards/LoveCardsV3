function definePattern({ kind, label, family, layout, media = 'none', showKicker = false }) {
  return Object.freeze({
    kind,
    label,
    family,
    layout,
    presentation: Object.freeze({ media, showKicker }),
  });
}

/**
 * The visual registry deliberately contains no permission or mutation rules.
 * Those belong to the interaction adapter registry and the runtime capability
 * resolver. A card pattern only describes the content surface it presents.
 */
export const CARD_PATTERN_REGISTRY = Object.freeze({
  text: definePattern({ kind: 'text', label: '随手记', family: 'content', layout: 'note', showKicker: true }),
  image: definePattern({ kind: 'image', label: '图片', family: 'media', layout: 'lead', media: 'single' }),
  gallery: definePattern({ kind: 'gallery', label: '图集', family: 'media', layout: 'tile-a', media: 'collection' }),
  video: definePattern({ kind: 'video', label: '视频', family: 'media', layout: 'tile-b', media: 'single' }),
  discussion: definePattern({ kind: 'discussion', label: '社区讨论', family: 'conversation', layout: 'note', showKicker: true }),
  quote: definePattern({ kind: 'quote', label: '引用', family: 'content', layout: 'quote', showKicker: true }),
  link: definePattern({ kind: 'link', label: '链接', family: 'content', layout: 'link', showKicker: true }),
  product: definePattern({ kind: 'product', label: '社区商品', family: 'commerce', layout: 'product', showKicker: true }),
});

export function resolveCardPattern(kind) {
  return CARD_PATTERN_REGISTRY[kind] || CARD_PATTERN_REGISTRY.text;
}

export function isRegisteredCardPattern(kind) {
  return Boolean(CARD_PATTERN_REGISTRY[kind]);
}

export function inferCardPattern({ requestedKind = '', mediaItems = [] } = {}) {
  const requested = String(requestedKind || '').trim();
  if (requested === 'discussion' && mediaItems.length === 0) return 'discussion';
  if (requested === 'quote' || requested === 'link' || requested === 'product') return requested;
  if (requested === 'text' && mediaItems.length === 0) return 'text';
  if (requested === 'video' && mediaItems.length > 0) return 'video';
  if (requested === 'gallery' && mediaItems.length > 1) return 'gallery';
  if (requested === 'image' && mediaItems.length > 0) return 'image';
  if (mediaItems.length === 1 && mediaItems[0]?.type === 'video') return 'video';
  if (mediaItems.length > 1) return 'gallery';
  if (mediaItems.length === 1) return 'image';
  return 'text';
}
