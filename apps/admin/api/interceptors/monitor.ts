import type { AxiosRequestConfig, AxiosResponse, AxiosError } from "axios";
import { useApiMonitorStore } from '~/stores/apiMonitorStore';

const request = (config: AxiosRequestConfig) => {
    //console.log('请求拦截器监控', config);
    const apiMonitorStore = useApiMonitorStore();
    apiMonitorStore.add(config, null, null);
    return config;
}

const response = (response: AxiosResponse) => {
    //console.log('响应拦截器监控', response);
    const apiMonitorStore = useApiMonitorStore();
    apiMonitorStore.add(null, response, null);
};

const error = (error: AxiosError) => {
    //console.log('错误拦截器监控', error);
    const apiMonitorStore = useApiMonitorStore();
    apiMonitorStore.add(null, null, error);
};

const ApiMonitor = {
    request,
    response,
    error
};

export default ApiMonitor;
