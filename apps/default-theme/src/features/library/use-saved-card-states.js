import { useEffect, useRef, useState } from 'react';
import { loadSavedCardIds } from '../../services/community-service.js';

export function useSavedCardStates(cardIds, viewerUid) {
  const idKey = [...new Set(cardIds.map(Number).filter((id) => Number.isSafeInteger(id) && id > 0))].join(',');
  const [snapshot, setSnapshot] = useState({ viewerUid: null, values: new Map() });
  const localChanges = useRef({ viewerUid: null, values: new Map() });

  useEffect(() => {
    if (localChanges.current.viewerUid !== viewerUid) {
      localChanges.current = { viewerUid, values: new Map() };
    }
    if (!viewerUid || !idKey) return;
    let active = true;
    const ids = idKey.split(',').map(Number);
    const changes = new Map(localChanges.current.values);
    loadSavedCardIds(ids).then((saved) => {
      if (!active) return;
      const values = new Map(ids.map((id) => {
        const change = localChanges.current.values.get(id);
        return [id, change !== changes.get(id) ? change.saved : saved.has(id)];
      }));
      setSnapshot({ viewerUid, values });
    }).catch(() => {
      if (active) setSnapshot((current) => current.viewerUid === viewerUid ? current : { viewerUid, values: new Map() });
    });
    return () => { active = false; };
  }, [idKey, viewerUid]);

  function updateSavedState(cardId, saved) {
    if (localChanges.current.viewerUid !== viewerUid) return;
    localChanges.current.values.set(Number(cardId), { saved });
    setSnapshot((current) => {
      const values = current.viewerUid === viewerUid ? new Map(current.values) : new Map();
      values.set(Number(cardId), saved);
      return { viewerUid, values };
    });
  }

  const values = snapshot.viewerUid === viewerUid ? snapshot.values : null;
  return { savedState: (cardId) => values?.get(Number(cardId)) ?? null, updateSavedState };
}
