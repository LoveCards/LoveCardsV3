//User
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
            } catch (err) {
                console.error('获取用户信息失败:', err);
            }
        }
    },
});