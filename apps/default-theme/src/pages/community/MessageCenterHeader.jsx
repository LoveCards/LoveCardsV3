export function MessageCenterHeader({ description, actions, className = '' }) {
  return <header className={`community-page-header message-center-header ${className}`.trim()}>
    <div>
      <h1 id="message-center-title">消息中心</h1>
      {description && <p>{description}</p>}
    </div>
    {actions && <div className="message-center-header-actions">{actions}</div>}
  </header>;
}
