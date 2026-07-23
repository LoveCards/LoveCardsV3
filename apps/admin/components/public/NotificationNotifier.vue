<template></template>

<script setup lang="ts">
import { useNotificationStore } from '~/stores/notificationStore';
import type { Notification } from '~/stores/notificationStore';

const notifier = useNotifier();
const notificationStore = useNotificationStore();

// 监听通知队列变化
watch(
    () => notificationStore.queue,
    (newQueue, oldQueue) => {
        // 只处理新增的通知
        const oldLength = oldQueue?.length || 0;
        if (newQueue.length > oldLength) {
            const newNotifications = newQueue.slice(oldLength);
            newNotifications.forEach((notification: Notification) => {
                // 显示通知
                notifier.toast({
                    text: notification.message,
                    type: notification.type,
                    timeout: notification.timeout,
                });
                
                // 显示后从队列中移除（延迟移除，确保通知已显示）
                const timeout = notification.timeout || 3000;
                setTimeout(() => {
                    notificationStore.remove(notification.id);
                }, timeout + 100); // 多等100ms确保通知已显示
            });
        }
    },
    { deep: true, immediate: false }
);
</script>

