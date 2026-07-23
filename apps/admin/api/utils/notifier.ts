import { useNotificationStore } from '~/stores/notificationStore';

/**
 * 显示错误通知
 * @param message 错误消息
 * @param timeout 超时时间（毫秒），默认5000
 */
export const showErrorNotification = (message: string, timeout: number = 5000) => {
    const notificationStore = useNotificationStore();
    notificationStore.add({
        message,
        type: 'error',
        timeout,
    });
};

/**
 * 显示成功通知
 * @param message 成功消息
 * @param timeout 超时时间（毫秒），默认3000
 */
export const showSuccessNotification = (message: string, timeout: number = 3000) => {
    const notificationStore = useNotificationStore();
    notificationStore.add({
        message,
        type: 'success',
        timeout,
    });
};

/**
 * 显示信息通知
 * @param message 信息消息
 * @param timeout 超时时间（毫秒），默认3000
 */
export const showInfoNotification = (message: string, timeout: number = 3000) => {
    const notificationStore = useNotificationStore();
    notificationStore.add({
        message,
        type: 'info',
        timeout,
    });
};

/**
 * 显示警告通知
 * @param message 警告消息
 * @param timeout 超时时间（毫秒），默认4000
 */
export const showWarningNotification = (message: string, timeout: number = 4000) => {
    const notificationStore = useNotificationStore();
    notificationStore.add({
        message,
        type: 'warning',
        timeout,
    });
};

