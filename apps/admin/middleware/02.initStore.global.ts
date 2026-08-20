import { useUserStore } from "@/stores/userStore";
import { useDashboardStore } from '@/stores/dashboardStore';
import { useTagsStore } from "@/stores/tagsStore";
import { useSystemStore } from '~/stores/systemStore';

export default defineNuxtRouteMiddleware(async (to, from) => {

    //无需鉴权跳过
    if (!to.path.startsWith('/apps')) return;

    const userStore = useUserStore();
    const systemStore = useSystemStore();
    const dashboardStore = useDashboardStore();
    const tagsStore = useTagsStore();

    // Optional page data must not block route navigation or leave the login
    // button spinning while a slow/failed auxiliary endpoint responds.
    if (userStore.hasGrant('config.read', 'global')) void systemStore.init();
    void dashboardStore.init();
    void tagsStore.init();
});
