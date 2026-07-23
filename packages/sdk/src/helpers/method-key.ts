export function methodKey(method: string, url: string, params?: any): string {
  return `${method}:${url}:${JSON.stringify(params ?? {})}`
}
