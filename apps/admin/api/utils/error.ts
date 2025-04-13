import type { AxiosError } from "axios";

//通用错误处理类
const common = (error: AxiosError) => {
    let detail = '';
    Object.entries(error.response?.data.detail).forEach(([key, value]) => {
        detail += `${value}\n`;
    });
    const text = `${error.response?.data.error}: ${detail}`;
    return text;
};

const ErrorUtils = {
    common
}

export default ErrorUtils;