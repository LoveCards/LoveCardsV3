import { useEffect, useRef, useState } from 'react';

export function gallerySwipeDirection(start, end) {
  if (!start || !end) return 0;
  const dx = end.x - start.x;
  const dy = end.y - start.y;
  if (Math.abs(dx) < 45 || Math.abs(dx) < Math.abs(dy) * 1.3) return 0;
  return dx > 0 ? -1 : 1;
}

export function useGalleryPager(count) {
  const [index, setIndex] = useState(0);
  const touchStart = useRef(null);
  const swipedAt = useRef(0);

  useEffect(() => { if (count > 0) setIndex((current) => Math.min(current, count - 1)); }, [count]);
  const move = (offset) => setIndex((current) => Math.max(0, Math.min(count - 1, current + offset)));
  const onKeyDown = (event) => {
    if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault();
      move(event.key === 'ArrowLeft' ? -1 : 1);
    }
  };
  const onTouchStart = (event) => {
    swipedAt.current = 0;
    const touch = event.touches.length === 1 ? event.touches[0] : null;
    touchStart.current = touch ? { id: touch.identifier, x: touch.clientX, y: touch.clientY } : null;
  };
  const onTouchMove = (event) => {
    if (event.touches.length !== 1) touchStart.current = null;
  };
  const onTouchEnd = (event) => {
    const start = touchStart.current;
    touchStart.current = null;
    if (event.touches.length || event.changedTouches.length !== 1) return;
    const touch = event.changedTouches[0];
    if (touch?.identifier !== start?.id) return;
    if (!start || !touch) return;
    const direction = gallerySwipeDirection(start, { x: touch.clientX, y: touch.clientY });
    if (!direction) return;
    swipedAt.current = Date.now();
    move(direction);
  };
  const onTouchCancel = () => { touchStart.current = null; swipedAt.current = 0; };
  const consumeSwipeClick = (event) => {
    if (!swipedAt.current || Date.now() - swipedAt.current > 500) return false;
    event.preventDefault();
    swipedAt.current = 0;
    return true;
  };

  return { index, move, onKeyDown, onTouchStart, onTouchMove, onTouchEnd, onTouchCancel, consumeSwipeClick };
}
