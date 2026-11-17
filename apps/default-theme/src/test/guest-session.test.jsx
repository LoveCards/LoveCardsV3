import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { AppProviders } from '../app/providers.jsx';
import { canAccessProtectedRoute } from '../app/auth-routing.jsx';
import { endCurrentSession } from '../app/end-current-session.js';
import { GuestMePage } from '../pages/profile/GuestMePage.jsx';

describe('guest session navigation', () => {
  it('allows visitors only through explicitly permitted protected routes', () => {
    const visitor = { isAuthenticated: false, isVisitor: true };
    expect(canAccessProtectedRoute(visitor)).toBe(false);
    expect(canAccessProtectedRoute(visitor, true)).toBe(true);
    expect(canAccessProtectedRoute({ isAuthenticated: false, isVisitor: false }, true)).toBe(false);
    expect(canAccessProtectedRoute({ isAuthenticated: true, isVisitor: false })).toBe(true);
  });

  it('renders the guest account surface without private profile controls', () => {
    const html = renderToStaticMarkup(<AppProviders><MemoryRouter initialEntries={['/me']}><GuestMePage /></MemoryRouter></AppProviders>);
    expect(html).toContain('游客体验');
    expect(html).toContain('结束游客会话');
    expect(html).toContain('class="mobile-bottom-nav"');
    expect(html).toContain('href="/settings"');
    expect(html).not.toContain('profile-tabs');
    expect(html).not.toContain('获赞');
  });

  it('replaces the guest route after confirmed logout', async () => {
    const logout = vi.fn().mockResolvedValue(undefined);
    const navigate = vi.fn();
    await endCurrentSession(logout, navigate, '/');
    expect(logout).toHaveBeenCalledOnce();
    expect(navigate).toHaveBeenCalledWith('/', { replace: true, state: null });
  });

  it('preserves an honest session notice after remote logout failure', async () => {
    const logout = vi.fn().mockRejectedValue(new Error('offline'));
    const navigate = vi.fn();
    await endCurrentSession(logout, navigate, '/');
    expect(navigate).toHaveBeenNthCalledWith(1, '/', { replace: true, state: null });
    expect(navigate).toHaveBeenCalledWith('/', { replace: true, state: { logoutUnconfirmed: true } });
  });
});
