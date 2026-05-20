import { defineStore } from 'pinia';
import { getUserInfo as apiGetUserInfo } from '~/api/app/user';
import ErrorUtils from '~/api/utils/error';
import { showErrorNotification } from '~/api/utils/notifier';

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
                const response = await apiGetUserInfo();
                this.userInfo = response.data;
                this.permissions = response.data?.permissions || [];
                this.roles = response.data?.roles || [];
                return response;
            } catch (error) {
                const errorDetail = ErrorUtils.parse(error);
                this.error = errorDetail.message;
                showErrorNotification(errorDetail.message);
                throw error;
            } finally {
                this.loading = false;
            }
        },
        hasPermission(routeName: string): boolean {
            return this.permissions.includes(routeName);
        },
        hasAdminAccess(): boolean {
            return this.permissions.some(p => p.includes('.allList') || p.includes('.allUpdate'));
        },
        isRoot(): boolean {
            return this.roles.some(r => r.slug === 'root');
        }
    },
});