import { defineStore } from 'pinia';
import { useApi, isApiError } from '~/lib/api';

export const useUserStore = defineStore('user', {
    state: () => ({
        userInfo: null as any,
        permissions: [] as string[],
        roles: [] as { id: number; name: string; slug: string }[],
        loading: false,
        error: null as string | null,
    }),
    actions: {
        async init() {
            if (this.userInfo === null && !this.loading) {
                await this.getUserInfo();
            }
        },
        async getUserInfo() {
            this.loading = true;
            this.error = null;
            try {
                const client = useApi();
                const user = await client.users.me();
                this.userInfo = user;
                this.permissions = user?.capabilities || [];
                this.roles = user?.roles || [];
                return user;
            } catch (error) {
                if (isApiError(error)) {
                    this.error = error.message;
                }
                throw error;
            } finally {
                this.loading = false;
            }
        },
        hasPermission(routeName: string): boolean {
            return this.permissions.includes(routeName);
        },
        hasAdminAccess(): boolean {
            return this.roles.some(r => r.slug === 'root' || r.slug === 'admin');
        },
        isRoot(): boolean {
            return this.roles.some(r => r.slug === 'root');
        }
    },
});
