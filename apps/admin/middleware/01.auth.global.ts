import { useUserStore } from '~/stores/userStore';
import { useErrorStore } from '~/stores/client/errorStore';
import CommonUtils from '~/utils/common';
import { canAccessPath } from '~/utils/access';

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

    if (!userStore.userInfo || !userStore.hasAdminAccess()) return navigateTo('/client/error');
    if (to.path.startsWith('/apps') && !canAccessPath(to.path, userStore.grants)) {
        errorStore.setViewData({
            message: '权限不足',
            jumpBtn: { text: '返回首页', clickFun: CommonUtils.jumpRoot }
        });
        return navigateTo('/client/error');
    }
    return;
});
