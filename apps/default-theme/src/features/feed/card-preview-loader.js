let detailModulePromise;

export function preloadCardPreviewDetail() {
  detailModulePromise ||= import('../../pages/detail/DetailPage.jsx').catch((error) => {
    detailModulePromise = undefined;
    throw error;
  });
  return detailModulePromise;
}

export function warmCardPreviewDetail() {
  void preloadCardPreviewDetail().catch(() => undefined);
}
