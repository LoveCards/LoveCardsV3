import type { AxiosRequestConfig, AxiosResponse, AxiosError } from "axios";
import { useApiMonitorStore } from '~/stores/apiMonitorStore';

let requestState = true;
let responseState = true;
let errorState = true;
let getMethodState = false;

const request = (config: AxiosRequestConfig) => {
    if (!requestState) { return config; }
    //console.log('请求拦截器监控', config);
    if (!getMethodState) {
        if (config.method === 'get') {
            return config;
        }
    }
    const apiMonitorStore = useApiMonitorStore();
    apiMonitorStore.add(config, null, null);
    return config;
}

const response = (response: AxiosResponse) => {
    //console.log('响应拦截器监控', response);
    if (!responseState) { return; }
    if (!getMethodState) {
        if (response.config.method === 'get') {
            return;
        }
    }
    const apiMonitorStore = useApiMonitorStore();
    apiMonitorStore.add(null, response, null);
};

const error = (error: AxiosError) => {
    //console.log('错误拦截器监控', error);
    if (!errorState) { return; }
    if (!getMethodState) {
        if (error.config.method === 'get') {
            return;
        }
    }
    const apiMonitorStore = useApiMonitorStore();
    apiMonitorStore.add(null, null, error);
};

const setState = (req: boolean | null = true, res: boolean | null = true, err: boolean | null = true) => {
    requestState = req === null ? requestState : req;
    responseState = res === null ? responseState : res;
    errorState = err === null ? errorState : err;
}

const ApiMonitor = {
    request,
    response,
    error,
    setState
};

export default ApiMonitor;
