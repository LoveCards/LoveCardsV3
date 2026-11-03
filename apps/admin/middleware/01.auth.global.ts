import { useUserStore } from '~/stores/userStore';
import { useErrorStore } from '~/stores/client/errorStore';
import { canAccessPath, firstAccessibleAdminPath } from '~/utils/access';

export default defineNuxtRouteMiddleware(async (to) => {
    if (to.path === '/login' || to.path.startsWith('/dev')) return;
    if (to.path.startsWith('/client')) return;

    const hasToken = localStorage.getItem('token');
    if (!hasToken) return navigateTo('/login');

    if (!to.path.startsWith('/apps')) return;

    const errorStore = useErrorStore();
    const userStore = useUserStore();

    try {
        await userStore.init();
    } catch (e) {
        return navigateTo('/login');
    }

    if (!userStore.userInfo || !userStore.hasAdminAccess()) {
        await errorStore.setViewData({
            code: 403,
            kind: 'forbidden',
            message: '当前账号没有进入管理端的权限。',
            details: '请联系超级管理员申请合适的管理职责。',
            jumpBtn: { text: '返回登录', url: '/login' },
        });
        return navigateTo('/client/error');
    }
    if (to.path.startsWith('/apps') && !canAccessPath(to.path, userStore.grants)) {
        const fallback = firstAccessibleAdminPath(userStore.grants);
        await errorStore.setViewData({
            code: 403,
            kind: 'forbidden',
            message: '你没有查看此页面的权限。',
            details: '页面入口已按当前账号的职责隐藏；如果你需要访问，请联系超级管理员。',
            jumpBtn: fallback
                ? { text: '前往可访问页面', url: fallback }
                : { text: '返回登录', url: '/login' },
        });
        return navigateTo('/client/error');
    }
    return;
});
