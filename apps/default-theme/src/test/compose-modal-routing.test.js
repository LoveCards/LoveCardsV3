import { describe, expect, it } from 'vitest';
import { composeModalBackground, composeModalState } from '../app/compose-modal-routing.js';

describe('compose modal routing', () => {
  const source = { pathname: '/', search: '?channel=latest', hash: '', state: null, key: 'feed' };

  it('keeps the source page for an in-app compose entry', () => {
    expect(composeModalBackground({ pathname: '/compose', state: composeModalState(source) })).toEqual(source);
  });

  it('uses the full page for direct, untrusted, and compose-origin entries', () => {
    expect(composeModalBackground({ pathname: '/compose', state: null })).toBeNull();
    expect(composeModalBackground({ pathname: '/compose', state: { ...composeModalState(source), composeModalSession: 'expired' } })).toBeNull();
    expect(composeModalBackground({ pathname: '/compose', state: composeModalState({ ...source, pathname: '/compose' }) })).toBeNull();
    expect(composeModalBackground({ pathname: '/compose', state: composeModalState({ ...source, pathname: '//other' }) })).toBeNull();
  });
});
