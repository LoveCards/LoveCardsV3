import { useCallback, useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { getUnreadNotificationCount, listConversations } from '../../services/community-service.js';
import { conversationUnreadTotalOf } from './message-utils.js';

const tabs = [
  { label: '私信', to: '/messages', matches: (pathname) => pathname.startsWith('/messages') },
  { label: '通知', to: '/inbox', matches: (pathname) => pathname.startsWith('/inbox') },
];

function normalizedUnread(value) {
  if (value === null || value === undefined) return null;
  const count = Number(value);
  return Number.isFinite(count) ? Math.max(0, Math.floor(count)) : null;
}

export function MessageCenterTabs({ messageUnread: messageUnreadProp, notificationUnread: notificationUnreadProp }) {
  const { pathname } = useLocation();
  const auth = useAuth();
  const messageUnread = normalizedUnread(messageUnreadProp);
  const notificationUnread = normalizedUnread(notificationUnreadProp);
  const [remoteUnread, setRemoteUnread] = useState({ message: 0, notification: 0 });

  const loadMissingUnread = useCallback(async () => {
    if (!auth.isAuthenticated) {
      setRemoteUnread({ message: 0, notification: 0 });
      return;
    }
    const [messagesResult, notificationsResult] = await Promise.allSettled([
      messageUnread === null ? listConversations() : Promise.resolve(null),
      notificationUnread === null ? getUnreadNotificationCount() : Promise.resolve(null),
    ]);
    setRemoteUnread((current) => ({
      message: messagesResult.status === 'fulfilled' && messagesResult.value
        ? conversationUnreadTotalOf(messagesResult.value.items)
        : current.message,
      notification: notificationsResult.status === 'fulfilled' && notificationsResult.value
        ? Math.max(0, Number(notificationsResult.value.unread) || 0)
        : current.notification,
    }));
  }, [auth.isAuthenticated, messageUnread, notificationUnread]);

  useEffect(() => { void loadMissingUnread(); }, [loadMissingUnread]);
  useEffect(() => {
    window.addEventListener('lc-notifications-changed', loadMissingUnread);
    window.addEventListener('lc-messages-changed', loadMissingUnread);
    return () => {
      window.removeEventListener('lc-notifications-changed', loadMissingUnread);
      window.removeEventListener('lc-messages-changed', loadMissingUnread);
    };
  }, [loadMissingUnread]);

  const unread = {
    messages: messageUnread ?? remoteUnread.message,
    inbox: notificationUnread ?? remoteUnread.notification,
  };

  return <nav className="community-tabs message-center-tabs" aria-label="消息中心分类">
    {tabs.map((tab) => {
      const active = tab.matches(pathname);
      const count = tab.to === '/messages' ? unread.messages : unread.inbox;
      return <Link className={active ? 'is-active' : ''} aria-current={active ? 'page' : undefined} aria-label={count ? `${tab.label}，${count} 条未读` : tab.label} key={tab.to} to={tab.to}><span className="message-center-tab-label">{tab.label}{count > 0 && <span className="message-center-tab-unread" aria-hidden="true">{count > 99 ? '99+' : count}</span>}</span></Link>;
    })}
  </nav>;
}
