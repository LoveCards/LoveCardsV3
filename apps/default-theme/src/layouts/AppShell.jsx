import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { Brand } from '../components/Brand.jsx';
import { HeaderSearch } from '../components/HeaderSearch.jsx';
import { ContentChannels } from '../components/ContentChannels.jsx';
import { ThemeToggle } from '../features/theme/ThemeToggle.jsx';
import { Avatar, Icon } from '../ui/icons.jsx';
import { IconButton } from '../ui/controls.jsx';
import { useAuth } from '../app/providers.jsx';
import { getRuntimeDataMode } from '../app/runtime-context.js';
import { getSdkClient, sdkConfigured } from '../app/sdk-client.js';
import { getCommunicationRuntime, SIGNAL_UNREAD } from '../services/communication-runtime.js';
import { composeModalState } from '../app/compose-modal-routing.js';
import { browseTaskState } from '../app/browse-task-routing.js';
import { useNavigationLogin } from '../app/NavigationLoginProvider.jsx';

const recommend = { to: '/', label: '推荐', icon: 'home' };
const activity = { to: '/activity', label: '动态', icon: 'users' };
const compose = { to: '/compose', label: '发布', icon: 'plus', compose: true };
const messages = { to: '/messages', label: '消息', icon: 'mail', inbox: true };
const profile = { to: '/me', label: '我的', icon: 'user' };
const bottomNav = [{ ...recommend, label: '首页' }, activity, compose, messages, profile];

const desktopNav = [
  recommend,
  { to: '/explore', label: '发现', icon: 'compass' },
  activity,
  messages,
  compose,
  profile,
];

const protectedNavigationPrefixes = ['/activity', '/me', '/messages', '/inbox', '/compose', '/drafts', '/settings/profile', '/settings/security', '/settings/session', '/settings/privacy', '/settings/blocked', '/settings/notifications'];

function isProtectedNavigationTarget(target) {
  return protectedNavigationPrefixes.some((prefix) => target === prefix || target.startsWith(`${prefix}/`) || target.startsWith(`${prefix}?`));
}

export function AppShell({ children, mobileMode = 'standard', pendingIdentity = false, desktopSearch }) {
  const navigate = useNavigate();
  const location = useLocation();
  const { pathname, search } = location;
  const searchRoute = pathname === '/search' || pathname.startsWith('/search/');
  const activityActive = pathname === '/activity';
  const browseChannel = pathname === '/' ? 'recommended' : pathname === '/explore' ? 'explore' : null;
  const exploreActive = pathname === '/explore' || pathname.startsWith('/topic/');
  const hasMobileBottomNav = mobileMode === 'standard';
  const messageCenterActive = pathname === '/messages' || pathname.startsWith('/messages/') || pathname === '/inbox' || pathname.startsWith('/inbox/');
  const composeActive = pathname === '/compose' || pathname.startsWith('/compose/');
  const meActive = pathname === '/me' || pathname.startsWith('/me/');
  const auth = useAuth();
  const { user, status: authStatus } = auth;
  const isAuthenticated = !pendingIdentity && auth.isAuthenticated;
  const isVisitor = !pendingIdentity && auth.isVisitor;
  const designPreview = getRuntimeDataMode() === 'design-preview';
  const requiresLogin = !designPreview && authStatus === 'ready' && !isAuthenticated;
  const needsNavigationLogin = (target) => requiresLogin && !(isVisitor && target === '/me') && isProtectedNavigationTarget(target);
  const [authExpired, setAuthExpired] = useState(false);
  const openLogin = useNavigationLogin();
  const [notificationUnread, setNotificationUnread] = useState(0);
  const [realtimeFault, setRealtimeFault] = useState(false);
  useEffect(() => {
    if (!isAuthenticated && !isVisitor) {
      setAuthExpired(false);
      return undefined;
    }
    const handleAuthError = () => setAuthExpired(true);
    window.addEventListener('lc-auth-error', handleAuthError);
    return () => window.removeEventListener('lc-auth-error', handleAuthError);
  }, [isAuthenticated, isVisitor]);
  useEffect(() => {
    if (!isAuthenticated || !sdkConfigured()) {
      setNotificationUnread(0);
      setRealtimeFault(false);
      return undefined;
    }

    let active = true;
    const refreshUnread = async () => {
      try {
        const result = await getSdkClient().notifications.unread();
        if (active) setNotificationUnread(Math.max(0, Number(result?.unread) || 0));
      } catch {
        // The badge is supplementary; the inbox keeps its own visible error state.
      }
    };
    const onVisibilityChange = () => {
      if (document.visibilityState === 'visible') void refreshUnread();
    };
    void refreshUnread();
    // 通信运行时（契约 §6）：唯一取数节奏调度器；组件只订阅刷新信号，不自行定时。
    const runtime = getCommunicationRuntime();
    const unsubscribe = runtime.subscribe((topic) => { if (topic === SIGNAL_UNREAD) void refreshUnread(); });
    const unsubscribeStatus = runtime.subscribeStatus((status) => { if (active) setRealtimeFault(status === 'fault'); });
    void runtime.start();
    window.addEventListener('lc-notifications-changed', refreshUnread);
    document.addEventListener('visibilitychange', onVisibilityChange);
    return () => {
      active = false;
      unsubscribe();
      unsubscribeStatus();
      runtime.stop();
      window.removeEventListener('lc-notifications-changed', refreshUnread);
      document.removeEventListener('visibilitychange', onVisibilityChange);
    };
  }, [isAuthenticated]);
  const handleNavigationClick = (event, target) => {
    if (!needsNavigationLogin(target) || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    openLogin(target);
  };
  const handleComposeClick = (event) => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (needsNavigationLogin('/compose')) { handleNavigationClick(event, '/compose'); return; }
    if (window.matchMedia('(min-width: 761px)').matches) {
      event.preventDefault();
      navigate('/compose', { state: composeModalState(location) });
    }
  };
  const profileRoute = pathname === '/me' || pathname.startsWith('/user/');
  const activeDesktopPath = browseChannel === 'recommended' ? '/' : exploreActive ? '/explore' : activityActive ? '/activity' : messageCenterActive ? '/messages' : composeActive ? '/compose' : meActive ? '/me' : null;
  return (
    <div className={`app-shell is-mobile-${mobileMode}${profileRoute ? ' is-profile-route' : ''}${messageCenterActive ? ' is-message-route' : ''}${hasMobileBottomNav ? '' : ' is-secondary-route'}`}>
      <aside className="product-sidebar" aria-label="桌面侧栏">
        <Brand />
        <nav className="desktop-nav" aria-label="主要导航">
          {desktopNav.map((item) => (
            <Link className={item.compose ? 'header-compose' : undefined} aria-current={item.to === activeDesktopPath ? 'page' : undefined} key={item.to} to={item.to} onClick={item.compose ? handleComposeClick : (event) => handleNavigationClick(event, item.to)}>
              <span className="sidebar-nav-icon" aria-hidden="true">
                {item === profile && isAuthenticated ? <Avatar label={user?.username || '我'} src={user?.avatar} tone="rose" size="sm" /> : <Icon name={item.icon} size={19} />}
              </span>
              <span>{item.label}</span>
              {item.inbox && notificationUnread > 0 && <span className="nav-unread" aria-label={`${notificationUnread} 条未读通知`}>{notificationUnread > 99 ? '99+' : notificationUnread}</span>}
            </Link>
          ))}
        </nav>
        <footer className="product-sidebar-footer">
          <ThemeToggle />
          <small>© LoveCards</small>
        </footer>
      </aside>
      <div className="app-workspace">
        {!messageCenterActive && <header className="product-header">
          <div className="product-header-inner">
            <div className="desktop-search">{desktopSearch || <HeaderSearch />}</div>
            <div className="mobile-product-header">
              <Brand iconOnly />
              {browseChannel ? <ContentChannels active={browseChannel} /> : activityActive ? <strong>动态</strong> : <span />}
              {!searchRoute && <Link className="mobile-search-trigger" to="/search" state={browseTaskState(location)} aria-label="打开搜索"><Icon name="search" size={20} /></Link>}
            </div>
          </div>
        </header>}
        {location.state?.logoutUnconfirmed && <div className="session-notice" role="alert"><span>本机凭证已清除，但服务器退出未确认。</span><IconButton className="session-notice-dismiss" type="button" onClick={() => navigate(`${pathname}${search}${location.hash}`, { replace: true, state: null })} label="关闭退出状态提示" variant="ghost" size="lg" icon={<Icon name="close" size={17} />} /></div>}
        {authExpired && <div className="session-notice" role="alert"><span>登录状态已失效，部分操作暂不可用。</span><Link to="/auth/login">重新登录</Link><IconButton className="session-notice-dismiss" type="button" onClick={() => setAuthExpired(false)} label="关闭登录状态提示" variant="ghost" size="lg" icon={<Icon name="close" size={17} />} /></div>}
        {realtimeFault && <div className="session-notice" role="status"><span>实时更新暂不可用；手动刷新仍可查看最新内容。</span></div>}
        {children}
      </div>
      {hasMobileBottomNav && <nav className="mobile-bottom-nav" aria-label="移动端产品导航">
        {bottomNav.map((item) => {
          if (item.unavailable) return <span className="mobile-nav-unavailable" aria-disabled="true" key={item.label}>
            <span className="mobile-nav-icon"><Icon name={item.icon} size={19} /></span>
            <span>{item.label}</span>
            <small>未开放</small>
          </span>;
          const content = <>
            <span className="mobile-nav-icon"><Icon name={item.icon} size={item.compose ? 22 : 19} /></span>
            {!item.compose && <span>{item.label}</span>}
            {item.inbox && notificationUnread > 0 && <span className="mobile-nav-unread" aria-label={`${notificationUnread} 条未读通知`}>{notificationUnread > 99 ? '99+' : notificationUnread}</span>}
            {item.compose && <span className="sr-only">{item.label}</span>}
          </>;
          if (item.inbox) return <Link className={messageCenterActive ? 'is-active' : undefined} aria-current={messageCenterActive ? 'page' : undefined} key={item.to} to={item.to} onClick={(event) => handleNavigationClick(event, item.to)}>{content}</Link>;
          const active = item.compose ? composeActive : item.to === '/' ? Boolean(browseChannel) : item.to === '/activity' ? activityActive : pathname === item.to;
          return <Link
            className={`${item.compose ? 'is-compose ' : ''}${active ? 'is-active' : ''}`.trim()}
            aria-current={active ? 'page' : undefined}
            key={item.to}
            to={item.to}
            onClick={(event) => handleNavigationClick(event, item.to)}
          >{content}</Link>;
        })}
      </nav>}
    </div>
  );
}
