import { useEffect, useRef } from 'react';
import { Link, useLocation } from 'react-router-dom';

export const profileContentTabs = [
  { label: '我的内容', to: '/me' },
  { label: '喜欢', to: '/me/library/likes' },
  { label: '评论', to: '/me/library/comments' },
  { label: '收藏', to: '/me/library/saved' },
  { label: '历史', to: '/me/library/history' },
];

export function ProfileContentTabs({ label = '个人内容分类' }) {
  const { pathname } = useLocation();
  const navRef = useRef(null);
  const activeRef = useRef(null);

  useEffect(() => {
    const nav = navRef.current;
    const active = activeRef.current;
    if (!nav || !active) return;
    const itemStart = active.offsetLeft;
    const itemEnd = itemStart + active.offsetWidth;
    if (itemStart < nav.scrollLeft) nav.scrollTo({ left: itemStart, behavior: 'auto' });
    else if (itemEnd > nav.scrollLeft + nav.clientWidth) nav.scrollTo({ left: itemEnd - nav.clientWidth, behavior: 'auto' });
  }, [pathname]);

  return <nav className="profile-tabs" aria-label={label} ref={navRef}>
    {profileContentTabs.map((tab) => {
      const active = pathname === tab.to;
      return <Link className={active ? 'is-active' : ''} aria-current={active ? 'page' : undefined} key={tab.to} ref={active ? activeRef : undefined} to={tab.to}>{tab.label}</Link>;
    })}
  </nav>;
}
