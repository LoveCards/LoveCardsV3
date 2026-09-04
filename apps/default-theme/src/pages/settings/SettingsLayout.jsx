import { Link } from 'react-router-dom';
import { Icon } from '../../ui/index.js';

// Only real destinations belong here. New settings categories should be added
// to this registry after their route and data contract are available.
export const settingsNavigationItems = [
  { key: 'profile', label: '个人资料', description: '头像、姓名与公开资料', href: '/settings/profile' },
  { key: 'appearance', label: '外观与无障碍', description: '主题与动态效果', href: '/settings/appearance' },
  { key: 'security', label: '账号与安全', description: '密码与联系方式', href: '/settings/security' },
  { key: 'privacy', label: '隐私', description: '主页、关系与私信权限', href: '/settings/privacy' },
  { key: 'blocked', label: '黑名单', description: '管理被限制互动的用户', href: '/settings/blocked' },
  { key: 'session', label: '登录状态', description: '退出当前设备上的账号', href: '/settings/session' },
  { key: 'notifications', label: '通知偏好', description: '选择接收哪些互动通知', href: '/settings/notifications' },
];

const settingsSidebarItems = [
  { key: 'overview', label: '设置总览', description: '全部设置分类', href: '/settings' },
  ...settingsNavigationItems,
];

function SettingsNavigation({ activeKey }) {
  return <nav className="settings-navigation" aria-label="设置分类">
    {settingsSidebarItems.map((item) => {
      const className = `settings-navigation-link${activeKey === item.key ? ' is-active' : ''}`;
      const content = <><span>{item.label}</span><small>{item.description}</small></>;
      return <Link key={item.key} className={className} to={item.href} aria-current={activeKey === item.key ? 'page' : undefined}>{content}</Link>;
    })}
  </nav>;
}

export function SettingsLayout({
  activeKey,
  title = '设置',
  description,
  children,
}) {
  const overview = activeKey === 'overview';
  const currentItem = settingsNavigationItems.find((item) => item.key === activeKey);
  const backTo = overview ? '/me' : '/settings';
  const backLabel = overview ? '返回我的主页' : '返回设置';
  const mobileTitle = overview ? '设置' : currentItem?.label || title;
  return <main className={`settings-page${overview ? ' is-overview' : ''}`} aria-labelledby="settings-title">
    <Link className="detail-back" to={backTo}>← {backLabel}</Link>
    <header className="settings-task-header">
      <Link to={backTo} aria-label={backLabel}><Icon name="arrow" size={19} /></Link>
      <strong>{mobileTitle}</strong>
      <span aria-hidden="true" />
    </header>
    <header className="settings-page-header">
      <div><h1 id="settings-title">{title}</h1>{description && <p>{description}</p>}</div>
    </header>
    <div className="settings-layout">
      <aside className="settings-sidebar">
        <header><strong>设置分类</strong><span>按任务查找和管理设置</span></header>
        <SettingsNavigation activeKey={activeKey} />
      </aside>
      <div className="settings-panel">{children}</div>
    </div>
  </main>;
}

export function SettingsGroup({ id, title, description, action, className = '', children }) {
  return <section id={id} className={`settings-section ${className}`.trim()}>
    <header className="settings-section-header"><div><h2>{title}</h2>{description && <p>{description}</p>}</div>{action && <div className="settings-section-action">{action}</div>}</header>
    {children}
  </section>;
}

export function SettingsRowsSkeleton({ rows = 3, label = '设置加载中' }) {
  return <div className="settings-rows-skeleton" role="status" aria-label={label}>
    <span className="sr-only">{label}</span>
    {Array.from({ length: rows }, (_, index) => <div className="settings-rows-skeleton-row" aria-hidden="true" key={index}>
      <span><i /><i /></span><i className="settings-rows-skeleton-control" />
    </div>)}
  </div>;
}
