import { defineStore } from 'pinia';
import { useApi } from '~/lib/api';
import type { Tag } from '@lovecards/sdk';

export const useTagsStore = defineStore('tags', {
    state: () => ({
        tags: null as Tag[] | null,
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
                const client = useApi();
                this.tags = await client.tags.list();
                return this.tags;
            } catch (error) {
                console.error('获取标签失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        }
    },
});
