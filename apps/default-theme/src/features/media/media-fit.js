export const MEDIA_FIT_MODES = Object.freeze(['smart', 'cover', 'contain', 'center-crop', 'blur']);

const MEDIA_FIT_ALIASES = Object.freeze({
  fill: 'cover',
  'aspect-fill': 'cover',
  fit: 'contain',
  'aspect-fit': 'contain',
  center: 'center-crop',
  crop: 'center-crop',
  blurred: 'blur',
});

export function normalizeMediaFit(value, fallback = 'smart') {
  const normalized = String(value || '').trim().toLowerCase().replaceAll('_', '-');
  const resolved = MEDIA_FIT_ALIASES[normalized] || normalized;
  return MEDIA_FIT_MODES.includes(resolved) ? resolved : fallback;
}
export function getMediaType(item) {
  const explicitType = String(item?.type || item?.kind || item?.mimeType || item?.mime_type || '').toLowerCase();
  const url = String(item?.url || '');
  if (explicitType === 'video' || explicitType.startsWith('video/') || /\.(mp4|webm|mov)(?:$|[?#])/i.test(url)) return 'video';
  if (explicitType === 'gif' || explicitType === 'image/gif' || /\.gif(?:$|[?#])/i.test(url)) return 'gif';
  return 'image';
}

export function getMediaPoster(item) {
  return item?.posterUrl || item?.poster_url || item?.poster || '';
}

export function getMediaRatio(item) {
  const width = Number(item?.width);
  const height = Number(item?.height);
  return Number.isFinite(width) && Number.isFinite(height) && width > 0 && height > 0 ? width / height : null;
}

export function getMediaFocalPoint(item) {
  const x = Number(item?.focalPoint?.x ?? item?.focal_x ?? item?.focalX);
  const y = Number(item?.focalPoint?.y ?? item?.focal_y ?? item?.focalY);
  return {
    x: Number.isFinite(x) && x >= 0 && x <= 1 ? x : 0.5,
    y: Number.isFinite(y) && y >= 0 && y <= 1 ? y : 0.5,
  };
}

export function mediaBackdropSource(item) {
  return getMediaType(item) === 'video' ? getMediaPoster(item) : String(item?.url || '');
}

export function resolveMediaFit({ item, fit, frameRatio = 1, context = 'frame' } = {}) {
  const requested = normalizeMediaFit(fit || item?.fit || item?.mediaFit || item?.media_fit);
  if (context === 'viewer') return 'contain';
  if (requested !== 'smart') return requested === 'blur' && !mediaBackdropSource(item) ? 'contain' : requested;

  const mediaRatio = getMediaRatio(item);
  if (!mediaRatio || !Number.isFinite(frameRatio) || frameRatio <= 0) {
    return getMediaType(item) === 'video' && !getMediaPoster(item) ? 'contain' : 'cover';
  }

  const similarity = Math.min(mediaRatio, frameRatio) / Math.max(mediaRatio, frameRatio);
  if (similarity >= 0.72) return 'cover';
  return mediaBackdropSource(item) ? 'blur' : 'contain';
}
