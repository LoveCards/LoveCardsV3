import { defineStore } from 'pinia';
import { useApi, isApiError } from '~/lib/api';
import type { DashboardData } from '@lovecards/sdk';

export const useDashboardStore = defineStore('dashboard', {
    state: () => ({
        dashboard: null as DashboardData | null,
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
                this.error = isApiError(error) ? error.message : '无法加载工作台数据，请稍后重试。';
                // Keep a previously loaded snapshot visible when a refresh fails.
                // The error state still lets the page communicate that the snapshot
                // may be stale instead of silently rendering fabricated zeroes.
                console.error('获取仪表盘失败:', error);
            } finally {
                this.loading = false;
            }
        }
    },
});
