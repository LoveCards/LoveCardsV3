import { useRef, useState } from 'react';
import { reportResource } from '../../services/community-service.js';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { Button, FormDialog, ScopedBanner, Selector, TextArea } from '../../ui/index.js';

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
  const [errorMessage, setErrorMessage] = useState('');
  const submitting = useRef(false);

  const close = () => {
    if (submitting.current) return;
    setReason('spam');
    setDetails('');
    setErrorMessage('');
    onOpenChange(false);
  };

  const submit = async () => {
    if (submitting.current || !resourceId) return;
    submitting.current = true;
    setBusy(true);
    setErrorMessage('');
    try {
      const explanation = details.trim();
      await reportResource({
        resource_type: resourceType,
        resource_id: String(resourceId),
        reason,
        ...(explanation && { details: explanation }),
      });
      setReason('spam');
      setDetails('');
      onOpenChange(false);
      onFeedback?.('举报已提交，我们会按流程处理', 'info');
    } catch (error) {
      setErrorMessage(getUserFacingError(error, { fallback: '举报暂时没有提交，请稍后重试。', validationFallback: '请检查举报原因和补充说明，再次提交。' }));
    } finally {
      submitting.current = false;
      setBusy(false);
    }
  };

  return (
    <FormDialog
      isOpen={isOpen}
      onOpenChange={(open) => { if (!open) close(); }}
      title={title}
      subtitle={resourceType === 'message' ? '这条私信将作为证据提供给有权限的审核人员' : '请选择最符合的原因，我们会按社区规则处理。'}
      isBusy={busy}
      footer={<><Button type="button" label="取消" variant="ghost" size="lg" isDisabled={busy} onClick={close} /><Button type="button" label="提交举报" variant="primary" size="lg" isLoading={busy} isDisabled={busy} onClick={() => void submit()} /></>}
    >
      {errorMessage && <ScopedBanner title="举报未提交" description={errorMessage} />}
      <Selector label="举报原因" options={REPORT_OPTIONS} value={reason} onChange={setReason} isDisabled={busy} width="100%" />
      <TextArea label="补充说明" value={details} onChange={setDetails} isDisabled={busy} maxLength={2000} rows={5} placeholder="可选，说明具体情况" width="100%" />
    </FormDialog>
  );
}
