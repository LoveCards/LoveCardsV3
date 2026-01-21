import { AxiosError } from "axios";

/**
 * 错误详情接口
 */
export interface ErrorDetail {
    message: string;
    code?: string | number;
    details?: Record<string, string>;
}

/**
 * 解析错误信息
 * @param error 错误对象
 * @returns 错误详情
 */
const parseError = (error: unknown): ErrorDetail => {
    if (error instanceof AxiosError) {
        // 网络错误（无响应）
        if (!error.response) {
            if (error.code === 'ECONNABORTED') {
                return { message: '请求超时，请稍后重试' };
            }
            if (error.code === 'ERR_NETWORK') {
                return { message: '网络连接失败，请检查网络设置' };
            }
            return { 
                message: error.message || '网络错误，请稍后重试',
                code: error.code 
            };
        }

        // HTTP错误（有响应）
        const status = error.response.status;
        const data = error.response.data;

        // ThinkPHP框架级错误处理
        if (data?.code) {
            return {
                message: data.message || '操作失败',
                code: data.code,
            };
        }

        // LC级错误处理
        if (data?.error) {
            const details: Record<string, string> = {};
            if (data.detail && typeof data.detail === 'object') {
                Object.entries(data.detail).forEach(([key, value]) => {
                    details[key] = String(value);
                });
            }
            return {
                message: data.error,
                details,
            };
        }

        // 标准HTTP状态码错误
        const statusMessages: Record<number, string> = {
            400: '请求参数错误',
            401: '未授权，请重新登录',
            403: '权限不足，无法执行此操作',
            404: '资源不存在',
            405: '请求方法不允许',
            408: '请求超时',
            409: '资源冲突',
            422: '请求参数验证失败',
            429: '请求过于频繁，请稍后重试',
            500: '服务器内部错误',
            502: '网关错误',
            503: '服务不可用',
            504: '网关超时',
        };

        return {
            message: statusMessages[status] || `请求失败 (${status})`,
            code: status,
        };
    }

    // 未知错误类型
    if (error instanceof Error) {
        return { message: error.message || '未知错误，请稍后重试' };
    }

    return { message: '未知错误，请稍后重试' };
};

/**
 * 格式化错误消息，包含详细信息
 * @param errorDetail 错误详情对象
 * @returns 格式化后的错误消息字符串
 */
const formatErrorMessage = (errorDetail: ErrorDetail): string => {
    let message = errorDetail.message;
    
    // 如果有详细信息，追加到消息中
    if (errorDetail.details && Object.keys(errorDetail.details).length > 0) {
        const detailMessages = Object.values(errorDetail.details).join(' ');
        message = `${message}：${detailMessages}`;
    }
    
    return message;
};

/**
 * 通用错误处理 - 返回错误消息字符串
 * @param error 错误对象
 * @returns 错误消息字符串（包含详细信息）
 */
const common = (error: unknown): string => {
    const errorDetail = parseError(error);
    return formatErrorMessage(errorDetail);
};

const ErrorUtils = {
    parse: parseError,
    common
};

export default ErrorUtils;