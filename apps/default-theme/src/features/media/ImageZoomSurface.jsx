import { useRef, useState } from 'react';
import { MediaFrame } from './MediaFrame.jsx';
import { getMediaType } from './media-fit.js';

export function clampImageScale(value) {
  return Math.max(1, Math.min(3, value));
}

function touchDistance(touches) {
  const dx = touches[0].clientX - touches[1].clientX;
  const dy = touches[0].clientY - touches[1].clientY;
  return Math.hypot(dx, dy);
}

export function ImageZoomSurface({ item, alt }) {
  const viewport = useRef(null);
  const tapStart = useRef(null);
  const lastTap = useRef(null);
  const pinch = useRef(null);
  const drag = useRef(null);
  const touchZoomAt = useRef(0);
  const [scale, setScale] = useState(1);
  const [offset, setOffset] = useState({ x: 0, y: 0 });

  const boundedOffset = (next, atScale = scale) => {
    const width = viewport.current?.clientWidth || 0;
    const height = viewport.current?.clientHeight || 0;
    return {
      x: Math.max(-width * (atScale - 1) / 2, Math.min(width * (atScale - 1) / 2, next.x)),
      y: Math.max(-height * (atScale - 1) / 2, Math.min(height * (atScale - 1) / 2, next.y)),
    };
  };
  const setZoom = (next) => {
    const value = clampImageScale(next);
    setScale(value);
    setOffset((current) => value === 1 ? { x: 0, y: 0 } : boundedOffset(current, value));
  };
  const toggleZoom = () => setZoom(scale > 1 ? 1 : 2);

  const onTouchStart = (event) => {
    if (event.touches.length === 2) {
      pinch.current = { distance: touchDistance(event.touches), scale };
      tapStart.current = null;
      lastTap.current = null;
      drag.current = null;
    } else if (event.touches.length === 1 && !pinch.current) {
      const touch = event.touches[0];
      tapStart.current = { x: touch.clientX, y: touch.clientY, at: Date.now() };
      drag.current = scale > 1 ? { x: touch.clientX, y: touch.clientY, offset } : null;
    } else {
      tapStart.current = null;
      drag.current = null;
    }
  };
  const onTouchMove = (event) => {
    if (event.touches.length === 2 && pinch.current?.distance) {
      setZoom(pinch.current.scale * touchDistance(event.touches) / pinch.current.distance);
      return;
    }
    if (event.touches.length !== 1 || pinch.current) {
      tapStart.current = null;
      return;
    }
    const touch = event.touches[0];
    if (tapStart.current && Math.hypot(touch.clientX - tapStart.current.x, touch.clientY - tapStart.current.y) > 12) tapStart.current = null;
    if (drag.current && scale > 1) setOffset(boundedOffset({ x: drag.current.offset.x + touch.clientX - drag.current.x, y: drag.current.offset.y + touch.clientY - drag.current.y }));
  };
  const onTouchEnd = (event) => {
    if (pinch.current) {
      if (event.touches.length < 2) pinch.current = null;
      tapStart.current = null;
      drag.current = null;
      return;
    }
    const touch = event.changedTouches.length === 1 && event.touches.length === 0 ? event.changedTouches[0] : null;
    const start = tapStart.current;
    tapStart.current = null;
    drag.current = null;
    if (!touch || !start || Date.now() - start.at > 250) return;
    const point = { x: touch.clientX, y: touch.clientY, at: Date.now() };
    if (lastTap.current && point.at - lastTap.current.at < 320 && Math.hypot(point.x - lastTap.current.x, point.y - lastTap.current.y) < 32) {
      toggleZoom();
      touchZoomAt.current = Date.now();
      lastTap.current = null;
    } else lastTap.current = point;
  };
  const onTouchCancel = () => { pinch.current = null; tapStart.current = null; lastTap.current = null; drag.current = null; };
  const onPointerDown = (event) => {
    if (event.pointerType !== 'mouse' || scale <= 1 || event.button !== 0) return;
    drag.current = { x: event.clientX, y: event.clientY, offset };
    event.currentTarget.setPointerCapture?.(event.pointerId);
  };
  const onPointerMove = (event) => {
    if (event.pointerType !== 'mouse' || !drag.current || scale <= 1) return;
    setOffset(boundedOffset({ x: drag.current.offset.x + event.clientX - drag.current.x, y: drag.current.offset.y + event.clientY - drag.current.y }));
  };
  const stopDrag = () => { drag.current = null; };
  const onKeyDown = (event) => {
    if (event.key === '0' && scale > 1) { event.preventDefault(); setZoom(1); }
    if ((event.key === '+' || event.key === '=') && scale < 3) { event.preventDefault(); setZoom(scale + 0.5); }
  };

  return <div ref={viewport} className="lc-media-image-viewport" role="group" aria-label="图片缩放" data-zoomed={scale > 1 || undefined} tabIndex={0} onDoubleClick={() => { if (Date.now() - touchZoomAt.current > 500) toggleZoom(); }} onTouchStart={onTouchStart} onTouchMove={onTouchMove} onTouchEnd={onTouchEnd} onTouchCancel={onTouchCancel} onPointerDown={onPointerDown} onPointerMove={onPointerMove} onPointerUp={stopDrag} onPointerCancel={stopDrag} onKeyDown={onKeyDown}>
    <div className="lc-media-image-transform" style={{ transform: `translate(${offset.x}px, ${offset.y}px) scale(${scale})` }}>
      <MediaFrame className="lc-media-viewer-frame" assetClassName={`lc-media-viewer-media${getMediaType(item) === 'gif' ? ' is-gif' : ''}`} item={item} fit="contain" context="viewer" alt={alt} />
    </div>
    <button className="lc-media-zoom-reset" type="button" aria-label={scale > 1 ? '还原图片比例' : '放大图片'} onClick={(event) => { event.stopPropagation(); toggleZoom(); }}>{scale > 1 ? '还原比例' : '放大'}</button>
    <span className="sr-only" aria-live="polite">图片比例 {Math.round(scale * 100)}%</span>
  </div>;
}
