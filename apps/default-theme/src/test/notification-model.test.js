import { describe, expect, it } from 'vitest';
import {
  NOTIFICATION_CATEGORIES,
  NOTIFICATION_TYPES,
  groupNotifications,
  normalizeNotificationItem,
  notificationDateGroup,
  notificationListParams,
  notificationMatchesCategory,
  notificationPresentation,
  safeNotificationActionUrl,
} from '../pages/community/notification-model.js';

const EXPECTED_TYPES = {
  'social.followed': 'social',
  message: 'message',
  'content.card_liked': 'interaction',
  'content.comment_liked': 'interaction',
  'library.saved': 'interaction',
  'content.commented': 'interaction',
  'content.replied': 'interaction',
  'content.mentioned': 'interaction',
  'system.notice': 'system',
  'system.security': 'system',
  'system.moderation': 'system',
};

describe('notification model', () => {
  it('covers the frozen notification types and category filters', () => {
    expect([...NOTIFICATION_TYPES]).toEqual(Object.keys(EXPECTED_TYPES));
    expect(NOTIFICATION_CATEGORIES.map(({ value }) => value)).toEqual([
      'engagement',
      'system',
    ]);
    expect(NOTIFICATION_CATEGORIES).toEqual([
      { value: 'engagement', label: '互动消息', to: '/inbox' },
      { value: 'system', label: '系统通知', to: '/inbox/system' },
    ]);

    for (const [type, category] of Object.entries(EXPECTED_TYPES)) {
      expect(notificationPresentation(type)).toMatchObject({ category });
      expect(normalizeNotificationItem({ type, category }).knownType).toBe(true);
      expect(notificationMatchesCategory({ type, category }, category === 'system' ? 'system' : 'engagement')).toBe(true);
    }
  });

  it('passes the two server list categories and opaque cursors without interpreting them', () => {
    expect(notificationListParams('engagement')).toEqual({ cursor: undefined, limit: 20, category: 'engagement' });
    expect(notificationListParams('system', 'opaque:created=2026-08-22T10%3A00%3A00Z&id=91')).toEqual({
      cursor: 'opaque:created=2026-08-22T10%3A00%3A00Z&id=91',
      limit: 20,
      category: 'system',
    });
  });

  it('keeps real item categories while grouping all user-driven projections as engagement', () => {
    for (const category of ['interaction', 'social', 'message']) {
      const item = normalizeNotificationItem({ type: category === 'social' ? 'social.followed' : category === 'message' ? 'message' : 'content.commented', category });
      expect(item.category).toBe(category);
      expect(notificationMatchesCategory(item, 'engagement')).toBe(true);
      expect(notificationMatchesCategory(item, 'system')).toBe(false);
    }
  });

  it.each([
    ['/post/26', '/post/26'],
    ['/post/26/thread/4?from=notification#comment-4', '/post/26/thread/4?from=notification#comment-4'],
    [' /messages/8 ', '/messages/8'],
    ['https://example.test/post/26', null],
    ['//example.test/post/26', null],
    ['post/26', null],
    ['/post\\26', null],
    ['/post/26\nredirect', null],
    ['', null],
    [null, null],
  ])('accepts only safe internal action URLs: %s', (input, expected) => {
    expect(safeNotificationActionUrl(input)).toBe(expected);
  });

  it('only exposes an action for an available resource with a safe URL', () => {
    expect(normalizeNotificationItem({ resource_state: 'available', action_url: '/post/8' })).toMatchObject({
      actionUrl: '/post/8',
      resourceStateLabel: '',
    });
    expect(normalizeNotificationItem({ resource_state: 'deleted', action_url: '/post/8' })).toMatchObject({
      actionUrl: null,
      resourceStateLabel: '内容已删除',
    });
    expect(normalizeNotificationItem({ resource_state: 'restricted', action_url: '/post/8' })).toMatchObject({
      actionUrl: null,
      resourceStateLabel: '当前不可访问',
    });
    expect(normalizeNotificationItem({ resource_state: 'available', resource_type: 'card', action_url: '//unsafe.test' })).toMatchObject({
      actionUrl: null,
      resourceStateLabel: '当前不可访问',
    });
  });

  it('downgrades invalid actor and severity values conservatively', () => {
    expect(normalizeNotificationItem({
      type: 'content.commented',
      actor_state: 'active',
      actor: { id: 7, username: '  ', avatar: null },
      payload: { severity: 'urgent' },
    })).toMatchObject({ actorState: 'unavailable', severity: 'info', severityLabel: '信息' });

    expect(normalizeNotificationItem({
      type: 'system.security',
      actor_state: 'active',
      actor: { id: 7, username: 'someone' },
      payload: { severity: 'critical' },
    })).toMatchObject({ actorState: 'system', category: 'system', severity: 'critical', severityLabel: '紧急' });

    expect(normalizeNotificationItem({
      type: 'content.card_liked',
      actor_state: 'active',
      actor: { id: 7, username: 'forest', avatar: null },
      payload: { severity: 'success' },
    })).toMatchObject({ actorState: 'active', category: 'interaction', severity: 'success' });

    expect(normalizeNotificationItem({
      type: 'content.card_liked',
      actor: { id: 7, username: 'forest', avatar: null },
    })).toMatchObject({ actorState: 'unavailable' });
  });

  it('groups fixed dates into today, yesterday, and earlier in display order', () => {
    const now = new Date(2026, 7, 21, 12, 0, 0);
    const today = new Date(2026, 7, 21, 8, 0, 0).toISOString();
    const yesterday = new Date(2026, 7, 20, 23, 0, 0).toISOString();
    const earlier = new Date(2026, 7, 18, 12, 0, 0).toISOString();

    expect(notificationDateGroup(today, now)).toBe('今天');
    expect(notificationDateGroup(yesterday, now)).toBe('昨天');
    expect(notificationDateGroup(earlier, now)).toBe('更早');
    expect(notificationDateGroup('not-a-date', now)).toBe('更早');

    const items = [
      { id: 3, created_at: earlier },
      { id: 1, created_at: today },
      { id: 2, created_at: yesterday },
    ];
    expect(groupNotifications(items, now)).toEqual([
      { label: '今天', items: [items[1]] },
      { label: '昨天', items: [items[2]] },
      { label: '更早', items: [items[0]] },
    ]);
  });

  it('groups backend SQL timestamps using their UTC meaning', () => {
    expect(notificationDateGroup('2026-08-22 03:30:00', new Date('2026-08-23T12:00:00+08:00'))).toBe('昨天');
  });
});
