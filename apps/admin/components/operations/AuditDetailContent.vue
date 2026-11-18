<template>
  <AdminAsyncState
    :loading="loading"
    :error="error"
    :error-title="errorTitle"
    :empty="missing"
    empty-description="该记录可能已超过保留期限，或输入的审计 ID 无效。"
    empty-icon="mdi-clipboard-remove-outline"
    empty-title="审计记录不存在"
    skeleton-type="heading, list-item-two-line@6"
    @retry="load"
  >
    <template v-if="auditLog">
      <AdminDetailSection title="操作概况">
        <v-list lines="two">
          <v-list-item prepend-icon="mdi-shield-check-outline" subtitle="操作" :title="auditLog.action" />
          <v-divider />
          <v-list-item prepend-icon="mdi-account-outline" subtitle="操作者">
            <template #title>
              <AdminResourceLink
                v-if="auditLog.actor_id"
                :label="`用户 #${auditLog.actor_id}`"
                resource-type="user"
                :resource-id="auditLog.actor_id"
              />
              <span v-else>系统</span>
            </template>
          </v-list-item>
          <v-divider />
          <v-list-item prepend-icon="mdi-target" subtitle="目标资源">
            <template #title>
              <AdminResourceLink
                v-if="auditLog.resource_type"
                :resource-type="auditLog.resource_type"
                :resource-id="auditLog.resource_id"
              />
              <span v-else>未关联资源</span>
            </template>
          </v-list-item>
          <v-divider />
          <v-list-item prepend-icon="mdi-clock-outline" subtitle="记录时间">
            <template #title><AdminTableTime :value="auditLog.created_at" seconds /></template>
          </v-list-item>
        </v-list>
      </AdminDetailSection>

      <AdminDetailSection title="状态变化" class="mt-5">
        <template #actions><v-chip size="small" variant="outlined">服务端已脱敏</v-chip></template>
        <v-row v-if="hasBefore || hasAfter" dense>
          <v-col cols="12" md="6">
            <v-sheet border class="audit-detail__snapshot pa-4" rounded="lg">
              <h3 class="mb-3 text-subtitle-1 font-weight-medium">操作前</h3>
              <pre>{{ serialize(payloadBefore) }}</pre>
            </v-sheet>
          </v-col>
          <v-col cols="12" md="6">
            <v-sheet border class="audit-detail__snapshot pa-4" rounded="lg">
              <h3 class="mb-3 text-subtitle-1 font-weight-medium">操作后</h3>
              <pre>{{ serialize(payloadAfter) }}</pre>
            </v-sheet>
          </v-col>
        </v-row>
        <v-sheet v-else border class="pa-4 text-body-2 text-medium-emphasis" rounded="lg">
          该审计事件没有记录资源状态快照。
        </v-sheet>
      </AdminDetailSection>

      <AdminDetailSection title="请求链路" class="mt-5">
        <v-list lines="two">
          <v-list-item prepend-icon="mdi-identifier" subtitle="Request ID" :title="auditLog.request_id || '未提供'" />
          <v-divider />
          <v-list-item prepend-icon="mdi-vector-link" subtitle="Correlation ID" :title="auditLog.correlation_id || '未提供'" />
        </v-list>
      </AdminDetailSection>

      <AdminDetailSection v-if="hasExtraContext" title="其他受控上下文" class="mt-5">
        <div class="audit-detail__snapshot"><pre>{{ serialize(extraContext) }}</pre></div>
      </AdminDetailSection>

      <v-alert
        class="mt-6"
        icon="mdi-shield-lock-outline"
        title="敏感信息由服务端统一处理"
        type="info"
        variant="tonal"
      >
        本页只消费审计资源的受控字段；IP 摘要和未列入资源契约的数据不会由前端请求或展示。
      </v-alert>
    </template>
  </AdminAsyncState>
</template>

<script setup lang="ts">
import type { OperationsAuditLog } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDetailSection from '~/components/shared/admin/AdminDetailSection.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue'
import { isApiError, useApi } from '~/lib/api'

const props = defineProps<{ auditId: number }>()
const client = useApi()
const auditLog = ref<OperationsAuditLog | null>(null)
const loading = ref(false)
const missing = ref(false)
const error = ref<string | null>(null)
const errorTitle = ref('加载失败')

const payload = computed<Record<string, unknown>>(() => auditLog.value?.payload ?? {})
const hasBefore = computed(() => Object.prototype.hasOwnProperty.call(payload.value, 'before'))
const hasAfter = computed(() => Object.prototype.hasOwnProperty.call(payload.value, 'after'))
const payloadBefore = computed(() => payload.value.before ?? null)
const payloadAfter = computed(() => payload.value.after ?? null)
const extraContext = computed(() => Object.fromEntries(
  Object.entries(payload.value).filter(([key]) => !['reason', 'before', 'after'].includes(key)),
))
const hasExtraContext = computed(() => Object.keys(extraContext.value).length > 0)

function serialize(value: unknown): string {
  try {
    return JSON.stringify(value, null, 2) ?? 'null'
  } catch {
    return '该上下文无法序列化显示。'
  }
}

let latestRequest = 0
async function load() {
  const requestId = ++latestRequest
  const id = props.auditId
  auditLog.value = null
  missing.value = false
  error.value = null
  errorTitle.value = '加载失败'

  if (!Number.isInteger(id) || id < 1) {
    missing.value = true
    loading.value = false
    return
  }

  loading.value = true
  try {
    const result = await client.operations.auditLog(id)
    if (requestId === latestRequest) auditLog.value = result
  } catch (cause) {
    if (requestId !== latestRequest) return
    if (isApiError(cause) && cause.status === 404) {
      missing.value = true
    } else {
      errorTitle.value = isApiError(cause) && cause.status === 403 ? '无权查看审计详情' : '无法加载审计详情'
      error.value = isApiError(cause) ? cause.message : '无法加载审计详情，请稍后重试。'
    }
  } finally {
    if (requestId === latestRequest) loading.value = false
  }
}

watch(() => props.auditId, () => { void load() }, { immediate: true })
onBeforeUnmount(() => { ++latestRequest })
</script>

<style scoped>
:deep(.v-list-item-title) {
  white-space: normal;
  overflow-wrap: anywhere;
}

.audit-detail__snapshot { min-width: 0; }
.audit-detail__snapshot pre {
  max-height: 28rem;
  margin: 0;
  overflow: auto;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
</style>
