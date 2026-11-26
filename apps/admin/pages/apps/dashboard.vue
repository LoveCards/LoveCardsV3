<template>
  <AdminPageContainer>
    <AdminPageHeader title="工作台" description="站点数据、运行状态与待办，一处掌握。">
      <template #actions><v-btn variant="outlined" prepend-icon="mdi-refresh" :loading="loading" @click="loadWorkbench">刷新</v-btn></template>
    </AdminPageHeader>

    <v-alert v-if="runtimeStatus && issues.length" type="warning" variant="tonal" class="mb-6">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3">
        <span>{{ runtimeError ? '上次快照存在' : '当前有' }} {{ issues.length }} 类运行异常需要关注</span>
        <v-btn variant="text" append-icon="mdi-arrow-down" @click="focusIssues">查看异常</v-btn>
      </div>
    </v-alert>
    <v-alert v-if="dashboardStore.error" type="error" variant="tonal" class="mb-4" data-testid="dashboard-error">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3">
        <div><strong>{{ dashboard ? '业务数据刷新失败，以下保留上次数据' : '业务数据读取失败' }}</strong><div class="text-body-2">{{ dashboardStore.error }}</div></div>
        <v-btn variant="outlined" :loading="dashboardStore.loading" @click="dashboardStore.getDashboard()">重试业务数据</v-btn>
      </div>
    </v-alert>

    <section class="mb-6" aria-labelledby="tasks-title" data-testid="workbench-tasks">
      <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3">
        <h2 id="tasks-title" class="text-subtitle-1 font-weight-bold">我的待办</h2>
        <span class="text-caption text-medium-emphasis">{{ dashboardStore.error && dashboard ? '上次读取' : '本次读取' }}{{ dashboardStore.readAt ? `：${dashboardStore.readAt}` : '中…' }}</span>
      </div>
      <v-skeleton-loader v-if="dashboardStore.loading && !dashboard" type="list-item-two-line@2" />
      <v-card v-else-if="dashboard" variant="flat" border>
        <v-list v-if="workbenchTasks.length" class="workbench-task-grid py-0" aria-label="待办处理入口">
          <v-list-item v-for="task in workbenchTasks" :key="task.key" :to="task.action_url" :prepend-icon="taskIcon(task.key)" class="workbench-task" min-height="76">
            <v-list-item-title>{{ task.label }}</v-list-item-title>
            <template #append><div class="d-flex align-center ga-3"><span class="workbench-task-count" :class="task.count > 0 ? 'text-primary' : 'text-medium-emphasis'">{{ formatNumber(task.count) }}<span class="text-caption ml-1">条</span></span><span class="text-body-2">{{ task.count ? '去处理' : '查看' }}</span><v-icon icon="mdi-arrow-right" size="18" /></div></template>
          </v-list-item>
        </v-list>
        <p v-else class="pa-5 text-body-2 text-medium-emphasis">当前权限没有可量化待办，可从下方职责入口开展工作。</p>
      </v-card>
    </section>

    <div class="workbench-grid" :class="{ 'workbench-grid--single': !canReadOperations || !overviewMetrics.length }">
      <template v-for="section in sectionOrder" :key="section">
        <v-card v-if="section === 'overview' && overviewMetrics.length" tag="section" variant="flat" border class="pa-5 workbench-overview" aria-labelledby="overview-title" data-testid="workbench-overview">
          <h2 id="overview-title" class="text-subtitle-1 font-weight-bold">业务概览</h2>
          <p class="text-body-2 text-medium-emphasis mt-1">{{ dashboardStore.error ? '上次读取的总量 · 数据可能已变化' : '截至本次读取的总量' }}</p>
          <dl class="workbench-metrics mt-3" aria-label="业务总量">
            <div v-for="metric in overviewMetrics" :key="metric.key" class="workbench-metric"><dt class="text-caption text-medium-emphasis">{{ metric.label }}总量（{{ metric.unit }}）</dt><dd class="workbench-metric-value">{{ formatNumber(metric.value) }}</dd></div>
          </dl>
          <div class="d-flex flex-wrap justify-space-between align-center ga-2 mt-4 mb-2"><h3 class="text-body-2 font-weight-medium">每日新增趋势</h3><span class="text-caption text-medium-emphasis">最近六天 · 截至昨日</span></div>
          <TrendCharts :series="chartSeries" title="每日新增趋势" range="最近六天，截至昨日" />
        </v-card>

        <v-card v-else-if="section === 'runtime' && canReadOperations" tag="section" variant="flat" border class="pa-5 workbench-runtime" aria-labelledby="runtime-title" data-testid="workbench-runtime">
          <div class="d-flex flex-wrap align-center justify-space-between ga-2"><h2 id="runtime-title" class="text-subtitle-1 font-weight-bold">运行状态</h2><v-chip v-if="runtimeStatus" size="small" variant="tonal" :color="runtimeError ? 'warning' : issues.length ? 'warning' : 'success'">{{ runtimeError ? '旧快照' : issues.length ? '需要关注' : '运行正常' }}</v-chip></div>
          <p class="text-body-2 text-medium-emphasis mt-1 mb-3">事件消费与前端通信</p>
          <v-alert v-if="runtimeError" type="error" variant="tonal" class="mb-3" data-testid="runtime-error">
            <p>{{ runtimeStatus ? '运行状态刷新失败，保留上次快照。' : '运行状态读取失败。' }}{{ runtimeError }}</p><v-btn class="mt-2" variant="outlined" :loading="runtimeLoading" @click="loadRuntime">重试运行状态</v-btn>
          </v-alert>
          <v-skeleton-loader v-if="runtimeLoading && !runtimeStatus" type="list-item-two-line@2" />
          <template v-if="runtimeStatus">
            <v-list class="py-0" lines="two">
              <v-list-item class="px-0" to="/apps/operations/eventing?source=dashboard" append-icon="mdi-chevron-right">
                <v-list-item-title>事件消费 <span class="text-body-2 ml-2">{{ eventModeLabel(runtimeStatus.event_processing.mode) }}</span></v-list-item-title>
                <v-list-item-subtitle>{{ eventStatusLabel }}</v-list-item-subtitle>
              </v-list-item>
              <v-divider />
              <v-list-item class="px-0" to="/apps/operations/communication?source=dashboard" append-icon="mdi-chevron-right">
                <v-list-item-title>前端通信 <span class="text-body-2 ml-2">{{ runtimeStatus.communication.desired_mode === 'realtime' ? '实时通信' : '轮询通信' }}</span></v-list-item-title>
                <v-list-item-subtitle>{{ runtimeStatus.communication.status === 'ready' ? '运行正常' : reasonLabel(runtimeStatus.communication.reason_code) }}</v-list-item-subtitle>
              </v-list-item>
            </v-list>
            <v-divider class="mt-2" />
            <dl class="workbench-runtime-counts py-4"><div><dt>待处理</dt><dd>{{ formatNumber(runtimeStatus.eventing.pending) }}</dd></div><div><dt>处理中</dt><dd>{{ formatNumber(runtimeStatus.eventing.processing) }}</dd></div><div><dt>失败（死信）</dt><dd :class="{ 'text-error': runtimeStatus.eventing.dead > 0 }">{{ formatNumber(runtimeStatus.eventing.dead) }}</dd></div></dl>
            <dl class="workbench-runtime-meta text-body-2">
              <div v-if="runtimeStatus.eventing.oldest_pending_age_seconds != null"><dt>最早可领取已等待</dt><dd>{{ formatAge(runtimeStatus.eventing.oldest_pending_age_seconds) }}</dd></div>
              <div v-if="runtimeStatus.eventing.expired_processing != null"><dt>过期处理租约</dt><dd>{{ runtimeStatus.eventing.expired_processing }}</dd></div>
              <div v-if="runtimeStatus.event_processing.mode === 'resident'"><dt>在线消费 Worker</dt><dd>{{ runtimeStatus.workers.filter(worker => worker.worker_type === 'eventing' && worker.online).length }}</dd></div>
            </dl>
            <p class="text-caption text-medium-emphasis mt-4">通信检查：{{ formatDate(runtimeStatus.communication.checked_at) }}<br />{{ runtimeError ? '上次读取' : '本次读取' }}：{{ runtimeReadAt }}</p>
          </template>
        </v-card>

        <v-card v-else-if="section === 'issues' && canReadOperations" tag="section" variant="flat" border class="pa-5 workbench-issues" aria-labelledby="issues-title" data-testid="workbench-issues">
          <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-1"><h2 id="issues-title" tabindex="-1" class="text-subtitle-1 font-weight-bold">当前异常</h2><span v-if="runtimeStatus" class="text-caption text-medium-emphasis">{{ issues.length }} 类{{ runtimeError ? '上次快照异常' : '当前异常' }}</span></div>
          <p class="text-body-2 text-medium-emphasis mb-3">{{ runtimeError ? '当前状态尚未确认；请先重试运行状态。' : '按问题类型汇总，展开查看处理入口。' }}</p>
          <v-skeleton-loader v-if="runtimeLoading && !runtimeStatus" type="list-item-two-line" />
          <p v-else-if="!runtimeStatus" class="text-body-2 text-medium-emphasis">运行状态不可用，暂时无法判断异常。</p>
          <p v-else-if="!issues.length" class="text-body-2" :class="runtimeError ? 'text-medium-emphasis' : 'text-success'"><v-icon :icon="runtimeError ? 'mdi-clock-outline' : 'mdi-check-circle-outline'" size="20" class="mr-2" />{{ runtimeError ? '上次快照未发现异常，当前状态待确认。' : '本次运行快照未发现异常。' }}</p>
          <v-expansion-panels v-else variant="accordion" flat>
            <v-expansion-panel v-for="issue in issues" :key="issue.key">
              <v-expansion-panel-title min-height="64"><v-icon :icon="issue.icon" :color="issue.color" size="20" class="mr-3" /><span class="workbench-issue-summary"><strong class="text-body-2 font-weight-medium">{{ issue.title }}</strong><span class="text-body-2 text-medium-emphasis">{{ issue.summary }}</span></span></v-expansion-panel-title>
              <v-expansion-panel-text><p class="text-body-2 mb-3">{{ issue.detail }}</p><v-btn v-if="issue.to" :to="issue.to" variant="outlined" append-icon="mdi-arrow-right">{{ issue.action }}</v-btn><p v-else class="text-caption text-medium-emphasis">当前账号没有读取处理明细的权限，请联系获授权的管理员。</p></v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-card>
      </template>
    </div>
    <p v-if="dashboard && !overviewMetrics.length && !canReadOperations" class="text-body-2 text-medium-emphasis mb-6">当前账号没有业务汇总或运行状态的读取权限。</p>

    <v-expansion-panels v-model="supplementary" variant="accordion" flat class="mt-6" data-testid="workbench-supplementary">
      <v-expansion-panel value="more">
        <v-expansion-panel-title min-height="56">职责、通知与最近变更</v-expansion-panel-title>
        <v-expansion-panel-text>
          <section aria-labelledby="responsibility-title">
            <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-3"><h2 id="responsibility-title" class="text-subtitle-1 font-weight-bold">当前职责</h2><div class="d-flex flex-wrap ga-2"><v-chip v-for="role in responsibilityRoles" :key="role.slug" size="small" variant="tonal">{{ role.name }}</v-chip></div></div>
            <p class="text-body-2 text-medium-emphasis mb-3">{{ responsibilityDescription }}</p>
            <v-list v-if="responsibilityLinks.length" lines="two" density="comfortable"><v-list-item v-for="link in visibleResponsibilityLinks" :key="link.key" :prepend-icon="link.icon" :to="link.to" :title="link.label" :subtitle="link.description" append-icon="mdi-chevron-right" /></v-list>
            <v-btn v-if="responsibilityLinks.length > responsibilityPreviewLimit" variant="text" :aria-expanded="showAllResponsibilities" @click="showAllResponsibilities = !showAllResponsibilities">{{ showAllResponsibilities ? '收起入口' : `展开其余 ${responsibilityLinks.length - responsibilityPreviewLimit} 个入口` }}</v-btn>
            <p v-if="!responsibilityLinks.length" class="text-body-2 text-medium-emphasis">当前角色尚未获得业务模块入口，请联系超级管理员核对权限。</p>
          </section>
          <section v-if="canReadCommunications" class="mt-6" aria-labelledby="notifications-title"><h2 id="notifications-title" class="text-subtitle-1 font-weight-bold mb-3">站内通知统计</h2><AdminAsyncState :loading="auxiliaryLoading && !notificationStats" :error="notificationsError" @retry="loadAuxiliaryData"><dl v-if="notificationStats" class="workbench-runtime-counts"><div><dt>通知总量</dt><dd>{{ formatNumber(notificationStats.total) }}</dd></div><div><dt>未读通知</dt><dd>{{ formatNumber(notificationStats.unread) }}</dd></div><div><dt>覆盖用户</dt><dd>{{ formatNumber(notificationStats.recipients) }}</dd></div></dl></AdminAsyncState></section>
          <section v-if="canReadAudit" class="mt-6" aria-labelledby="changes-title"><div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3"><h2 id="changes-title" class="text-subtitle-1 font-weight-bold">最近变更</h2><v-btn to="/apps/operations/audit" variant="text" append-icon="mdi-arrow-right">查看审计日志</v-btn></div><AdminAsyncState :loading="auxiliaryLoading" :error="auditError" :empty="!recentChanges.length" empty-title="暂无近期变更" @retry="loadAuxiliaryData"><v-list lines="two"><v-list-item v-for="change in recentChanges" :key="change.id" :to="`/apps/operations/audit/${change.id}`" :title="change.action" :subtitle="`操作者 ${change.actor_id ? `#${change.actor_id}` : '系统或匿名'} · ${change.resource_type || '未关联资源'} ${change.resource_id || ''} · ${change.created_at}`" append-icon="mdi-chevron-right" /></v-list></AdminAsyncState></section>
          <section v-if="dashboard?.notice.length" class="mt-6" aria-labelledby="notice-title"><h2 id="notice-title" class="text-subtitle-1 font-weight-bold mb-3">产品公告</h2><v-list lines="two"><v-list-item v-for="notice in dashboard.notice" :key="`${notice.date}-${notice.title}`" :href="notice.url || undefined" target="_blank" rel="noopener noreferrer" :title="notice.title" :subtitle="`${notice.content} · ${notice.date}`" /></v-list></section>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { DashboardTask, OperationsAuditLog, OperationsNotificationStats, OperationsRuntimeStatus, RoleInfo } from '@lovecards/sdk'
import { useDisplay } from 'vuetify'
import TrendCharts from '~/components/dashboard/TrendCharts.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import { useApi, isApiError } from '~/lib/api'
import { useDashboardStore } from '~/stores/dashboardStore'
import { useUserStore } from '~/stores/userStore'

const client = useApi()
const dashboardStore = useDashboardStore()
const userStore = useUserStore()
const { width } = useDisplay()
const dashboard = computed(() => dashboardStore.dashboard)
const refreshing = ref(false)
const runtimeStatus = ref<OperationsRuntimeStatus | null>(null)
const runtimeError = ref<string | null>(null)
const runtimeLoading = ref(false)
const runtimeReadAt = ref('')
const notificationStats = ref<OperationsNotificationStats | null>(null)
const notificationsError = ref<string | null>(null)
const recentChanges = ref<OperationsAuditLog[]>([])
const auditError = ref<string | null>(null)
const auxiliaryLoading = ref(false)
const supplementary = ref<string>()
const showAllResponsibilities = ref(false)
const responsibilityPreviewLimit = 3
const canReadOperations = computed(() => userStore.hasGrant('operations.read', 'global'))
const canReadCommunications = computed(() => userStore.hasGrant('communications.read', 'global'))
const canReadAudit = computed(() => userStore.hasGrant('audit.read', 'global'))
const loading = computed(() => refreshing.value || dashboardStore.loading || runtimeLoading.value)
const sectionOrder = computed(() => width.value <= 680 ? ['issues', 'overview', 'runtime'] : ['overview', 'runtime', 'issues'])
const formatNumber = (value: number) => value.toLocaleString('zh-CN')
function formatDate(value?: string | null) { return value && !Number.isNaN(Date.parse(value)) ? new Date(value).toLocaleString('zh-CN') : '未提供' }
function formatAge(seconds: number) { return seconds < 60 ? `${Math.floor(seconds)} 秒` : seconds < 3600 ? `${Math.floor(seconds / 60)} 分钟` : `${Math.floor(seconds / 3600)} 小时` }
function eventModeLabel(mode: string) { return mode === 'resident' ? '常驻消费' : mode === 'scheduled' ? '定时消费' : '不可用' }
const eventStatusLabel = computed(() => {
  const event = runtimeStatus.value?.event_processing
  if (!event) return '不可用'
  if (event.state === 'draining') return `策略切换中，目标为${eventModeLabel(event.target_mode ?? 'unknown')}`
  return event.status === 'ready' && event.state === 'active' ? '运行正常' : reasonLabel(event.reason_code)
})
const reasonLabels: Record<string, string> = {
  'config-locked': '环境变量已锁定运行模式', 'schema-not-ready': '运行时配置表未就绪',
  'worker-heartbeat-stale': '消费 Worker 心跳超时或未运行', 'worker-required-queue-missing': '消费 Worker 未覆盖全部业务队列',
  'worker-realtime-queue-missing': '消费 Worker 未覆盖实时信号队列', 'worker-release-mismatch': '消费 Worker 与当前版本不一致',
  'event-consumer-unavailable': '事件消费状态表未就绪', 'event-consumer-draining': '策略切换中，等待在途任务结算',
  'event-consumer-not-resident': '事件处理尚未切换为常驻消费', 'scheduler-trigger-unconfigured': '尚未声明定时触发源',
  'scheduler-run-failed': '最近一次定时执行失败', 'scheduler-trigger-mismatch': '定时执行与声明的触发方式不一致',
  'probe-stale': '探测结果过期或从未运行', 'probe-unavailable': '探测暂不可用',
  'gateway-not-configured': '未配置实时网关', 'gateway-unreachable': '实时网关健康检查不可达',
  'gateway-protocol-mismatch': '实时网关协议能力不匹配', 'gateway-auth-unavailable': '实时网关鉴权凭证不可用',
  'gateway-not-ready': '实时网关尚未就绪', 'realtime-transport-degraded': 'Redis 实时信号通道不可用',
  'redis-not-configured': 'Redis 尚未配置', 'redis-unreachable': 'Redis 连接不可用', 'not-required': '当前模式不要求',
}
function reasonLabel(code?: string | null) { return code ? reasonLabels[code] ?? `需要检查（${code}）` : '状态不可用，请查看诊断' }

const roleDescriptions: Record<string, string> = {
  root: '负责全域治理、访问控制与系统高风险操作。',
  admin: '负责站点日常综合管理。',
  content_operator: '负责卡片与标签的日常内容治理。',
  community_operator: '负责评论、举报与社区运营。',
  moderator: '负责卡片、评论和举报审核。',
  security_admin: '负责账号状态、会话与恢复事件。',
  operations_admin: '负责基础设施、投递与升级迁移。',
  auditor: '负责只读核对运行状态和审计记录。',
}
const responsibilityRoles = computed<RoleInfo[]>(() => {
  const roles = userStore.roles.filter((role) => !['user', 'guest'].includes(role.slug))
  return roles.length ? roles : userStore.roles
})
const responsibilityDescription = computed(() => {
  const descriptions = responsibilityRoles.value
    .map((role) => roleDescriptions[role.slug])
    .filter((value): value is string => Boolean(value))
  if (!descriptions.length) return '此账号使用自定义角色；可用入口和操作由当前权限决定。'
  return `${descriptions.join(' ')}可用入口和操作由当前权限决定。`
})

const responsibilityLinks = computed(() => [
  { key: 'cards', label: '内容治理', description: '查看和处理获授权的卡片内容。', icon: 'mdi-card-text-outline', to: '/apps/content/cards' },
  { key: 'comments', label: '评论治理', description: '查看评论及允许执行的治理动作。', icon: 'mdi-comment-text-outline', to: '/apps/content/comments' },
  { key: 'tags', label: '标签治理', description: '维护内容分类和标签状态。', icon: 'mdi-tag-multiple-outline', to: '/apps/content/tags' },
  { key: 'reports', label: '举报处理', description: '核对证据、状态和处理历史。', icon: 'mdi-flag-outline', to: '/apps/community/reports' },
  { key: 'insights', label: '社区洞察', description: '查看隐私安全的聚合指标。', icon: 'mdi-chart-line', to: '/apps/community/insights' },
  { key: 'users', label: '用户', description: '查看获授权的用户和账号安全状态。', icon: 'mdi-shield-account-outline', to: '/apps/users' },
  { key: 'eventing', label: '事件管理', description: '查看事件事实、处理任务、消费策略和 Worker。', icon: 'mdi-inbox-arrow-down-outline', to: '/apps/operations/eventing' },
  { key: 'communication', label: '前端通信', description: '检查轮询或实时通信及其依赖。', icon: 'mdi-access-point-network', to: '/apps/operations/communication' },
  { key: 'storage', label: '文件存储', description: '维护文件渠道和基础设施配置。', icon: 'mdi-database-cog-outline', to: '/apps/settings/storage' },
  { key: 'upgrade', label: '系统升级', description: '检查升级计划和迁移状态。', icon: 'mdi-package-up', to: '/apps/operations/upgrade' },
  { key: 'audit', label: '审计记录', description: '追溯管理动作、资源变化和请求链路。', icon: 'mdi-clipboard-text-clock-outline', to: '/apps/operations/audit' },
  { key: 'roles', label: '角色与权限', description: '管理角色和 capability grants。', icon: 'mdi-shield-lock-outline', to: '/apps/access/roles' },
].filter((link) => userStore.canAccessPath(link.to)))
const visibleResponsibilityLinks = computed(() => showAllResponsibilities.value
  ? responsibilityLinks.value
  : responsibilityLinks.value.slice(0, responsibilityPreviewLimit))


const workbenchTasks = computed<DashboardTask[]>(() => (dashboard.value?.workbench?.tasks ?? [])
  .filter(task => !canReadOperations.value || task.key !== 'deliveries_dead')
  .map(task => task.key === 'deliveries_dead' ? { ...task, action_url: '/apps/operations/deliveries?queue=all&view=dead' } : task)
  .filter(task => userStore.canAccessPath(task.action_url)))
function taskIcon(key: string) {
  return ({ cards_pending: 'mdi-card-search-outline', comments_pending: 'mdi-comment-alert-outline', reports_open: 'mdi-flag-outline', deliveries_dead: 'mdi-alert-circle-outline', files_pending: 'mdi-file-clock-outline' } as Record<string, string>)[key] ?? 'mdi-clipboard-check-outline'
}
const overviewMetrics = computed(() => {
  const count = dashboard.value?.count
  if (!count) return []
  return [
    { key: 'cards', label: '卡片', chartLabel: '卡片', unit: '张', value: count.cards },
    { key: 'comments', label: '评论', chartLabel: '评论', unit: '条', value: count.comments },
    { key: 'good', label: '获赞', chartLabel: '点赞', unit: '次', value: count.good },
  ].filter((metric): metric is typeof metric & { value: number } => typeof metric.value === 'number' && Number.isFinite(metric.value))
})
const chartSeries = computed(() => overviewMetrics.value.map(metric => ({
  key: metric.key,
  unit: metric.unit,
  label: metric.chartLabel,
  data: dashboard.value?.chart.find(dataset => dataset.label === metric.chartLabel)?.data ?? { x: [], y: [] },
})))
const issues = computed(() => {
  const snapshot = runtimeStatus.value
  if (!snapshot) return []
  const result: { key: string; title: string; summary: string; detail: string; icon: string; color: string; to: string | null; action: string }[] = []
  const event = snapshot.event_processing
  if (event.status !== 'ready' || event.state !== 'active' || event.mode === 'unknown') result.push({ key: 'event', title: '事件消费需要关注', summary: eventStatusLabel.value, detail: `当前为${eventModeLabel(event.mode)}。${eventStatusLabel.value}。运行模式不会自动切换，请进入事件管理核对配置与诊断。`, icon: 'mdi-progress-alert', color: 'warning', to: '/apps/operations/eventing?source=dashboard', action: '检查事件消费' })
  if (snapshot.communication.status !== 'ready') result.push({ key: 'communication', title: '前端通信需要关注', summary: reasonLabel(snapshot.communication.reason_code), detail: `当前选择${snapshot.communication.desired_mode === 'realtime' ? '实时通信' : '轮询通信'}。${reasonLabel(snapshot.communication.reason_code)}。请进入前端通信核对依赖与探测结果。`, icon: 'mdi-access-point-network-off', color: 'warning', to: '/apps/operations/communication?source=dashboard', action: '检查前端通信' })
  if (snapshot.eventing.dead > 0) result.push({ key: 'dead', title: '处理任务失败（死信）', summary: `${formatNumber(snapshot.eventing.dead)} 条任务已耗尽自动重试额度`, detail: '此数量汇总全部队列。处理明细将按全部队列的死信状态打开，核对失败原因后再决定重试或处置。', icon: 'mdi-alert-circle-outline', color: 'error', to: canReadCommunications.value ? '/apps/operations/deliveries?queue=all&view=dead' : null, action: '查看失败任务' })
  if ((snapshot.eventing.expired_processing ?? 0) > 0) result.push({ key: 'lease', title: '处理租约已过期', summary: `${snapshot.eventing.expired_processing} 条处理中任务的租约已过期`, detail: '任务可能因消费进程中断而未及时结算，请检查消费进程、队列和租约回收情况。', icon: 'mdi-timer-alert-outline', color: 'warning', to: '/apps/operations/eventing?source=dashboard', action: '检查事件管理' })
  return result
})
function focusIssues() { const heading = document.getElementById('issues-title'); heading?.focus(); heading?.scrollIntoView({ block: 'center' }) }
function errorMessage(error: unknown, fallback: string) { return isApiError(error) ? error.message : fallback }
async function loadRuntime() {
  if (!canReadOperations.value || runtimeLoading.value) return
  runtimeLoading.value = true
  runtimeError.value = null
  try { runtimeStatus.value = await client.operations.runtime(); runtimeReadAt.value = new Date().toLocaleString('zh-CN') }
  catch (error) { runtimeError.value = errorMessage(error, '请稍后重试。') }
  finally { runtimeLoading.value = false }
}
async function loadAuxiliaryData() {
  if (auxiliaryLoading.value) return
  auxiliaryLoading.value = true
  await Promise.all([
    canReadCommunications.value ? (async () => { notificationsError.value = null; try { notificationStats.value = await client.operations.notificationStats() } catch (error) { notificationsError.value = errorMessage(error, '无法加载通知统计。') } })() : Promise.resolve(),
    canReadAudit.value ? (async () => { auditError.value = null; try { recentChanges.value = (await client.operations.auditLogs({ page: 1, list_rows: 5 })).data } catch (error) { auditError.value = errorMessage(error, '无法加载最近变更。') } })() : Promise.resolve(),
  ])
  auxiliaryLoading.value = false
}
async function loadWorkbench() {
  if (refreshing.value) return
  refreshing.value = true
  try { await Promise.all([dashboardStore.getDashboard(), loadRuntime(), supplementary.value === 'more' ? loadAuxiliaryData() : Promise.resolve()]) }
  finally { refreshing.value = false }
}
watch(supplementary, value => { if (value === 'more') void loadAuxiliaryData() })
onMounted(() => { void Promise.all([dashboardStore.init(), loadRuntime()]) })
</script>

<style scoped>
.workbench-grid { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(300px, 1fr); gap: 24px; align-items: start; }
.workbench-grid--single { grid-template-columns: minmax(0, 1fr); }
.workbench-overview, .workbench-runtime, .workbench-issues { min-width: 0; }
.workbench-issues { grid-column: 1 / -1; }
.workbench-task-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.workbench-task { border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.workbench-task:nth-child(odd) { border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.workbench-task-count { font-size: 1.25rem; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
.workbench-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 0; border-block: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.workbench-metric { min-width: 0; padding: 10px 12px; }
.workbench-metric:first-child { padding-left: 0; }
.workbench-metric + .workbench-metric { border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.workbench-metric-value { margin: 2px 0 0; font-size: 1.25rem; font-weight: 600; line-height: 1.3; font-variant-numeric: tabular-nums; }
.workbench-runtime-counts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 0; font-variant-numeric: tabular-nums; }
.workbench-runtime-counts dt { font-size: .75rem; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); }
.workbench-runtime-counts dd { margin: 4px 0 0; font-size: 1.375rem; font-weight: 600; }
.workbench-runtime-meta { margin: 0; }
.workbench-runtime-meta > div { display: flex; gap: 16px; justify-content: space-between; margin-top: 8px; }
.workbench-runtime-meta dt { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); }
.workbench-runtime-meta dd { margin: 0; font-variant-numeric: tabular-nums; }
.workbench-issue-summary { display: flex; align-items: center; justify-content: space-between; flex: 1; gap: 12px; padding-right: 16px; }
@media (max-width: 1000px) { .workbench-grid { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 680px) {
  .workbench-task-grid { grid-template-columns: minmax(0, 1fr); }
  .workbench-task:nth-child(odd) { border-right: 0; }
  .workbench-issue-summary { align-items: flex-start; flex-direction: column; gap: 4px; }
  .workbench-metric { padding-inline: 4px; }
  .workbench-metric-value { font-size: 1.125rem; }
}
</style>
