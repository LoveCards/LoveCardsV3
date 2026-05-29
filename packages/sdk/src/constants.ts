export const PUBLIC_API = {
  'cards.hot':     { method: 'GET',  path: '/api/cards/hot' },
  'cards.list':    { method: 'GET',  path: '/api/cards' },
  'cards.get':     { method: 'GET',  path: '/api/cards/:id' },
  'cards.search':  { method: 'GET',  path: '/api/cards/search' },
  'tags.list':     { method: 'GET',  path: '/api/tags' },
  'tags.get':      { method: 'GET',  path: '/api/tags/:id' },
  'comments.list': { method: 'GET',  path: '/api/comments/card/:id' },
  'users.me':      { method: 'GET',  path: '/api/users/me' },
  'system.theme':  { method: 'GET',  path: '/api/theme/config' },
} as const

export type PublicAPIKey = keyof typeof PUBLIC_API
