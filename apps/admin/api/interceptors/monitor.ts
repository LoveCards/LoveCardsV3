import type { AxiosRequestConfig, AxiosResponse, AxiosError } from "axios";
import { useApiMonitorStore } from '~/stores/apiMonitorStore';

/**
 * API请求记录
 * 用于监控和记录API请求、响应和错误信息
 * 
 * 使用方法：
 * 1. 在axios配置中注册拦截器：
 *    axios.interceptors.request.use(ApiMonitor.request);
 *    axios.interceptors.response.use(ApiMonitor.response, ApiMonitor.error);
 * 
 * 2. 控制监控状态：
 *    // 关闭所有监控
 *    ApiMonitor.setState(false, false, false);
 *    
 *    // 只监控请求
 *    ApiMonitor.setState(true, false, false);
 *    
 *    // 保持当前状态，只修改错误监控
 *    ApiMonitor.setState(null, null, false);
 *    
 *    // 恢复默认状态（监控所有非GET请求）
 *    ApiMonitor.setState();
 * 
 * 3. 查看监控数据：
 *    const apiMonitorStore = useApiMonitorStore();
 *    // 获取监控记录
 *    const records = apiMonitorStore.records;
 */

// 监控状态控制变量
let requestState = true;    // 请求监控状态
let responseState = true;   // 响应监控状态
let errorState = true;      // 错误监控状态
let getMethodState = {
    request: false,   // GET请求监控状态
    response: false,  // GET请求响应状态
    error: true      // GET请求错误状态
}; // GET请求监控状态

/**
 * 请求拦截器
 * 用于监控和记录API请求信息
 * @param config Axios请求配置对象
 * @returns 处理后的请求配置
 * 
 * 监控内容：
 * - 请求URL
 * - 请求方法
 * - 请求头
 * - 请求参数
 * - 请求时间
 */
const request = (config: AxiosRequestConfig) => {
    if (!requestState) { return config; }
    //console.log('请求拦截器监控', config);
    if (!getMethodState.request) {
        if (config.method === 'get') {
            return config;
        }
    }
    const apiMonitorStore = useApiMonitorStore();
    apiMonitorStore.add(config, null, null);
    return config;
}

/**
 * 响应拦截器
 * 用于监控和记录API响应信息
 * @param response Axios响应对象
 * 
 * 监控内容：
 * - 响应状态码
 * - 响应数据
 * - 响应时间
 * - 请求耗时
 */
const response = (response: AxiosResponse) => {
    //console.log('响应拦截器监控', response);
    if (!responseState) { return; }
    if (!getMethodState.response) {
        if (response.config.method === 'get') {
            return;
        }
    }
    const apiMonitorStore = useApiMonitorStore();
    apiMonitorStore.add(null, response, null);
};

/**
 * 错误拦截器
 * 用于监控和记录API错误信息
 * @param error Axios错误对象
 * 
 * 监控内容：
 * - 错误类型
 * - 错误信息
 * - 错误详情
 * - 请求配置
 */
const error = (error: AxiosError) => {
    //console.log('错误拦截器监控', error);
    if (!errorState) { return; }
    if (!getMethodState.error) {
        if (error.config && error.config.method === 'get') {
            return;
        }
    }
    const apiMonitorStore = useApiMonitorStore();
    apiMonitorStore.add(null, null, error);
};

/**
 * 设置监控状态
 * 用于控制是否监控请求、响应和错误
 * @param req 请求监控状态（true:开启, false:关闭, null:保持当前状态）
 * @param res 响应监控状态（true:开启, false:关闭, null:保持当前状态）
 * @param err 错误监控状态（true:开启, false:关闭, null:保持当前状态）
 * 
 * 示例：
 * ApiMonitor.setState(true, false, true);  // 只监控请求和错误
 * ApiMonitor.setState(null, true, null);   // 只修改响应监控状态
 */
const setState = (req: boolean | null = true, res: boolean | null = true, err: boolean | null = true) => {
    requestState = req === null ? requestState : req;
    responseState = res === null ? responseState : res;
    errorState = err === null ? errorState : err;
}

const setGetState = (err: boolean | null = false, req: boolean | null = false, res: boolean | null = false) => {
    getMethodState.request = req === null ? getMethodState.request : req;
    getMethodState.response = res === null ? getMethodState.response : res;
    getMethodState.error = err === null ? getMethodState.error : err;
}

// 导出API监控对象
const ApiMonitor = {
    request,
    response,
    error,
    setState,
    setGetState
};

export default ApiMonitor;
