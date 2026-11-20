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
  autoPlay = false,
  videoRef,
  onVideoPlay,
  onVideoPause,
  muted = false,
  playsInline = true,
  preload = 'metadata',
  ariaHidden = false,
  children,
}) {
  const [renewed, setRenewed] = useState(null);
  const [unavailableUrl, setUnavailableUrl] = useState(null);
  const [loadedUrl, setLoadedUrl] = useState(null);
  const lastAttempt = useRef(0);
  const restoreTime = useRef(0);
  const source = renewed?.original === item.url ? renewed.url : item.url;
  const renew = async (event) => {
    if (typeof item.renewUrl !== 'function' || Date.now() - lastAttempt.current < 5000) {
      setUnavailableUrl(item.url);
      return;
    }
    lastAttempt.current = Date.now();
    restoreTime.current = event.currentTarget.currentTime || 0;
    try {
      const url = await item.renewUrl();
      if (!url) setUnavailableUrl(item.url);
      else setUnavailableUrl(null);
      setRenewed({ original: item.url, url: url || undefined });
    } catch {
      // 撤权与网络错误均不沿用旧凭据，也不无限重试。
      setRenewed({ original: item.url, url: undefined });
      setUnavailableUrl(item.url);
    }
  };
  const type = getMediaType(item);
  const unavailable = unavailableUrl === item.url;
  const imageLoading = type !== 'video' && Boolean(source) && loadedUrl !== source && !unavailable;
  const unavailableLabel = type === 'video' ? '视频暂时无法显示' : '图片暂时无法显示';
  const resolvedFit = resolveMediaFit({ item, fit, frameRatio, context });
  const backdrop = resolvedFit === 'blur' ? mediaBackdropSource(item) : '';
  const focalPoint = getMediaFocalPoint(item);
  const style = resolvedFit === 'cover' ? { '--lc-media-position': `${focalPoint.x * 100}% ${focalPoint.y * 100}%` } : undefined;
  const frameClass = `media-fit-frame media-fit-${resolvedFit}${className ? ` ${className}` : ''}`;
  const common = { className: `media-fit-asset${assetClassName ? ` ${assetClassName}` : ''}`, 'aria-hidden': ariaHidden || undefined };

  return (
    <Frame className={frameClass} data-media-fit={resolvedFit} style={style}>
      {backdrop && !unavailable && <img className="media-fit-backdrop" src={backdrop} alt="" aria-hidden="true" loading="lazy" />}
      {imageLoading && <span className="media-fit-loading" aria-hidden="true" />}
      {unavailable ? <span className="media-fit-unavailable" role={ariaHidden ? undefined : 'img'} aria-label={ariaHidden ? undefined : unavailableLabel} aria-hidden={ariaHidden || undefined}><span aria-hidden="true">▧</span><span>{unavailableLabel}</span></span> : type === 'video' ? (
        <video {...common} ref={videoRef} src={source} onError={renew} onLoadedMetadata={(event) => { if (restoreTime.current) event.currentTarget.currentTime = restoreTime.current; }} onPlay={onVideoPlay} onPause={onVideoPause} poster={getMediaPoster(item) || undefined} controls={controls} autoPlay={autoPlay} muted={muted} playsInline={playsInline} preload={preload} />
      ) : (
        <img {...common} src={source} onLoad={() => setLoadedUrl(source)} onError={renew} alt={ariaHidden ? '' : alt} loading={loading} fetchPriority={fetchPriority} />
      )}
      {children}
    </Frame>
  );
}
