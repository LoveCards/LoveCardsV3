import axios from "axios";
import type { AxiosRequestConfig, AxiosResponse, AxiosError } from "axios";
import Cookies from "./utils/cookie";
import ApiMonitor from "./interceptors/monitor";

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
    function (error) {
        ApiMonitor.error(error);
        // 超出 2xx 范围的状态码都会触发该函数。
        // 对响应错误做点什么
        return Promise.reject(error);
    }
);

export default instance;
