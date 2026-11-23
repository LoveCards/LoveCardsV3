import { useEffect, useId, useRef, useState } from 'react';
import { Icon } from '../../ui/index.js';
import { Button } from '../../ui/controls.jsx';

export function DiscoverySearchSurface({ query = '', onSubmit }) {
  const inputId = useId();
  const [draft, setDraft] = useState(query);
  const composing = useRef(false);

  useEffect(() => setDraft(query), [query]);

  const submit = (event) => {
    event.preventDefault();
    if (composing.current) return;
    onSubmit(draft.trim());
  };

  return <form className="discovery-search-surface" onSubmit={submit} role="search">
    <Icon name="search" size={18} />
    <label className="search-query-label" htmlFor={inputId}>搜索内容、作者或话题</label>
    <input id={inputId} className="search-query-input" type="search" value={draft} onChange={(event) => setDraft(event.target.value)} onCompositionStart={() => { composing.current = true; }} onCompositionEnd={() => { composing.current = false; }} onKeyDown={(event) => { if (event.key === 'Enter' && (event.nativeEvent.isComposing || event.nativeEvent.keyCode === 229)) event.preventDefault(); }} placeholder="搜索内容、作者或话题" autoComplete="off" enterKeyHint="search" />
    <Button className="discovery-search-submit" type="submit" label="搜索" variant="primary" size="lg" />
  </form>;
}
