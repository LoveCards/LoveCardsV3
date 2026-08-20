import { useState } from 'react';
import { ActionMenu } from '../../ui/index.js';
import { Icon } from '../../ui/icons.jsx';
import { useCardSave } from '../library/SaveCardButton.jsx';
import { ReportResourceDialog } from '../moderation/ReportResourceDialog.jsx';

export function buildCardMoreMenuItems({ saved, busy, isReadOnly, canReport, onToggleSave, onCopyLink, onReport }) {
  const items = [
    {
      label: busy ? (saved ? '取消收藏中' : '收藏中') : saved ? '取消收藏' : '收藏',
      icon: <Icon name="bookmark" size={17} />,
      isDisabled: isReadOnly || busy,
      onClick: onToggleSave,
    },
    {
      label: '复制链接',
      icon: <Icon name="copy" size={17} />,
      isDisabled: isReadOnly,
      onClick: onCopyLink,
    },
  ];
  if (canReport) {
    items.push(
      { type: 'divider' },
      {
        label: '举报',
        icon: <Icon name="flag" size={17} />,
        isDisabled: isReadOnly,
        onClick: onReport,
      },
    );
  }
  return items;
}

export function CardMoreMenu({ cardId, source = 'live', isAuthenticated = false, isSaved = false, isStaticPreview = false, canReport = true, onRequireLogin, onSavedChange, onFeedback }) {
  const [reportOpen, setReportOpen] = useState(false);
  const { saved, busy, isReadOnly, toggleSaved } = useCardSave({
    cardId,
    source,
    isAuthenticated,
    isSaved,
    isStatic: isStaticPreview,
    onRequireLogin,
    onSavedChange,
    onFeedback,
  });

  const copyLink = async () => {
    if (isReadOnly) return;
    try {
      if (!globalThis.navigator?.clipboard?.writeText || typeof window === 'undefined') throw new Error('clipboard-unavailable');
      const url = new URL(`/post/${encodeURIComponent(String(cardId))}`, window.location.origin).href;
      await navigator.clipboard.writeText(url);
      onFeedback?.('链接已复制', 'info');
    } catch {
      onFeedback?.('当前浏览器无法复制链接', 'error');
    }
  };

  const openReport = () => {
    if (isReadOnly) return;
    if (!isAuthenticated) {
      onRequireLogin?.('report');
      return;
    }
    setReportOpen(true);
  };

  const items = buildCardMoreMenuItems({
    saved,
    busy,
    isReadOnly,
    canReport,
    onToggleSave: toggleSaved,
    onCopyLink: copyLink,
    onReport: openReport,
  });

  return (
    <>
      <ActionMenu className="story-more-menu" items={items} label="更多操作" />
      {canReport && <ReportResourceDialog isOpen={reportOpen} onOpenChange={setReportOpen} resourceType="card" resourceId={cardId} onFeedback={onFeedback} />}
    </>
  );
}
