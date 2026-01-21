import { defineStore } from 'pinia';
import { getUserInfo as apiGetUserInfo } from '~/api/app/user';
import ErrorUtils from '~/api/utils/error';
import { showErrorNotification } from '~/api/utils/notifier';

export const useUserStore = defineStore('user', {
    state: () => ({
        userInfo: null,
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
                return response;
            } catch (error) {
                const errorDetail = ErrorUtils.parse(error);
                this.error = errorDetail.message;
                // 用户信息获取失败时显示错误通知
                showErrorNotification(errorDetail.message);
                throw error;
            } finally {
                this.loading = false;
            }
        }
    },
});