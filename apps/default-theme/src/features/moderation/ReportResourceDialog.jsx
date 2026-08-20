import { useState } from 'react';
import { reportResource } from '../../services/community-service.js';
import { Button, FormDialog, Selector, TextArea } from '../../ui/index.js';

const REPORT_OPTIONS = [
  { value: 'spam', label: '垃圾信息' },
  { value: 'abuse', label: '骚扰或辱骂' },
  { value: 'privacy', label: '侵犯隐私' },
  { value: 'illegal', label: '违法内容' },
  { value: 'other', label: '其他' },
];

export function ReportResourceDialog({ isOpen, onOpenChange, resourceType, resourceId, title = '举报内容', onFeedback }) {
  const [reason, setReason] = useState('spam');
  const [details, setDetails] = useState('');
  const [busy, setBusy] = useState(false);

  const close = () => {
    if (busy) return;
    setReason('spam');
    setDetails('');
    onOpenChange(false);
  };

  const submit = async () => {
    if (busy || !resourceId) return;
    setBusy(true);
    try {
      await reportResource({
        resource_type: resourceType,
        resource_id: String(resourceId),
        reason,
        details: details.trim(),
      });
      setReason('spam');
      setDetails('');
      onOpenChange(false);
      onFeedback?.('举报已提交，我们会按流程处理', 'info');
    } catch (error) {
      onFeedback?.(error?.message || '举报提交失败，请稍后重试', 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <FormDialog
      isOpen={isOpen}
      onOpenChange={(open) => { if (!open) close(); }}
      title={title}
      subtitle="请选择最符合的原因，我们会按社区规则处理。"
      isBusy={busy}
      footer={<><Button type="button" label="取消" variant="ghost" size="lg" isDisabled={busy} onClick={close} /><Button type="button" label="提交举报" variant="primary" size="lg" isLoading={busy} isDisabled={busy} clickAction={submit} /></>}
    >
      <Selector label="举报原因" options={REPORT_OPTIONS} value={reason} onChange={setReason} width="100%" />
      <TextArea label="补充说明" value={details} onChange={setDetails} maxLength={2000} rows={5} placeholder="可选，说明具体情况" width="100%" />
    </FormDialog>
  );
}
