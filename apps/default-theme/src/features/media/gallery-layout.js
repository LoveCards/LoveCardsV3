export function normalizeGalleryLayout(value, imageCount = 2) {
  if (value === 'pager' || value === 'grid') return value;
  return imageCount === 1 ? 'pager' : 'grid';
}

export function restoreComposeGalleryLayout(value, imageCount, publishType) {
  return normalizeGalleryLayout(value, publishType === 'image' && imageCount > 1 ? imageCount : 1);
}
