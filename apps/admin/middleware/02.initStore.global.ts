import { useUserStore } from "@/stores/userStore";
import { useDashboardStore } from '@/stores/dashboardStore';
import { useTagsStore } from "@/stores/tagsStore";
import { useSystemStore } from '~/stores/systemStore';

export default defineNuxtRouteMiddleware((to) => {

    //无需鉴权跳过
    if (!to.path.startsWith('/apps')) return;

    const userStore = useUserStore();
    const systemStore = useSystemStore();
    const dashboardStore = useDashboardStore();
    const tagsStore = useTagsStore();

    // Optional page data is route-scoped and capability-scoped. Fetching every
    // auxiliary endpoint on every page both wastes work and can create false
    // 403 errors for deliberately narrow admin roles.
    if (to.path === '/apps/dashboard' && userStore.hasGrant('dashboard.read', 'global')) {
        void dashboardStore.init().catch(() => undefined);
    }
    if ((to.path === '/apps/content/cards' || to.path.startsWith('/apps/content/cards/')) && userStore.hasGrant('cards.read', 'all')) {
        void tagsStore.init().catch(() => undefined);
    }
    if (
        to.path === '/apps/operations/upgrade'
        && userStore.hasGrant('system.update', 'global')
    ) {
        void systemStore.init().catch(() => undefined);
    }
});
