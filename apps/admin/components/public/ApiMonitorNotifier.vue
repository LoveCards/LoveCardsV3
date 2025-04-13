<template></template>

<script setup lang="ts">
import { useApiMonitorStore } from '~/stores/apiMonitorStore';
import ErrorUtils from '~/api/utils/error';

const notifier = useNotifier();

const apiMonitorStore = useApiMonitorStore();

// 监听 store 中的 requests 变化
watch(
  () => apiMonitorStore.list,
  (newList) => {
    if (newList.length > 0) {

      //请求监控
      // newList.forEach((item, index) => {
      //   let text;
      //   let type: "error" | "success" | "info" | "warning" | undefined;
      //   const config = item.config;
      //   const response = item.response;
      //   const error = item.error;

      //   // if (config) {
      //   //   const url = config.baseURL + (config.url ?? '');
      //   //   text = `正在请求: ${config.method} - ${url} \n 请求参数: ${JSON.stringify(config.data)}`;
      //   // }
      //   // if (response) {
      //   //   const url = response.config.baseURL + (response.config.url ?? '');
      //   //   text = `响应成功: ${response.config.method} - ${url}\n 状态码: ${response.status}`;
      //   // }
      //   // if (error) {
      //   //   const url = error.config.baseURL + (error.config.url ?? '');
      //   //   const status = error.response ? error.response.status : '未知';
      //   //   text = `响应失败: ${error.config.method} - ${url}\n 状态码: ${status} \n 错误信息: ${error.message}`;
      //   // }

      //   if (config) {
      //     const url = config.baseURL + (config.url ?? '');
      //     type = 'info';
      //     text = `正在请求 ${url}`;
      //   }
      //   if (response) {
      //     const url = response.config.baseURL + (response.config.url ?? '');
      //     type = 'success';
      //     text = `请求成功 ${url}`;
      //   }
      //   if (error) {
      //     const url = error.config.baseURL + (error.config.url ?? '');
      //     type = 'error';
      //     text = `请求失败 ${url} - ${error.message}`;
      //   }

      //   notifier.toast({
      //     "text": text,
      //     "icon": "mdi-api",
      //     "type": type,
      //   })
      // });

      //判断请求方法 post get put delete 默认通知

      //默认通知
      newList.forEach((item, index) => {
        let text;
        let type: "error" | "success" | "info" | "warning" | undefined;
        const config = item.config;
        const response = item.response;
        const error = item.error;

        if (config) {
          type = 'info';
          text = `正在提交`;
        }
        if (response) {
          type = 'success';
          text = `提交成功`;
        }
        if (error) {
          //备注 可以再API类中写一个通用错误处理方法
          type = 'error';
          text = ErrorUtils.common(error);
        }

        notifier.toast({
          "text": text,
          "type": type,
        })
      });

      // 清除 store 中的数据
      apiMonitorStore.clear();
    }
  },
  { deep: true, immediate: false }
);
</script>