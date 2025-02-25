import axios from "axios";
import type { AxiosRequestConfig, AxiosResponse, AxiosError } from "axios";

//实例化 基础配置
const instance = axios.create({
    baseURL: "/api",
    timeout: 1000,
    headers: { },
});

// 添加请求拦截器
instance.interceptors.request.use(
    function (config) {
        // 在发送请求之前做些什么
        config.headers[
            "authorization"
        ] = `Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpc3MiOiJodHRwOi8vc2VydmVyaHViLmNvbSIsImF1ZCI6IiIsImlhdCI6MTc0MDMyNzAxNCwibmJmIjoxNzQwMzI3MDE0LCJkYXRhIjp7ImFpZCI6MzZ9fQ.gXOBmWBvW4z2xgcuDXkbDi__8hVFEUkdgRXB-C3U0IWBNgv41uB3nRpMbbWidDMbM0-f6z1fs5Aw_00D0PQSzSznk_GERgzZP4kw94PVeMaNyv8W6_PmTS9dZuXtbxOLLpTIfewjMEzdPjbzPzcVG3KjsUll-JPrXQRrl88iOv74V04BKzpqg93OrJ7GuyYEYHu4iSuX7CYC5moz1HCPCg6z42APzacpx4gyCq_E5GZP5XSXGW0Up-nC7cTo0bwSif0r5BAAIGs6DFwwRM2O7L7BsshSpt1bis4X9LVq4Hy90PqXmmjs1Mt5Szniry4Raoo7tDIYDPg1L1VUt4JRGQ`;
        console.log(config.headers);
        return config;
    },
    function (error) {
        // 对请求错误做些什么
        return Promise.reject(error);
    }
);

// 添加响应拦截器
instance.interceptors.response.use(
    function (response) {
        // 2xx 范围内的状态码都会触发该函数。
        // 对响应数据做点什么
        return response;
    },
    function (error) {
        // 超出 2xx 范围的状态码都会触发该函数。
        // 对响应错误做点什么
        return Promise.reject(error);
    }
);

export default instance;
