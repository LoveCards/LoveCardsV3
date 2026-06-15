import { defineStore } from 'pinia';
import { useApi } from '~/lib/api';

export const useDashboardStore = defineStore('dashboard', {
    state: () => ({
        dashboard: null as any,
        loading: false,
        error: null as string | null,
    }),
    actions: {
        async init() {
            if (this.dashboard === null && !this.loading) {
                await this.getDashboard();
            }
        },
        async getDashboard() {
            this.loading = true;
            this.error = null;
            try {
                const client = useApi();
                this.dashboard = await client.dashboard.index();
            } catch (error) {
                console.error('获取仪表盘失败:', error);
            } finally {
                this.loading = false;
            }
        }
    },
});
