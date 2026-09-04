import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { NotificationItem, NotificationListSkeleton } from '../pages/community/NotificationItem.jsx';

const FIXED_TIME = '2026-08-21T08:30:00.000Z';

function render(element) {
  return renderToStaticMarkup(<MemoryRouter>{element}</MemoryRouter>);
}

function notification(overrides = {}) {
  return {
    id: 26,
    type: 'content.card_liked',
    category: 'interaction',
    actor_id: 8,
    actor: { id: 8, username: 'forest', avatar: '/avatars/forest.webp' },
    actor_state: 'active',
    resource_type: 'card',
    resource_id: '42',
    resource_state: 'available',
    action_url: '/post/42',
    payload: { card_id: 42, message: '喜欢了《夏日海边》' },
    read_at: null,
    created_at: FIXED_TIME,
    ...overrides,
  };
}

describe('notification item', () => {
  it('links an active actor and a safe resource as separate interaction paths', () => {
    const html = render(<NotificationItem item={notification()} onRead={vi.fn()} />);

    expect(html).toContain('href="/user/forest"');
    expect(html).toContain('href="/post/42"');
    expect(html).toContain('aria-label="未读通知，查看：喜欢了你的内容"');
    expect(html).toContain('notification-unread-dot" aria-hidden="true"');
    expect(html.match(/<a\b/g)).toHaveLength(2);
    expect(html).toContain('src="/avatars/forest.webp"');
    expect(html).toContain('喜欢了你的内容');
    expect(html).toContain('喜欢了《夏日海边》');

    const actorLinkStart = html.indexOf('href="/user/forest"');
    const actorLinkEnd = html.indexOf('</a>', actorLinkStart);
    expect(html.slice(actorLinkStart, actorLinkEnd)).not.toContain('href="/post/42"');
  });

  it('keeps an unavailable actor non-interactive', () => {
    const html = render(<NotificationItem item={notification({
      actor: null,
      actor_id: null,
      actor_state: 'unavailable',
    })} />);

    expect(html).toContain('该用户已不可用');
    expect(html).not.toContain('/user/');
    expect(html).toContain('href="/post/42"');
  });

  it('renders a system identity and severity without an ordinary actor profile', () => {
    const html = render(<NotificationItem item={notification({
      type: 'system.security',
      category: 'system',
      actor: { id: 8, username: 'ignored-user', avatar: '/ignored.webp' },
      actor_state: 'active',
      resource_type: null,
      resource_id: null,
      resource_state: 'unavailable',
      action_url: null,
      payload: { severity: 'warning', message: '发现新的登录设备' },
    })} />);

    expect(html).toContain('notification-system-identity is-warning');
    expect(html).toContain('severity-warning');
    expect(html).toContain('LoveCards');
    expect(html).toContain('系统通知 · 重要');
    expect(html).toContain('发现新的登录设备');
    expect(html).not.toContain('/user/ignored-user');
    expect(html).not.toContain('author-identity');
  });

  it('does not guess a resource route and shows the unavailable placeholder', () => {
    const deleted = render(<NotificationItem item={notification({
      resource_state: 'deleted',
      action_url: '/post/42',
    })} />);
    const unsafe = render(<NotificationItem item={notification({
      resource_state: 'available',
      action_url: '//example.test/post/42',
    })} />);

    expect(deleted).toContain('内容已删除');
    expect(deleted).not.toContain('href="/post/42"');
    expect(unsafe).toContain('当前不可访问');
    expect(unsafe).not.toContain('href="//example.test/post/42"');
  });

  it('marks unread state and disables the read control while busy', () => {
    const unavailableResource = {
      resource_type: null,
      resource_id: null,
      resource_state: 'unavailable',
      action_url: null,
    };
    const unread = render(<NotificationItem item={notification(unavailableResource)} busy />);
    const read = render(<NotificationItem item={notification({ ...unavailableResource, read_at: FIXED_TIME })} />);

    expect(unread).toContain('notification-item category-interaction is-unread');
    expect(unread).toContain('notification-unread-dot" aria-hidden="true"');
    expect(unread).toContain('aria-label="标记已读：喜欢了你的内容"');
    expect(unread).toContain('<span class="sr-only">未读通知：</span>');
    expect(unread).toContain('disabled=""');
    expect(read).not.toContain(' is-unread');
    expect(read).toContain('<span class="sr-only">已读通知：</span>');
    expect(read).toContain('notification-copy is-static');
    expect(read).not.toContain('<button');
  });

  it('renders a matching status skeleton with the requested row count', () => {
    const html = render(<NotificationListSkeleton count={3} />);

    expect(html).toContain('role="status"');
    expect(html).toContain('aria-label="通知加载中"');
    expect(html).toContain('notification-skeleton-heading" aria-hidden="true"');
    expect(html).toContain('notification-skeleton" aria-hidden="true"');
    expect(html.match(/notification-skeleton-row/g)).toHaveLength(3);
    expect(html).not.toContain('notification-skeleton-row" aria-hidden="true"');
  });
});
