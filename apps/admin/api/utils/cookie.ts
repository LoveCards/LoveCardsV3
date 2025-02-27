// cookie.ts
import jsCookies from 'js-cookie';

class Cookies {
    // 设置 Cookie
    public setCookie(name: string, value: string, options?: { expires?: number; path?: string }) {
        jsCookies.set(name, value, options);
    }

    // 获取 Cookie
    public getCookie(name: string): string | undefined {
        return jsCookies.get(name);
    }

    // 删除 Cookie
    public deleteCookie(name: string, options?: { path?: string }) {
        jsCookies.remove(name, options);
    }

    // 获取所有 Cookie
    public getAllCookies(): Record<string, string> {
        return jsCookies.get();
    }
}

// 导出单例
const cookies = new Cookies();
export default cookies;