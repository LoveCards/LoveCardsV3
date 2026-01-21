import { defineStore } from 'pinia';
import SystemApi from '~/api/app/admin/system';
import ErrorUtils from '~/api/utils/error';
import { showErrorNotification } from '~/api/utils/notifier';

/**
 * 系统更新信息接口
 */
export interface UpdataInfo {
    ver: {
        VerS: string;
        Ver: string;
    };
    latest: {
        tag_name: string;
        name: string;
        url: string;
    };
    verlog: string;
}

export const useSystemStore = defineStore('system', {
    state: () => ({
        updata: null as UpdataInfo | null,
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
                const response = await SystemApi.getUpdata();
                this.updata = response.data;
            } catch (error) {
                const errorDetail = ErrorUtils.parse(error);
                this.error = errorDetail.message;
                // 系统更新信息获取失败时显示错误通知（静默失败，不影响使用）
                // 只在控制台记录，不显示通知，因为这不是关键功能
                console.error('获取系统更新失败:', error);
            } finally {
                this.loading = false;
            }
        }
    },
});