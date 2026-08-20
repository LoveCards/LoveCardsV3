import { Button } from '../ui/index.js';
import { FeedSkeleton } from '../features/feed/FeedSkeleton.jsx';

export function ResourceState({ status, error, empty = false, onRetry, loadingVariant = 'generic', loadingClassName = '', loadingLabel = '正在加载', children }) {
  if (status === 'loading') return loadingVariant === 'feed'
    ? <FeedSkeleton className={loadingClassName} label={loadingLabel} />
    : <div className="resource-state is-loading" role="status" aria-live="polite"><span className="sr-only">{loadingLabel}</span><div className="resource-skeleton" aria-hidden="true"><i /><i /><i /></div></div>;
  if (status === 'error') return <div className="resource-state is-error" role="alert"><strong>暂时无法加载</strong><span>{error?.message || '请稍后再试。'}</span>{onRetry && <Button type="button" className="secondary-action" label="重试" variant="secondary" size="lg" onClick={onRetry} />}</div>;
  if (empty) return <div className="resource-state is-empty"><strong>这里还没有内容</strong><span>换一个关键词或稍后再来看看。</span></div>;
  return children;
}
