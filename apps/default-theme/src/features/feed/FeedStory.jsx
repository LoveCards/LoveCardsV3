import { Link } from 'react-router-dom';
import { Icon } from '../../ui/icons.jsx';
import { resolveCardPattern } from './card-patterns.js';
import { MediaFrame } from '../media/MediaFrame.jsx';
import { CardMoreMenu } from './CardMoreMenu.jsx';
import { AuthorIdentity } from '../../components/AuthorIdentity.jsx';
import { MentionText, mentionSegments } from '../../components/MentionText.jsx';

function storyFrameRatio(gridCount, index) {
  if (gridCount === 1) return 4 / 5;
  if (gridCount === 2) return 1 / 2;
  if (gridCount === 3 && index === 0) return 1 / 2;
  return 1;
}

function cardPath(card) {
  const id = card?.id;
  if (id === null || id === undefined || String(id).trim() === '') return null;
  return `/post/${encodeURIComponent(String(id))}`;
}

function StoryMedia({ card, isStaticPreview, href }) {
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
  return isStaticPreview || !href ? (
    <div className={`story-media story-media-grid-${gridCount}`}>{content}</div>
  ) : (
    <Link className={`story-media story-media-grid-${gridCount}`} to={href} aria-label={`查看内容：${card.title}`}>
      {content}
    </Link>
  );
}

function resolveFeedFamily(pattern) {
  if (pattern.family === 'media') return 'media';
  if (pattern.family === 'conversation') return 'discussion';
  return 'text';
}

function StoryCopy({ card, family, isStaticPreview, href }) {
  const content = card.content || card.title;
  const hasLinkedMention = !isStaticPreview && href && mentionSegments(content, card.mentions).some((segment) => segment.type === 'mention' && segment.actionUrl && segment.mention?.state !== 'unavailable');
  const copy = hasLinkedMention
    ? <MentionText text={content} mentions={card.mentions} renderText={(value, key) => <Link to={href} key={key}>{value}</Link>} />
    : <MentionText text={content} mentions={card.mentions} />;
  return (
    <div className={`story-copy story-copy-${family}`}>
      <p className="story-copy-primary">
        {isStaticPreview || !href || hasLinkedMention ? copy : <Link to={href}>{copy}</Link>}
      </p>
    </div>
  );
}

export function FeedStory({ card, source, isAuthenticated = false, isSaved = false, isStaticPreview = false, canReport = true, onRequireLogin, onSavedChange, onFeedback, renderAction, showMoreMenu = true, showTopics = false }) {
  const pattern = typeof card.pattern === 'object' ? card.pattern : resolveCardPattern(card.kind);
  const family = resolveFeedFamily(pattern);
  const href = cardPath(card);
  const likeCount = Number.isFinite(Number(card.likes)) ? Number(card.likes) : 0;
  const replyCount = Number.isFinite(Number(card.replies)) ? Number(card.replies) : 0;
  const renderedAction = typeof renderAction === 'function' ? renderAction(card) : null;
  const action = renderedAction && typeof renderedAction.then === 'function' ? null : renderedAction;

  return (
    <article className={`feed-story feed-story-${card.kind} feed-story-family-${family}${card.layout ? ` feed-story-layout-${card.layout}` : ''}${card.unavailable ? ' feed-story-unavailable' : ''}${isStaticPreview ? ' is-static-preview' : ''}`} aria-label={isStaticPreview ? '发布后的卡片预览' : undefined}>
      <StoryMedia card={card} isStaticPreview={isStaticPreview} href={href} />
      {family !== 'media' && <StoryCopy card={card} family={family} isStaticPreview={isStaticPreview} href={href} />}
      <footer className="story-meta">
        <AuthorIdentity author={card.author} uid={card.authorUid} handle={card.authorHandle} avatarUrl={card.authorAvatar} tone={card.kind === 'discussion' ? 'ink' : 'rose'} variant="compact" className="story-author" />
        <div className="story-meta-actions">
          <span className="story-stat story-stat-likes" aria-label={`${likeCount} 个喜欢`} title={`${likeCount} 个喜欢`}><Icon name="heart" size={14} /><span className="story-stat-label">喜欢</span><span>{likeCount}</span></span>
          <span className="story-stat story-stat-replies" aria-label={`${replyCount} 条评论`} title={`${replyCount} 条评论`}><Icon name="comment" size={14} /><span className="story-stat-label">评论</span><span>{replyCount}</span></span>
          {action && <span className="story-extra-action">{action}</span>}
          {showMoreMenu && <CardMoreMenu cardId={card.id} source={source} isAuthenticated={isAuthenticated} isSaved={isSaved} isStaticPreview={isStaticPreview} canReport={canReport} onRequireLogin={onRequireLogin} onSavedChange={onSavedChange} onFeedback={onFeedback} />}
        </div>
      </footer>
      {showTopics && card.topics?.length > 0 && <nav className="story-topics" aria-label="内容话题">{card.topics.slice(0, 2).map((topic) => <Link key={topic.id} className="story-topic" to={`/topic/${Number.isInteger(Number(topic?.id)) && Number(topic.id) > 0 ? `${topic.id}-` : ''}${encodeURIComponent(String(topic?.name || '').trim())}`} onClick={(event) => event.stopPropagation()}>#{topic.name}</Link>)}</nav>}
      <span className="sr-only">{card.meta}{showTopics && card.topic ? `，${card.topic}` : ''}，{replyCount} 条回复</span>
    </article>
  );
}
