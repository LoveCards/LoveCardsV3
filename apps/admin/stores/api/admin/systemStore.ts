import { defineStore } from 'pinia';
import SystemApi from '~/api/app/admin/system';

export const useSystemStore = defineStore('system', {
    state: () => ({
        updata: null,
    }),
    actions: {
        async init() {
            if (this.updata === null) {
                await this.getUpdata();
            }
        },
        async getUpdata() {
            try {
                const response = await SystemApi.getUpdata();
                this.updata = response.data;

            } catch (err) {
                console.error('获取系统更新失败:', err);
            }
        }
    },
});