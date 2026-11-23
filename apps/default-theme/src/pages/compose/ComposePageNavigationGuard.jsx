import { useEffect } from 'react';
import { useBlocker } from 'react-router-dom';

export function ComposePageNavigationGuard({ dirty, allowLeaveRef, onBlocked }) {
  const blocker = useBlocker(() => dirty && !allowLeaveRef.current);
  useEffect(() => {
    if (blocker.state === 'blocked') onBlocked(blocker);
  }, [blocker.state, onBlocked]);
  return null;
}
