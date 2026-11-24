import { useCallback, useRef, useState } from 'react';
import { useLocation, useNavigate, useNavigationType } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { AuthPromptDialog, useToast } from '../../ui/index.js';
import { FeedStory } from './FeedStory.jsx';
import { useFeedMasonry } from './use-feed-masonry.js';
import { useSavedCardStates } from '../library/use-saved-card-states.js';

export function FeedCollection({ items, source, className, ariaLabel }) {
  const masonryRef = useRef(null);
  const toast = useToast();
  const navigate = useNavigate();
  const location = useLocation();
  const navigationType = useNavigationType();
  const { isAuthenticated, session } = useAuth();
  const { savedState, updateSavedState } = useSavedCardStates(items.map((card) => card.id), source === 'live' && isAuthenticated ? session?.uid : null);
  const [authPromptAction, setAuthPromptAction] = useState('');

  const showFeedback = useCallback((message, type = 'info') => {
    toast({ body: message, type, uniqueID: 'feed-action-feedback', collisionBehavior: 'overwrite', autoHideDuration: 2200 });
  }, [toast]);

  const requestLogin = useCallback((action) => {
    setAuthPromptAction(action === 'report' ? '举报内容' : '收藏内容');
  }, []);
  useFeedMasonry(masonryRef, items);

  return (
    <section ref={masonryRef} className={`${className} feed-masonry${navigationType === 'POP' ? '' : ' lc-content-enter'}`} aria-label={ariaLabel}>
      <span className="masonry-column-sizer" aria-hidden="true" />
      <span className="masonry-gutter-sizer" aria-hidden="true" />
      {items.map((card) => (
        <FeedStory
          card={card}
          source={source}
          isAuthenticated={isAuthenticated}
          isSaved={savedState(card.id)}
          canReport={!isAuthenticated || (card.ownerUid ? card.ownerUid !== session?.uid : Number(card.ownerId) !== Number(session?.uid))}
          onRequireLogin={requestLogin}
          onSavedChange={(saved) => updateSavedState(card.id, saved)}
          onFeedback={showFeedback}
          key={card.id}
        />
      ))}
      <AuthPromptDialog isOpen={Boolean(authPromptAction)} action={authPromptAction} onOpenChange={(open) => { if (!open) setAuthPromptAction(''); }} onLogin={() => { setAuthPromptAction(''); navigate('/auth/login', { state: { from: `${location.pathname}${location.search}${location.hash}` } }); }} />
    </section>
  );
}
