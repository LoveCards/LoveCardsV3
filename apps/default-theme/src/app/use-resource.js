import { useCallback, useEffect, useRef, useState } from 'react';

export function useResource(loader, initialData = null) {
  const [state, setState] = useState({ status: initialData?.source === 'loading' ? 'loading' : 'success', data: initialData, error: null });
  const controllerRef = useRef(null);
  const reload = useCallback(async () => {
    controllerRef.current?.abort();
    const controller = new AbortController();
    controllerRef.current = controller;
    setState((current) => ({ ...current, status: 'loading', error: null }));
    try {
      const data = await loader(controller.signal);
      setState({ status: 'success', data, error: null });
    } catch (error) {
      if (error?.name !== 'AbortError') setState((current) => ({ ...current, status: 'error', error }));
    }
    return undefined;
  }, [loader]);

  useEffect(() => { reload(); return () => controllerRef.current?.abort(); }, [reload]);
  return { ...state, reload };
}
