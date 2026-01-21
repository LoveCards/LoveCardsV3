import { defineStore } from 'pinia';
import { getDashboard as apiGetDashboard } from '~/api/app/admin/dashboard';
import ErrorUtils from '~/api/utils/error';
import { showErrorNotification } from '~/api/utils/notifier';

export const useDashboardStore = defineStore('dashboard', {
    state: () => ({
        dashboard: null,
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
                const response = await apiGetDashboard();
                this.dashboard = response.data;
            } catch (error) {
                const errorDetail = ErrorUtils.parse(error);
                this.error = errorDetail.message;
                // 仪表盘数据获取失败时显示错误通知
                showErrorNotification(`获取仪表盘失败：${errorDetail.message}`);
                console.error('获取仪表盘失败:', error);
            } finally {
                this.loading = false;
            }
        }
    },
});