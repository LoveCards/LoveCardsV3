import { AxiosError } from "axios";

//通用错误处理类
const common = (error: unknown) => {
    let detail = '';

    //console.log(error.response);
    //客户端级别错误
    // if (!error.response) {
    //     const text = `${error.code}: ${error.message}`;
    //     return text;
    // }
    if (error instanceof AxiosError) {
        if (error.code = 'ECONNABORTED') {
            const text = `请求超时`;
            return text;
        }

        //ThinkPHP框架级错误处理
        if (error.response?.data.code) {
            const text = `${error.response?.data.code}: ${error.response?.data.message}`;
            return text;
        }

        //LC级错误处理
        if (error.response?.data.error) {
            Object.entries(error.response?.data.detail).forEach(([key, value]) => {
                detail += `${value}\n`;
            });
            const text = `${error.response?.data.error}: ${detail}`;
            return text;
        }
    }
};

const ErrorUtils = {
    common
}

export default ErrorUtils;