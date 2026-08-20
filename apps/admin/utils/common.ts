import * as lodash from 'lodash-es'

export const removeCommonProperties = <T extends Record<string, unknown>>(obj1: T, obj2: Partial<T>): Partial<T> => {
    const result = { ...obj1 } as Partial<T>
    for (const key in result) {
        if (Object.prototype.hasOwnProperty.call(obj2, key) && result[key] === obj2[key as keyof T]) {
            delete result[key]
        }
    }
    return result
}

export const deepClone = <T>(obj: T, hash = new WeakMap<object, unknown>()): T => {
    if (obj === null || typeof obj !== 'object') return obj
    if (obj instanceof Date) return new Date(obj) as T
    if (obj instanceof RegExp) return new RegExp(obj) as T
    if (hash.has(obj as object)) return hash.get(obj as object) as T

    const result: Record<string, unknown> | unknown[] = Array.isArray(obj) ? [] : {}
    hash.set(obj as object, result)
    if (Array.isArray(result)) {
        for (const item of obj as unknown[]) result.push(deepClone(item, hash))
    } else {
        for (const key of Object.keys(obj as object)) {
            result[key] = deepClone((obj as Record<string, unknown>)[key], hash)
        }
    }
    return result as T
}

const jumpRoot = (path = '/') => {
    return window.location.href = path;
}

const CommonUtils = {
    lodash,
    jumpRoot,
    removeCommonProperties,
    deepClone,
}

export default CommonUtils;
