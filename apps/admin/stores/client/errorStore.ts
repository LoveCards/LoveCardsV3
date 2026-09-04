import { defineStore } from 'pinia';

interface ViewData {
    message: string | null
    code?: number | string | null
    kind?: 'forbidden' | 'unauthorized' | 'not-found' | 'error'
    details?: string | null
    jumpBtn?: {
        text?: string | null
        url?: string | null
        clickFun?: (() => void) | null
    }
}

export const useErrorStore = defineStore('error', {
    state: () => ({
        viewData: {
            message: null,
            code: null,
            kind: 'error',
            details: null,
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
