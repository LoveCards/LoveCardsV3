import { useUserStore } from '~/stores/userStore';

const ROOT_ONLY_PREFIXES = [
    '/apps/roles',
    '/apps/permissions',
    '/apps/storage',
    '/apps/sender',
    '/apps/captcha',
    '/apps/system',
    '/apps/view',
    '/apps/updata',
];

export default defineNuxtRouteMiddleware((to) => {
    if (!to.path.startsWith('/apps')) return;

    const userStore = useUserStore();
    const isRootOnly = ROOT_ONLY_PREFIXES.some(p => to.path.startsWith(p));

    if (isRootOnly && !userStore.isRoot()) {
        return navigateTo('/apps/dashboard');
    }
});
