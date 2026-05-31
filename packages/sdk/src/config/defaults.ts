export const defaultTokenStore = {
  get: () => {
    if (typeof localStorage !== 'undefined') return localStorage.getItem('token')
    return null
  },
  set: (token: string) => {
    if (typeof localStorage !== 'undefined') localStorage.setItem('token', token)
  },
  clear: () => {
    if (typeof localStorage !== 'undefined') localStorage.removeItem('token')
  },
}

export const defaultConfig = {
  deduplicate: true,
  timeout: 10000,
  contentType: 'application/json',
}
