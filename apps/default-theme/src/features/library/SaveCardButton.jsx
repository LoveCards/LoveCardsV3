import { useCallback, useEffect, useState } from 'react';
import { Icon } from '../../ui/icons.jsx';
import { removeSavedItem, saveCard } from '../../services/community-service.js';
import { getUserFacingError } from '../../app/user-facing-error.js';

export function useCardSave({ cardId, source = 'live', isAuthenticated = false, isSaved = false, isStatic = false, onRequireLogin, onFeedback, onSavedChange }) {
  const [saved, setSaved] = useState(Boolean(isSaved));
  const [busy, setBusy] = useState(false);
  const isReadOnly = source !== 'live' || isStatic;

  useEffect(() => { setSaved(Boolean(isSaved)); }, [isSaved]);

  const toggleSaved = useCallback(async () => {
    if (busy || isReadOnly) return false;
    if (!isAuthenticated) {
      onRequireLogin?.('save');
      return false;
    }
    const next = !saved;
    setBusy(true);
    try {
      if (next) await saveCard(cardId);
      else await removeSavedItem({ resource_type: 'card', resource_id: String(cardId) });
      setSaved(next);
      onSavedChange?.(next);
      onFeedback?.(next ? '已收藏' : '已取消收藏', 'info');
      return true;
    }
    catch (error) {
      onFeedback?.(getUserFacingError(error, { fallback: next ? '内容暂时没有收藏，请稍后重试。' : '暂时无法取消收藏，请稍后重试。' }), 'error');
      return false;
    } finally {
      setBusy(false);
    }
  }, [busy, cardId, isAuthenticated, isReadOnly, onFeedback, onRequireLogin, onSavedChange, saved]);

  return { saved, busy, isReadOnly, toggleSaved };
}

export function SaveCardButton({ cardId, source = 'live', isAuthenticated = false, isSaved = false, isStatic = false, onRequireLogin, onFeedback, onSavedChange, className = '' }) {
  const { saved, busy, isReadOnly, toggleSaved } = useCardSave({ cardId, source, isAuthenticated, isSaved, isStatic, onRequireLogin, onFeedback, onSavedChange });
  const handleClick = async (event) => {
    event.preventDefault();
    event.stopPropagation();
    await toggleSaved();
  };
  const label = source !== 'live' ? '收藏暂未开放' : isStatic ? (saved ? '已收藏' : '收藏') : !isAuthenticated ? '登录后收藏' : saved ? (busy ? '取消中' : '已收藏') : busy ? '收藏中' : '收藏';
  return <button type="button" className={`save-card-button${saved ? ' is-saved' : ''}${busy ? ' is-busy' : ''} ${className}`.trim()} onClick={handleClick} disabled={busy || isReadOnly} aria-label={label} aria-pressed={saved} title={label}>
    <Icon name="bookmark" size={17} /> <span>{label}</span>
  </button>;
}
