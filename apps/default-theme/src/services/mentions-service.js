/**
 * Pure mention interaction helpers.
 *
 * The editor only sends the resulting raw text to the API.  Resolution,
 * eligibility and notification creation remain server responsibilities.  A
 * search adapter is therefore injected by the caller when a typed SDK
 * capability becomes available; this module never constructs an HTTP URL.
 */

export const MENTION_TRIGGER = '@';
export const MENTION_HANDLE_MIN_LENGTH = 3;
export const MENTION_HANDLE_MAX_LENGTH = 12;

const HANDLE_PATTERN = /^[\u3400-\u4db5\u4e00-\u9fef\u{20000}-\u{2ebe0}A-Za-z0-9_-]{3,12}$/u;
const QUERY_PATTERN = /^[\u3400-\u4db5\u4e00-\u9fef\u{20000}-\u{2ebe0}A-Za-z0-9_-]*$/u;
const TOKEN_BOUNDARY_PATTERN = /\s/u;
const UNAVAILABLE_STATES = new Set(['inactive', 'deleted', 'disabled', 'blocked', 'unavailable', 'deactivated']);

function characterLength(value) {
  return Array.from(value).length;
}

/** Return a canonical handle without the display-only leading @. */
export function normalizeMentionHandle(value) {
  if (typeof value !== 'string') return null;
  const handle = value.trim().replace(/^@/u, '');
  if (!handle || characterLength(handle) < MENTION_HANDLE_MIN_LENGTH || characterLength(handle) > MENTION_HANDLE_MAX_LENGTH) return null;
  return HANDLE_PATTERN.test(handle) ? handle : null;
}

export function isValidMentionHandle(value) {
  return normalizeMentionHandle(value) !== null;
}

/**
 * Normalize the query passed to a user-search adapter.  Empty queries are
 * intentionally not searched: opening an @ menu must not enumerate users.
 */
export function normalizeMentionQuery(value) {
  if (typeof value !== 'string') return '';
  const query = value.trim().replace(/^@/u, '');
  if (!query || characterLength(query) > MENTION_HANDLE_MAX_LENGTH || !QUERY_PATTERN.test(query)) return '';
  return query;
}

/**
 * Find the @ token currently touched by a text cursor.
 *
 * The parser mirrors the editor trigger boundary (start of input or
 * whitespace), rather than guessing mentions after punctuation or inside an
 * e-mail address. `end` is the supplied cursor so callers can use the query
 * prefix without reading unrelated text after the caret.
 */
export function findMentionToken(value, cursor) {
  if (typeof value !== 'string') return null;
  if (!Number.isInteger(cursor) || cursor < 0 || cursor > value.length) return null;

  let start = cursor;
  while (start > 0 && !TOKEN_BOUNDARY_PATTERN.test(value[start - 1])) start -= 1;
  const segment = value.slice(start, cursor);
  const triggerIndex = segment.lastIndexOf(MENTION_TRIGGER);
  if (triggerIndex < 0) return null;

  const tokenStart = start + triggerIndex;
  if (tokenStart > 0 && !TOKEN_BOUNDARY_PATTERN.test(value[tokenStart - 1])) return null;

  const query = value.slice(tokenStart + MENTION_TRIGGER.length, cursor);
  if (characterLength(query) > MENTION_HANDLE_MAX_LENGTH || !QUERY_PATTERN.test(query)) return null;

  return {
    trigger: MENTION_TRIGGER,
    query,
    raw: value.slice(tokenStart, cursor),
    start: tokenStart,
    end: cursor,
    cursor,
  };
}

// Readable aliases for callers that use controller terminology.
export const extractMentionToken = findMentionToken;

function candidateHandle(candidate) {
  if (typeof candidate === 'string') return normalizeMentionHandle(candidate);
  if (!candidate || typeof candidate !== 'object') return null;
  const values = [
    candidate.username,
    candidate.handle,
    candidate.user?.username,
    candidate.user?.handle,
    candidate.actor?.username,
    candidate.actor?.handle,
  ];
  for (const value of values) {
    const handle = normalizeMentionHandle(value);
    if (handle) return handle;
  }
  return null;
}

function candidateState(candidate) {
  if (!candidate || typeof candidate !== 'object') return '';
  return String(candidate.actor_state ?? candidate.user_state ?? candidate.state ?? candidate.status ?? '').trim().toLowerCase();
}

function isUnavailableCandidate(candidate) {
  if (!candidate || typeof candidate !== 'object') return false;
  if (candidate.active === false || candidate.is_active === false) return true;
  return UNAVAILABLE_STATES.has(candidateState(candidate));
}

function unwrapCandidateCollection(result) {
  if (Array.isArray(result)) return result;
  if (!result || typeof result !== 'object') return [];
  if (Array.isArray(result.items)) return result.items;
  if (Array.isArray(result.users)) return result.users;
  if (Array.isArray(result.data)) return result.data;
  if (result.data && typeof result.data === 'object') return unwrapCandidateCollection(result.data);
  return [];
}

/**
 * Convert an adapter response to stable, handle-based candidates.  IDs are
 * retained only as metadata; selection and links always use the canonical
 * username/handle.
 */
export function normalizeMentionCandidates(result, { limit = 8 } = {}) {
  const max = Number.isInteger(limit) && limit > 0 ? limit : 8;
  const seen = new Set();
  const candidates = [];
  for (const item of unwrapCandidateCollection(result)) {
    if (isUnavailableCandidate(item)) continue;
    const handle = candidateHandle(item);
    if (!handle) continue;
    const key = handle.toLowerCase();
    if (seen.has(key)) continue;
    seen.add(key);
    const source = item && typeof item === 'object' ? item : {};
    const sourceId = source.id ?? source.user_id ?? source.user?.id ?? source.actor?.id ?? null;
    const displayName = String(source.display_name ?? source.displayName ?? source.name ?? source.auxiliaryData?.displayName ?? source.user?.display_name ?? source.user?.name ?? '').trim().replace(/^@/u, '');
    const avatar = source.avatar ?? source.avatar_url ?? source.user?.avatar ?? source.actor?.avatar ?? null;
    candidates.push({
      // Astryx's SearchableItem contract requires a stable string id. Keep
      // the numeric server id only in `sourceId`; selection remains handle
      // based and never submits this id to the content API.
      id: String(sourceId ?? handle),
      sourceId,
      handle,
      username: handle,
      label: displayName || `@${handle}`,
      displayName: displayName || null,
      avatar: typeof avatar === 'string' && avatar.trim() ? avatar.trim() : null,
      auxiliaryData: { handle, displayName: displayName || null, avatar: typeof avatar === 'string' && avatar.trim() ? avatar.trim() : null },
      source: item,
    });
    if (candidates.length >= max) break;
  }
  return candidates;
}

/** Convert a candidate to the raw token consumed by the backend parser. */
export function mentionCandidateToToken(candidate) {
  const handle = candidateHandle(candidate);
  if (!handle) return null;
  // Return plain text, not an inline token chip. The backend intentionally
  // receives the original body and resolves mentions authoritatively.
  return `@${handle} `;
}

/**
 * Adapt the generated SDK client without teaching the UI about a URL or
 * transport detail. The method is optional while an older SDK is hot-loaded;
 * callers can hide the trigger menu until the capability is present.
 */
export function createSdkMentionAdapter(client) {
  const search = client?.users?.searchMentionCandidates;
  if (typeof search !== 'function') return null;
  return {
    searchUsers: (query, { limit = 8 } = {}) => search.call(client.users, { query, limit }),
  };
}

/** Local-only candidates used by the read-only design preview. */
export function createPreviewMentionAdapter(items = []) {
  const candidates = Array.isArray(items) ? items : [];
  return {
    searchUsers: (query, { limit = 8 } = {}) => {
      const normalized = normalizeMentionQuery(query).toLocaleLowerCase();
      if (!normalized) return [];
      return normalizeMentionCandidates(candidates.filter((item) => {
        const handle = candidateHandle(item);
        const name = String(item?.display_name ?? item?.name ?? '').trim();
        return handle?.toLocaleLowerCase().includes(normalized) || name.toLocaleLowerCase().includes(normalized);
      }), { limit });
    },
  };
}

/**
 * Replace the active token and return the new value plus caret position.
 * Invalid or stale candidates are a no-op, which keeps the composer usable
 * when a search request finishes after the user has moved the caret.
 */
export function insertMentionCandidate(value, cursor, candidate, { separator = ' ' } = {}) {
  if (typeof value !== 'string') return { value: '', cursor: 0, inserted: false, token: null };
  const safeCursor = Number.isInteger(cursor) ? Math.min(Math.max(cursor, 0), value.length) : value.length;
  const token = findMentionToken(value, safeCursor);
  const handle = candidateHandle(candidate);
  if (!token || !handle) return { value, cursor: safeCursor, inserted: false, token };

  let replacementEnd = token.end;
  while (replacementEnd < value.length && QUERY_PATTERN.test(value[replacementEnd])) replacementEnd += 1;
  const suffix = value.slice(replacementEnd);
  const safeSeparator = typeof separator === 'string' ? separator : ' ';
  const joiner = safeSeparator && !/^\s/u.test(suffix) ? safeSeparator : '';
  const mention = `@${handle}`;
  const nextValue = `${value.slice(0, token.start)}${mention}${joiner}${suffix}`;
  return {
    value: nextValue,
    cursor: token.start + mention.length + joiner.length,
    inserted: true,
    token: { ...token, end: replacementEnd, handle, mention },
  };
}

export const replaceMentionToken = insertMentionCandidate;

/**
 * Keep the caret in a text node before a contenteditable receives a key.
 *
 * React-controlled contenteditables can restore a collapsed selection at an
 * element boundary (for example, the root node at child offset N). Astryx's
 * trigger parser intentionally reads text-node selections so it can avoid
 * guessing across inline tokens. Normalising that boundary here preserves
 * the editor's visual caret while making the shared trigger reliable after a
 * controlled re-render, including when a user starts typing with `@`.
 */
export function normalizeMentionCaret(editableOrRoot) {
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  const editable = editableOrRoot?.matches?.('[contenteditable="true"]')
    ? editableOrRoot
    : editableOrRoot?.querySelector?.('[contenteditable="true"]');
  if (!editable) return;

  const selection = window.getSelection?.();
  if (!selection || selection.rangeCount === 0 || !selection.isCollapsed) return;
  const range = selection.getRangeAt(0);
  const directChildren = Array.from(editable.childNodes || []);
  if (directChildren.length > 1 && directChildren.every((node) => node.nodeType === Node.TEXT_NODE)) {
    let caretOffset = 0;
    if (range.startContainer.nodeType === Node.TEXT_NODE) {
      const index = directChildren.indexOf(range.startContainer);
      if (index >= 0) {
        caretOffset = directChildren.slice(0, index).reduce((total, node) => total + (node.textContent?.length || 0), 0) + range.startOffset;
      }
    } else if (range.startContainer === editable) {
      caretOffset = directChildren.slice(0, range.startOffset).reduce((total, node) => total + (node.textContent?.length || 0), 0);
    }
    const text = directChildren.map((node) => node.textContent || '').join('');
    const merged = document.createTextNode(text);
    editable.replaceChildren(merged);
    const nextRange = document.createRange();
    nextRange.setStart(merged, Math.min(caretOffset, text.length));
    nextRange.collapse(true);
    selection.removeAllRanges();
    selection.addRange(nextRange);
    return;
  }
  const findLastTextNode = (node) => {
    if (!node) return null;
    if (node.nodeType === Node.TEXT_NODE) return node;
    const nested = Array.from(node.childNodes || []);
    for (let index = nested.length - 1; index >= 0; index -= 1) {
      const found = findLastTextNode(nested[index]);
      if (found) return found;
    }
    return null;
  };
  if (!editable.contains(range.startContainer)) {
    const last = findLastTextNode(editable) || document.createTextNode('');
    if (!last.parentNode) editable.appendChild(last);
    const nextRange = document.createRange();
    nextRange.setStart(last, last.textContent?.length || 0);
    nextRange.collapse(true);
    selection.removeAllRanges();
    selection.addRange(nextRange);
    return;
  }

  // An empty trailing text node is another common controlled-edit boundary.
  // Move it back to the nearest preceding text node; otherwise the core
  // parser would correctly read that empty node and miss the visible `@foo`.
  if (range.startContainer.nodeType === Node.TEXT_NODE) {
    const currentText = range.startContainer;
    if ((currentText.textContent || '').length > 0) return;
    const parent = currentText.parentNode;
    if (!parent) return;
    const siblings = Array.from(parent.childNodes || []);
    const currentIndex = siblings.indexOf(currentText);
    let previous = null;
    for (let index = currentIndex - 1; index >= 0; index -= 1) {
      previous = findLastTextNode(siblings[index]);
      if (previous) break;
    }
    if (previous) {
      const nextRange = document.createRange();
      nextRange.setStart(previous, previous.textContent?.length || 0);
      nextRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(nextRange);
    }
    return;
  }

  const container = range.startContainer;
  const children = Array.from(container.childNodes || []);
  const boundary = Math.max(0, Math.min(range.startOffset, children.length));
  const firstText = (node) => {
    if (!node) return null;
    if (node.nodeType === Node.TEXT_NODE) return node;
    for (const child of Array.from(node.childNodes || [])) {
      const found = firstText(child);
      if (found) return found;
    }
    return null;
  };
  const lastText = findLastTextNode;

  // React often leaves an empty trailing text node after a controlled edit.
  // Prefer the previous text node for any non-zero boundary so the parser can
  // still see the complete text before the caret (rather than reading that
  // empty trailing node as an empty query).
  let target = null;
  let offset = 0;
  if (boundary > 0) {
    for (let index = boundary - 1; index >= 0; index -= 1) {
      target = lastText(children[index]);
      if (target) break;
    }
    if (target) offset = target.textContent?.length || 0;
  }
  if (!target) {
    for (let index = boundary; index < children.length; index += 1) {
      target = firstText(children[index]);
      if (target) break;
    }
    if (target) offset = 0;
  }

  if (!target) {
    target = document.createTextNode('');
    editable.appendChild(target);
  }
  const nextRange = document.createRange();
  nextRange.setStart(target, Math.min(offset, target.textContent?.length || 0));
  nextRange.collapse(true);
  selection.removeAllRanges();
  selection.addRange(nextRange);
}

const SEARCH_METHODS = [
  ['searchUsers', (adapter) => adapter?.searchUsers],
  ['users.search', (adapter) => adapter?.users?.search],
  ['users.searchPublic', (adapter) => adapter?.users?.searchPublic],
  ['users.listPublic', (adapter) => adapter?.users?.listPublic],
];

/**
 * Detect an injected public-user search capability without making a request.
 * `social.profile` is deliberately not considered: it resolves one known
 * handle and cannot safely power autocomplete.
 */
export function detectMentionSearchCapability(adapter) {
  if (!adapter) return { available: false, method: null, reason: 'no-adapter' };
  for (const [method, get] of SEARCH_METHODS) {
    if (typeof get(adapter) === 'function') return { available: true, method, reason: null };
  }
  return { available: false, method: null, reason: 'missing-public-user-search' };
}

export const getMentionSearchCapability = detectMentionSearchCapability;

function resolveSearchMethod(adapter, method) {
  if (method === 'searchUsers') return { owner: adapter, fn: adapter.searchUsers };
  if (method === 'users.search') return { owner: adapter.users, fn: adapter.users.search };
  if (method === 'users.searchPublic') return { owner: adapter.users, fn: adapter.users.searchPublic };
  if (method === 'users.listPublic') return { owner: adapter.users, fn: adapter.users.listPublic };
  return null;
}

/**
 * Build the adapter expected by Astryx `ChatComposerInput`'s `searchSource`.
 * It never searches on an empty/invalid query and swallows unavailable or
 * network failures into an empty result, exposing state for a caller that
 * wants to show a non-blocking status message.
 */
export function createMentionSearchSource(adapter, { limit = 8 } = {}) {
  const capability = detectMentionSearchCapability(adapter);
  const resolved = capability.available ? resolveSearchMethod(adapter, capability.method) : null;
  let sequence = 0;
  let controller = null;
  let status = capability.available ? 'idle' : 'unavailable';
  let lastError = null;

  const cancel = () => {
    sequence += 1;
    controller?.abort();
    controller = null;
    if (capability.available) status = 'idle';
  };

  const search = async (rawQuery) => {
    const query = normalizeMentionQuery(rawQuery);
    if (!capability.available || !resolved || !query) {
      sequence += 1;
      controller?.abort();
      controller = null;
      if (capability.available) status = 'idle';
      lastError = null;
      return [];
    }

    sequence += 1;
    const requestSequence = sequence;
    controller?.abort();
    controller = typeof AbortController === 'function' ? new AbortController() : null;
    status = 'loading';
    lastError = null;
    try {
      const result = await resolved.fn.call(resolved.owner, query, {
        limit,
        signal: controller?.signal,
      });
      if (requestSequence !== sequence) return [];
      status = 'ready';
      return normalizeMentionCandidates(result, { limit });
    } catch (error) {
      if (requestSequence !== sequence || error?.name === 'AbortError') return [];
      status = 'error';
      lastError = error;
      return [];
    }
  };

  return {
    ...capability,
    capability,
    search,
    // Empty-query bootstrap is intentionally local and empty; do not enumerate
    // every account merely because a user typed the trigger character.
    bootstrap: () => [],
    cancel,
    getState: () => ({ status, available: capability.available, lastError }),
  };
}

/** Build the trigger shape consumed by Astryx ChatComposerInput. */
export function createMentionTrigger(adapter, options = {}) {
  const searchSource = createMentionSearchSource(adapter, options);
  return {
    character: MENTION_TRIGGER,
    searchSource,
    onSelect: mentionCandidateToToken,
    menuLabel: options.menuLabel || '提及用户',
    loadingText: options.loadingText || '正在查找用户…',
    emptySearchResultsText: options.emptySearchResultsText || '没有找到可提及的用户',
    ...(typeof options.renderItem === 'function' ? { renderItem: options.renderItem } : {}),
  };
}

/** A small controller facade for callers that want one dependency. */
export function createMentionController(adapter, options = {}) {
  const searchSource = createMentionSearchSource(adapter, options);
  return {
    searchSource,
    trigger: {
      character: MENTION_TRIGGER,
      searchSource,
      onSelect: mentionCandidateToToken,
      menuLabel: options.menuLabel || '提及用户',
      loadingText: options.loadingText || '正在查找用户…',
      emptySearchResultsText: options.emptySearchResultsText || '没有找到可提及的用户',
      ...(typeof options.renderItem === 'function' ? { renderItem: options.renderItem } : {}),
    },
    findToken: findMentionToken,
    insertCandidate: insertMentionCandidate,
    capability: searchSource.capability,
  };
}
