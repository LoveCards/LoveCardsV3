import type { JsonObject, JsonValue } from '@lovecards/sdk'

export function readString(value: JsonValue | undefined, fallback = ''): string {
  return typeof value === 'string' ? value : fallback
}

export function readBoolean(value: JsonValue | undefined, fallback = false): boolean {
  return typeof value === 'boolean' ? value : fallback
}

export function readNumber(value: JsonValue | undefined, fallback = 0): number {
  return typeof value === 'number' ? value : fallback
}

export function readObject(value: JsonValue | undefined): JsonObject {
  return value && typeof value === 'object' && !Array.isArray(value) ? value : {}
}
