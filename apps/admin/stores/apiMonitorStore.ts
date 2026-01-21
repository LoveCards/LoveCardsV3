import { defineStore } from 'pinia';
import type { AxiosRequestConfig, AxiosResponse, AxiosError } from 'axios';

export interface ApiMonitorRecord {
    id: number;
    config: AxiosRequestConfig | null;
    response: AxiosResponse | null;
    error: AxiosError | null;
    timestamp: number;
}

export const useApiMonitorStore = defineStore('apiMonitor', {
    state: () => ({
        list: [] as Array<ApiMonitorRecord>,
        maxRecords: 100,  // 最大记录数，防止内存泄漏
        maxAge: 3600000,  // 最大记录年龄（毫秒），默认1小时
    }),
    actions: {
        /**
         * 添加监控记录
         * @param config 请求配置
         * @param response 响应对象
         * @param error 错误对象
         * @returns 记录ID
         */
        add(config: AxiosRequestConfig | null, response: AxiosResponse | null, error: AxiosError | null) {
            const requestId = Date.now();
            const record: ApiMonitorRecord = {
                id: requestId,
                config,
                response,
                error,
                timestamp: requestId,
            };
            
            this.list.push(record);
            
            // 自动清理旧记录，保持记录数在最大限制内
            if (this.list.length > this.maxRecords) {
                // 只保留最新的 maxRecords 条记录
                this.list = this.list.slice(-this.maxRecords);
            }
            
            return requestId;
        },
        /**
         * 清空所有记录
         */
        clear() {
            this.list = [];
        },
        /**
         * 清理过期记录
         * 移除超过最大年龄的记录
         * @param maxAge 最大年龄（毫秒），如果不提供则使用默认值
         */
        clearExpired(maxAge?: number) {
            const age = maxAge ?? this.maxAge;
            const cutoffTime = Date.now() - age;
            this.list = this.list.filter(record => record.timestamp > cutoffTime);
        },
        /**
         * 移除指定记录
         * @param id 记录ID
         */
        remove(id: number) {
            const index = this.list.findIndex(record => record.id === id);
            if (index > -1) {
                this.list.splice(index, 1);
            }
        },
        /**
         * 设置最大记录数
         * @param maxRecords 最大记录数
         */
        setMaxRecords(maxRecords: number) {
            this.maxRecords = maxRecords;
            // 如果当前记录数超过新的最大值，清理旧记录
            if (this.list.length > maxRecords) {
                this.list = this.list.slice(-maxRecords);
            }
        },
        /**
         * 设置最大记录年龄
         * @param maxAge 最大年龄（毫秒）
         */
        setMaxAge(maxAge: number) {
            this.maxAge = maxAge;
        },
    },
    getters: {
        /**
         * 获取记录数量
         */
        count: (state) => state.list.length,
        /**
         * 检查是否已满
         */
        isFull: (state) => state.list.length >= state.maxRecords,
    },
});