import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { createHeaderSearchSource } from '../../services/header-search-source.js';
import { Avatar, Icon, Typeahead, TypeaheadItem } from '../../ui/index.js';
import { Button } from '../../ui/controls.jsx';

export function DiscoverySearchSurface({ query = '', onSubmit }) {
  const navigate = useNavigate();
  const composingRef = useRef(false);
  const surfaceRef = useRef(null);
  const [draft, setDraft] = useState(query);
  const [suggestionsOpen, setSuggestionsOpen] = useState(false);
  const searchSource = useMemo(() => createHeaderSearchSource({ isComposing: () => composingRef.current }), []);

  useEffect(() => setDraft(query), [query]);

  useEffect(() => {
    const surface = surfaceRef.current;
    if (!surface || !suggestionsOpen) {
      surface?.style.removeProperty('--discovery-suggestions-height');
      return undefined;
    }

    let frameId;
    let resizeObserver;

    const syncSuggestionHeight = () => {
      const input = surface.querySelector('input[aria-controls]');
      const listboxId = input?.getAttribute('aria-controls');
      const listbox = listboxId ? document.getElementById(listboxId) : null;

      if (!listbox) {
        frameId = requestAnimationFrame(syncSuggestionHeight);
        return;
      }

      const updateHeight = () => {
        const height = Math.ceil(listbox.getBoundingClientRect().height);
        if (height > 0) surface.style.setProperty('--discovery-suggestions-height', `${height}px`);
      };

      updateHeight();
      if (typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(updateHeight);
        resizeObserver.observe(listbox);
      }
    };

    frameId = requestAnimationFrame(syncSuggestionHeight);
    return () => {
      cancelAnimationFrame(frameId);
      resizeObserver?.disconnect();
      surface.style.removeProperty('--discovery-suggestions-height');
    };
  }, [suggestionsOpen]);

  const openResult = (item) => {
    const href = item?.auxiliaryData?.href;
    if (href) navigate(href);
  };

  const submit = (event) => {
    event.preventDefault();
    onSubmit(draft.trim());
  };

  return (
    <form ref={surfaceRef} className={`discovery-search-surface${suggestionsOpen ? ' is-suggestions-open' : ''}`} onSubmit={submit} role="search" onCompositionStart={() => { composingRef.current = true; }} onCompositionEnd={() => { composingRef.current = false; }}>
      <div className="discovery-search-context">
        <Icon name="search" size={18} />
        {query ? <><span>正在搜索</span><b>“{query}”</b><button type="button" aria-label="清除当前搜索" onClick={() => onSubmit('')}>×</button></> : <span>搜索内容、作者或话题</span>}
      </div>
      <Typeahead
        className="discovery-search-typeahead"
        label="搜索内容、作者或话题"
        isLabelHidden
        placeholder={query ? '输入新的关键词…' : '输入关键词，实时查看结果…'}
        searchSource={searchSource}
        value={null}
        onChange={openResult}
        onChangeQuery={setDraft}
        onOpenChange={setSuggestionsOpen}
        hasEntriesOnFocus
        hasClear
        debounceMs={180}
        maxMenuItems={8}
        emptySearchResultsText="没有找到相关内容，可尝试其他关键词"
        size="lg"
        width="100%"
        renderItem={(item) => <TypeaheadItem item={item} icon={item.auxiliaryData?.kind === 'post' ? <Avatar label={item.auxiliaryData.author || '内容'} tone="rose" size="sm" /> : item.auxiliaryData?.kind === 'topic' ? <span className="header-search-topic" aria-hidden="true">#</span> : <Icon name="info" size={17} />} description={item.auxiliaryData?.description} />}
      />
      <Button className="discovery-search-submit" type="submit" label="搜索" variant="primary" size="lg" />
    </form>
  );
}
