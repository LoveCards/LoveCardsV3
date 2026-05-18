import { useUserStore } from '~/stores/userStore';
import { useErrorStore } from '~/stores/client/errorStore';
import CommonUtils from '~/utils/common';
import Cookies, { COOKIE_NAMES } from '~/api/utils/cookie';

export default defineNuxtRouteMiddleware(async (to, from) => {
    if (to.path.startsWith('/dev')) return;
    if (to.path.startsWith('/client')) return;

    const hasToken = Cookies.getCookie(COOKIE_NAMES.USER_TOKEN);
    if (!hasToken) {
        return navigateTo('/dev/login');
    }

    if (!to.path.startsWith('/apps')) return;

    const errorStore = useErrorStore();
    const userStore = useUserStore();

    try {
        await userStore.init();
    } catch (e) {
        console.error("用户信息获取失败:", e);
        return navigateTo('/dev/login');
    }

    if (userStore.userInfo !== null) {
        if (userStore.hasAdminAccess()) {
            console.log("通过.");
        } else {
            console.log("无权限.");
            const viewData = {
                message: "权限不足",
                jumpBtn: {
                    text: "返回首页",
                    clickFun: CommonUtils.jumpRoot
                }
            }
            errorStore.setViewData(viewData);
            return navigateTo('/client/error');
        }
    } else {
        console.error("用户信息为空:");
        return navigateTo('/dev/login');
    }
});
