import { describe, expect, it } from 'vitest';
import {
  conversationPreviewOf,
  conversationTimeOf,
  conversationUnreadCountOf,
  conversationUnreadTotalOf,
  formatMessageTime,
  parseMessageDate,
} from '../pages/community/message-utils.js';

describe('message time presentation', () => {
  const now = new Date('2026-08-23T12:00:00+08:00');

  it('uses compact relative labels for recent messages', () => {
    expect(formatMessageTime('2026-08-23T11:59:40+08:00', now)).toBe('刚刚');
    expect(formatMessageTime('2026-08-23T11:32:00+08:00', now)).toBe('28 分钟前');
    expect(formatMessageTime('2026-08-23T09:00:00+08:00', now)).toBe('3 小时前');
  });

  it('falls back to localized absolute time and handles invalid input', () => {
    expect(formatMessageTime('2026-08-22T10:05:00+08:00', now)).toContain('昨天');
    expect(formatMessageTime('not-a-date', now)).toBe('时间未提供');
    expect(formatMessageTime('2026-08-23T11:00:00+08:00', 'not-a-date')).toBe('时间未提供');
  });

  it('treats backend SQL timestamps as UTC', () => {
    expect(parseMessageDate('2026-08-23 03:59:40').toISOString()).toBe('2026-08-23T03:59:40.000Z');
  });

  it('prefers the hydrated conversation preview fields and exposes safe fallbacks', () => {
    expect(conversationPreviewOf({ last_message_body: '  好呀，周末见。  ', last_message: '旧摘要' })).toBe('好呀，周末见。');
    expect(conversationPreviewOf({ last_message: '旧摘要' })).toBe('旧摘要');
    expect(conversationPreviewOf({ last_message_id: 18 })).toBe('消息内容暂不可见');
    expect(conversationPreviewOf({})).toBe('还没有消息，发送第一条');
    expect(conversationTimeOf({ last_message_at: '2026-08-23 03:00:00', updated_at: 'later' })).toBe('2026-08-23 03:00:00');
    expect(conversationUnreadCountOf({ unread_count: 3 })).toBe(3);
    expect(conversationUnreadCountOf({ unread_count: -1 })).toBe(0);
    expect(conversationUnreadCountOf({ unread_count: 'not-a-number' })).toBe(0);
    expect(conversationUnreadTotalOf([{ unread_count: 3 }, { unread_count: 2 }, { unread_count: -1 }])).toBe(5);
    expect(conversationUnreadTotalOf(null)).toBe(0);
  });
});
