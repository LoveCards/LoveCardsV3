import { getMediaFocalPoint, getMediaPoster, getMediaType, mediaBackdropSource, resolveMediaFit } from './media-fit.js';

export function MediaFrame({
  as: Frame = 'span',
  item,
  fit,
  frameRatio = 1,
  context = 'frame',
  className = '',
  assetClassName = '',
  alt = '',
  loading = 'lazy',
  fetchPriority = 'auto',
  controls = false,
  muted = false,
  playsInline = true,
  preload = 'metadata',
  ariaHidden = false,
  children,
}) {
  const type = getMediaType(item);
  const resolvedFit = resolveMediaFit({ item, fit, frameRatio, context });
  const backdrop = resolvedFit === 'blur' ? mediaBackdropSource(item) : '';
  const focalPoint = getMediaFocalPoint(item);
  const style = resolvedFit === 'cover' ? { '--lc-media-position': `${focalPoint.x * 100}% ${focalPoint.y * 100}%` } : undefined;
  const frameClass = `media-fit-frame media-fit-${resolvedFit}${className ? ` ${className}` : ''}`;
  const common = { className: `media-fit-asset${assetClassName ? ` ${assetClassName}` : ''}`, 'aria-hidden': ariaHidden || undefined };

  return (
    <Frame className={frameClass} data-media-fit={resolvedFit} style={style}>
      {backdrop && <img className="media-fit-backdrop" src={backdrop} alt="" aria-hidden="true" loading="lazy" />}
      {type === 'video' ? (
        <video {...common} src={item.url} poster={getMediaPoster(item) || undefined} controls={controls} muted={muted} playsInline={playsInline} preload={preload} />
      ) : (
        <img {...common} src={item.url} alt={ariaHidden ? '' : alt} loading={loading} fetchPriority={fetchPriority} />
      )}
      {children}
    </Frame>
  );
}
