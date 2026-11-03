import { Button, EmptyState } from '../ui/index.js';
import { ScopedBanner } from '../ui/interaction.jsx';
import { FeedSkeleton } from '../features/feed/FeedSkeleton.jsx';
import { getResourceErrorPresentation } from '../app/user-facing-error.js';

const resourceCopy = {
  forbidden: ['当前内容不可访问', '当前账号没有查看这部分内容的权限。'],
  'not-found': ['没有找到相关内容', '内容可能已删除，或链接已经失效。'],
  deleted: ['内容已删除', '这项内容已经不存在，无法继续打开。'],
  restricted: ['当前内容受限', '由于可见范围限制，这项内容暂时无法查看。'],
  unavailable: ['当前内容不可用', '这项内容暂时无法访问，请稍后再试。'],
  offline: ['网络连接不稳定', '请检查网络后重试，已经显示的内容不会丢失。'],
};

function stateClassName(status, className) {
  return `resource-state is-${status} ${className}`.trim();
}

export function ResourceErrorBanner({ error, onRetry, className = '' }) {
  const presentation = getResourceErrorPresentation(error);
  return <ScopedBanner
    className={`resource-error-banner is-${presentation.kind} ${className}`.trim()}
    title={presentation.title}
    description={presentation.description}
    actionLabel={onRetry && presentation.retryable ? presentation.actionLabel : undefined}
    onAction={onRetry}
  />;
}

export function ResourceState({
  status,
  error,
  empty = false,
  onRetry,
  loadingVariant = 'generic',
  loadingClassName = '',
  loadingLabel = '正在加载',
  title,
  description,
  actionLabel = '重试加载',
  emptyTitle = '这里还没有内容',
  emptyDescription = '换一个关键词或稍后再来看看。',
  emptyAction,
  headingLevel = 2,
  className = '',
  children,
}) {
  if (status === 'loading') return loadingVariant === 'feed'
    ? <FeedSkeleton className={loadingClassName} label={loadingLabel} />
    : <div className={stateClassName('loading', className)} role="status" aria-live="polite"><span className="sr-only">{loadingLabel}</span><div className="resource-skeleton" aria-hidden="true"><i /><i /><i /></div></div>;
  if (status === 'error') {
    const presentation = getResourceErrorPresentation(error);
    const hasConcreteError = Boolean(error);
    const resolvedActionLabel = hasConcreteError ? presentation.actionLabel : actionLabel;
    const showAction = Boolean(onRetry) && (hasConcreteError ? presentation.retryable : Boolean(actionLabel));
    return <div className={stateClassName(presentation.kind, className)} role="alert" data-error-kind={presentation.kind}><strong>{hasConcreteError ? presentation.title : title || presentation.title}</strong><span>{hasConcreteError ? presentation.description : description || presentation.description}</span>{showAction && <Button type="button" className="secondary-action" label={resolvedActionLabel} variant="secondary" size="lg" onClick={onRetry} />}</div>;
  }
  if (resourceCopy[status]) {
    const [defaultTitle, defaultDescription] = resourceCopy[status];
    const role = status === 'offline' ? 'alert' : 'status';
    return <div className={stateClassName(status, className)} role={role}><strong>{title || defaultTitle}</strong><span>{description || defaultDescription}</span>{onRetry && <Button type="button" className="secondary-action" label={actionLabel} variant="secondary" size="lg" onClick={onRetry} />}</div>;
  }
  // Empty is only evaluated after request and access failures. A failed
  // collection starts with no items too, but that is not a valid empty result.
  if (empty || status === 'empty') return <EmptyState className={stateClassName('empty', className)} title={emptyTitle} description={emptyDescription} actions={emptyAction} headingLevel={headingLevel} isCompact />;
  return children;
}
