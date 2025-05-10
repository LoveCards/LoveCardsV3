import { useUserStore } from "@/stores/userStore";
import { useDashboardStore } from '@/stores/dashboardStore';
import { useTagStore } from "@/stores/TagStore";


export default defineNuxtRouteMiddleware(async (to, from) => {
    const userStore = useUserStore();
    const dashboardStore = useDashboardStore();
    const tagStore = useTagStore();

    await userStore.init();
    await dashboardStore.init();
    await tagStore.init();
});
