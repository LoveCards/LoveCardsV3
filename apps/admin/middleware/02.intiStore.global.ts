import { useUserStore } from "@/stores/userStore";
import { useDashboardStore } from '@/stores/dashboardStore';
import { useTagsStore } from "@/stores/tagsStore";
import { useSystemStore } from '~/stores/api/admin/systemStore';

export default defineNuxtRouteMiddleware(async (to, from) => {
    const userStore = useUserStore();
    const systemStore = useSystemStore();
    const dashboardStore = useDashboardStore();
    const tagsStore = useTagsStore();

    //超级管理员加载项
    const userInfo: any = userStore.userInfo;
    const rolesId = JSON.parse(userInfo.roles_id);
    if (rolesId.includes(0)) {
        await systemStore.init();
    }

    await dashboardStore.init();
    await tagsStore.init();
});
