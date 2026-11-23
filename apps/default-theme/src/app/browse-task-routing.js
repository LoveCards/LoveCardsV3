const session = Math.random().toString(36).slice(2);

export function browseTaskState(location, index = globalThis.history?.state?.idx) {
  return {
    browseTaskSource: { session, index, pathname: location.pathname, search: location.search, hash: location.hash },
  };
}

export function browseTaskBack(state, kind, index = globalThis.history?.state?.idx) {
  const fallback = kind === 'topic'
    ? { to: '/explore', label: '返回发现' }
    : { to: '/', label: '返回首页' };
  const source = state?.browseTaskSource;
  if (source?.session !== session || !Number.isInteger(source.index) || !Number.isInteger(index) || source.index < 0 || source.index >= index) return fallback;
  if (typeof source.pathname !== 'string' || !/^\/(?!\/)/.test(source.pathname) || source.pathname.includes('\\')) return fallback;
  return {
    to: { pathname: source.pathname, search: source.search, hash: source.hash },
    label: '返回来源页',
    delta: source.index - index,
  };
}
