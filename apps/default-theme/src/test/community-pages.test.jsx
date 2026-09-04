import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { renderRoute } from './renderRoute.jsx';
import { conversationIdOf, conversationRecipientOf } from '../pages/community/MessagesPage.jsx';

describe('community page routes', () => {
  it('renders the real community page shells instead of pending engineering pages', () => {
    expect(renderRoute('/drafts')).toContain('草稿箱');
    expect(renderRoute('/inbox')).toContain('通知');
    expect(renderRoute('/messages')).toContain('私信');
    expect(renderRoute('/messages/new/example')).toContain('新私信');
    expect(renderRoute('/me?tab=history')).toContain('>历史</a>');
    expect(renderRoute('/settings/privacy')).toContain('隐私偏好');
    expect(renderRoute('/auth/recovery')).toContain('找回你的账号');
    expect(renderRoute('/messages/requests')).toContain('消息请求');
    expect(renderRoute('/messages/requests')).toContain('暂未开放');
    expect(renderRoute('/inbox')).not.toContain('BlueprintPendingPage');
  });

  it('keeps conversation identifiers and recipient inference explicit', () => {
    expect(conversationIdOf({ conversation_id: '12' })).toBe(12);
    expect(conversationIdOf({ id: 'invalid' })).toBeNull();
    expect(conversationRecipientOf({ recipient_uid: '123456789012' }, '123456789013')).toBe('123456789012');
    expect(conversationRecipientOf({ recipient_uid: '123456789013', peer_uid: '123456789014' }, '123456789013')).toBe('123456789014');
    expect(conversationRecipientOf({ user_id: 3 }, 3)).toBeNull();
  });

  it('declares focused mobile shells for private-message tasks and pending routes', () => {
    const newMessageSource = readFileSync(fileURLToPath(new URL('../pages/community/NewMessagePage.jsx', import.meta.url)), 'utf8');
    const pendingSource = readFileSync(fileURLToPath(new URL('../pages/system/BlueprintPendingPage.jsx', import.meta.url)), 'utf8');

    expect(newMessageSource).toContain('<AppShell mobileMode="immersive">');
    expect(pendingSource).toContain("mobileMode = 'standard'");
    expect(pendingSource).toContain('<AppShell mobileMode={mobileMode}>');
  });
});
