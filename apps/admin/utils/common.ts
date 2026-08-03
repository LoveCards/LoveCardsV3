import * as lodash from 'lodash-es'

const removeCommonProperties = (obj1: Record<string, any>, obj2: Record<string, any>) => {
    const result = { ...obj1 }
    for (const key in result) {
        if (Object.prototype.hasOwnProperty.call(obj2, key) && result[key] === obj2[key]) {
            delete result[key]
        }
    }
    return result
}

const deepClone = <T>(obj: T, hash = new WeakMap<object, any>()): T => {
    if (obj === null || typeof obj !== 'object') return obj
    if (obj instanceof Date) return new Date(obj) as T
    if (obj instanceof RegExp) return new RegExp(obj) as T
    if (hash.has(obj as object)) return hash.get(obj as object) as T

    const result: any = Array.isArray(obj) ? [] : {}
    hash.set(obj as object, result)
    for (const key of Object.keys(obj as object)) {
        result[key] = deepClone((obj as Record<string, any>)[key], hash)
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
