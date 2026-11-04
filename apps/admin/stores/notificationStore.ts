import { defineStore } from 'pinia';

export interface Notification {
    id: number;
    message: string;
    type: 'error' | 'success' | 'info' | 'warning';
    timeout?: number;
}

export const useNotificationStore = defineStore('notification', {
    state: () => ({
        queue: [] as Notification[],
        // 递增序号：不能用 Date.now()，同一毫秒的多条通知会撞同一 id，导致 remove 只能删掉其中一条。
        nextId: 1,
    }),
    actions: {
        /**
         * 添加通知到队列
         */
        add(notification: Omit<Notification, 'id'>) {
            const id = this.nextId++;
            this.queue.push({
                id,
                ...notification,
            });
            return id;
        },
        /**
         * 移除通知
         */
        remove(id: number) {
            const index = this.queue.findIndex(n => n.id === id);
            if (index > -1) {
                this.queue.splice(index, 1);
            }
        },
        /**
         * 清空通知队列
         */
        clear() {
            this.queue = [];
        },
    },
});

