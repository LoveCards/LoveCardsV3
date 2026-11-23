import { Navigate, useLocation } from 'react-router-dom';
import { getRuntimeDataMode } from './runtime-context.js';
import { useAuth } from './providers.jsx';
import { RouteBoundary } from './RouteBoundary.jsx';

export function safeReturnPath(value) {
  if (typeof value !== 'string') return null;
  const path = value.trim();
  return /^\/(?!\/)/.test(path) ? path : null;
}

export function currentLocationPath(location) {
  const pathname = typeof location?.pathname === 'string' ? location.pathname : '/';
  const search = typeof location?.search === 'string' ? location.search : '';
  const hash = typeof location?.hash === 'string' ? location.hash : '';
  return `${pathname}${search}${hash}`;
}

export function canAccessProtectedRoute(auth, allowVisitor = false) {
  return auth.isAuthenticated || (allowVisitor && auth.isVisitor);
}

export function ProtectedRoute({ children, allowVisitor = false }) {
  const auth = useAuth();
  const location = useLocation();
  const designPreview = getRuntimeDataMode() === 'design-preview';
  // The Vite app is client-rendered; static markup is only used by the
  // route/a11y smoke tests and must keep rendering the actual page shell.
  if (designPreview || typeof window === 'undefined') return children;
  if (auth.status === 'checking') return <RouteBoundary checkingAuth />;
  if (auth.status === 'error') return <RouteBoundary checkingAuth error={auth.error} onRetry={auth.refresh} />;
  if (!canAccessProtectedRoute(auth, allowVisitor)) {
    const from = currentLocationPath(location);
    return <Navigate to="/auth/login" replace state={{ from }} />;
  }
  return children;
}

export function canonicalHomeFeedPath(location) {
  const params = new URLSearchParams(location.search);
  if (!params.has('feed') && !params.has('channel')) return null;
  const following = params.get('feed') === 'following' || params.get('channel') === 'following';
  const latest = params.get('channel') === 'latest';
  params.delete('feed');
  params.delete('channel');
  const path = following ? '/activity' : latest ? '/explore' : '/';
  const query = params.toString();
  return `${path}${query ? `?${query}` : ''}${location.hash || ''}`;
}

export function canonicalLegacyFeedPath(location, to) {
  const params = new URLSearchParams(location.search);
  params.delete('feed');
  params.delete('channel');
  const query = params.toString();
  return `${to}${query ? `?${query}` : ''}${location.hash || ''}`;
}

export function LegacyFeedRoute({ to }) {
  const location = useLocation();
  return <Navigate to={canonicalLegacyFeedPath(location, to)} replace state={location.state} />;
}

export function HomeFeedRoute({ children }) {
  const location = useLocation();
  const canonicalPath = canonicalHomeFeedPath(location);
  return canonicalPath ? <Navigate to={canonicalPath} replace state={location.state} /> : children;
}
