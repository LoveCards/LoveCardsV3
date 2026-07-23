import { useUserStore } from '~/stores/userStore';
import { useErrorStore } from '~/stores/client/errorStore';
import CommonUtils from '~/utils/common';

export default defineNuxtRouteMiddleware(async (to) => {
    if (to.path.startsWith('/dev')) return;
    if (to.path.startsWith('/client')) return;

    const hasToken = localStorage.getItem('token');
    if (!hasToken) return navigateTo('/dev/login');

    if (!to.path.startsWith('/apps')) return;

    const errorStore = useErrorStore();
    const userStore = useUserStore();

    try {
        await userStore.init();
    } catch (e) {
        return navigateTo('/dev/login');
    }

    if (userStore.userInfo && userStore.hasAdminAccess()) {
        return;
    }

    errorStore.setViewData({
        message: '权限不足',
        jumpBtn: { text: '返回首页', clickFun: CommonUtils.jumpRoot }
    });
    return navigateTo('/client/error');
});
