import { defineStore } from 'pinia';
import UserTagsApi from '~/api/app/user/tags';
import ErrorUtils from '~/api/utils/error';
import { showErrorNotification } from '~/api/utils/notifier';

export const useTagsStore = defineStore('tags', {
    state: () => ({
        tags: null,
        loading: false,
        error: null as string | null,
    }),
    actions: {
        async init() {
            if (this.tags === null && !this.loading) {
                await this.getIndex();
            }
        },
        async getIndex() {
            this.loading = true;
            this.error = null;
            try {
                const response = await UserTagsApi.getIndex();
                this.tags = response.data;
                return response;
            } catch (error) {
                const errorDetail = ErrorUtils.parse(error);
                this.error = errorDetail.message;
                // 标签获取失败时显示错误通知
                showErrorNotification(`获取标签失败：${errorDetail.message}`);
                throw error;
            } finally {
                this.loading = false;
            }
        }
    },
});