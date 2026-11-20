import { useEffect, useRef, useState } from 'react';

export function useGalleryPager(count) {
  const [index, setIndex] = useState(0);
  const touchStart = useRef(null);
  const swipedAt = useRef(0);

  useEffect(() => { setIndex((current) => Math.min(current, Math.max(0, count - 1))); }, [count]);
  const move = (offset) => setIndex((current) => Math.max(0, Math.min(count - 1, current + offset)));
  const onKeyDown = (event) => {
    if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault();
      move(event.key === 'ArrowLeft' ? -1 : 1);
    }
  };
  const onTouchStart = (event) => {
    const touch = event.touches[0];
    swipedAt.current = 0;
    touchStart.current = touch ? { x: touch.clientX, y: touch.clientY } : null;
  };
  const onTouchEnd = (event) => {
    const touch = event.changedTouches[0];
    const start = touchStart.current;
    touchStart.current = null;
    if (!start || !touch) return;
    const dx = touch.clientX - start.x;
    const dy = touch.clientY - start.y;
    if (Math.abs(dx) < 45 || Math.abs(dx) < Math.abs(dy) * 1.3) return;
    swipedAt.current = Date.now();
    move(dx > 0 ? -1 : 1);
  };
  const consumeSwipeClick = (event) => {
    if (!swipedAt.current || Date.now() - swipedAt.current > 500) return false;
    event.preventDefault();
    swipedAt.current = 0;
    return true;
  };

  return { index, move, onKeyDown, onTouchStart, onTouchEnd, consumeSwipeClick };
}
