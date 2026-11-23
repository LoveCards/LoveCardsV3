import { Link, useLocation } from 'react-router-dom';
import { AppShell } from '../layouts/AppShell.jsx';
import { ResourceState } from '../components/ResourceState.jsx';
import { SettingsLayout } from '../pages/settings/SettingsLayout.jsx';
import { Icon } from '../ui/icons.jsx';
import { TaskPageHeader } from '../components/TaskPageHeader.jsx';
import { browseTaskBack } from './browse-task-routing.js';
import { cardReturnSource } from './card-preview-routing.js';

function mobileTask(location) {
  const { pathname, state } = location;
  if (pathname === '/search' || pathname.startsWith('/search/')) return { title: '搜索', ...browseTaskBack(state, 'search') };
  if (/^\/topic\/[^/]+\/?$/.test(pathname)) return { title: '话题', ...browseTaskBack(state, 'topic') };
  if (/^\/post\/[^/]+(?:\/thread\/[^/]+)?\/?$/.test(pathname)) {
    const source = cardReturnSource(location);
    return source && state.previewMode !== 'full' ? null : {
      title: '内容详情',
      to: source || '/',
      label: source ? '返回来源页' : '回到信息流',
      state: source?.state,
      delta: source && globalThis.history?.state?.idx > 0 ? -1 : undefined,
    };
  }
  const relation = /^(\/me|\/user\/[^/]+)\/(following|followers)$/.exec(pathname);
  if (relation) return { title: relation[2] === 'following' ? '关注' : '粉丝', to: relation[1], label: '返回主页', family: 'relation' };
  if (pathname === '/drafts' || pathname.startsWith('/drafts/')) return { title: '草稿箱', to: '/compose', label: '返回发布' };
  if (pathname.startsWith('/messages/')) {
    const composing = pathname.startsWith('/messages/new/');
    const title = composing ? '新私信' : pathname === '/messages/search' ? '搜索会话' : pathname === '/messages/requests' ? '消息请求' : '私信';
    return { title, to: '/messages', label: '返回私信列表', immersive: composing };
  }
  if (pathname === '/compose' || pathname.startsWith('/compose/')) return { title: '发布', to: '/', label: '返回首页', immersive: true };
  return null;
}

export function RouteBoundary({ checkingAuth = false, error, onRetry }) {
  const location = useLocation();
  const { pathname } = location;
  const label = checkingAuth ? '正在检查登录状态' : '页面加载中';
  const content = error
    ? <ResourceState className="route-boundary-error" status="error" error={error} onRetry={onRetry} />
    : <div className="route-loading-state" role="status" aria-live="polite">{label}</div>;

  if (pathname.startsWith('/auth/') || pathname.startsWith('/media/')) return <main>{content}</main>;
  if (pathname === '/settings' || pathname.startsWith('/settings/')) {
    const key = pathname.split('/')[2] || 'overview';
    return <AppShell mobileMode="task" pendingIdentity={checkingAuth}><SettingsLayout activeKey={key}>{content}</SettingsLayout></AppShell>;
  }
  const task = mobileTask(location);
  return <AppShell mobileMode={task ? task.immersive ? 'immersive' : 'task' : 'standard'} pendingIdentity={checkingAuth}>
    <main className="route-boundary" aria-label={task?.title || label}>
      {task?.family === 'relation' ? <header className="profile-secondary-mobile-top">
        <Link to={task.to} aria-label={task.label}><Icon name="arrow" size={19} /></Link>
        <strong>{task.title}</strong>
      </header> : task && <TaskPageHeader title={task.title} back={task} />}
      {content}
    </main>
  </AppShell>;
}
