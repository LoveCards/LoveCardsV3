import { useUserStore } from '~/stores/userStore';
import { useErrorStore } from '~/stores/client/errorStore';
import CommonUtils from '~/utils/common';

export default defineNuxtRouteMiddleware(async (to, from) => {
    const errorStore = useErrorStore();
    const userStore = useUserStore();

    //无需鉴权跳过
    if (!to.path.startsWith('/apps')) return;

    await userStore.init(); //初始化用户信息
    if (userStore.userInfo !== null) {
        //console.log(userStore.userInfo);   // 获取用户信息
        const data: any = userStore.userInfo;
        const rolesId = JSON.parse(data.roles_id);

        if (rolesId.includes(0) || rolesId.includes(1)) {
            console.log("通过."); // 用户有权限访问
        } else {
            console.log("无权限."); // 用户无权限，重定向到首页
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
        console.error("用户信息获取失败:",);
        const viewData = {
            message: "登入信息不存在",
            jumpBtn: {
                text: "返回首页",
                clickFun: CommonUtils.jumpRoot
            }
        }
        errorStore.setViewData(viewData);
        return navigateTo('/client/error');
    }
});
