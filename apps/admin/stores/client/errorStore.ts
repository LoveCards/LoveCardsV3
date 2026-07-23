import { defineStore } from 'pinia';

interface ViewData {
    message: String | null
    jumpBtn?: {
        text?: String | null
        url?: String | null
        clickFun?: Function | null
    }
}

export const useErrorStore = defineStore('error', {
    state: () => ({
        viewData: {
            message: null,
            jumpBtn: {
                text: null,
                url: null,
                clickFun: null
            }
        } as ViewData
    }),
    actions: {
        async setViewData(viewData: ViewData) {
            this.viewData = viewData;
        }
    },

});