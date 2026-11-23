import { useEffect, useMemo, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { createHeaderSearchSource } from '../services/header-search-source.js';
import { browseTaskState } from '../app/browse-task-routing.js';
import { searchHref } from '../pages/search/search-route.js';
import { Avatar, Button, Icon, Typeahead, TypeaheadItem } from '../ui/index.js';

export function HeaderSearch({ mobile = false, onNavigate }) {
  const navigate = useNavigate();
  const location = useLocation();
  const composingRef = useRef(false);
  const searchSource = useMemo(() => createHeaderSearchSource({ isComposing: () => composingRef.current }), []);
  const [isOpen, setIsOpen] = useState(false);
  const [draft, setDraft] = useState('');

  useEffect(() => searchSource.cancel, [searchSource]);

  const goTo = onNavigate || ((href) => navigate(href, { state: browseTaskState(location) }));

  const selectResult = (item) => {
    if (!item?.auxiliaryData?.href) return;
    setIsOpen(false);
    goTo(item.auxiliaryData.href);
  };

  const submitKeyword = (event) => {
    if (event.key !== 'Enter' || event.target.tagName !== 'INPUT' || composingRef.current || event.nativeEvent.isComposing || event.nativeEvent.keyCode === 229 || !draft.trim()) return;
    const activeOption = event.target.getAttribute?.('aria-activedescendant');
    if (activeOption && !activeOption.endsWith('-option-0')) return;
    event.preventDefault();
    event.stopPropagation();
    setIsOpen(false);
    goTo(searchHref('all', draft));
  };

  return (
    <div className={`${mobile ? 'mobile-search-entry' : 'header-search'}${isOpen ? ' is-open' : ''}`} onCompositionStartCapture={() => { composingRef.current = true; }} onCompositionEndCapture={() => { composingRef.current = false; }} onKeyDownCapture={submitKeyword}>
      <Typeahead
        className="header-search-field"
        label="搜索内容、作者或话题"
        isLabelHidden
        placeholder="搜索内容、作者或话题"
        searchSource={searchSource}
        value={null}
        onChange={selectResult}
        onChangeQuery={setDraft}
        onOpenChange={setIsOpen}
        startIcon={<Icon name="search" size={18} />}
        hasAutoFocus={mobile}
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
            icon={item.auxiliaryData?.kind === 'user'
              ? <Avatar label={item.label || '用户'} src={item.auxiliaryData.avatar} tone="sand" size="sm" />
              : item.auxiliaryData?.kind === 'topic'
                ? <span className="header-search-topic" aria-hidden="true">#</span>
                : <Icon name="info" size={17} />}
            description={item.auxiliaryData?.description}
          />
        )}
      />
      {mobile && <Button type="button" label="搜索" variant="primary" size="lg" isDisabled={!draft.trim()} onClick={() => goTo(searchHref('all', draft))} />}
    </div>
  );
}
