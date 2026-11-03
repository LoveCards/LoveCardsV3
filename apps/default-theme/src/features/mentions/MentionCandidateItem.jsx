import { Avatar } from '../../ui/index.js';

/** Shared visual row for the @ menu in card and comment composers. */
export function MentionCandidateItem({ item }) {
  const data = item?.auxiliaryData || {};
  const displayName = data.displayName || item?.label || `@${item?.username || item?.handle || ''}`;
  const handle = item?.username || item?.handle || '';
  return <span className="lc-mention-menu-item">
    <Avatar label={displayName} src={data.avatar || item?.avatar} size="sm" tone="rose" />
    <span className="lc-mention-menu-copy"><strong>{displayName}</strong><small>@{handle}</small></span>
  </span>;
}

export function renderMentionCandidateItem(item) {
  return <MentionCandidateItem item={item} />;
}
