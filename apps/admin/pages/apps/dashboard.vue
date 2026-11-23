<template>
  <AdminPageContainer>
    <AdminPageHeader
      title="工作台"
      description="从这里查看内容、社区和运行状态的关键任务。可见模块取决于当前账号的管理权限。"
    >
      <template #actions>
        <v-btn
          variant="outlined"
          prepend-icon="mdi-refresh"
          :loading="loading"
          @click="loadWorkbench"
        >
          刷新
        </v-btn>
      </template>
    </AdminPageHeader>

    <AdminAsyncState
      :loading="loading && !dashboard"
      :error="dashboardStore.error"
      :empty="false"
      skeleton-type="heading, paragraph, card@3"
      @retry="loadWorkbench"
    >
      <div class="workbench-layout">
        <div class="workbench-layout__main">
        <section v-if="dashboard" class="workbench-tasks" aria-labelledby="tasks-title">
          <div class="mb-3">
            <h2 id="tasks-title" class="text-subtitle-1 font-weight-bold">我的待办</h2>
            <p class="text-body-2 text-medium-emphasis mt-1">只显示当前账号有权处理的待办。</p>
          </div>
          <v-card v-if="pendingTasks.length">
            <v-list lines="two">
              <template v-for="(task, index) in pendingTasks" :key="task.key">
                <v-list-item :to="task.action_url" :value="`workbench-task-${task.key}`">
                  <template #prepend>
                    <v-icon :icon="taskIcon(task.key)" :color="taskColor(task.severity)" class="mr-3" />
                  </template>
                  <v-list-item-title>{{ task.label }}</v-list-item-title>
                  <v-list-item-subtitle>{{ task.description }}</v-list-item-subtitle>
                  <template #append>
                    <div class="d-flex align-center ga-2">
                      <v-chip :color="taskColor(task.severity)" variant="tonal" size="small">{{ task.count }}</v-chip>
                      <v-icon icon="mdi-chevron-right" size="18" />
                    </div>
                  </template>
                </v-list-item>
                <v-divider v-if="index < pendingTasks.length - 1" />
              </template>
            </v-list>
          </v-card>
          <v-alert v-else-if="workbenchTasks.length" type="success" variant="tonal">
            当前没有待处理事项。系统仍会按当前权限持续汇总新的任务。
          </v-alert>
          <v-alert v-else type="info" variant="tonal">
            当前权限没有可量化待办；你仍可从“当前职责”中的入口开展日常工作。
          </v-alert>
        </section>

        <section v-if="overviewMetrics.length" class="workbench-overview" aria-labelledby="overview-title">
          <div class="d-flex flex-wrap align-start justify-space-between ga-3 mb-3">
            <div>
              <h2 id="overview-title" class="text-subtitle-1 font-weight-bold">内容概览</h2>
              <p class="text-body-2 text-medium-emphasis mt-1">来自服务端仪表盘的实时汇总。</p>
            </div>
            <v-chip v-if="dashboard && dashboardReadAt" size="small" variant="tonal" color="primary">本次读取于 {{ dashboardReadAt }}</v-chip>
          </div>

          <v-card v-if="dashboard">
            <v-list lines="two">
              <template v-for="(metric, index) in overviewMetrics" :key="metric.key">
                <v-list-item :prepend-icon="metric.icon" :to="metric.to" :value="`overview-${metric.key}`">
                  <v-list-item-title>{{ metric.label }}</v-list-item-title>
                  <v-list-item-subtitle>{{ metric.detail }}</v-list-item-subtitle>
                  <template #append>
                    <div class="d-flex align-center ga-3">
                      <span class="text-h6 font-weight-bold">{{ metric.value }}</span>
                      <v-icon icon="mdi-chevron-right" size="18" />
                    </div>
                  </template>
                </v-list-item>
                <v-divider v-if="index < overviewMetrics.length - 1" />
              </template>
            </v-list>
          </v-card>
        </section>
        </div>
        <div class="workbench-layout__aside">
        <section v-if="canReadOperations" class="workbench-runtime" aria-labelledby="runtime-title">
          <div class="d-flex flex-wrap align-start justify-space-between ga-3 mb-3">
            <div>
              <h2 id="runtime-title" class="text-subtitle-1 font-weight-bold">运行状态</h2>
              <p class="text-body-2 text-medium-emphasis mt-1">当前通信方式和事件队列状态。</p>
            </div>
          </div>
          <v-alert v-if="runtimeError" type="error" variant="tonal">{{ runtimeError }}</v-alert>
          <v-card v-else-if="runtimeStatus">
            <v-list lines="two">
              <v-list-item
                :prepend-icon="runtimeStatus.communication.status === 'ready' ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline'"
                title="浏览器更新"
                :subtitle="communicationSummary"
                :base-color="runtimeStatus.communication.status === 'ready' ? 'success' : 'warning'"
                to="/apps/operations/communication?source=dashboard"
                value="runtime-communication"
              />
              <v-divider />
              <v-list-item
                :prepend-icon="eventReady ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline'"
                :base-color="eventReady ? 'success' : 'warning'"
                title="事件处理"
                :subtitle="eventProcessingSummary"
                to="/apps/operations/eventing?source=dashboard"
                value="runtime-eventing"
              />
              <v-divider />
              <v-list-item
                prepend-icon="mdi-inbox-arrow-down-outline"
                title="事件队列"
                :subtitle="`${runtimeStatus.eventing.pending} 待处理 · ${runtimeStatus.eventing.dead} 失败 · ${runtimeStatus.eventing.expired_processing ?? 0} 过期租约`"
              >
                <template v-if="canReadCommunications && runtimeStatus.eventing.dead > 0" #append>
                  <v-btn to="/apps/operations/deliveries?queue=all&view=dead" variant="text" color="primary">查看失败任务</v-btn>
                </template>
              </v-list-item>
            </v-list>
          </v-card>
          <v-card v-else class="pa-5 text-body-2 text-medium-emphasis">正在读取运行状态…</v-card>
        </section>

        <section class="workbench-responsibility" aria-labelledby="responsibility-title">
          <v-card class="pa-4">
            <div class="d-flex flex-wrap align-start justify-space-between ga-4">
              <div>
                <h2 id="responsibility-title" class="text-subtitle-1 font-weight-bold">当前职责</h2>
                <p class="text-body-2 text-medium-emphasis mt-1">{{ responsibilityDescription }}</p>
              </div>
              <div class="d-flex flex-wrap ga-2" aria-label="当前账号角色">
                <v-chip
                  v-for="role in responsibilityRoles"
                  :key="role.slug"
                  size="small"
                  color="primary"
                  variant="tonal"
                >
                  {{ role.name }}
                </v-chip>
              </div>
            </div>

            <v-divider class="my-4" />
            <v-list v-if="responsibilityLinks.length" lines="two" density="comfortable">
              <template v-for="(link, index) in visibleResponsibilityLinks" :key="link.key">
                <v-list-item :prepend-icon="link.icon" :to="link.to" :value="`responsibility-${link.key}`">
                  <v-list-item-title>{{ link.label }}</v-list-item-title>
                  <v-list-item-subtitle>{{ link.description }}</v-list-item-subtitle>
                  <template #append><v-icon icon="mdi-chevron-right" size="18" /></template>
                </v-list-item>
                <v-divider v-if="index < visibleResponsibilityLinks.length - 1" />
              </template>
            </v-list>
            <v-btn
              v-if="responsibilityLinks.length > responsibilityPreviewLimit"
              class="mt-2"
              variant="text"
              :append-icon="showAllResponsibilities ? 'mdi-chevron-up' : 'mdi-chevron-down'"
              :aria-expanded="showAllResponsibilities"
              @click="showAllResponsibilities = !showAllResponsibilities"
            >
              {{ showAllResponsibilities ? '收起入口' : `展开其余 ${responsibilityLinks.length - responsibilityPreviewLimit} 个入口` }}
            </v-btn>
            <v-alert v-if="!responsibilityLinks.length" type="info" variant="tonal">
              当前角色尚未获得业务模块入口，请联系超级管理员核对 grants。
            </v-alert>
          </v-card>
        </section>

        <section v-if="canReadCommunications" class="workbench-notifications" aria-labelledby="notifications-title">
          <div class="d-flex flex-wrap align-start justify-space-between ga-3 mb-3">
            <div>
              <h2 id="notifications-title" class="text-subtitle-1 font-weight-bold">站内通知统计</h2>
              <p class="text-body-2 text-medium-emphasis mt-1">已生成的用户通知累计数据；处理异常请查看事件明细。</p>
            </div>
            <v-btn to="/apps/operations/eventing?tab=events" variant="text" color="primary" append-icon="mdi-arrow-right">查看事件明细</v-btn>
          </div>
        <v-card v-if="notificationStats">
          <dl class="workbench-notification-stats">
            <div><dt>通知总量</dt><dd>{{ notificationStats.total }}</dd></div>
            <div><dt>未读通知</dt><dd>{{ notificationStats.unread }}</dd></div>
            <div><dt>覆盖用户</dt><dd>{{ notificationStats.recipients }}</dd></div>
          </dl>
          </v-card>
          <v-alert v-else-if="notificationsError" type="error" variant="tonal">{{ notificationsError }}</v-alert>
          <v-card v-else class="pa-5 text-body-2 text-medium-emphasis">正在读取通知统计…</v-card>
        </section>
        </div>
        <section v-if="canReadAudit" class="workbench-changes" aria-labelledby="changes-title">
          <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-3">
            <div>
              <h2 id="changes-title" class="text-subtitle-1 font-weight-bold">最近变更</h2>
              <p class="text-body-2 text-medium-emphasis mt-1">只展示当前账号有权读取的管理操作记录。</p>
            </div>
            <v-btn to="/apps/operations/audit" variant="text" append-icon="mdi-arrow-right">查看审计日志</v-btn>
          </div>
          <AdminAsyncState :loading="auditLoading" :error="auditError" :empty="!recentChanges.length" empty-title="暂无近期变更" @retry="loadRecentChanges">
            <v-card>
              <v-list lines="two">
                <template v-for="(change, index) in recentChanges" :key="change.id">
                  <v-list-item :to="`/apps/operations/audit/${change.id}`" :title="change.action" :subtitle="`操作者 ${change.actor_id ? `#${change.actor_id}` : '系统或匿名'} · ${change.resource_type || '未关联资源'} ${change.resource_id || ''} · ${change.created_at}`" append-icon="mdi-chevron-right" />
                  <v-divider v-if="index < recentChanges.length - 1" />
                </template>
              </v-list>
            </v-card>
          </AdminAsyncState>
        </section>

        <section v-if="dashboard && dashboard.notice.length" class="workbench-notice" aria-labelledby="notice-title">
          <div class="mb-3">
            <div>
              <h2 id="notice-title" class="text-subtitle-1 font-weight-bold">产品公告</h2>
              <p class="text-body-2 text-medium-emphasis mt-1">来自 LoveCards 服务端的公告。</p>
            </div>
          </div>
          <v-card>
            <v-list lines="two" density="compact">
              <v-list-item v-for="notice in dashboard.notice" :key="`${notice.date}-${notice.title}`" :href="notice.url || undefined" target="_blank" :value="`notice-${notice.date}-${notice.title}`">
                <template #prepend><v-icon color="primary" class="mr-3">mdi-information-outline</v-icon></template>
                <v-list-item-title>{{ notice.title }}</v-list-item-title>
                <v-list-item-subtitle>{{ notice.content }} · {{ notice.date }}</v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-card>
        </section>
      </div>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import { eventModeLabel, eventProcessingReasonLabel } from '~/lib/event-processing-labels'
import type { DashboardData, DashboardTask, OperationsAuditLog, OperationsNotificationStats, OperationsRuntimeStatus, RoleInfo } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import { useApi, isApiError } from '~/lib/api'
import { useDashboardStore } from '~/stores/dashboardStore'
import { useUserStore } from '~/stores/userStore'

const client = useApi()
const dashboardStore = useDashboardStore()
const userStore = useUserStore()

const dashboard = computed<DashboardData | null>(() => dashboardStore.dashboard)
const loading = ref(false)
const runtimeStatus = ref<OperationsRuntimeStatus | null>(null)
const runtimeError = ref<string | null>(null)
const notificationStats = ref<OperationsNotificationStats | null>(null)
const notificationsError = ref<string | null>(null)
const dashboardReadAt = ref('')
const recentChanges = ref<OperationsAuditLog[]>([])
const auditLoading = ref(false)
const auditError = ref<string | null>(null)
const showAllResponsibilities = ref(false)
const responsibilityPreviewLimit = 3

const canReadOperations = computed(() => userStore.hasGrant('operations.read', 'global'))
const canReadCommunications = computed(() => userStore.hasGrant('communications.read', 'global'))
const canReadAudit = computed(() => userStore.hasGrant('audit.read', 'global'))
const eventReady = computed(() => runtimeStatus.value?.event_processing?.status === 'ready'
  && runtimeStatus.value.event_processing.state !== 'draining')
const eventProcessingSummary = computed(() => {
  const current = runtimeStatus.value?.event_processing
  if (!current) return '状态不可用'
  const mode = eventModeLabel(current.mode)
  if (current.state === 'draining') return `${mode} · 切换中`
  if (eventReady.value) return `${mode} · 正常`
  return `${mode} · ${current.reason_code ? eventProcessingReasonLabel(current.reason_code) : '需维护'}`
})
const communicationSummary = computed(() => {
  const communication = runtimeStatus.value?.communication
  if (!communication) return '状态不可用'
  const mode = communication.desired_mode === 'realtime' ? '实时' : communication.desired_mode === 'polling' ? '轮询' : '未知'
  return communication.status === 'ready' ? `当前为${mode}模式，运行正常` : `当前为${mode}模式，主动提示暂停，请检查依赖`
})

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

const workbenchTasks = computed<DashboardTask[]>(() => dashboard.value?.workbench?.tasks ?? [])
const pendingTasks = computed(() => workbenchTasks.value.filter((task) => task.count > 0))

function taskColor(severity: DashboardTask['severity']) {
  return severity === 'error' ? 'error' : severity === 'warning' ? 'warning' : 'info'
}

function taskIcon(key: string) {
  return ({
    cards_pending: 'mdi-card-search-outline',
    comments_pending: 'mdi-comment-alert-outline',
    reports_open: 'mdi-flag-outline',
    reports_reviewing: 'mdi-flag-outline',
    deliveries_dead: 'mdi-alert-circle-outline',
    files_pending: 'mdi-file-clock-outline',
  } as Record<string, string>)[key] ?? 'mdi-clipboard-check-outline'
}

const overviewMetrics = computed(() => {
  if (!dashboard.value) return []
  return [
    { key: 'cards', label: '卡片', value: dashboard.value.count.cards ?? 0, detail: '当前可管理内容', icon: 'mdi-card-text-outline', to: '/apps/content/cards' },
    { key: 'comments', label: '评论', value: dashboard.value.count.comments ?? 0, detail: '当前评论总量', icon: 'mdi-comment-text-multiple-outline', to: '/apps/content/comments' },
    { key: 'likes', label: '获赞', value: dashboard.value.count.good ?? 0, detail: '按获赞数查看卡片', icon: 'mdi-heart-outline', to: '/apps/content/cards?order_key=goods&order_desc=1' },
  ].filter((metric) => userStore.canAccessPath(metric.to))
})

function errorMessage(error: unknown, fallback: string) {
  return isApiError(error) ? error.message : fallback
}

async function loadWorkbench() {
  loading.value = true
  runtimeError.value = null
  notificationsError.value = null
  try {
    await dashboardStore.getDashboard()
    if (!dashboardStore.error) dashboardReadAt.value = new Date().toLocaleString('zh-CN')
    await loadAuxiliaryData()
  } finally {
    loading.value = false
  }
}

async function loadAuxiliaryData() {
  if (canReadOperations.value) {
    try {
      runtimeStatus.value = await client.operations.runtime()
    } catch (error) {
      runtimeStatus.value = null
      runtimeError.value = errorMessage(error, '无法加载运行状态。')
    }
  }
  if (canReadCommunications.value) {
    try {
      notificationStats.value = await client.operations.notificationStats()
    } catch (error) {
      notificationsError.value = errorMessage(error, '无法加载通知统计。')
    }
  }
  if (canReadAudit.value) await loadRecentChanges()
}

async function loadRecentChanges() {
  auditLoading.value = true
  auditError.value = null
  try {
    const result = await client.operations.auditLogs({ page: 1, list_rows: 5 })
    recentChanges.value = result.data
  } catch (error) {
    auditError.value = errorMessage(error, '无法加载最近变更。')
  } finally {
    auditLoading.value = false
  }
}

onMounted(async () => {
  loading.value = true
  await dashboardStore.init()
  if (!dashboardStore.error) dashboardReadAt.value = new Date().toLocaleString('zh-CN')
  await loadAuxiliaryData()
  loading.value = false
})
</script>

<style scoped>
.workbench-layout {
  display: grid;
  grid-template-columns: minmax(0, 7fr) minmax(320px, 5fr);
  gap: 24px;
  align-items: start;
}

.workbench-layout__main,
.workbench-layout__aside {
  display: flex;
  width: 100%;
  min-width: 0;
  flex-direction: column;
  gap: 24px;
}

.workbench-changes,
.workbench-notice {
  grid-column: 1 / -1;
}

.workbench-notification-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: 0;
}

.workbench-notification-stats > div {
  min-width: 0;
  padding: 14px 8px;
  text-align: center;
}

.workbench-notification-stats > div + div {
  border-left: 1px solid rgb(var(--v-theme-outline));
}

.workbench-notification-stats dt {
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 0.75rem;
}

.workbench-notification-stats dd {
  margin: 4px 0 0;
  font-size: 1.25rem;
  font-weight: 700;
}

@media (max-width: 960px) {
  .workbench-layout {
    grid-template-columns: minmax(0, 1fr);
  }

  .workbench-changes,
  .workbench-notice {
    grid-column: 1;
  }
}
</style>
