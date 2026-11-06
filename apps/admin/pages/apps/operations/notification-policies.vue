<template>
  <AdminPageContainer>
    <AdminPageHeader title="通知策略" description="启停互动事件的通知投影。安全、审核与账号类通知已锁定，普通管理员不可关闭。" :breadcrumbs="[{ title: '系统运维' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading" :error="error" :empty="!catalog" @retry="load">
      <template v-if="catalog">
        <v-alert v-if="!dirty" class="mb-4" type="info" variant="tonal">
          当前策略版本 {{ catalog.revision ?? '未初始化' }}。修改会以 CAS 生成新版本；在途投递按登记时的版本执行，不受本次修改追溯。
        </v-alert>
        <v-alert v-else class="mb-4" type="warning" variant="tonal">
          有 {{ dirtyCount }} 项未保存修改（基于策略版本 {{ baseRevision }}）。保存将生成版本 {{ baseRevision + 1 }}。
        </v-alert>
        <AdminActionGuard capability="communications.configure">
          <AdminResourceTableShell>
            <v-data-table :headers="headers" :items="rows" item-value="event_type" items-per-page-text="每页行数">
              <template #item.event_type="{ item }">
                <strong>{{ item.event_type }}</strong>
                <div class="text-caption text-medium-emphasis">通知类型：{{ item.notification_type || '由处理器决定' }}</div>
              </template>
              <template #item.enabled="{ item }">
                <v-switch
                  v-model="item.enabled"
                  :disabled="item.locked"
                  density="compact"
                  hide-details
                  color="primary"
                  inset
                />
              </template>
              <template #item.locked="{ item }">
                <AdminStatusChip :status="item.locked ? 'disabled' : 'available'" :label="item.locked ? '已锁定' : '可配置'" />
              </template>
            </v-data-table>
          </AdminResourceTableShell>
          <v-divider class="my-4" />
          <v-text-field
            v-model="reason"
            label="变更原因（写入审计）"
            maxlength="255"
            density="compact"
            variant="outlined"
            hint="例如：大促期间暂停点赞通知"
            persistent-hint
          />
          <div class="d-flex flex-wrap mt-4 ga-2">
            <v-btn color="primary" variant="elevated" :disabled="!dirty" :loading="saving" @click="openSave">保存变更</v-btn>
            <v-btn variant="text" :disabled="!dirty" @click="revert">放弃修改</v-btn>
          </div>
        </AdminActionGuard>
        <AdminDangerActionDialog
          v-model="saveDialog"
          title="启停通知策略"
          action-label="确认保存"
          :impact="saveImpact"
          :loading="saving"
          :error="saveError"
          @confirm="doSave"
        />
      </template>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import type { NotificationPolicyCatalog, NotificationPolicyRule } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { useApi, isApiError } from '~/lib/api'

interface EditableRow extends NotificationPolicyRule {
  original: boolean
}

const client = useApi()
const notifier = useAdminNotifier()
const catalog = ref<NotificationPolicyCatalog | null>(null)
const rows = ref<EditableRow[]>([])
const baseRevision = ref(0)
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const reason = ref('')
const saveDialog = ref(false)
const saveError = ref<string | undefined>(undefined)

const headers = [
  { title: '事件', key: 'event_type' },
  { title: '启用', key: 'enabled' },
  { title: '可关闭性', key: 'locked' },
  { title: '接收人规则', key: 'recipient_rule' },
]

const dirty = computed(() => rows.value.some((row) => row.enabled !== row.original))
const dirtyCount = computed(() => rows.value.filter((row) => row.enabled !== row.original).length)

const saveImpact = computed(() => {
  const changes = rows.value.filter((row) => row.enabled !== row.original)
  const lines = changes.map((row) => `${row.event_type}：${row.original ? '关闭' : '开启'}`)
  return [`策略版本 ${baseRevision.value} → ${baseRevision.value + 1}`, ...lines].join('；')
})

async function load() {
  loading.value = true
  error.value = null
  try {
    catalog.value = await client.communications.policies()
    baseRevision.value = catalog.value.revision ?? 0
    rows.value = catalog.value.policies.map((policy) => ({ ...policy, original: policy.enabled }))
  } catch (err) {
    error.value = isApiError(err) ? err.message : '加载通知策略失败'
  } finally {
    loading.value = false
  }
}

function revert() {
  rows.value = rows.value.map((row) => ({ ...row, enabled: row.original }))
  reason.value = ''
}

function openSave() {
  saveError.value = undefined
  saveDialog.value = true
}

async function doSave() {
  if (saving.value) return
  saving.value = true
  saveError.value = undefined
  try {
    const result = await client.communications.applyPolicies({
      expected_revision: baseRevision.value,
      reason: reason.value.trim() || undefined,
      policies: rows.value
        .filter((row) => row.enabled !== row.original)
        .map((row) => ({ event_type: row.event_type, event_version: row.event_version, rule_key: row.rule_key, enabled: row.enabled })),
    })
    baseRevision.value = result.revision
    rows.value = result.policies.map((policy) => ({ ...policy, original: policy.enabled }))
    saveDialog.value = false
    reason.value = ''
    notifier.success(`通知策略已更新到版本 ${result.revision}`)
  } catch (err) {
    if (isApiError(err) && err.code === 9016) {
      saveError.value = '策略已被其他操作更新，列表已刷新为最新版本，请重新确认。'
      saving.value = false
      await load()
      return
    }
    saveError.value = isApiError(err) ? err.message : '保存通知策略失败'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
