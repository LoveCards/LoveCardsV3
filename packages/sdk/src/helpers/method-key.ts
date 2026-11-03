export function methodKey(method: string, url: string, params?: unknown): string {
  return `${method}:${url}:${JSON.stringify(params ?? {})}`
}
