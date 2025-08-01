// import { useUserStore } from "@/stores/userStore";
import { useDashboardStore } from '@/stores/dashboardStore';
import { useTagsStore } from "@/stores/tagsStore";
import { useSystemStore } from '~/stores/api/admin/systemStore';

export default defineNuxtRouteMiddleware(async (to, from) => {
    // const userStore = useUserStore();
    const systemStore = useSystemStore();
    const dashboardStore = useDashboardStore();
    const tagsStore = useTagsStore();

    // await userStore.init();
    await systemStore.init();
    await dashboardStore.init();
    await tagsStore.init();
});
