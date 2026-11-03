import { useCallback, useEffect, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { AuthPromptDialog, useToast } from '../../ui/index.js';
import { FeedStory } from './FeedStory.jsx';
import { useFeedMasonry } from './use-feed-masonry.js';
import { listSavedItems } from '../../services/community-service.js';

export function FeedCollection({ items, source, className, ariaLabel }) {
  const masonryRef = useRef(null);
  const toast = useToast();
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated, session } = useAuth();
  const [savedIds, setSavedIds] = useState(() => new Set());
  const [authPromptAction, setAuthPromptAction] = useState('');

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
    setAuthPromptAction(action === 'report' ? '举报内容' : '收藏内容');
  }, []);
  const updateSavedState = useCallback((cardId, saved) => {
    setSavedIds((current) => {
      const next = new Set(current);
      const numericId = Number(cardId);
      if (saved) next.add(numericId);
      else next.delete(numericId);
      return next;
    });
  }, []);

  useFeedMasonry(masonryRef, items);

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
          canReport={!isAuthenticated || (card.ownerUid ? card.ownerUid !== session?.uid : Number(card.ownerId) !== Number(session?.uid))}
          onRequireLogin={requestLogin}
          onSavedChange={(saved) => updateSavedState(card.id, saved)}
          onFeedback={showFeedback}
          key={card.id}
        />
      ))}
      <AuthPromptDialog isOpen={Boolean(authPromptAction)} action={authPromptAction} onOpenChange={(open) => { if (!open) setAuthPromptAction(''); }} onLogin={() => { setAuthPromptAction(''); navigate('/auth/login', { state: { from: `${location.pathname}${location.search}` } }); }} />
    </section>
  );
}
