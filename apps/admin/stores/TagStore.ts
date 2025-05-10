import { defineStore } from 'pinia';
import UserTagsApi from '~/api/app/tag';

export const useTagStore = defineStore('tag', {
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