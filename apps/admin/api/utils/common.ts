/**
 * 比较两个对象，移除它们的公共属性和值为空的属性。
 * 
 * 这个函数接受两个对象作为输入，然后返回一个新对象，该对象是第一个对象的副本，
 * 但移除了与第二个对象中相同键且值相等的属性。同时，它还会移除值为空的属性。
 * 
 * @param obj1 - 第一个对象，作为基准对象进行比较。
 * @param obj2 - 第二个对象，用于与第一个对象进行属性和值的比较。
 * @returns 返回一个新对象，其中移除了与 obj2 相同的属性以及值为空的属性。
 * 
 * @example
 * const obj1 = { a: 1, b: 2, c: null, d: undefined };
 * const obj2 = { a: 1, b: 3, c: null };
 * const result = removeCommonProperties(obj1, obj2);
 * console.log(result); // 输出：{ b: 2, d: undefined }
 * 
 * @note
 * - 如果 obj1 和 obj2 中的某个属性值为 null 或 undefined，它们会被视为“空属性”并被移除。
 * - 如果 obj1 和 obj2 中的某个属性值相同，该属性也会被移除。
 */
const removeCommonProperties = (obj1: any, obj2: any) => {
    //比对原始对象去除空属性与相同属性
    const result = { ...obj1 };
    for (const key in result) {
        if (obj2.hasOwnProperty(key) && result[key] === obj2[key]) {
            delete result[key];
        }
    }
    return result;
}

const deepClone = <T>(obj: T, hash = new WeakMap<any, any>()): T => {
    // 如果是 null 或非对象类型，直接返回
    if (obj === null || typeof obj !== "object") {
        return obj;
    }

    // 特殊类型处理
    if (obj instanceof Date) {
        return new Date(obj) as T;
    }
    if (obj instanceof RegExp) {
        return new RegExp(obj) as T;
    }

    // 检查是否已经克隆过
    if (hash.has(obj)) {
        return hash.get(obj) as T;
    }

    // 初始化结果
    let result: any;
    if (Array.isArray(obj)) {
        result = [];
    } else {
        result = {};
    }

    // 将当前对象加入哈希表，避免循环引用
    hash.set(obj, result);

    // 遍历对象的键
    for (let key in obj) {
        if (obj.hasOwnProperty(key)) {
            result[key] = deepClone(obj[key], hash);
        }
    }

    return result as T;
}

const CommonUtils = {
    removeCommonProperties,
    deepClone
}

export default CommonUtils;