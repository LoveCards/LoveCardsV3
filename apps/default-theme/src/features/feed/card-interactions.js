const THREAD_ADAPTER = Object.freeze({
  id: 'comments.thread.v1',
  type: 'thread',
  adapter: 'comments.thread.v1',
  source: 'comments',
  trust: 'server-resource',
});

export const CARD_INTERACTION_ADAPTER_REGISTRY = Object.freeze({
  'comments.thread.v1': THREAD_ADAPTER,
});

/** Return the one interaction currently supported by the product surface. */
export function normalizeCardInteractions() {
  return Object.freeze([
    Object.freeze({ ...THREAD_ADAPTER, id: 'comments', status: 'declared' }),
  ]);
}
