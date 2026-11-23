export const composeModalSession = Math.random().toString(36).slice(2);

export function composeModalState(location) {
  return {
    composeModalSession,
    backgroundLocation: {
      pathname: location.pathname,
      search: location.search,
      hash: location.hash,
      state: location.state,
      key: location.key,
    },
  };
}

export function composeModalBackground(location) {
  if (location?.pathname !== '/compose' || location.state?.composeModalSession !== composeModalSession) return null;
  const background = location.state.backgroundLocation;
  if (!background?.pathname?.startsWith('/') || background.pathname.startsWith('//') || background.pathname.startsWith('/compose')) return null;
  return background;
}
