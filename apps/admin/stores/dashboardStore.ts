import { defineStore } from 'pinia';
import { getDashboard as apiGetDashboard } from '~/api/app/dashboard';

export const useDashboardStore = defineStore('dashboard', {
    state: () => ({
        dashboard: null,
    }),
    actions: {
        async init() {
            if (this.dashboard === null) {
                await this.getDashboard();
            }
        },
        async getDashboard() {
            try {
                const response = await apiGetDashboard();
                this.dashboard = response.data;
            } catch (err) {
                console.error('获取仪仪表盘失败:', err);
            }
        }
    },
});