import axios from "axios";
import type { AxiosRequestConfig, AxiosResponse, AxiosError } from "axios";
import Cookies from "./utils/cookie";
import ApiMonitor from "./interceptors/monitor";
import ErrorUtils from "./utils/error";
import { showErrorNotification } from "./utils/notifier";

//实例化 基础配置
const instance = axios.create({
    baseURL: "/api",
    timeout: 1000,
    headers: {},
});

// 添加请求拦截器
instance.interceptors.request.use(
    function (config) {
        // 在发送请求之前做些什么
        ApiMonitor.request(config);
        // 读取Token并添加到请求头
        config.headers[
            "authorization"
        ] = `Bearer ` + Cookies.getCookie("UTOKEN");

        //console.log(config.headers);
        return config;
    },
    function (error) {
        ApiMonitor.error(error);
        // 对请求错误做些什么
        return Promise.reject(error);
    }
);

// 添加响应拦截器
instance.interceptors.response.use(
    function (response) {
        ApiMonitor.response(response);
        // 2xx 范围内的状态码都会触发该函数。
        // 对响应数据做点什么
        return response;
    },
    function (error: AxiosError) {
        // API监控（404错误不监控）
        if (error.response?.status !== 404) {
            ApiMonitor.error(error);
        }

        // 统一错误处理
        const errorDetail = ErrorUtils.parse(error);
        
        // 根据错误类型进行不同处理
        if (error.response?.status === 401) {
            // Token过期或未授权
            showErrorNotification('未授权，请重新登录');
        } else if (error.response?.status === 403) {
            // 权限不足
            showErrorNotification('权限不足，无法执行此操作');
        } else if (error.code === 'ECONNABORTED') {
            // 请求超时
            showErrorNotification('请求超时，请稍后重试');
        } else if (!error.response) {
            // 网络错误
            showErrorNotification('网络连接失败，请检查网络设置');
        } else {
            // 其他错误，使用解析后的错误消息
            showErrorNotification(errorDetail.message);
        }

        // 超出 2xx 范围的状态码都会触发该函数。
        // 对响应错误做点什么
        return Promise.reject(error);
    }
);

export default instance;
