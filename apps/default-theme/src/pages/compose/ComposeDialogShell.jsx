import { useEffect, useLayoutEffect, useRef } from 'react';
import { useBlocker } from 'react-router-dom';
import { Dialog } from '../../ui/interaction.jsx';
import { useDialogPresence } from '../../ui/useDialogPresence.js';

export function ComposeDialogShell({ children, step, isOpen, dirty, busy, allowLeaveRef, onRequestClose, onNavigationBlocked, onExited }) {
  const blocker = useBlocker(() => dirty && !allowLeaveRef.current);
  const motion = useDialogPresence(isOpen, (open) => { if (!open) (step?.onCancel || onRequestClose)(); }, busy && !step);
  const lastEditorFocus = useRef(null);
  const editorRef = useRef(null);
  const stepRef = useRef(null);
  const hadStep = useRef(false);
  const stepTitle = step?.title;
  useLayoutEffect(() => {
    if (stepTitle) {
      hadStep.current = true;
      const prompt = stepRef.current;
      const target = prompt?.querySelector('input:not(:disabled)') || prompt?.querySelector('button:not(:disabled)');
      target?.focus({ preventScroll: true });
      return;
    }
    if (!hadStep.current || !isOpen) return;
    hadStep.current = false;
    const editor = editorRef.current;
    const source = lastEditorFocus.current;
    const frame = requestAnimationFrame(() => {
      const target = editor?.contains(source) && !source.matches(':disabled') ? source : editor?.querySelector('.compose-back');
      target?.focus({ preventScroll: true });
    });
    return () => cancelAnimationFrame(frame);
  }, [isOpen, stepTitle]);

  useEffect(() => {
    if (blocker.state !== 'blocked') return;
    if (busy) blocker.reset();
    else onNavigationBlocked(blocker);
  }, [blocker, busy, onNavigationBlocked]);

  useEffect(() => {
    if (!isOpen && !motion.presented) onExited();
  }, [isOpen, motion.presented, onExited]);

  return <Dialog ref={motion.dialogRef} className="lc-motion-dialog lc-compose-dialog" data-lc-motion-phase={motion.phase} isOpen={motion.presented} onOpenChange={motion.requestOpenChange} purpose="form" width="min(900px, calc(100vw - 48px))" maxHeight="min(90dvh, 900px)" padding={0} aria-labelledby={step ? 'compose-step-title' : 'compose-title'} aria-describedby={step ? 'compose-step-description' : undefined}>
    <div ref={editorRef} className="compose-dialog-editor" inert={Boolean(step)} onFocusCapture={(event) => { lastEditorFocus.current = event.target; }}>{children}</div>
    {step && <section ref={stepRef} className="compose-dialog-step">
      <div><h2 id="compose-step-title">{step.title}</h2><p id="compose-step-description">{step.description}</p>
        {step.body}
        <footer>{step.footer}</footer>
      </div>
    </section>}
  </Dialog>;
}
