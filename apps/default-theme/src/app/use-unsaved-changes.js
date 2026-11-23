import { useEffect } from 'react';

export function useUnsavedChanges(dirty, onNavigationAttempt, { interceptLinks = true } = {}) {
  useEffect(() => {
    if (!dirty) return undefined;

    const beforeUnload = (event) => {
      event.preventDefault();
      event.returnValue = '';
    };

    const interceptLink = (event) => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      const anchor = event.target.closest?.('a[href]');
      if (!anchor || anchor.target || anchor.hasAttribute('download')) return;
      const target = new URL(anchor.href, window.location.href);
      if (target.origin !== window.location.origin) return;
      const destination = `${target.pathname}${target.search}${target.hash}`;
      const current = `${window.location.pathname}${window.location.search}${window.location.hash}`;
      if (destination === current) return;
      event.preventDefault();
      event.stopPropagation();
      onNavigationAttempt(destination);
    };

    window.addEventListener('beforeunload', beforeUnload);
    if (interceptLinks) document.addEventListener('click', interceptLink, true);
    return () => {
      window.removeEventListener('beforeunload', beforeUnload);
      if (interceptLinks) document.removeEventListener('click', interceptLink, true);
    };
  }, [dirty, onNavigationAttempt, interceptLinks]);
}
