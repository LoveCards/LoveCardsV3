import { defineStore } from 'pinia';
import { useApi } from '~/lib/api';
import type { SystemUpdateInfo } from '@lovecards/sdk';

export const useSystemStore = defineStore('system', {
    state: () => ({
        updata: null as SystemUpdateInfo | null,
        loading: false,
        error: null as string | null,
    }),
    actions: {
        async init() {
            if (this.updata === null && !this.loading) {
                await this.getUpdata();
            }
        },
        async getUpdata() {
            this.loading = true;
            this.error = null;
            try {
                const client = useApi();
                this.updata = await client.system.update();
            } catch (error) {
                console.error('获取系统更新失败:', error);
            } finally {
                this.loading = false;
            }
        }
    },
});
