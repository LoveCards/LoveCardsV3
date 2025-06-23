import { useUserStore } from "@/stores/userStore";
import { useDashboardStore } from '@/stores/dashboardStore';
import { useTagsStore } from "@/stores/tagsStore";

export default defineNuxtRouteMiddleware(async (to, from) => {
    const userStore = useUserStore();
    const dashboardStore = useDashboardStore();
    const tagsStore = useTagsStore();

    await userStore.init();
    await dashboardStore.init();
    await tagsStore.init();

    // if(dashboardStore.dashboard === null) {
    //     return navigateTo('/system/error');
    // }
});
