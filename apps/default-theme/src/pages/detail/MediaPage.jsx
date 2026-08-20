import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useResource } from '../../app/use-resource.js';
import { ResourceState } from '../../components/ResourceState.jsx';
import { MediaRouteViewer } from '../../features/media/MediaGallery.jsx';
import { getDetailSnapshot, loadDetail } from '../../services/detail-service.js';

function mediaItems(card) {
  if (Array.isArray(card?.mediaItems) && card.mediaItems.length) return card.mediaItems;
  return card?.mediaUrl ? [{ url: card.mediaUrl, alt: card.mediaAlt || card.title, fit: card.mediaFit || 'smart' }] : [];
}

function MediaBoundary({ id, title, detail }) {
  return (
    <section className="media-route-boundary" role="status">
      <h1>{title}</h1>
      <p>{detail}</p>
      <Link className="action-link" to={`/post/${id}`}>返回内容详情</Link>
    </section>
  );
}

export function MediaPage() {
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const requestedItem = Number(searchParams.get('index') || 1);
  const requestedIndex = Number.isInteger(requestedItem) && requestedItem > 0 ? requestedItem - 1 : 0;
  const loader = useCallback((signal) => loadDetail({ id, signal }), [id]);
  const initial = useMemo(() => getDetailSnapshot(id), [id]);
  const resource = useResource(loader, initial);
  const data = resource.data || initial;
  const items = useMemo(() => mediaItems(data.card), [data.card]);
  const [activeIndex, setActiveIndex] = useState(requestedIndex);

  useEffect(() => {
    setActiveIndex(items.length ? Math.min(requestedIndex, items.length - 1) : 0);
  }, [items.length, requestedIndex]);

  let content;
  if (data.source === 'configuration-error') {
    content = <MediaBoundary id={id} title="媒体暂不可用" detail={data.detail} />;
  } else if (data.source !== 'loading' && !data.card) {
    content = <MediaBoundary id={id} title="没有找到这条内容" detail="它可能已被删除、移动，或当前不可见。" />;
  } else if (data.card && !items.length) {
    content = <MediaBoundary id={id} title="这条内容没有媒体" detail="可以返回内容详情继续阅读和参与讨论。" />;
  } else if (data.card) {
    content = <MediaRouteViewer items={items} title={data.card.title} activeIndex={activeIndex} onIndexChange={(nextIndex) => { setActiveIndex(nextIndex); const next = new URLSearchParams(searchParams); next.set('index', String(nextIndex + 1)); navigate(`/media/${id}?${next.toString()}`, { replace: true }); }} onClose={() => navigate(`/post/${id}`, { replace: true })} />;
  }

  return (
    <main className="media-route-page">
      <ResourceState status={resource.status} error={resource.error} onRetry={resource.reload}>{content}</ResourceState>
    </main>
  );
}
