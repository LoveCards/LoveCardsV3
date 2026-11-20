import { useCallback, useEffect, useRef, useState } from 'react';

function reduceMotionIsActive() {
  return document.documentElement.dataset.reduceMotion === 'true'
    || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
}

export function useDialogPresence(isOpen, onOpenChange, isBusy = false) {
  const dialogRef = useRef(null);
  const returnFocusRef = useRef(null);
  const wasOpenRef = useRef(false);
  if (isOpen && !wasOpenRef.current && !returnFocusRef.current?.isConnected) returnFocusRef.current = document.activeElement;
  wasOpenRef.current = isOpen;
  const requestedOpenRef = useRef(isOpen);
  requestedOpenRef.current = isOpen;
  const presentedRef = useRef(isOpen);
  const [presented, setPresented] = useState(isOpen);
  presentedRef.current = presented;
  const [phase, setPhase] = useState(isOpen ? 'opening' : 'closed');
  const phaseRef = useRef(phase);
  phaseRef.current = phase;

  const requestOpenChange = useCallback((open) => {
    if (!open && isBusy) return;
    if (!open && !requestedOpenRef.current) return;
    onOpenChange(open);
  }, [isBusy, onOpenChange]);

  useEffect(() => {
    if (isOpen) {
      setPresented(true);
      if (phaseRef.current === 'closing' || reduceMotionIsActive()) {
        setPhase('open');
        return undefined;
      }
      setPhase('opening');
      let secondFrame;
      const firstFrame = requestAnimationFrame(() => {
        secondFrame = requestAnimationFrame(() => {
          if (requestedOpenRef.current) setPhase('open');
        });
      });
      return () => {
        cancelAnimationFrame(firstFrame);
        if (secondFrame) cancelAnimationFrame(secondFrame);
      };
    }
    if (!presentedRef.current) return undefined;
    if (reduceMotionIsActive()) {
      setPresented(false);
      setPhase('closed');
      return undefined;
    }
    setPhase('closing');
    return undefined;
  }, [isOpen]);

  useEffect(() => {
    if (phase !== 'closing') return undefined;
    const dialog = dialogRef.current;
    const finish = () => {
      if (requestedOpenRef.current) return;
      setPresented(false);
      setPhase('closed');
    };
    if (!dialog || !dialog.open || reduceMotionIsActive()) {
      finish();
      return undefined;
    }

    const onTransitionEnd = (event) => {
      if (event.target === dialog && event.propertyName === 'opacity') finish();
    };
    const onTransitionCancel = (event) => {
      if (event.target === dialog && event.propertyName === 'opacity') finish();
    };
    const onVisibilityChange = () => {
      if (document.visibilityState === 'hidden') finish();
    };
    const media = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    const onMotionChange = () => { if (reduceMotionIsActive()) finish(); };
    const observer = new MutationObserver(onMotionChange);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-reduce-motion'] });
    dialog.addEventListener('transitionend', onTransitionEnd);
    dialog.addEventListener('transitioncancel', onTransitionCancel);
    document.addEventListener('visibilitychange', onVisibilityChange);
    media?.addEventListener?.('change', onMotionChange);
    const frame = requestAnimationFrame(() => {
      if (dialog.getAnimations().length === 0) finish();
    });
    return () => {
      cancelAnimationFrame(frame);
      observer.disconnect();
      dialog.removeEventListener('transitionend', onTransitionEnd);
      dialog.removeEventListener('transitioncancel', onTransitionCancel);
      document.removeEventListener('visibilitychange', onVisibilityChange);
      media?.removeEventListener?.('change', onMotionChange);
    };
  }, [phase]);

  useEffect(() => {
    if (isOpen || presented) return undefined;
    const frame = requestAnimationFrame(() => {
      const target = returnFocusRef.current;
      if (target?.isConnected && !target.closest('[inert]') && !document.querySelector('dialog[open]')) {
        target.focus({ preventScroll: true });
      }
      returnFocusRef.current = null;
    });
    return () => cancelAnimationFrame(frame);
  }, [isOpen, presented]);

  return { dialogRef, presented, phase, requestOpenChange };
}
