import { useCallback, useEffect, useRef, useState } from 'react';
import { MediaFrame } from './MediaFrame.jsx';

let activeDetailVideo = null;

export function DetailVideoSurface({ item }) {
  const videoRef = useRef(null);
  const mountedRef = useRef(false);
  const playRequestRef = useRef(0);
  const [playBlocked, setPlayBlocked] = useState(false);

  const play = useCallback(() => {
    const video = videoRef.current;
    if (!video) return;
    video.muted = true;
    if (activeDetailVideo && activeDetailVideo !== video) activeDetailVideo.pause();
    const request = ++playRequestRef.current;
    try {
      const result = video.play();
      if (result && typeof result.then === 'function') {
        result.then(() => {
          if (mountedRef.current && request === playRequestRef.current) setPlayBlocked(false);
        }).catch(() => {
          if (mountedRef.current && request === playRequestRef.current) setPlayBlocked(true);
        });
      }
    } catch {
      if (mountedRef.current && request === playRequestRef.current) setPlayBlocked(true);
    }
  }, []);

  useEffect(() => {
    const video = videoRef.current;
    if (!video) return undefined;
    mountedRef.current = true;
    play();
    const pauseWhenHidden = () => { if (document.hidden) video.pause(); };
    document.addEventListener('visibilitychange', pauseWhenHidden);
    const dialog = video.closest('dialog.lc-card-preview');
    const observer = dialog && new MutationObserver(() => {
      if (dialog.dataset.lcPreviewPhase === 'closing') video.pause();
    });
    observer?.observe(dialog, { attributes: true, attributeFilter: ['data-lc-preview-phase'] });
    return () => {
      mountedRef.current = false;
      playRequestRef.current += 1;
      observer?.disconnect();
      document.removeEventListener('visibilitychange', pauseWhenHidden);
      video.pause();
      if (activeDetailVideo === video) activeDetailVideo = null;
    };
  }, [item.url, play]);

  const handlePlay = () => {
    const video = videoRef.current;
    if (!video) return;
    if (activeDetailVideo && activeDetailVideo !== video) activeDetailVideo.pause();
    activeDetailVideo = video;
    setPlayBlocked(false);
  };

  return <div className="detail-media-grid is-count-1 is-video" role="group" aria-label="视频">
    <div className="detail-video-surface">
      <MediaFrame as="div" className="detail-video-frame" item={item} fit="contain" frameRatio={16 / 10} context="viewer" controls autoPlay muted preload="metadata" videoRef={videoRef} onVideoPlay={handlePlay} onVideoPause={() => { if (activeDetailVideo === videoRef.current) activeDetailVideo = null; }} />
      {playBlocked && <button className="detail-video-play" type="button" onClick={play}>播放视频</button>}
    </div>
  </div>;
}
