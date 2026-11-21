import { useEffect } from 'react';
import { useBlocker } from 'react-router-dom';
import { Dialog } from '../../ui/interaction.jsx';
import { useDialogPresence } from '../../ui/useDialogPresence.js';

export function ComposeDialogShell({ children, isOpen, dirty, busy, allowLeaveRef, onRequestClose, onNavigationBlocked, onExited }) {
  const blocker = useBlocker(() => dirty && !allowLeaveRef.current);
  const motion = useDialogPresence(isOpen, (open) => { if (!open) onRequestClose(); }, busy);

  useEffect(() => {
    if (blocker.state === 'blocked') onNavigationBlocked(blocker);
  }, [blocker.state, onNavigationBlocked]);

  useEffect(() => {
    if (!isOpen && !motion.presented) onExited();
  }, [isOpen, motion.presented, onExited]);

  return <Dialog ref={motion.dialogRef} className="lc-motion-dialog lc-compose-dialog" data-lc-motion-phase={motion.phase} isOpen={motion.presented} onOpenChange={motion.requestOpenChange} purpose="form" width="min(900px, calc(100vw - 48px))" maxHeight="min(90dvh, 900px)" padding={0} aria-labelledby="compose-title">{children}</Dialog>;
}
