import { describe, expect, it } from 'vitest';
import { renderRoute } from './renderRoute.jsx';
import { conversationIdOf, conversationRecipientOf } from '../pages/community/MessagesPage.jsx';

describe('community page routes', () => {
  it('renders the real community page shells instead of pending engineering pages', () => {
    expect(renderRoute('/drafts')).toContain('草稿箱');
    expect(renderRoute('/inbox')).toContain('通知');
    expect(renderRoute('/messages')).toContain('私信');
    expect(renderRoute('/me/library/history')).toContain('浏览历史');
    expect(renderRoute('/settings/privacy')).toContain('隐私与黑名单');
    expect(renderRoute('/auth/recovery')).toContain('找回你的账号');
    expect(renderRoute('/messages/requests')).toContain('消息请求');
    expect(renderRoute('/messages/requests')).toContain('暂未开放');
    expect(renderRoute('/inbox')).not.toContain('BlueprintPendingPage');
  });

  it('keeps conversation identifiers and recipient inference explicit', () => {
    expect(conversationIdOf({ conversation_id: '12' })).toBe(12);
    expect(conversationIdOf({ id: 'invalid' })).toBeNull();
    expect(conversationRecipientOf({ recipient_id: 8 }, 3)).toBe(8);
    expect(conversationRecipientOf({ recipient_id: 3, peer_id: 9 }, 3)).toBe(9);
    expect(conversationRecipientOf({ user_id: 3 }, 3)).toBeNull();
  });
});
