import { useEffect, useRef } from 'react';
import { Link, useLocation } from 'react-router-dom';

export const profileContentTabs = [
  { label: '内容', tab: 'content' },
  { label: '喜欢', tab: 'likes' },
  { label: '收藏', tab: 'saved' },
  { label: '历史', tab: 'history' },
];
const profileTabKeys = new Set(profileContentTabs.map((tab) => tab.tab));

function publicContentTab(handle) {
  const value = typeof handle === 'string' ? handle.trim() : '';
  return value ? [{ label: '内容', to: `/user/${encodeURIComponent(value)}` }] : [{ label: '内容', to: '#' }];
}

export function ProfileContentTabs({ label = '个人内容分类', mode = 'library', handle, contentCount = null }) {
  const { pathname, search } = useLocation();
  const navRef = useRef(null);
  const activeRef = useRef(null);
  const isPublic = mode === 'public';
  const tabs = isPublic ? publicContentTab(handle) : profileContentTabs;
  const legacyTab = pathname === '/me' ? 'content' : pathname === '/me/library/likes' ? 'likes' : pathname === '/me/library/saved' ? 'saved' : pathname === '/me/library/history' ? 'history' : null;
  const requestedTab = new URLSearchParams(search).get('tab');
  const activeTab = profileTabKeys.has(requestedTab) ? requestedTab : legacyTab || 'content';

  useEffect(() => {
    const nav = navRef.current;
    const active = activeRef.current;
    if (!nav || !active) return;
    const itemStart = active.offsetLeft;
    const itemEnd = itemStart + active.offsetWidth;
    if (itemStart < nav.scrollLeft) nav.scrollTo({ left: itemStart, behavior: 'auto' });
    else if (itemEnd > nav.scrollLeft + nav.clientWidth) nav.scrollTo({ left: itemEnd - nav.clientWidth, behavior: 'auto' });
  }, [pathname, search]);

  return <nav className="profile-tabs" aria-label={label} ref={navRef}>
    {tabs.map((tab) => {
      const active = isPublic ? pathname === tab.to : activeTab === tab.tab;
      const to = isPublic ? tab.to : tab.tab === 'content' ? '/me' : `/me?tab=${tab.tab}`;
      const hasCount = !isPublic && tab.tab === 'content' && contentCount !== null && contentCount !== undefined;
      const tabLabel = hasCount ? `${tab.label} ${contentCount}` : tab.label;
      const accessibleLabel = hasCount ? `${tab.label}，${contentCount} 条` : tab.label;
      return to === '#' ? <span className="is-active" key={tab.label} ref={active ? activeRef : undefined} aria-label={accessibleLabel}>{tabLabel}</span> : <Link className={active ? 'is-active' : ''} aria-current={active ? 'page' : undefined} aria-label={accessibleLabel} key={tab.label} ref={active ? activeRef : undefined} to={to}>{tabLabel}</Link>;
    })}
  </nav>;
}
