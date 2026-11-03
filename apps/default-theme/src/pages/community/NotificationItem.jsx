import { Link } from 'react-router-dom';
import { AuthorIdentity } from '../../components/AuthorIdentity.jsx';
import { Avatar, Icon } from '../../ui/icons.jsx';
import { formatMessageTime } from './message-utils.js';
import { normalizeNotificationItem } from './notification-model.js';

function NotificationActor({ item }) {
  if (item.actorState === 'system') {
    return <span className={`notification-system-identity is-${item.severity}`}><span className="notification-system-mark" aria-hidden="true"><Icon name={item.presentation.icon} size={18} /></span><span><b>LoveCards</b><small>系统通知 · {item.severityLabel}</small></span></span>;
  }
  if (item.actorState !== 'active') {
    return <span className="notification-actor-unavailable"><Avatar label="该" tone="ink" size="md" /><span>该用户已不可用</span></span>;
  }
  return <AuthorIdentity
    author={item.actor.username}
    uid={item.actor.uid}
    handle={item.actor.username}
    avatarUrl={item.actor.avatar || ''}
    size="md"
    variant="list"
    className="notification-actor"
  />;
}

function NotificationAction({ item }) {
  if (item.actionUrl) return <span className="notification-action" aria-hidden="true"><Icon name="arrow" size={15} /></span>;
  if (item.resourceStateLabel) return <span className="notification-resource-state">{item.resourceStateLabel}</span>;
  return null;
}

export function NotificationItem({ item: source, busy = false, onRead }) {
  const item = normalizeNotificationItem(source);
  const unread = !item.read_at;
  const message = typeof item.payload.message === 'string' && item.payload.message.trim()
    ? item.payload.message.trim()
    : item.presentation.description;
  const activate = () => { if (unread && !busy) onRead?.(source); };
  const readStateLabel = unread ? '未读通知' : '已读通知';
  const copy = <><span className="sr-only">{readStateLabel}：</span><strong>{item.presentation.title}</strong><span>{message}</span><small className="notification-time">{formatMessageTime(item.created_at)}</small></>;

  return <article className={`notification-item category-${item.category}${unread ? ' is-unread' : ''}${item.category === 'system' ? ` severity-${item.severity}` : ''}`}>
    <span className="notification-unread-dot" aria-hidden="true" />
    <NotificationActor item={item} />
    {item.actionUrl
      ? <Link className="notification-copy" to={item.actionUrl} aria-label={`${readStateLabel}，查看：${item.presentation.title}`} onClick={activate}>{copy}</Link>
      : unread
        ? <button className="notification-copy" type="button" onClick={activate} disabled={busy} aria-label={`标记已读：${item.presentation.title}`}>{copy}</button>
        : <div className="notification-copy is-static">{copy}</div>}
    <NotificationAction item={item} />
  </article>;
}

export function NotificationListSkeleton({ count = 4 }) {
  return <section className="notification-skeleton-shell" role="status" aria-label="通知加载中">
    <span className="sr-only">通知加载中</span>
    <div className="notification-skeleton-heading" aria-hidden="true"><i /><i /></div>
    <div className="notification-skeleton" aria-hidden="true">{Array.from({ length: count }, (_, index) => <div className="notification-skeleton-row" key={index}><span className="notification-skeleton-actor"><i /><i /></span><span className="notification-skeleton-copy"><i /><i /><i /></span><i className="notification-skeleton-action" /></div>)}</div>
  </section>;
}
