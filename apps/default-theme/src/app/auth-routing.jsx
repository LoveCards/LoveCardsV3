import { Navigate, useLocation } from 'react-router-dom';
import { getRuntimeDataMode } from './runtime-context.js';
import { useAuth } from './providers.jsx';
import { ResourceState } from '../components/ResourceState.jsx';

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

export function ProtectedRoute({ children }) {
  const auth = useAuth();
  const location = useLocation();
  const designPreview = getRuntimeDataMode() === 'design-preview';
  // The Vite app is client-rendered; static markup is only used by the
  // route/a11y smoke tests and must keep rendering the actual page shell.
  if (designPreview || typeof window === 'undefined') return children;
  if (auth.status === 'checking') return <main className="auth-route-state" aria-label="正在检查登录状态"><ResourceState status="loading" loadingLabel="正在检查登录状态" /></main>;
  if (auth.status === 'error') return <main className="auth-route-state" aria-label="登录状态检查失败"><ResourceState status="error" error={auth.error} onRetry={auth.refresh} /></main>;
  if (!auth.isAuthenticated) {
    const from = currentLocationPath(location);
    return <Navigate to="/auth/login" replace state={{ from }} />;
  }
  return children;
}
