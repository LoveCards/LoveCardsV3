export const cardPreviewSession = Math.random().toString(36).slice(2);
let detailModulePromise;

export function preloadCardPreviewDetail() {
  detailModulePromise ||= import('../pages/detail/DetailPage.jsx').catch((error) => {
    detailModulePromise = undefined;
    throw error;
  });
  return detailModulePromise;
}

export function warmCardPreviewDetail() {
  void preloadCardPreviewDetail().catch(() => undefined);
}

export function cardPreviewState(location, cardId, kind = 'text', trigger = null) {
  if (!location || !cardId) return undefined;
  return {
    previewSession: cardPreviewSession,
    previewCardId: String(cardId),
    previewKind: ['image', 'gallery', 'video'].includes(kind) ? kind : ['discussion', 'quote', 'link'].includes(kind) ? kind : 'text',
    previewTrigger: trigger,
    sourceScrollY: typeof window === 'undefined' ? 0 : window.scrollY,
    backgroundLocation: {
      pathname: location.pathname,
      search: location.search,
      hash: location.hash,
      state: location.state,
      key: location.key,
    },
  };
}

export function cardReturnSource(location) {
  const state = location?.state;
  const background = state?.backgroundLocation;
  if (state?.previewSession !== cardPreviewSession) return null;
  if (!/^\/post\/[^/]+$/.test(location.pathname)) return null;
  const routeCardId = location.pathname.slice(6);
  if (!/^\d+$/.test(routeCardId) || state?.previewCardId !== routeCardId) return null;
  const path = background?.pathname;
  if (typeof path !== 'string' || !/^\/(?!\/)/.test(path) || path.includes('\\') || path.startsWith('/post/')) return null;
  return background;
}

export function previewBackground(location) {
  return location?.state?.previewMode === 'full' ? null : cardReturnSource(location);
}
