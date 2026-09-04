import { useRef } from 'react';
import { FeedSkeleton } from '../features/feed/FeedSkeleton.jsx';
import { FeedStory } from '../features/feed/FeedStory.jsx';
import { useFeedMasonry } from '../features/feed/use-feed-masonry.js';
import { normalizeProfileUid } from './ProfileIdentitySurface.jsx';

function profileCardIdentity(item, profile) {
  const itemHandle = typeof item?.authorHandle === 'string' ? item.authorHandle.trim() : '';
  const profileUid = normalizeProfileUid(profile?.uid);
  const profileName = typeof profile?.username === 'string' ? profile.username.trim() : '';
  const itemAuthor = typeof item?.author === 'string' ? item.author.trim() : '';
  // A profile fallback is safe only when the card is clearly owned by that
  // profile. Likes/saved/history may contain somebody else's card without a
  // usable author handle; those cards must stay non-interactive.
  const belongsToProfile = !itemAuthor || !profileName || itemAuthor === profileName;
  return {
    ...item,
    authorHandle: itemHandle || null,
    authorUid: item.authorUid || (belongsToProfile ? profileUid || null : null),
  };
}

export function ProfileCard({ item, profile, renderAction }) {
  const card = profileCardIdentity(item, profile);
  return <FeedStory card={card} source="profile" renderAction={renderAction} showMoreMenu={false} />;
}

export function ProfileCardFeed({ items = [], profile, ariaLabel = '内容流', className = '', renderAction }) {
  const masonryRef = useRef(null);
  useFeedMasonry(masonryRef, items);
  return <section ref={masonryRef} className={`profile-post-list profile-feed feed-masonry${className ? ` ${className}` : ''}`} aria-label={ariaLabel}>
    <span className="masonry-column-sizer" aria-hidden="true" />
    <span className="masonry-gutter-sizer" aria-hidden="true" />
    {items.map((item, index) => <ProfileCard item={item} profile={profile} renderAction={renderAction} key={item?.id ?? index} />)}
  </section>;
}

export function ProfileCardFeedSkeleton({ count = 4, ariaLabel = '内容加载中', className = '' }) {
  return <FeedSkeleton className={`profile-feed${className ? ` ${className}` : ''}`} count={count} label={ariaLabel} />;
}
