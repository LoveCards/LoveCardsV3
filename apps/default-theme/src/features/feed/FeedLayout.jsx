import { createContext, useContext, useLayoutEffect, useState } from 'react';

const FeedLayoutContext = createContext(null);
const storageKey = 'lc-feed-layout';

export function FeedLayoutProvider({ children }) {
  const [layout, setLayout] = useState(() => typeof window !== 'undefined' && window.localStorage.getItem(storageKey) === 'single' ? 'single' : 'multi');
  useLayoutEffect(() => {
    document.documentElement.dataset.feedLayout = layout;
    window.localStorage.setItem(storageKey, layout);
  }, [layout]);

  return <FeedLayoutContext value={{ layout, setLayout }}>{children}</FeedLayoutContext>;
}

export function FeedLayoutControl() {
  const { layout, setLayout } = useContext(FeedLayoutContext);
  return <select className="feed-layout-control" aria-label="信息流布局" value={layout} onChange={(event) => setLayout(event.target.value)}>
    <option value="single">单列</option>
    <option value="multi">多列</option>
  </select>;
}
