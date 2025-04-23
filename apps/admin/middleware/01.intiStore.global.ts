import { useUserStore } from "~/stores/userStore";
import { useDashboardStore } from '~/stores/dashboardStore';


export default defineNuxtRouteMiddleware(async (to, from) => {
    const userStore = useUserStore();
    const dashboardStore = useDashboardStore();
    await userStore.init();
    await dashboardStore.init();
});
