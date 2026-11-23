const eventProcessingReasons: Record<string, string> = {
  'config-locked': '环境变量已锁定运行模式',
  'schema-not-ready': '运行时配置表未就绪（请先执行迁移）',
  'worker-heartbeat-stale': 'Event Worker 心跳超时或未运行',
  'worker-required-queue-missing': 'Event Worker 未覆盖全部业务任务队列',
  'worker-release-mismatch': 'Event Worker 与当前 Release 不一致',
  'event-consumer-unavailable': '事件消费状态表未就绪',
  'event-consumer-draining': '事件策略切换中，等待在途任务结算',
  'scheduler-trigger-unconfigured': '尚未声明定时触发源',
  'scheduler-run-failed': '最近一次定时执行失败',
  'scheduler-trigger-mismatch': '最近一次执行与声明的触发方式不一致',
  'probe-stale': '探测结果过期或从未运行',
  'probe-unavailable': '探测暂不可用',
}
export function eventProcessingReasonLabel(code: string | null | undefined) {
  return code ? eventProcessingReasons[code] ?? '需要关注' : '运行正常'
}
export function eventModeLabel(mode: string | null | undefined) { return mode === 'resident' ? '常驻消费' : mode === 'scheduled' ? '定时消费' : '不可用' }
