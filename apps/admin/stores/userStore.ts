import { defineStore } from 'pinia';
import { useApi, isApiError } from '~/lib/api';
import { canAccessPath } from '~/utils/access';

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
                this.permissions = (user as any)?.capabilities || (user as any)?.permissions || [];
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
        hasAnyPermission(capabilities: string[]): boolean {
            return capabilities.some((capability) => this.permissions.includes(capability));
        },
        canAccessPath(path: string): boolean {
            return canAccessPath(path, this.permissions, this.isRoot());
        },
        hasAdminAccess(): boolean {
            return this.roles.some((role: any) => {
                const key = String(role?.slug ?? role?.code ?? role?.name ?? '').toLowerCase();
                return key === 'root' || key === 'admin';
            });
        },
        isRoot(): boolean {
            return this.roles.some((role: any) => {
                const key = String(role?.slug ?? role?.code ?? role?.name ?? '').toLowerCase();
                return key === 'root';
            });
        },
        reset(): void {
            this.userInfo = null;
            this.permissions = [];
            this.roles = [];
            this.error = null;
            this.loading = false;
        }
    },
});
