import { describe, expect, it } from 'vitest';
import { isCardReference } from '../services/profile-service.js';
import { cardReferenceId } from '../services/profile-card-service.js';

describe('profile library references', () => {
  it('keeps card records and ignores non-card interactions', () => {
    expect(isCardReference({ ref_type: 'card', ref_id: 12 })).toBe(true);
    expect(isCardReference({ resource_type: 'card', resource_id: 12 })).toBe(true);
    expect(isCardReference({ ref_type: 'comment', ref_id: 12 })).toBe(false);
    expect(isCardReference({ resource_type: 'comment', resource_id: 12 })).toBe(false);
  });

  it('uses the real liked card id instead of the legacy aid channel id', () => {
    expect(cardReferenceId({ aid: 1, pid: 26, ref_type: 'card', ref_id: 26 })).toBe(26);
    expect(cardReferenceId({ aid: 1, pid: 26, ref_type: 'card', ref_id: 26, resource_id: '26' })).toBe(26);
    expect(cardReferenceId({ aid: 1, pid: 26, ref_type: 'comment', ref_id: 26 })).toBeNull();
  });
});
