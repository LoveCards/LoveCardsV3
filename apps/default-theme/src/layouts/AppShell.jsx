import { useEffect, useState } from 'react';
import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom';
import { Brand } from '../components/Brand.jsx';
import { HeaderSearch } from '../components/HeaderSearch.jsx';
import { ThemeToggle } from '../features/theme/ThemeToggle.jsx';
import { Avatar, Icon } from '../ui/icons.jsx';
import { ActionMenu } from '../ui/interaction.jsx';
import { IconButton } from '../ui/controls.jsx';
import { useAuth } from '../app/providers.jsx';
import { getRuntimeDataMode } from '../app/runtime-context.js';
import { getSdkClient, sdkConfigured } from '../app/sdk-client.js';
import { getCommunicationRuntime } from '../services/communication-runtime.js';

const bottomNav = [
  { to: '/', label: '首页', icon: 'home', end: true },
  { to: '/explore', label: '发现', icon: 'search' },
  { to: '/compose', label: '发布', icon: 'plus', compose: true },
  { to: '/messages', label: '消息', icon: 'mail', inbox: true },
  { to: '/me', label: '我的', icon: 'user' },
];

const desktopNav = [
  { to: '/', label: '首页', end: true },
  { to: '/explore', label: '发现' },
  { to: '/messages', label: '消息', inbox: true },
];

const protectedNavigationPrefixes = ['/me', '/messages', '/inbox', '/compose', '/drafts', '/settings/profile', '/settings/security', '/settings/session', '/settings/privacy', '/settings/blocked', '/settings/notifications'];

function isProtectedNavigationTarget(target) {
  return protectedNavigationPrefixes.some((prefix) => target === prefix || target.startsWith(`${prefix}/`) || target.startsWith(`${prefix}?`));
}

export function AppShell({ children, mobileMode = 'standard' }) {
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const hasMobileBottomNav = mobileMode === 'standard';
  const messageCenterActive = pathname === '/messages' || pathname.startsWith('/messages/') || pathname === '/inbox' || pathname.startsWith('/inbox/');
  const composeActive = pathname === '/compose' || pathname.startsWith('/compose/');
  const { isAuthenticated, isVisitor, user, logout, status: authStatus } = useAuth();
  const designPreview = getRuntimeDataMode() === 'design-preview';
  const requiresLogin = !designPreview && authStatus === 'ready' && !isAuthenticated;
  const navigationTarget = (target) => requiresLogin && isProtectedNavigationTarget(target) ? '/auth/login' : target;
  const navigationState = (target) => requiresLogin && isProtectedNavigationTarget(target) ? { from: target } : undefined;
  const [authExpired, setAuthExpired] = useState(false);
  const [sessionError, setSessionError] = useState(false);
  const [notificationUnread, setNotificationUnread] = useState(0);
  useEffect(() => {
    const handleAuthError = () => setAuthExpired(true);
    window.addEventListener('lc-auth-error', handleAuthError);
    return () => window.removeEventListener('lc-auth-error', handleAuthError);
  }, []);
  useEffect(() => {
    if (!isAuthenticated || !sdkConfigured()) {
      setNotificationUnread(0);
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
    const unsubscribe = runtime.subscribe(() => { void refreshUnread(); });
    void runtime.start();
    window.addEventListener('lc-notifications-changed', refreshUnread);
    document.addEventListener('visibilitychange', onVisibilityChange);
    return () => {
      active = false;
      unsubscribe();
      runtime.stop();
      window.removeEventListener('lc-notifications-changed', refreshUnread);
      document.removeEventListener('visibilitychange', onVisibilityChange);
    };
  }, [isAuthenticated]);
  const endSession = async (destination) => {
    setSessionError(false);
    try {
      await logout();
    } catch {
      setSessionError(true);
    } finally {
      navigate(destination, { replace: true });
    }
  };
  const accountItems = isAuthenticated ? [
    { label: '我的主页', icon: <Icon name="user" size={17} />, onClick: () => navigate('/me') },
    { label: '设置', icon: <Icon name="settings" size={17} />, onClick: () => navigate('/settings/appearance') },
    { type: 'divider' },
    { label: '退出登录', icon: <Icon name="logout" size={17} />, onClick: () => { void endSession('/auth/login'); } },
  ] : designPreview ? [
    { label: '设计预览主页', icon: <Icon name="user" size={17} />, onClick: () => navigate('/me') },
    { label: '外观设置', icon: <Icon name="settings" size={17} />, onClick: () => navigate('/settings/appearance') },
  ] : [
    { label: isVisitor ? '登录正式账号' : '登录', icon: <Icon name="user" size={17} />, onClick: () => navigate('/auth/login') },
    { label: '创建账号', icon: <Icon name="plus" size={17} />, onClick: () => navigate('/auth/register') },
    { label: '外观设置', icon: <Icon name="settings" size={17} />, onClick: () => navigate('/settings/appearance') },
    ...(isVisitor ? [{ type: 'divider' }, { label: '结束游客会话', icon: <Icon name="logout" size={17} />, onClick: () => { void endSession('/'); } }] : []),
  ];

  const profileRoute = pathname === '/me' || pathname.startsWith('/user/');
  return (
    <div className={`app-shell is-mobile-${mobileMode}${profileRoute ? ' is-profile-route' : ''}${messageCenterActive ? ' is-message-route' : ''}${composeActive ? ' is-compose-route' : ''}${hasMobileBottomNav ? '' : ' is-secondary-route'}`}>
      <header className="product-header">
        <div className="product-header-inner">
          <Brand />
          <nav className="desktop-nav" aria-label="主要导航">
            {desktopNav.map((item) => {
              if (item.unavailable) return <span className="nav-unavailable" aria-disabled="true" key={item.label}>{item.label}<small>未开放</small></span>;
              const content = <>{item.label}{item.inbox && notificationUnread > 0 && <span className="nav-unread" aria-label={`${notificationUnread} 条未读通知`}>{notificationUnread > 99 ? '99+' : notificationUnread}</span>}</>;
              return item.inbox
                ? <Link aria-current={messageCenterActive ? 'page' : undefined} key={item.to} to={navigationTarget(item.to)} state={navigationState(item.to)}>{content}</Link>
                : <NavLink end={item.end} key={item.to} to={navigationTarget(item.to)} state={navigationState(item.to)}>{content}</NavLink>;
            })}
          </nav>
          <HeaderSearch />
          <div className="header-actions">
            <IconButton className="mobile-search-trigger" type="button" label="打开搜索" tooltip="搜索" variant="ghost" size="lg" icon={<Icon name="search" size={20} />} onClick={() => window.dispatchEvent(new CustomEvent('lc-open-mobile-search'))} />
            <Link className="header-compose" to={navigationTarget('/compose')} state={navigationState('/compose')}>发布</Link>
            <ThemeToggle />
            <div className="header-profile"><ActionMenu items={accountItems} label={isAuthenticated ? `${user?.username || '账号'}菜单` : designPreview ? '设计预览菜单' : isVisitor ? '游客菜单' : '登录与设置'} icon={<Avatar label={isAuthenticated ? (user?.username || '我') : designPreview ? '预' : isVisitor ? '访' : '登'} src={isAuthenticated ? user?.avatar : undefined} tone="sand" size="sm" />} /></div>
          </div>
        </div>
      </header>
      {(authExpired || sessionError) && <div className="session-notice" role="alert"><span>{sessionError ? '本地登录状态已清除，但服务器退出确认失败。' : '登录状态已失效，部分操作暂不可用。'}</span><Link to="/auth/login">重新登录</Link><IconButton className="session-notice-dismiss" type="button" onClick={() => { setAuthExpired(false); setSessionError(false); }} label="关闭登录状态提示" variant="ghost" size="lg" icon={<Icon name="close" size={17} />} /></div>}
      {children}
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
          if (item.inbox) return <Link className={messageCenterActive ? 'is-active' : undefined} aria-current={messageCenterActive ? 'page' : undefined} key={item.to} to={navigationTarget(item.to)} state={navigationState(item.to)}>{content}</Link>;
          return <NavLink
            className={({ isActive }) => `${item.compose ? 'is-compose ' : ''}${isActive ? 'is-active' : ''}`.trim()}
            end={item.end}
            key={item.to}
            to={navigationTarget(item.to)}
            state={navigationState(item.to)}
          >{content}</NavLink>;
        })}
      </nav>}
    </div>
  );
}
