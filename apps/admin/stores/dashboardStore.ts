import { defineStore } from 'pinia';
import { useApi, isApiError } from '~/lib/api';
import type { DashboardData } from '@lovecards/sdk';

let dashboardRequest: Promise<void> | null = null;

export const useDashboardStore = defineStore('dashboard', {
    state: () => ({
        dashboard: null as DashboardData | null,
        loading: false,
        error: null as string | null,
        readAt: '',
    }),
    actions: {
        async init() {
            if (this.dashboard === null) {
                await this.getDashboard();
            }
        },
        async getDashboard() {
            // 路由预取与页面刷新共用一次在途请求，避免首屏重复读取。
            if (dashboardRequest) return dashboardRequest;
            this.loading = true;
            this.error = null;
            const client = useApi();
            dashboardRequest = (async () => {
            try {
                this.dashboard = await client.dashboard.index();
                this.readAt = new Date().toLocaleString('zh-CN');
            } catch (error) {
                this.error = isApiError(error) ? error.message : '无法加载工作台数据，请稍后重试。';
                // 刷新失败保留上次快照和读取时间，由页面明确标记旧数据。
            } finally {
                this.loading = false;
                dashboardRequest = null;
            }
            })();
            return dashboardRequest;
        }
    },
});
