import { useEffect, useState } from 'react';
import { Dialog, Icon, IconButton } from '../../ui/index.js';
import { MediaFrame } from './MediaFrame.jsx';
import { DetailVideoSurface } from './DetailVideoSurface.jsx';
import { getMediaType } from './media-fit.js';
import { normalizeGalleryLayout } from './gallery-layout.js';
import { useGalleryPager } from './useGalleryPager.js';

function ViewerMedia({ item, title, index }) {
  const type = getMediaType(item);
  const alt = item.alt || `${title}，第 ${index + 1} 项媒体`;
  return <MediaFrame className="lc-media-viewer-frame" assetClassName={`lc-media-viewer-media${type === 'gif' ? ' is-gif' : ''}`} item={item} fit="contain" context="viewer" alt={alt} controls />;
}

function detailFrameRatio(gridCount) {
  if (gridCount === 1) return 16 / 10;
  if (gridCount === 2) return 8 / 9;
  if (gridCount === 3) return 1;
  if (gridCount >= 5 && gridCount <= 6) return 2 / 3;
  if (gridCount >= 7) return 1;
  return 4 / 3;
}

function MediaViewerSurface({ items, title, activeIndex, onIndexChange, onClose, closeLabel = '关闭媒体查看器' }) {
  const activeItem = items[activeIndex];
  const hasMultiple = items.length > 1;

  const move = (offset) => {
    if (!hasMultiple) return;
    onIndexChange((activeIndex + offset + items.length) % items.length);
  };

  useEffect(() => {
    if (!activeItem || !hasMultiple) return undefined;
    const handleKeyDown = (event) => {
      const tagName = event.target?.tagName;
      if (event.defaultPrevented || tagName === 'VIDEO' || tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') return;
      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        move(-1);
      } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        move(1);
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [activeIndex, activeItem, hasMultiple, items.length]);

  if (!activeItem) return null;

  return (
    <div className="lc-media-viewer-shell">
      <header className="lc-media-viewer-header">
        <strong>{title}</strong>
        <span aria-live="polite">{activeIndex + 1} / {items.length}</span>
        <IconButton className="lc-media-viewer-close" data-autofocus label={closeLabel} tooltip={closeLabel} variant="ghost" size="lg" icon={<Icon name="close" size={20} />} onClick={onClose} />
      </header>
      <div className="lc-media-viewer-stage">
        <ViewerMedia key={`${activeItem.url}-${activeIndex}`} item={activeItem} title={title} index={activeIndex} />
        {hasMultiple && (
          <>
            <IconButton className="lc-media-viewer-nav lc-media-viewer-previous" label="上一项媒体" tooltip="上一项" variant="ghost" size="lg" icon={<Icon name="arrow" size={22} />} onClick={() => move(-1)} />
            <IconButton className="lc-media-viewer-nav lc-media-viewer-next" label="下一项媒体" tooltip="下一项" variant="ghost" size="lg" icon={<Icon name="arrow" size={22} />} onClick={() => move(1)} />
          </>
        )}
      </div>
    </div>
  );
}

export function MediaViewer({ items, title, activeIndex, onIndexChange, onClose }) {
  const isOpen = activeIndex !== null && activeIndex >= 0 && activeIndex < items.length;

  return (
    <Dialog className="lc-media-viewer" isOpen={isOpen} onOpenChange={(open) => { if (!open) onClose(); }} variant="fullscreen" purpose="info" padding={0} aria-label={`媒体查看器：${title}`}>
      {isOpen && <MediaViewerSurface items={items} title={title} activeIndex={activeIndex} onIndexChange={onIndexChange} onClose={onClose} />}
    </Dialog>
  );
}

export function MediaRouteViewer({ items, title, activeIndex, onIndexChange, onClose }) {
  return (
    <section className="lc-media-route-viewer" aria-label={`媒体查看器：${title}`}>
      <MediaViewerSurface items={items} title={title} activeIndex={activeIndex} onIndexChange={onIndexChange} onClose={onClose} closeLabel="返回内容详情" />
    </section>
  );
}

export function MediaGallery({ items = [], title, galleryLayout = 'grid' }) {
  const mediaItems = items.filter((item) => item && typeof item.url === 'string' && item.url.trim());
  const [activeIndex, setActiveIndex] = useState(null);
  const pager = useGalleryPager(mediaItems.length);
  if (!mediaItems.length) return null;
  if (mediaItems.length === 1 && getMediaType(mediaItems[0]) === 'video') return <DetailVideoSurface item={mediaItems[0]} />;

  const isPager = mediaItems.length > 1 && mediaItems.every((item) => getMediaType(item) !== 'video') && normalizeGalleryLayout(galleryLayout) === 'pager';
  const pageIndex = Math.min(pager.index, mediaItems.length - 1);
  const visibleItems = mediaItems.slice(0, 9);
  const gridCount = visibleItems.length;

  return (
    <>
      {isPager ? <div className="detail-media-grid is-pager" role="group" aria-label={`图片翻页，共 ${mediaItems.length} 张`} onKeyDown={pager.onKeyDown} onTouchStart={pager.onTouchStart} onTouchEnd={pager.onTouchEnd}>
        <button className="detail-media-tile" type="button" onClick={(event) => { if (!pager.consumeSwipeClick(event)) setActiveIndex(pageIndex); }} aria-label={`放大第 ${pageIndex + 1} 张图片，共 ${mediaItems.length} 张`}>
          <MediaFrame key={mediaItems[pageIndex].url} className="detail-media-frame" item={mediaItems[pageIndex]} fit="contain" ariaHidden />
        </button>
        <span className="detail-media-page-count" aria-live="polite">{pageIndex + 1} / {mediaItems.length}</span>
        <button className="detail-media-page-nav is-previous" type="button" aria-label="上一张图片" onClick={() => pager.move(-1)} disabled={pageIndex === 0}>‹</button>
        <button className="detail-media-page-nav is-next" type="button" aria-label="下一张图片" onClick={() => pager.move(1)} disabled={pageIndex === mediaItems.length - 1}>›</button>
      </div> : <div className={`detail-media-grid is-count-${gridCount}${gridCount > 4 ? ' is-many' : ''}`} role="group" aria-label={`媒体，共 ${mediaItems.length} 项`}>
        {visibleItems.map((item, index) => {
          const type = getMediaType(item);
          return (
            <button className="detail-media-tile" type="button" key={`${item.url}-${index}`} onClick={() => setActiveIndex(index)} aria-label={`查看第 ${index + 1} 项媒体，共 ${mediaItems.length} 项`}>
              <MediaFrame className="detail-media-frame" item={item} frameRatio={detailFrameRatio(gridCount)} muted ariaHidden />
              {type === 'video' && <span className="detail-media-type"><Icon name="play" size={14} /> 视频</span>}
              {type === 'gif' && <span className="detail-media-type">GIF</span>}
              {index === 8 && mediaItems.length > 9 && <span className="detail-media-overflow">+{mediaItems.length - 9}</span>}
            </button>
          );
        })}
      </div>}
      <MediaViewer items={mediaItems} title={title} activeIndex={activeIndex} onIndexChange={setActiveIndex} onClose={() => setActiveIndex(null)} />
    </>
  );
}
