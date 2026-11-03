import { describe, expect, it, vi } from 'vitest';

import {
  createMentionController,
  createMentionSearchSource,
  createPreviewMentionAdapter,
  createSdkMentionAdapter,
  detectMentionSearchCapability,
  findMentionToken,
  insertMentionCandidate,
  isValidMentionHandle,
  mentionCandidateToToken,
  normalizeMentionCandidates,
  normalizeMentionHandle,
  normalizeMentionQuery,
} from '../services/mentions-service.js';

describe('mentions service', () => {
  it('normalizes canonical handles without accepting invalid syntax', () => {
    expect(normalizeMentionHandle(' @森林里 ')).toBe('森林里');
    expect(normalizeMentionHandle('@abc_12')).toBe('abc_12');
    expect(isValidMentionHandle('ab')).toBe(false);
    expect(isValidMentionHandle('abc!')).toBe(false);
    expect(isValidMentionHandle('a.bcd')).toBe(false);
    expect(isValidMentionHandle('a'.repeat(13))).toBe(false);
    expect(normalizeMentionQuery('@forest')).toBe('forest');
    expect(normalizeMentionQuery('forest name')).toBe('');
  });

  it('finds only a boundary-safe token at the current caret', () => {
    expect(findMentionToken('@for', 4)).toMatchObject({ query: 'for', start: 0, end: 4 });
    expect(findMentionToken('hello @for there', 10)).toMatchObject({ query: 'for', start: 6, end: 10 });
    expect(findMentionToken('hello @', 7)).toMatchObject({ query: '', start: 6, end: 7 });
    expect(findMentionToken('mail@test.com', 9)).toBeNull();
    expect(findMentionToken('hello,@forest', 13)).toBeNull();
    expect(findMentionToken('@@forest', 8)).toBeNull();
    expect(findMentionToken('@forest!', 8)).toBeNull();
    expect(findMentionToken('@forest', 99)).toBeNull();
  });

  it('replaces the active token and reports the new caret position', () => {
    expect(insertMentionCandidate('说 hello @fo world', 11, { username: 'forest' })).toEqual({
      value: '说 hello @forest world',
      cursor: 15,
      inserted: true,
      token: expect.objectContaining({ start: 8, end: 11, handle: 'forest', mention: '@forest' }),
    });
    expect(insertMentionCandidate('@fo', 3, { username: 'forest' }, { separator: '' })).toMatchObject({
      value: '@forest',
      cursor: 7,
      inserted: true,
    });
    expect(insertMentionCandidate('hello', 5, { username: 'forest' })).toMatchObject({ value: 'hello', cursor: 5, inserted: false });
    expect(insertMentionCandidate('@fo', 3, { id: 9 })).toMatchObject({ value: '@fo', cursor: 3, inserted: false });
  });

  it('normalizes public-user candidates, filters unavailable users, and deduplicates handles', () => {
    expect(normalizeMentionCandidates({ data: [
      { id: 1, username: 'Forest', display_name: '森林' },
      { id: 2, handle: 'forest' },
      { id: 3, username: 'gone', status: 'inactive' },
      { id: 4, username: 'bad!' },
      { id: 5, actor: { username: 'river', avatar: '/river.png' } },
    ] })).toEqual([
      { id: '1', sourceId: 1, handle: 'Forest', username: 'Forest', label: '森林', displayName: '森林', avatar: null, auxiliaryData: { handle: 'Forest', displayName: '森林', avatar: null }, source: { id: 1, username: 'Forest', display_name: '森林' } },
      { id: '5', sourceId: 5, handle: 'river', username: 'river', label: '@river', displayName: null, avatar: '/river.png', auxiliaryData: { handle: 'river', displayName: null, avatar: '/river.png' }, source: { id: 5, actor: { username: 'river', avatar: '/river.png' } } },
    ]);
    expect(mentionCandidateToToken({ username: 'Forest' })).toBe('@Forest ');
    expect(mentionCandidateToToken({ id: 1 })).toBeNull();
  });

  it('detects only an injected public search method', () => {
    expect(detectMentionSearchCapability({ searchUsers: vi.fn() })).toMatchObject({ available: true, method: 'searchUsers' });
    expect(detectMentionSearchCapability({ users: { search: vi.fn() } })).toMatchObject({ available: true, method: 'users.search' });
    expect(detectMentionSearchCapability({ social: { profile: vi.fn() } })).toMatchObject({ available: false, reason: 'missing-public-user-search' });
    expect(detectMentionSearchCapability(null)).toMatchObject({ available: false, reason: 'no-adapter' });
  });

  it('adapts the generated SDK method without constructing a URL', async () => {
    const searchMentionCandidates = vi.fn().mockResolvedValue({ data: [{ username: 'forest', avatar: null }] });
    const adapter = createSdkMentionAdapter({ users: { searchMentionCandidates } });
    await expect(adapter.searchUsers('fo', { limit: 5 })).resolves.toEqual({ data: [{ username: 'forest', avatar: null }] });
    expect(searchMentionCandidates).toHaveBeenCalledWith({ query: 'fo', limit: 5 });
    expect(createSdkMentionAdapter({ users: {} })).toBeNull();
  });

  it('keeps design-preview search local and applies the same candidate normalization', () => {
    const adapter = createPreviewMentionAdapter([
      { username: 'forest', display_name: '森林' },
      { username: 'river', display_name: '河流' },
    ]);
    expect(adapter.searchUsers('fo', { limit: 1 })).toMatchObject([
      { handle: 'forest', displayName: '森林' },
    ]);
  });

  it('degrades to an empty menu when search capability is absent or fails', async () => {
    const unavailable = createMentionSearchSource(null);
    await expect(unavailable.search('forest')).resolves.toEqual([]);
    expect(unavailable.getState()).toMatchObject({ available: false, status: 'unavailable' });

    const searchUsers = vi.fn().mockRejectedValue(new Error('network down'));
    const source = createMentionSearchSource({ searchUsers });
    await expect(source.search('forest')).resolves.toEqual([]);
    expect(searchUsers).toHaveBeenCalledWith('forest', expect.objectContaining({ limit: 8, signal: expect.anything() }));
    expect(source.getState()).toMatchObject({ available: true, status: 'error' });
  });

  it('does not query for an empty trigger and discards stale requests', async () => {
    let releaseFirst;
    const first = new Promise((resolve) => { releaseFirst = resolve; });
    const searchUsers = vi.fn()
      .mockImplementationOnce(() => first)
      .mockResolvedValueOnce({ items: [{ username: 'river' }] });
    const source = createMentionSearchSource({ searchUsers });
    await expect(source.search('')).resolves.toEqual([]);
    const firstRequest = source.search('fo');
    const secondRequest = source.search('riv');
    releaseFirst({ items: [{ username: 'forest' }] });
    await expect(firstRequest).resolves.toEqual([]);
    await expect(secondRequest).resolves.toMatchObject([{ handle: 'river' }]);
  });

  it('cancels a pending query when the trigger is cleared', async () => {
    let release;
    const pending = new Promise((resolve) => { release = resolve; });
    const searchUsers = vi.fn().mockImplementation(() => pending);
    const source = createMentionSearchSource({ searchUsers });
    const request = source.search('forest');
    await expect(source.search('')).resolves.toEqual([]);
    release({ items: [{ username: 'forest' }] });
    await expect(request).resolves.toEqual([]);
    expect(source.getState()).toMatchObject({ status: 'idle' });
  });

  it('exposes a reusable trigger/controller without coupling to a page', () => {
    const controller = createMentionController({ searchUsers: vi.fn() });
    expect(controller.trigger).toMatchObject({ character: '@', searchSource: controller.searchSource });
    expect(controller.findToken('@fo', 3)).toMatchObject({ query: 'fo' });
    expect(controller.capability.available).toBe(true);
  });
});
