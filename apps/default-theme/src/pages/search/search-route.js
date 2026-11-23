import { SEARCH_SCOPES } from '../../services/search-service.js';

export function canonicalSearchPath({ scope, search = '', hash = '' }) {
  const params = new URLSearchParams(search);
  const term = String(params.get('q') || '').trim();
  const validScope = SEARCH_SCOPES.includes(scope) ? scope : null;
  const path = term && validScope ? `/search/${validScope}` : '/search';
  if (term) params.set('q', term);
  else params.delete('q');
  if (!term || !validScope) params.delete('page');
  const sort = params.get('sort');
  if (term && validScope === 'posts' && sort === 'latest') params.set('sort', 'latest');
  else params.delete('sort');
  const page = Number(params.get('page'));
  if (params.has('page') && (!Number.isSafeInteger(page) || page <= 1)) params.delete('page');
  const nextQuery = params.toString();
  const next = `${path}${nextQuery ? `?${nextQuery}` : ''}${hash}`;
  const current = `/search${scope ? `/${scope}` : ''}${search}${hash}`;
  return current === next ? null : next;
}

export function searchHref(scope, query, page = 1, sort = 'match') {
  const params = new URLSearchParams({ q: String(query).trim() });
  if (page > 1) params.set('page', String(page));
  if (scope === 'posts' && sort === 'latest') params.set('sort', 'latest');
  return `/search${scope && scope !== 'all' ? `/${scope}` : ''}?${params}`;
}
