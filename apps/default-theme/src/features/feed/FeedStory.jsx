import { Link } from 'react-router-dom';
import { Avatar, Icon } from '../../ui/icons.jsx';
import { resolveCardPattern } from './card-patterns.js';
import { MediaFrame } from '../media/MediaFrame.jsx';
import { CardMoreMenu } from './CardMoreMenu.jsx';

function storyFrameRatio(gridCount, index) {
  if (gridCount === 1) return 4 / 5;
  if (gridCount === 2) return 1 / 2;
  if (gridCount === 3 && index === 0) return 1 / 2;
  return 1;
}

function StoryMedia({ card, isStaticPreview }) {
  const mediaItems = card.mediaItems?.length
    ? card.mediaItems
    : card.mediaUrl ? [{ url: card.mediaUrl, type: 'image', alt: card.mediaAlt || card.title, fit: card.mediaFit || 'smart' }] : [];
  if (!mediaItems.length) return null;
  const visibleItems = mediaItems.slice(0, 4);
  const gridCount = visibleItems.length;
  const moreCount = mediaItems.length - visibleItems.length;
  const content = (
    <>
      {visibleItems.map((item, index) => (
        <MediaFrame className="story-media-item" item={item} fit={item.fit || card.mediaFit} frameRatio={storyFrameRatio(gridCount, index)} loading={card.layout === 'lead' && index === 0 ? 'eager' : 'lazy'} fetchPriority={card.layout === 'lead' && index === 0 ? 'high' : 'auto'} muted ariaHidden={!isStaticPreview} alt={item.alt || card.mediaAlt || card.title} key={`${item.url}-${index}`}>
          {item.type === 'video' && <span className="story-media-type"><Icon name="play" size={14} /> 视频</span>}
          {item.type === 'gif' && <span className="story-media-type">GIF</span>}
          {moreCount > 0 && index === visibleItems.length - 1 && <span className="story-media-more">+{moreCount}</span>}
        </MediaFrame>
      ))}
      {mediaItems.length > 1 && <span className="story-media-count" aria-label={`${mediaItems.length} 项媒体`}>{mediaItems.length}</span>}
      {card.mediaLabel && <span className="story-media-label">{card.mediaLabel}</span>}
      {card.duration && <span className="story-media-duration">{card.duration}</span>}
    </>
  );
  return isStaticPreview ? (
    <div className={`story-media story-media-grid-${gridCount}`}>{content}</div>
  ) : (
    <Link className={`story-media story-media-grid-${gridCount}`} to={`/post/${card.id}`} aria-label={`查看内容：${card.title}`}>
      {content}
    </Link>
  );
}

function AuthorIdentity({ card }) {
  const content = (
    <>
      <Avatar label={card.author} tone={card.kind === 'discussion' ? 'ink' : 'rose'} size="sm" />
      <span>{card.author}</span>
    </>
  );
  return card.authorHref
    ? <Link className="story-author" to={card.authorHref}>{content}</Link>
    : <span className="story-author" title="公开用户主页暂未开放">{content}</span>;
}

function resolveFeedFamily(pattern) {
  if (pattern.family === 'media') return 'media';
  if (pattern.family === 'conversation') return 'discussion';
  return 'text';
}

function StoryCopy({ card, family, isStaticPreview }) {
  return (
    <div className={`story-copy story-copy-${family}`}>
      <h2>
        {isStaticPreview ? <span>{card.title}</span> : <Link to={`/post/${card.id}`}>{card.title}</Link>}
      </h2>
      {card.body && <p>{card.body}</p>}
    </div>
  );
}

export function FeedStory({ card, source, isAuthenticated = false, isSaved = false, isStaticPreview = false, canReport = true, onRequireLogin, onSavedChange, onFeedback }) {
  const pattern = typeof card.pattern === 'object' ? card.pattern : resolveCardPattern(card.kind);
  const family = resolveFeedFamily(pattern);
  const likeCount = Number.isFinite(Number(card.likes)) ? Number(card.likes) : 0;
  const replyCount = Number.isFinite(Number(card.replies)) ? Number(card.replies) : 0;

  return (
    <article className={`feed-story feed-story-${card.kind} feed-story-family-${family}${card.layout ? ` feed-story-layout-${card.layout}` : ''}${isStaticPreview ? ' is-static-preview' : ''}`} aria-label={isStaticPreview ? '发布后的卡片预览' : undefined}>
      <StoryMedia card={card} isStaticPreview={isStaticPreview} />
      {family !== 'media' && <StoryCopy card={card} family={family} isStaticPreview={isStaticPreview} />}
      <footer className="story-meta">
        <AuthorIdentity card={card} />
        <div className="story-meta-actions">
          <span className="story-stat story-stat-likes" aria-label={`${likeCount} 个喜欢`} title={`${likeCount} 个喜欢`}><Icon name="heart" size={14} /><span className="story-stat-label">喜欢</span><span>{likeCount}</span></span>
          <span className="story-stat story-stat-replies" aria-label={`${replyCount} 条评论`} title={`${replyCount} 条评论`}><Icon name="comment" size={14} /><span className="story-stat-label">评论</span><span>{replyCount}</span></span>
          <CardMoreMenu cardId={card.id} source={source} isAuthenticated={isAuthenticated} isSaved={isSaved} isStaticPreview={isStaticPreview} canReport={canReport} onRequireLogin={onRequireLogin} onSavedChange={onSavedChange} onFeedback={onFeedback} />
        </div>
      </footer>
      <span className="sr-only">{card.meta}{card.topic ? `，${card.topic}` : ''}，{replyCount} 条回复</span>
    </article>
  );
}
