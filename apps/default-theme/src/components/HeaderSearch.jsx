import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { createHeaderSearchSource } from '../services/header-search-source.js';
import { Avatar, Dialog, DialogHeader, Icon, Typeahead, TypeaheadItem } from '../ui/index.js';

export function HeaderSearch() {
  const navigate = useNavigate();
  const composingRef = useRef(false);
  const searchSource = useMemo(() => createHeaderSearchSource({ isComposing: () => composingRef.current }), []);
  const [isOpen, setIsOpen] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  useEffect(() => {
    const open = () => setMobileOpen(true);
    window.addEventListener('lc-open-mobile-search', open);
    return () => window.removeEventListener('lc-open-mobile-search', open);
  }, []);

  const selectResult = (item) => {
    if (!item?.auxiliaryData?.href) return;
    setIsOpen(false);
    setMobileOpen(false);
    navigate(item.auxiliaryData.href);
  };

  return (
    <>
      <div className={`header-search${isOpen ? ' is-open' : ''}`} onCompositionStartCapture={() => { composingRef.current = true; }} onCompositionEndCapture={() => { composingRef.current = false; }}>
        <Typeahead
          className="header-search-field"
          label="搜索内容、作者或话题"
          isLabelHidden
          placeholder="搜索内容、作者或话题"
          searchSource={searchSource}
          value={null}
          onChange={selectResult}
          onOpenChange={setIsOpen}
          startIcon={<Icon name="search" size={18} />}
          hasEntriesOnFocus
          hasClear
          debounceMs={180}
          maxMenuItems={8}
          emptySearchResultsText="没有找到相关内容，可尝试其他关键词"
          size="lg"
          width="100%"
          renderItem={(item) => (
            <TypeaheadItem
              item={item}
              icon={item.auxiliaryData?.kind === 'post'
                ? <Avatar label={item.auxiliaryData.author || '内容'} tone="rose" size="sm" />
                : item.auxiliaryData?.kind === 'topic'
                  ? <span className="header-search-topic" aria-hidden="true">#</span>
                  : <Icon name="info" size={17} />}
              description={item.auxiliaryData?.description}
            />
          )}
        />
      </div>
      <Dialog className="mobile-search-dialog" isOpen={mobileOpen} onOpenChange={setMobileOpen} variant="fullscreen" purpose="info" padding={0} aria-label="移动端搜索">
        <div className="mobile-search-dialog-inner">
          <DialogHeader title="搜索" subtitle="内容 · 作者 · 话题" onOpenChange={setMobileOpen} />
          <Typeahead
            className="mobile-search-field"
            label="搜索内容、作者或话题"
            isLabelHidden
            placeholder="输入关键词…"
            searchSource={searchSource}
            value={null}
            onChange={selectResult}
            startIcon={<Icon name="search" size={18} />}
            hasEntriesOnFocus
            hasClear
            hasAutoFocus
            debounceMs={180}
            maxMenuItems={8}
            emptySearchResultsText="没有找到相关内容，可尝试其他关键词"
            size="lg"
            width="100%"
            renderItem={(item) => <TypeaheadItem item={item} icon={item.auxiliaryData?.kind === 'post' ? <Avatar label={item.auxiliaryData.author || '内容'} tone="rose" size="sm" /> : item.auxiliaryData?.kind === 'topic' ? <span className="header-search-topic" aria-hidden="true">#</span> : <Icon name="info" size={17} />} description={item.auxiliaryData?.description} />}
          />
        </div>
      </Dialog>
    </>
  );
}
