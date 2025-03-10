//AI
import { defineStore } from 'pinia';
import type { AxiosRequestConfig, AxiosResponse, AxiosError } from 'axios';

export const useApiMonitorStore = defineStore('apiMonitor', {
    state: () => ({
        list: [] as Array<{
            id: number;
            config: AxiosRequestConfig | null;
            response: AxiosResponse | null;
            error: AxiosError | null;
        }>,
    }),
    actions: {
        add(config: AxiosRequestConfig | null, response: AxiosResponse | null, error: AxiosError | null) {
            const requestId = Date.now();
            this.list.push({
                id: requestId,
                config,
                response,
                error,
            });
            return requestId;
        },
        clear() {
            this.list = [];
        },
    },
});