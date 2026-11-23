import { lazy, Suspense, useCallback, useLayoutEffect, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { Dialog } from '@astryxdesign/core/Dialog';
import { ToastViewport } from '@astryxdesign/core/Toast';
import { preloadCardPreviewDetail } from '../../app/card-preview-routing.js';
import { Icon } from '../../ui/icons.jsx';
import { toastConfig } from '../../app/toast-config.js';

const DetailPage = lazy(() => preloadCardPreviewDetail().then((module) => ({ default: module.DetailPage })));

function PreviewLoading({ presentation }) {
  return <div className={`lc-card-preview-loading lc-card-preview-loading-${presentation}`} role="status" aria-label="内容加载中">
    {presentation === 'media' && <div className="lc-card-preview-loading-media" aria-hidden="true" />}
    <div className="lc-card-preview-loading-copy" aria-hidden="true">
      <i className="lc-card-preview-loading-avatar" /><i /><i /><i /><i />
    </div>
    <span className="lc-card-preview-loading-label">内容加载中…</span>
  </div>;
}

export function CardPreviewSurface() {
  const location = useLocation();
  const navigate = useNavigate();
  const dialogRef = useRef(null);
  const titleRef = useRef(null);
  const closingRef = useRef(false);
  const [closing, setClosing] = useState(false);
  const kind = location.state?.previewKind || 'text';
  const presentation = ['image', 'gallery', 'video'].includes(kind) ? 'media' : 'text';
  const close = useCallback(() => {
    if (closingRef.current) return;
    const dialog = dialogRef.current;
    const reduced = document.documentElement.dataset.reduceMotion === 'true' || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const returnToSource = () => {
      const background = location.state?.backgroundLocation;
      if (window.history.state?.idx > 0) {
        navigate(-1);
      } else {
        navigate(background ? `${background.pathname}${background.search || ''}${background.hash || ''}` : '/', { replace: true });
      }
    };
    closingRef.current = true;
    if (!dialog || reduced) { returnToSource(); return; }
    setClosing(true);
    requestAnimationFrame(() => {
      const exit = dialog.getAnimations().find((animation) => animation.effect?.target === dialog);
      if (exit) void exit.finished.catch(() => undefined).then(() => { if (closingRef.current) returnToSource(); });
      else returnToSource();
    });
  }, [location.state, navigate]);
  const openFull = useCallback(() => {
    navigate(location.pathname, { replace: true, state: { ...location.state, previewMode: 'full' } });
  }, [location, navigate]);

  useLayoutEffect(() => {
    const frame = requestAnimationFrame(() => {
      const dialog = dialogRef.current;
      if (!dialog?.open || closingRef.current || !dialog.contains(document.activeElement)) return;
      const active = document.activeElement;
      if (active === dialog || active?.closest('.lc-card-preview-header-actions')) {
        titleRef.current?.focus({ preventScroll: true });
      }
    });
    return () => cancelAnimationFrame(frame);
  }, []);

  useLayoutEffect(() => {
    const background = location.state?.backgroundLocation;
    const id = location.state?.previewCardId;
    const trigger = location.state?.previewTrigger;
    if (!background) return undefined;
    return () => {
      requestAnimationFrame(() => {
        const returnPath = `${background.pathname}${background.search || ''}${background.hash || ''}`;
        const currentPath = `${window.location.pathname}${window.location.search}${window.location.hash}`;
        if (currentPath !== returnPath) return;
        const source = id && trigger
          ? document.querySelector(`[data-card-preview-id="${CSS.escape(id)}"][data-card-preview-trigger="${CSS.escape(trigger)}"]`)
          : null;
        const fallback = document.querySelector('main h1');
        const target = source?.isConnected ? source : fallback;
        if (fallback && target === fallback && !fallback.hasAttribute('tabindex')) fallback.setAttribute('tabindex', '-1');
        target?.focus({ preventScroll: true });
      });
    };
  }, [location.state]);

  useLayoutEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;
    const id = location.state?.previewCardId;
    const trigger = location.state?.previewTrigger;
    const source = id && trigger ? document.querySelector(`[data-card-preview-id="${CSS.escape(id)}"][data-card-preview-trigger="${CSS.escape(trigger)}"]`) : null;
    if (!source) return;
    const sourceRect = source.getBoundingClientRect();
    const targetRect = dialog.getBoundingClientRect();
    const visible = sourceRect.width > 0 && sourceRect.height > 0 && sourceRect.bottom > 0 && sourceRect.top < innerHeight && sourceRect.right > 0 && sourceRect.left < innerWidth;
    if (!visible || !targetRect.width || !targetRect.height) return;
    const x = sourceRect.left + sourceRect.width / 2 - targetRect.left - targetRect.width / 2;
    const y = sourceRect.top + sourceRect.height / 2 - targetRect.top - targetRect.height / 2;
    dialog.style.setProperty('--lc-preview-origin-x', `${Math.round(x)}px`);
    dialog.style.setProperty('--lc-preview-origin-y', `${Math.round(y)}px`);
    dialog.dataset.hasOrigin = 'true';
  }, [location.state]);

  return <Dialog ref={dialogRef} className={`lc-card-preview lc-card-preview-${presentation} lc-card-preview-${kind}`} data-lc-preview-phase={closing ? 'closing' : 'open'} aria-label="内容预览" isOpen onOpenChange={(open) => { if (!open) close(); }} width={presentation === 'media' ? 'min(1080px, calc(100vw - 48px))' : 'min(760px, calc(100vw - 48px))'} maxHeight="min(92dvh, 940px)" padding={0}>
    <ToastViewport {...toastConfig} isTopLayer={false}>
      <div className="lc-card-preview-shell">
        <header className="lc-card-preview-header">
          <h2 ref={titleRef} tabIndex={-1}>{presentation === 'media' ? kind === 'video' ? '视频' : '图文' : kind === 'discussion' ? '讨论' : '随笔'}</h2>
          <div className="lc-card-preview-header-actions">
            <button type="button" onClick={openFull}>查看完整详情</button>
            <button type="button" onClick={close} aria-label="关闭内容预览" disabled={closing}><Icon name="close" size={19} /></button>
          </div>
        </header>
        <Suspense fallback={<PreviewLoading presentation={presentation} />}><DetailPage preview loadingFallback={<PreviewLoading presentation={presentation} />} /></Suspense>
      </div>
    </ToastViewport>
  </Dialog>;
}
