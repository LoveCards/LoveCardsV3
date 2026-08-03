import { useUserStore } from "@/stores/userStore";
import { useDashboardStore } from '@/stores/dashboardStore';
import { useTagsStore } from "@/stores/tagsStore";
import { useSystemStore } from '~/stores/api/admin/systemStore';

export default defineNuxtRouteMiddleware(async (to, from) => {

    //无需鉴权跳过
    if (!to.path.startsWith('/apps')) return;

    const userStore = useUserStore();
    const systemStore = useSystemStore();
    const dashboardStore = useDashboardStore();
    const tagsStore = useTagsStore();

    //超级管理员加载项
    const userInfo: any = await userStore.userInfo;
    // Optional page data must not block route navigation or leave the login
    // button spinning while a slow/failed auxiliary endpoint responds.
    if (userStore.isRoot()) void systemStore.init();
    void dashboardStore.init();
    void tagsStore.init();
});
