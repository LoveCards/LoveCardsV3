import { useCallback, useContext, useEffect, useRef, useState } from 'react';
import { UNSAFE_DataRouterContext, useNavigate } from 'react-router-dom';
import { useUnsavedChanges } from '../../app/use-unsaved-changes.js';

export function useComposeNavigation({ isDialog, dirty, busy, returnDestination, onBlocked }) {
  const navigate = useNavigate();
  const dataRouter = useContext(UNSAFE_DataRouterContext);
  const allowLeaveRef = useRef(false);
  const blockedNavigation = useRef(null);
  const discardAction = useRef('leave');
  const [dialogOpen, setDialogOpen] = useState(true);
  const [discardOpen, setDiscardOpen] = useState(false);
  const [discardDestination, setDiscardDestination] = useState('/');

  const requestDiscard = useCallback((destination = returnDestination, action = 'leave') => {
    discardAction.current = action;
    setDiscardDestination(destination);
    setDiscardOpen(true);
  }, [returnDestination]);

  const onNavigationBlocked = useCallback((blocker) => {
    onBlocked();
    discardAction.current = 'leave';
    blockedNavigation.current = blocker;
    setDiscardOpen(true);
  }, [onBlocked]);

  useEffect(() => {
    if (dirty) allowLeaveRef.current = false;
  }, [dirty]);
  useUnsavedChanges(dirty, requestDiscard, { interceptLinks: !dataRouter && !isDialog });

  const requestClose = useCallback(() => {
    if (busy) return;
    if (dirty) requestDiscard(returnDestination, 'close');
    else setDialogOpen(false);
  }, [busy, dirty, requestDiscard, returnDestination]);

  const closeOrReturn = () => {
    if (isDialog) requestClose();
    else if (dirty) requestDiscard(returnDestination);
    else navigate(returnDestination);
  };
  const onDialogExited = useCallback(() => navigate(-1), [navigate]);

  const cancelDiscard = () => {
    setDiscardOpen(false);
    const blocker = blockedNavigation.current;
    if (blocker?.state === 'blocked') blocker.reset();
    blockedNavigation.current = null;
  };
  const confirmDiscard = () => {
    setDiscardOpen(false);
    allowLeaveRef.current = true;
    const blocker = blockedNavigation.current;
    blockedNavigation.current = null;
    if (isDialog && discardAction.current === 'close') setDialogOpen(false);
    else if (blocker?.state === 'blocked') blocker.proceed();
    else navigate(discardDestination);
  };

  return {
    dialogOpen, discardOpen, allowLeaveRef, onNavigationBlocked, requestClose,
    closeOrReturn, onDialogExited, cancelDiscard, confirmDiscard, useDataRouterGuard: !isDialog && Boolean(dataRouter),
  };
}
