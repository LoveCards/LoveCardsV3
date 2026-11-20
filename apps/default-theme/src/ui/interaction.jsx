import { useEffect, useRef, useState } from 'react';
import { AlertDialog } from '@astryxdesign/core/AlertDialog';
import { Banner } from '@astryxdesign/core/Banner';
import { Dialog, DialogHeader } from '@astryxdesign/core/Dialog';
import { FileInput } from '@astryxdesign/core/FileInput';
import { MoreMenu } from '@astryxdesign/core/MoreMenu';
import { Popover } from '@astryxdesign/core/Popover';
import { SegmentedControl, SegmentedControlItem } from '@astryxdesign/core/SegmentedControl';
import { Selector } from '@astryxdesign/core/Selector';
import { Switch } from '@astryxdesign/core/Switch';
import { Tooltip } from '@astryxdesign/core/Tooltip';
import { useToast } from '@astryxdesign/core/Toast';
import { Button } from './controls.jsx';
import { Icon } from './icons.jsx';
import { useDialogPresence } from './useDialogPresence.js';
import { ContextualLoginForm } from '../features/auth/ContextualLoginForm.jsx';

function blockClosingInteraction(event) {
  event.preventDefault();
  event.stopPropagation();
}

export function ActionMenu({ items, label = '更多操作', icon = <Icon name="more" size={18} />, className = '' }) {
  return <span className={`lc-action-menu ${className}`.trim()}><MoreMenu items={items} label={label} icon={icon} size="lg" /></span>;
}

export function ConfirmActionDialog({ isOpen, onOpenChange, title, description, actionLabel, isLoading, onAction }) {
  const motion = useDialogPresence(isOpen, onOpenChange, isLoading);
  const lastContent = useRef(null);
  if (isOpen) lastContent.current = { title, description, actionLabel, onAction };
  const content = !isOpen && motion.presented ? lastContent.current : { title, description, actionLabel, onAction };
  const closing = !isOpen || motion.phase === 'closing';
  if (!motion.presented && !isOpen) return null;
  return <AlertDialog ref={motion.dialogRef} className="lc-alert-dialog lc-motion-dialog" data-lc-motion-phase={motion.phase} onClickCapture={closing ? blockClosingInteraction : undefined} onKeyDownCapture={closing ? blockClosingInteraction : undefined} isOpen={motion.presented} onOpenChange={motion.requestOpenChange} title={content.title} description={content.description} cancelLabel="取消" actionLabel={content.actionLabel} isActionLoading={isLoading} onAction={closing ? () => {} : content.onAction} width="min(420px, calc(100vw - 32px))" />;
}

export function AuthPromptDialog({ isOpen, onOpenChange, action = '', onLogin, onSuccess }) {
  const [busy, setBusy] = useState(false);
  const lastOpen = useRef(false);
  const formCycle = useRef(0);
  if (lastOpen.current && !isOpen) formCycle.current += 1;
  lastOpen.current = isOpen;
  return <FormDialog isOpen={isOpen} onOpenChange={onOpenChange} title="登录 LoveCards" subtitle={action ? `继续${action}` : '欢迎回来'} className="lc-auth-prompt-dialog" footer={null} isBusy={busy} width="min(460px, calc(100vw - 32px))">
    <ContextualLoginForm key={formCycle.current} description={action ? undefined : '登录后即可继续浏览与互动。'} onSuccess={() => { onOpenChange(false); onSuccess?.(); }} onCancel={() => onOpenChange(false)} onFullPage={onLogin} onBusyChange={setBusy} />
  </FormDialog>;
}

export function ScopedBanner({ status = 'error', title, description, actionLabel, onAction, className = '' }) {
  const endContent = actionLabel
    ? <Button type="button" label={actionLabel} variant="ghost" size="lg" onClick={onAction} />
    : undefined;
  return <Banner className={`lc-status-banner ${className}`.trim()} status={status} title={title} description={description} endContent={endContent} container="card" />;
}

export function FormDialog({ isOpen, onOpenChange, title, subtitle, children, footer, isBusy = false, width = 'min(520px, calc(100vw - 32px))', className = '' }) {
  const motion = useDialogPresence(isOpen, onOpenChange, isBusy);
  useEffect(() => {
    if (!isOpen || motion.phase !== 'open') return undefined;
    const frame = requestAnimationFrame(() => {
      const dialog = motion.dialogRef.current;
      const body = dialog?.querySelector('.lc-dialog-body');
      const controls = body?.querySelectorAll('input:not([type="hidden"]):not([type="file"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), [role="combobox"]:not([aria-disabled="true"]), button:not([disabled])');
      const field = Array.from(controls || []).find((control) => control.getClientRects().length && !control.closest('[hidden], [inert], [aria-hidden="true"]'));
      if (dialog?.open && field && dialog.contains(document.activeElement)) field.focus({ preventScroll: true });
    });
    return () => cancelAnimationFrame(frame);
  }, [isOpen, motion.dialogRef, motion.phase]);
  const lastContent = useRef(null);
  if (isOpen) lastContent.current = { title, subtitle, children, footer };
  const content = !isOpen && motion.presented ? lastContent.current : { title, subtitle, children, footer };
  const closing = !isOpen || motion.phase === 'closing';
  if (!motion.presented && !isOpen) return null;
  return (
    <Dialog ref={motion.dialogRef} className={`lc-form-dialog lc-motion-dialog ${className}`.trim()} data-lc-motion-phase={motion.phase} onClickCapture={closing ? blockClosingInteraction : undefined} onKeyDownCapture={closing ? blockClosingInteraction : undefined} isOpen={motion.presented} onOpenChange={motion.requestOpenChange} width={width} maxHeight="min(75dvh, 620px)" purpose="form" padding={0}>
      <DialogHeader title={content.title} subtitle={content.subtitle} onOpenChange={isBusy || closing ? undefined : motion.requestOpenChange} hasDivider />
      <div className="lc-dialog-body">{content.children}</div>
      {content.footer && <footer className="lc-dialog-footer"><div className="lc-dialog-actions">{content.footer}</div></footer>}
    </Dialog>
  );
}

export function MediaDropzone({ label, description, value, onChange, isDisabled, disabledMessage, isLoading, status, accept, maxSize, isMultiple = false, maxFiles, placeholder }) {
  const defaultPlaceholder = isMultiple ? '拖入多个文件，或点击选择' : '拖入文件，或点击选择';
  return <FileInput className="lc-file-dropzone" label={label} description={description} value={value} onChange={onChange} accept={accept} maxSize={maxSize} isMultiple={isMultiple} maxFiles={maxFiles} mode="dropzone" width="100%" isDisabled={isDisabled} disabledMessage={isDisabled ? (disabledMessage || '当前页面为只读状态，不能选择文件。') : undefined} isLoading={isLoading} status={status} placeholder={placeholder || defaultPlaceholder} />;
}

export function ImageDropzone(props) {
  return <MediaDropzone accept="image/jpeg,image/png,image/gif,image/webp" maxSize={10 * 1024 * 1024} placeholder={props.isMultiple ? '拖入多张图片，或点击选择' : '拖入图片，或点击选择'} {...props} />;
}

export function InfoPopover({ label, children }) {
  return <Popover label={label} placement="below" alignment="end" width="min(320px, calc(100vw - 32px))" content={<div className="lc-info-popover">{children}</div>}>
    {(triggerProps) => <Button className="lc-info-trigger" type="button" {...triggerProps} label={label} variant="ghost" size="lg" icon={<Icon name="info" size={16} />} />}
  </Popover>;
}

export { Dialog, DialogHeader, FileInput, SegmentedControl, SegmentedControlItem, Selector, Switch, Tooltip, useToast };
