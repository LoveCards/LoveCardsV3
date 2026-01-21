// cookie.ts
import jsCookies from 'js-cookie';

/**
 * Cookie选项接口
 * 提供完整的Cookie选项类型定义
 */
export interface CookieOptions {
    /**
     * Cookie过期时间（天数）
     */
    expires?: number | Date;
    /**
     * Cookie路径
     */
    path?: string;
    /**
     * Cookie域名
     */
    domain?: string;
    /**
     * 是否只在HTTPS下传输
     */
    secure?: boolean;
    /**
     * SameSite属性
     */
    sameSite?: 'strict' | 'lax' | 'none';
    /**
     * 是否启用HttpOnly（注意：客户端js-cookie无法设置HttpOnly，此选项仅用于类型定义）
     */
    httpOnly?: boolean;
}

/**
 * Cookie工具类
 * 提供类型安全的Cookie操作方法
 */
class Cookies {
    /**
     * 设置 Cookie
     * @param name Cookie名称
     * @param value Cookie值
     * @param options Cookie选项
     * @example
     * cookies.setCookie('token', 'abc123', { expires: 7, path: '/' })
     */
    public setCookie(name: string, value: string, options?: CookieOptions): void {
        jsCookies.set(name, value, options);
    }

    /**
     * 获取 Cookie
     * @param name Cookie名称
     * @returns Cookie值，如果不存在则返回undefined
     * @example
     * const token = cookies.getCookie('token')
     */
    public getCookie(name: string): string | undefined {
        return jsCookies.get(name);
    }

    /**
     * 获取 Cookie（带类型断言）
     * @param name Cookie名称
     * @returns Cookie值，如果不存在则返回null
     * @example
     * const token = cookies.getCookieOrNull('token') ?? 'default'
     */
    public getCookieOrNull(name: string): string | null {
        const value = jsCookies.get(name);
        return value ?? null;
    }

    /**
     * 检查 Cookie 是否存在
     * @param name Cookie名称
     * @returns 如果Cookie存在返回true，否则返回false
     * @example
     * if (cookies.hasCookie('token')) { ... }
     */
    public hasCookie(name: string): boolean {
        return jsCookies.get(name) !== undefined;
    }

    /**
     * 删除 Cookie
     * @param name Cookie名称
     * @param options Cookie选项（主要用于指定path和domain）
     * @example
     * cookies.deleteCookie('token', { path: '/' })
     */
    public deleteCookie(name: string, options?: Pick<CookieOptions, 'path' | 'domain'>): void {
        jsCookies.remove(name, options);
    }

    /**
     * 获取所有 Cookie
     * @returns 包含所有Cookie的键值对对象
     * @example
     * const allCookies = cookies.getAllCookies()
     */
    public getAllCookies(): Record<string, string> {
        return jsCookies.get();
    }

    /**
     * 获取所有Cookie名称
     * @returns Cookie名称数组
     * @example
     * const cookieNames = cookies.getCookieNames()
     */
    public getCookieNames(): string[] {
        return Object.keys(this.getAllCookies());
    }
}

/**
 * Cookie名称常量
 * 统一管理项目中使用的Cookie名称，避免硬编码
 */
export const COOKIE_NAMES = {
    /**
     * 用户Token
     */
    USER_TOKEN: 'UTOKEN',
    /**
     * 刷新Token（如果使用）
     */
    REFRESH_TOKEN: 'REFRESH_TOKEN',
    /**
     * 用户信息（如果使用）
     */
    USER_INFO: 'USER_INFO',
} as const;

/**
 * Cookie名称类型
 */
export type CookieName = typeof COOKIE_NAMES[keyof typeof COOKIE_NAMES] | string;

// 导出单例
const cookies = new Cookies();
export default cookies;