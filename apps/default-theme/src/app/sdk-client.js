import { createClient } from '@lovecards/sdk';
import { getThemeRuntimeContext } from './runtime-context.js';

let client;
export function resolveApiUrl() {
  return getThemeRuntimeContext()?.apiUrl || import.meta.env.VITE_API_URL?.trim() || '';
}

export function getSdkClient() {
  const apiUrl = resolveApiUrl();
  if (!apiUrl) return null;
  if (!client) client = createClient({
    apiUrl,
    onAuthError: () => {
      client?.clearToken();
      window.dispatchEvent(new CustomEvent('lc-auth-error'));
      window.dispatchEvent(new CustomEvent('lc-auth-changed'));
    },
  });
  return client;
}
export function sdkConfigured() { return Boolean(resolveApiUrl()); }
