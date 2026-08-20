import { AlertDialog } from '@astryxdesign/core/AlertDialog';
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

export function ActionMenu({ items, label = '更多操作', icon = <Icon name="more" size={18} />, className = '' }) {
  return <span className={`lc-action-menu ${className}`.trim()}><MoreMenu items={items} label={label} icon={icon} size="lg" /></span>;
}

export function ConfirmActionDialog({ isOpen, onOpenChange, title, description, actionLabel, isLoading, onAction }) {
  return <AlertDialog className="lc-alert-dialog" isOpen={isOpen} onOpenChange={onOpenChange} title={title} description={description} cancelLabel="取消" actionLabel={actionLabel} isActionLoading={isLoading} onAction={onAction} width="min(420px, calc(100vw - 32px))" />;
}

export function FormDialog({ isOpen, onOpenChange, title, subtitle, children, footer, isBusy = false, width = 'min(520px, calc(100vw - 32px))', className = '' }) {
  const requestOpenChange = (open) => {
    if (!open && isBusy) return;
    onOpenChange(open);
  };
  return (
    <Dialog className={`lc-form-dialog ${className}`.trim()} isOpen={isOpen} onOpenChange={requestOpenChange} width={width} maxHeight="min(75dvh, 620px)" purpose="form" padding={0}>
      <DialogHeader title={title} subtitle={subtitle} onOpenChange={isBusy ? undefined : requestOpenChange} hasDivider />
      <div className="lc-dialog-body">{children}</div>
      <footer className="lc-dialog-footer"><div className="lc-dialog-actions">{footer}</div></footer>
    </Dialog>
  );
}

export function MediaDropzone({ label, description, value, onChange, isDisabled, disabledMessage, isLoading, status, accept, maxSize, isMultiple = false, maxFiles, placeholder }) {
  const defaultPlaceholder = isMultiple ? '拖入多个文件，或点击选择' : '拖入文件，或点击选择';
  return <FileInput className="lc-file-dropzone" label={label} description={description} value={value} onChange={onChange} accept={accept} maxSize={maxSize} isMultiple={isMultiple} maxFiles={maxFiles} mode="dropzone" width="100%" isDisabled={isDisabled} disabledMessage={isDisabled ? (disabledMessage || '当前页面为只读状态，不能选择文件。') : undefined} isLoading={isLoading} status={status} placeholder={placeholder || defaultPlaceholder} />;
}

export function ImageDropzone(props) {
  return <MediaDropzone accept="image/jpeg,image/png,image/webp" maxSize={12 * 1024 * 1024} placeholder={props.isMultiple ? '拖入多张图片，或点击选择' : '拖入图片，或点击选择'} {...props} />;
}

export function InfoPopover({ label, children }) {
  return <Popover label={label} placement="below" alignment="end" width="min(320px, calc(100vw - 32px))" content={<div className="lc-info-popover">{children}</div>}>
    {(triggerProps) => <Button className="lc-info-trigger" type="button" {...triggerProps} label={label} variant="ghost" size="lg" icon={<Icon name="info" size={16} />} />}
  </Popover>;
}

export { Dialog, DialogHeader, SegmentedControl, SegmentedControlItem, Selector, Switch, Tooltip, useToast };
