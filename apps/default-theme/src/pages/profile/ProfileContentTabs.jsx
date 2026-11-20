import { useEffect, useLayoutEffect, useRef, useState } from 'react';
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
  const tabRefs = useRef({});
  const indicatorRef = useRef(null);
  const [focusedTab, setFocusedTab] = useState('content');
  const isPublic = mode === 'public';
  const tabs = isPublic ? publicContentTab(handle) : profileContentTabs;
  const legacyTab = pathname === '/me' ? 'content' : pathname === '/me/library/likes' ? 'likes' : pathname === '/me/library/saved' ? 'saved' : pathname === '/me/library/history' ? 'history' : null;
  const requestedTab = new URLSearchParams(search).get('tab');
  const activeTab = profileTabKeys.has(requestedTab) ? requestedTab : legacyTab || 'content';

  useEffect(() => { setFocusedTab(activeTab); }, [activeTab]);

  useLayoutEffect(() => {
    if (isPublic) return undefined;
    const nav = navRef.current;
    const indicator = indicatorRef.current;
    const active = activeRef.current;
    if (!nav || !indicator || !active) return undefined;
    const update = () => {
      const navRect = nav.getBoundingClientRect();
      const activeRect = active.getBoundingClientRect();
      const left = activeRect.left - navRect.left - nav.clientLeft + nav.scrollLeft;
      indicator.style.setProperty('--lc-tab-x', `${left}px`);
      indicator.style.setProperty('--lc-tab-width', String(activeRect.width));
      indicator.dataset.ready = 'true';
    };
    update();
    const observer = new ResizeObserver(update);
    observer.observe(nav);
    Object.values(tabRefs.current).forEach((tab) => { if (tab) observer.observe(tab); });
    return () => observer.disconnect();
  }, [activeTab, contentCount, isPublic]);

  const handleTabKeyDown = (event, tab) => {
    const index = profileContentTabs.findIndex((item) => item.tab === tab);
    let nextIndex;
    if (event.key === 'ArrowRight') nextIndex = (index + 1) % profileContentTabs.length;
    else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + profileContentTabs.length) % profileContentTabs.length;
    else if (event.key === 'Home') nextIndex = 0;
    else if (event.key === 'End') nextIndex = profileContentTabs.length - 1;
    else if (event.key === ' ') {
      event.preventDefault();
      event.currentTarget.click();
      return;
    } else return;
    event.preventDefault();
    const next = profileContentTabs[nextIndex].tab;
    setFocusedTab(next);
    tabRefs.current[next]?.focus();
  };

  useEffect(() => {
    const nav = navRef.current;
    const active = activeRef.current;
    if (!nav || !active) return;
    const itemStart = active.offsetLeft;
    const itemEnd = itemStart + active.offsetWidth;
    if (itemStart < nav.scrollLeft) nav.scrollTo({ left: itemStart, behavior: 'auto' });
    else if (itemEnd > nav.scrollLeft + nav.clientWidth) nav.scrollTo({ left: itemEnd - nav.clientWidth, behavior: 'auto' });
  }, [pathname, search]);

  return <nav className="profile-tabs" aria-label={label} role={isPublic ? undefined : 'tablist'} ref={navRef}>
    {tabs.map((tab) => {
      const active = isPublic ? pathname === tab.to : activeTab === tab.tab;
      const to = isPublic ? tab.to : tab.tab === 'content' ? '/me' : `/me?tab=${tab.tab}`;
      const hasCount = !isPublic && tab.tab === 'content' && contentCount !== null && contentCount !== undefined;
      const tabLabel = hasCount ? `${tab.label} ${contentCount}` : tab.label;
      const accessibleLabel = hasCount ? `${tab.label}，${contentCount} 条` : tab.label;
      return to === '#' ? <span className="is-active" key={tab.label} ref={active ? activeRef : undefined} aria-label={accessibleLabel}>{tabLabel}</span> : <Link className={active ? 'is-active' : ''} role={isPublic ? undefined : 'tab'} id={isPublic ? undefined : `profile-tab-${tab.tab}`} aria-selected={isPublic ? undefined : active} aria-controls={isPublic ? undefined : 'profile-content-panel'} aria-current={isPublic && active ? 'page' : undefined} aria-label={accessibleLabel} tabIndex={isPublic ? undefined : focusedTab === tab.tab ? 0 : -1} onFocus={isPublic ? undefined : () => setFocusedTab(tab.tab)} onKeyDown={isPublic ? undefined : (event) => handleTabKeyDown(event, tab.tab)} key={tab.label} ref={(node) => { if (!isPublic) tabRefs.current[tab.tab] = node; if (active) activeRef.current = node; }} to={to}>{tabLabel}</Link>;
    })}
    {!isPublic && <span className="profile-tab-indicator" aria-hidden="true" ref={indicatorRef} />}
  </nav>;
}
