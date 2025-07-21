import { defineStore } from 'pinia';

interface ViewData {
    message: string | null
    jumpBtn: {
        text: string | null
        url: string | null
    }
}

export const useErrorStore = defineStore('error', {
    state: () => ({
        viewData: {
            message: null,
            jumpBtn: {
                text: null,
                url: null
            }
        } as ViewData
    }),
    actions: {
        async setViewData(viewData: ViewData) {
            this.viewData = viewData;
        }
    },

});