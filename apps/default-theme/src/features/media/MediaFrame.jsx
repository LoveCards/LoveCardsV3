import { useRef, useState } from 'react';
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
  const [renewed, setRenewed] = useState(null);
  const lastAttempt = useRef(0);
  const restoreTime = useRef(0);
  const source = renewed?.original === item.url ? renewed.url : item.url;
  const renew = async (event) => {
    if (typeof item.renewUrl !== 'function' || Date.now() - lastAttempt.current < 5000) return;
    lastAttempt.current = Date.now();
    restoreTime.current = event.currentTarget.currentTime || 0;
    try {
      const url = await item.renewUrl();
      setRenewed({ original: item.url, url: url || undefined });
    } catch {
      // 撤权与网络错误均不沿用旧凭据，也不无限重试。
      setRenewed({ original: item.url, url: undefined });
    }
  };
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
        <video {...common} src={source} onError={renew} onLoadedMetadata={(event) => { if (restoreTime.current) event.currentTarget.currentTime = restoreTime.current; }} poster={getMediaPoster(item) || undefined} controls={controls} muted={muted} playsInline={playsInline} preload={preload} />
      ) : (
        <img {...common} src={source} onError={renew} alt={ariaHidden ? '' : alt} loading={loading} fetchPriority={fetchPriority} />
      )}
      {children}
    </Frame>
  );
}
