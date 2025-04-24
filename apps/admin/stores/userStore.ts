import { defineStore } from 'pinia';
import { getUserInfo as apiGetUserInfo } from '~/api/app/user';

export const useUserStore = defineStore('user', {
    state: () => ({
        userInfo: null,
    }),
    actions: {
        async init() {
            if (this.userInfo === null) {
                await this.getUserInfo();
            }
        },
        async getUserInfo() {
            try {
                const response = await apiGetUserInfo();
                this.userInfo = response.data;
                return response;
            } catch (error) {
                return error;
            }
        }
    },
});