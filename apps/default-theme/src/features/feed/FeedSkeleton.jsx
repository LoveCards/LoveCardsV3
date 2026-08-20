const skeletonStories = [
  { kind: 'image', family: 'media', titleLines: 0 },
  { kind: 'text', family: 'text', titleLines: 3 },
  { kind: 'gallery', family: 'media', titleLines: 0 },
  { kind: 'discussion', family: 'discussion', titleLines: 2 },
  { kind: 'video', family: 'media', titleLines: 0 },
  { kind: 'text', family: 'text', titleLines: 4 },
];

function SkeletonLine({ className = '' }) {
  return <i className={`feed-skeleton-line ${className}`.trim()} aria-hidden="true" />;
}

function SkeletonMeta() {
  return (
    <footer className="story-meta feed-skeleton-meta" aria-hidden="true">
      <span className="feed-skeleton-identity"><i className="feed-skeleton-avatar" /><SkeletonLine className="is-author" /></span>
      <span className="feed-skeleton-actions"><SkeletonLine className="is-action" /><SkeletonLine className="is-action" /><SkeletonLine className="is-menu" /></span>
    </footer>
  );
}

function SkeletonStory({ story }) {
  const isMedia = story.family === 'media';
  return (
    <article className={`feed-story feed-story-${story.kind} feed-story-family-${story.family} feed-story-skeleton`} aria-hidden="true">
      {isMedia ? <div className="story-media feed-skeleton-media"><div className="feed-skeleton-media-block" /></div> : <div className="story-copy feed-skeleton-copy">
        {Array.from({ length: story.titleLines }, (_, index) => <SkeletonLine className={index === story.titleLines - 1 ? 'is-short' : ''} key={index} />)}
        <SkeletonLine className="is-copy" />
      </div>}
      <SkeletonMeta />
    </article>
  );
}

export function FeedSkeleton({ className = '', label = '内容加载中' }) {
  return (
    <section className={`feed-skeleton-grid feed-masonry ${className}`.trim()} aria-label={label} role="status">
      <span className="sr-only">{label}</span>
      {skeletonStories.map((story, index) => <SkeletonStory story={story} key={`${story.kind}-${index}`} />)}
    </section>
  );
}
