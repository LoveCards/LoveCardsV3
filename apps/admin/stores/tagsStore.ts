import { defineStore } from 'pinia';
import UserTagsApi from '~/api/app/user/tags';

export const useTagsStore = defineStore('tags', {
    state: () => ({
        tags: null,
    }),
    actions: {
        async init() {
            if (this.tags === null) {
                await this.getIndex();
            }
        },
        async getIndex() {
            try {
                const response = await UserTagsApi.getIndex();
                this.tags = response.data;
                return response;
            } catch (error) {
                return error;
            }
        }
    },
});