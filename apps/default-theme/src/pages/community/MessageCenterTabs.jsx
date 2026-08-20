import { Link, useLocation } from 'react-router-dom';

const tabs = [
  { label: '通知', to: '/inbox', matches: (pathname) => pathname.startsWith('/inbox') },
  { label: '私信', to: '/messages', matches: (pathname) => pathname.startsWith('/messages') },
];

export function MessageCenterTabs() {
  const { pathname } = useLocation();

  return <nav className="community-tabs message-center-tabs" aria-label="消息中心分类">
    {tabs.map((tab) => {
      const active = tab.matches(pathname);
      return <Link className={active ? 'is-active' : ''} aria-current={active ? 'page' : undefined} key={tab.to} to={tab.to}>{tab.label}</Link>;
    })}
  </nav>;
}
