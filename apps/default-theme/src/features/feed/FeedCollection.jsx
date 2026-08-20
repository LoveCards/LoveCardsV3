import { useCallback, useEffect, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { useToast } from '../../ui/index.js';
import { FeedStory } from './FeedStory.jsx';
import { listSavedItems } from '../../services/community-service.js';

export function FeedCollection({ items, source, className, ariaLabel }) {
  const masonryRef = useRef(null);
  const toast = useToast();
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated, session } = useAuth();
  const [savedIds, setSavedIds] = useState(() => new Set());

  useEffect(() => {
    let active = true;
    if (source !== 'live' || !isAuthenticated) { setSavedIds(new Set()); return () => { active = false; }; }
    listSavedItems().then((page) => {
      if (!active) return;
      setSavedIds(new Set(page.items.filter((item) => String(item.resource_type || 'card') === 'card').map((item) => Number(item.resource_id)).filter(Number.isFinite)));
    }).catch(() => { if (active) setSavedIds(new Set()); });
    return () => { active = false; };
  }, [isAuthenticated, source]);

  const showFeedback = useCallback((message, type = 'info') => {
    toast({ body: message, type, uniqueID: 'feed-action-feedback', collisionBehavior: 'overwrite', autoHideDuration: 2200 });
  }, [toast]);

  const requestLogin = useCallback((action) => {
    toast({ body: action === 'report' ? '登录后即可举报内容' : '登录后即可收藏内容', uniqueID: 'feed-login-required', collisionBehavior: 'overwrite' });
    navigate('/auth/login', { state: { from: `${location.pathname}${location.search}` } });
  }, [location.pathname, location.search, navigate, toast]);
  const updateSavedState = useCallback((cardId, saved) => {
    setSavedIds((current) => {
      const next = new Set(current);
      const numericId = Number(cardId);
      if (saved) next.add(numericId);
      else next.delete(numericId);
      return next;
    });
  }, []);

  useEffect(() => {
    const element = masonryRef.current;
    if (!element || !items.length) return undefined;
    let active = true;
    let masonry;
    let observer;
    let frame;
    const schedule = (callback) => typeof requestAnimationFrame === 'function' ? requestAnimationFrame(callback) : setTimeout(callback, 0);
    const cancel = (handle) => typeof cancelAnimationFrame === 'function' ? cancelAnimationFrame(handle) : clearTimeout(handle);
    import('masonry-layout').then(({ default: Masonry }) => {
      if (!active) return;
      masonry = new Masonry(element, {
        itemSelector: '.feed-story',
      columnWidth: '.masonry-column-sizer',
      gutter: '.masonry-gutter-sizer',
      percentPosition: true,
      transitionDuration: 0,
      });
      frame = schedule(() => masonry.layout());
      observer = typeof ResizeObserver === 'function' ? new ResizeObserver(() => {
        cancel(frame);
        frame = schedule(() => masonry.layout());
      }) : null;
      element.querySelectorAll('.feed-story').forEach((item) => observer?.observe(item));
    });
    return () => {
      active = false;
      cancel(frame);
      observer?.disconnect();
      masonry?.destroy();
    };
  }, [items]);

  return (
    <section ref={masonryRef} className={`${className} feed-masonry`} aria-label={ariaLabel}>
      <span className="masonry-column-sizer" aria-hidden="true" />
      <span className="masonry-gutter-sizer" aria-hidden="true" />
      {items.map((card) => (
        <FeedStory
          card={card}
          source={source}
          isAuthenticated={isAuthenticated}
          isSaved={savedIds.has(Number(card.id))}
          canReport={!isAuthenticated || Number(card.ownerId) !== Number(session?.uid)}
          onRequireLogin={requestLogin}
          onSavedChange={(saved) => updateSavedState(card.id, saved)}
          onFeedback={showFeedback}
          key={card.id}
        />
      ))}
    </section>
  );
}
