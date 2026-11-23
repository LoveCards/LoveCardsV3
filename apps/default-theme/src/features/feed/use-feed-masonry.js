import { useEffect } from 'react';

export function useFeedMasonry(ref, items) {
  useEffect(() => {
    const element = ref.current;
    if (!element || !items.length) return undefined;
    let active = true;
    let masonry;
    let observer;
    let frame;
    const schedule = (callback) => typeof requestAnimationFrame === 'function' ? requestAnimationFrame(callback) : setTimeout(callback, 0);
    const cancel = (handle) => typeof cancelAnimationFrame === 'function' ? cancelAnimationFrame(handle) : clearTimeout(handle);
    import('masonry-layout').then(({ default: Masonry }) => {
      if (!active) return;
      masonry = new Masonry(element, {
        itemSelector: '.feed-story',
        columnWidth: '.masonry-column-sizer',
        gutter: '.masonry-gutter-sizer',
        percentPosition: true,
        transitionDuration: 0,
      });
      frame = schedule(() => masonry.layout());
      const columnSizer = element.querySelector('.masonry-column-sizer');
      observer = typeof ResizeObserver === 'function' ? new ResizeObserver((entries) => {
        cancel(frame);
        if (entries.some(({ target }) => target === columnSizer)) masonry.layout();
        else frame = schedule(() => masonry.layout());
      }) : null;
      if (columnSizer) observer?.observe(columnSizer);
      element.querySelectorAll('.feed-story').forEach((item) => observer?.observe(item));
    });
    return () => {
      active = false;
      cancel(frame);
      observer?.disconnect();
      masonry?.destroy();
    };
  }, [items, ref]);
}
