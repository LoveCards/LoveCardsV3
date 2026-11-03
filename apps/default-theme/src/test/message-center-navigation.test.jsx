import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { renderRoute } from './renderRoute.jsx';

function globalNavigationOf(html, className) {
  return html.match(new RegExp(`<nav class="${className}"[\\s\\S]*?<\\/nav>`))?.[0] || '';
}

describe('message center navigation', () => {
  beforeEach(() => vi.stubEnv('VITE_DATA_MODE', 'design-preview'));
  afterEach(() => vi.unstubAllEnvs());

  it.each(['/messages', '/inbox', '/inbox/system'])(
    'keeps the global message entry selected on %s while linking to private messages',
    (path) => {
      const html = renderRoute(path);
      const desktop = globalNavigationOf(html, 'desktop-nav');
      const mobile = globalNavigationOf(html, 'mobile-bottom-nav');

      expect(desktop).toMatch(/<a(?=[^>]*aria-current="page")(?=[^>]*href="\/messages")[^>]*>消息/);
      expect(mobile).toMatch(/<a(?=[^>]*aria-current="page")(?=[^>]*href="\/messages")[^>]*>/);
      expect(desktop).not.toContain('href="/inbox"');
      expect(mobile).not.toContain('href="/inbox"');
    },
  );

  it('uses a secondary task shell for message search on mobile', () => {
    const html = renderRoute('/messages/search');
    const source = readFileSync(fileURLToPath(new URL('../pages/community/MessagesPage.jsx', import.meta.url)), 'utf8');
    expect(html).not.toContain('class="mobile-bottom-nav"');
    expect(source).toContain("mobileMode={searchMode ? 'task' : 'standard'}");
    expect(html).toContain('aria-label="返回私信列表"');
  });

  it('uses a secondary task shell for an individual conversation on mobile', () => {
    const html = renderRoute('/messages/1');
    const source = readFileSync(fileURLToPath(new URL('../pages/community/ConversationPage.jsx', import.meta.url)), 'utf8');
    expect(html).not.toContain('class="mobile-bottom-nav"');
    expect(source).toContain('mobileMode="task"');
    expect(source).toContain('initialRecipientId={location.state?.recipientId}');
    expect(source).toContain('key={conversationId}');
    expect(html).toContain('aria-label="会话加载中"');
    expect(html).toContain('aria-label="返回私信列表"');
  });

  it('keeps only engagement and system as canonical notification URLs', () => {
    const source = readFileSync(fileURLToPath(new URL('../app/router.jsx', import.meta.url)), 'utf8');

    expect(source).toContain('<Route path="/inbox" element={<ProtectedRoute><InboxPage /></ProtectedRoute>} />');
    expect(source).toContain('<Route path="/inbox/system" element={<ProtectedRoute><InboxPage /></ProtectedRoute>} />');
    for (const legacyType of ['all', 'engagement', 'message', 'social', 'interaction']) {
      expect(source).toContain(`<Route path="/inbox/${legacyType}" element={<Navigate to="/inbox" replace />} />`);
    }
    expect(source).not.toContain('path="/inbox/:type"');
  });

  it('renders the two canonical notification routes with distinct server categories', () => {
    const engagement = renderRoute('/inbox');
    const system = renderRoute('/inbox/system');

    expect(engagement).toContain('互动消息');
    expect(engagement).toContain('回复了你的评论');
    expect(engagement).not.toContain('账号安全提醒');
    expect(system).toContain('系统通知');
    expect(system).toContain('账号安全提醒');
    expect(system).not.toContain('回复了你的评论');
  });

  it('shows unread counts on each notification category tab', () => {
    const html = renderRoute('/inbox');

    expect(html).toContain('aria-label="互动消息，2 条未读"');
    expect(html).toContain('aria-label="系统通知，1 条未读"');
    expect(html).toContain('class="notification-filter-unread"');
    expect(html).toContain('aria-label="通知，3 条未读"');
    expect(html).toContain('class="message-center-tab-unread"');
  });
});
