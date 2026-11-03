import { defineStore } from 'pinia';
import { useApi, isApiError } from '~/lib/api';
import { canAccessPath } from '~/utils/access';
import type { Grant, RoleInfo, User } from '@lovecards/sdk';

let userLoadPromise: Promise<User> | null = null;

export const useUserStore = defineStore('user', {
    state: () => ({
        userInfo: null as User | null,
        grants: [] as Grant[],
        roles: [] as RoleInfo[],
        loading: false,
        error: null as string | null,
    }),
    actions: {
        async init() {
            if (this.userInfo) return this.userInfo;
            if (!userLoadPromise) {
                userLoadPromise = this.getUserInfo().finally(() => {
                    userLoadPromise = null;
                });
            }
            return userLoadPromise;
        },
        async getUserInfo() {
            this.loading = true;
            this.error = null;
            try {
                const client = useApi();
                const user = await client.users.me();
                this.userInfo = user;
                this.grants = user.grants ?? [];
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
        hasGrant(capability: string, scope: Grant['scope']): boolean {
            return this.grants.some((grant) => grant.capability === capability && grant.scope === scope);
        },
        canAccessPath(path: string): boolean {
            return canAccessPath(path, this.grants);
        },
        hasAdminAccess(): boolean {
            return this.hasGrant('admin.access', 'global');
        },
        reset(): void {
            userLoadPromise = null;
            this.userInfo = null;
            this.grants = [];
            this.roles = [];
            this.error = null;
            this.loading = false;
        }
    },
});
