import { useUserStore } from '~/stores/userStore';

const rootGo = (redirect: string) => {
    redirect = encodeURIComponent(redirect);//编码
    navigateTo({ path: "/root-go.html", query: { redirect: redirect } });
}

export default defineNuxtRouteMiddleware((to, from) => {
    const userStore = useUserStore();
    if (userStore.userInfo !== null) {
        //console.log(userStore.userInfo);   // 获取用户信息
        const data: any = userStore.userInfo;
        const rolesId = JSON.parse(data.roles_id);

        if (rolesId.includes(0) || rolesId.includes(1)) {
            console.log("通过."); // 用户有权限访问
        } else {
            console.log("无权限."); // 用户无权限，重定向到首页
            //rootGo("/")
            return false;
        }
    } else {
        console.error("用户信息获取失败:",);
        //rootGo("/")
        return false;
    }
});
