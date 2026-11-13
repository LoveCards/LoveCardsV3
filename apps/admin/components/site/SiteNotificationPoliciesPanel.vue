<template>
  <div class="site-policy-panel">
    <div class="d-flex justify-end mb-4">
      <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" :disabled="dirty" @click="load">刷新</v-btn>
    </div>
    <AdminAsyncState :loading="loading && !catalog" :error="error && !catalog ? error : null" :empty="!catalog" @retry="load">
      <template v-if="catalog">
        <v-alert v-if="error" class="mb-4" type="warning" variant="tonal">
          刷新失败，以下为上次成功读取的数据：{{ error }}
        </v-alert>
        <v-alert v-if="hasMissingDescriptions" class="mb-4" type="warning" variant="tonal">
          部分提醒规则缺少影响说明，已设为只读。请检查策略目录后刷新此页。
        </v-alert>
        <v-alert v-if="conflictMessage" class="mb-4" type="warning" variant="tonal">{{ conflictMessage }}</v-alert>
        <p class="mb-4 text-body-2">这里控制是否生成站内提醒；用户自己的提醒偏好仍会单独生效。<NuxtLink to="/apps/operations/deliveries">查看通知投递记录</NuxtLink></p>
        <v-sheet class="d-sm-none">
            <div v-for="row in rows" :key="row.event_type" class="pa-4 border-b">
              <strong>{{ policyLabel(row) }}</strong>
              <p class="my-1 text-body-2 text-medium-emphasis">{{ policyImpact(row) }}</p>
              <span v-if="isMissingDescription(row)">待核对（当前{{ row.enabled ? '已开启' : '已关闭' }}）</span>
              <span v-else-if="row.locked">{{ row.enabled ? '始终开启' : '已锁定（当前关闭）' }}</span>
              <AdminActionGuard v-else capability="communications.configure">
                <v-switch :model-value="row.enabled" :aria-label="`${policyLabel(row)}：${row.enabled ? '已开启' : '已关闭'}`" :label="row.enabled ? '已开启' : '已关闭'" density="compact" hide-details color="primary" inset @update:model-value="setEnabled(row.event_type, Boolean($event))" />
                <template #fallback><span>{{ row.enabled ? '已开启' : '已关闭' }}</span></template>
              </AdminActionGuard>
            </div>
        </v-sheet>
        <AdminResourceTableShell class="d-none d-sm-block">
            <v-data-table :headers="headers" :items="rows" item-value="event_type" :items-per-page="-1" hide-default-footer>
              <template #item.event_type="{ item }">
                <strong>{{ policyLabel(item) }}</strong>
                <div class="text-body-2 text-medium-emphasis">{{ policyImpact(item) }}</div>
              </template>
              <template #item.enabled="{ item }">
                <span v-if="isMissingDescription(item)">待核对（当前{{ item.enabled ? '已开启' : '已关闭' }}）</span>
                <span v-else-if="item.locked">{{ item.enabled ? '始终开启' : '已锁定（当前关闭）' }}</span>
                <AdminActionGuard v-else capability="communications.configure">
                  <v-switch :model-value="item.enabled" :aria-label="`${policyLabel(item)}：${item.enabled ? '已开启' : '已关闭'}`" :label="item.enabled ? '已开启' : '已关闭'" density="compact" hide-details color="primary" inset @update:model-value="setEnabled(item.event_type, Boolean($event))" />
                  <template #fallback><span>{{ item.enabled ? '已开启' : '已关闭' }}</span></template>
                </AdminActionGuard>
              </template>
            </v-data-table>
        </AdminResourceTableShell>
          <v-expansion-panels class="mt-4">
            <v-expansion-panel title="技术信息：事件名与版本" elevation="0" border>
              <v-expansion-panel-text>
                <div v-for="row in rows" :key="row.event_type" class="text-body-2 mb-1">{{ policyLabel(row) }}：{{ row.event_type }} · v{{ row.event_version }}</div>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        <AdminActionGuard capability="communications.configure">
          <AdminConfigSaveBar v-if="dirty" class="mt-4" :dirty="dirty" label="通知策略">
            <span class="text-body-2">已修改 {{ dirtyCount }} 项</span>
            <v-btn variant="text" @click="revert">放弃修改</v-btn>
            <v-btn color="primary" variant="flat" :loading="saving" @click="openSave">审阅并保存</v-btn>
          </AdminConfigSaveBar>
        </AdminActionGuard>
        <p v-if="!dirty" class="mt-4 text-body-2 text-medium-emphasis">当前策略版本 {{ catalog.revision ?? '未初始化' }}。仅影响新登记的事件；在途事件沿用登记时的策略。</p>
        <AdminDangerActionDialog
          v-model="saveDialog"
          title="确认提醒规则变更"
          action-label="确认保存"
          color="primary"
          :impact="saveImpact"
          :loading="saving"
          :error="saveError"
          @confirm="doSave"
        />
      </template>
    </AdminAsyncState>
  </div>
</template>

<script setup lang="ts">
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import type { NotificationPolicyCatalog, NotificationPolicyRule } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminConfigSaveBar from '~/components/shared/admin/AdminConfigSaveBar.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { useApi, isApiError } from '~/lib/api'

interface EditableRow extends NotificationPolicyRule {
  original: boolean
}

const client = useApi()
const notifier = useAdminNotifier()
const props = withDefaults(defineProps<{ active?: boolean }>(), { active: true })
const emit = defineEmits<{ 'dirty-change': [dirty: boolean] }>()
const catalog = ref<NotificationPolicyCatalog | null>(null)
const rows = ref<EditableRow[]>([])
const baseRevision = ref(0)
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const saveDialog = ref(false)
const saveError = ref<string | undefined>(undefined)
const conflictMessage = ref('')

const headers = [
  { title: '提醒与影响', key: 'event_type' },
  { title: '当前设置', key: 'enabled', sortable: false },
]

function isMissingDescription(row: NotificationPolicyRule) { return !row.display_name || !row.impact_text }
function policyLabel(row: NotificationPolicyRule) { return row.display_name || row.event_type }
function policyImpact(row: NotificationPolicyRule) { return row.impact_text || '此规则缺少影响说明，请检查策略目录。' }
const hasMissingDescriptions = computed(() => rows.value.some(isMissingDescription))
function setEnabled(eventType: string, enabled: boolean) {
  const row = rows.value.find((item) => item.event_type === eventType)
  if (row && !row.locked && !isMissingDescription(row)) row.enabled = enabled
}

const dirty = computed(() => rows.value.some((row) => row.enabled !== row.original))
watch(dirty, value => emit('dirty-change', value), { immediate: true })
const dirtyCount = computed(() => rows.value.filter((row) => row.enabled !== row.original).length)

const saveImpact = computed(() => {
  const changes = rows.value.filter((row) => row.enabled !== row.original)
  const lines = changes.map((row) => `${policyLabel(row)}：${row.original ? '开启 → 关闭' : '关闭 → 开启'}`)
  return [...lines, '变更仅影响新登记的事件；在途事件沿用原策略。操作会写入审计记录。'].join('；')
})

async function load() {
  loading.value = true
  error.value = null
  try {
    catalog.value = await client.communications.policies()
    baseRevision.value = catalog.value.revision ?? 0
    rows.value = catalog.value.policies.map((policy) => ({ ...policy, original: policy.enabled }))
    conflictMessage.value = ''
  } catch (err) {
    error.value = isApiError(err) ? err.message : '加载通知策略失败'
  } finally {
    loading.value = false
  }
}

function revert() {
  rows.value = rows.value.map((row) => ({ ...row, enabled: row.original }))
  conflictMessage.value = ''
}

function openSave() {
  if (!dirty.value) return
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
      policies: rows.value
        .filter((row) => row.enabled !== row.original)
        .map((row) => ({ event_type: row.event_type, event_version: row.event_version, rule_key: row.rule_key, enabled: row.enabled })),
    })
    catalog.value = result
    baseRevision.value = result.revision
    rows.value = result.policies.map((policy) => ({ ...policy, original: policy.enabled }))
    saveDialog.value = false
    conflictMessage.value = ''
    notifier.success(`通知策略已更新到版本 ${result.revision}`)
  } catch (err) {
    if (isApiError(err) && err.code === 9016) {
      const edits = new Map(rows.value.filter((row) => row.enabled !== row.original).map((row) => [row.event_type, row.enabled] as const))
      const previous = new Map((catalog.value?.policies ?? []).map((row) => [row.event_type, row] as const))
      try {
        const latest = await client.communications.policies()
        const changed = latest.policies.filter((row) => previous.has(row.event_type) && previous.get(row.event_type)?.enabled !== row.enabled).map(policyLabel)
        const canRebase = [...edits.keys()].every((eventType) => {
          const current = latest.policies.find((row) => row.event_type === eventType)
          const old = previous.get(eventType)
          return current && old && !current.locked && !isMissingDescription(current)
            && current.event_version === old.event_version && current.rule_key === old.rule_key
        })
        if (canRebase) {
          catalog.value = latest
          baseRevision.value = latest.revision ?? 0
          rows.value = latest.policies.map((row) => ({ ...row, original: row.enabled, enabled: edits.get(row.event_type) ?? row.enabled }))
          conflictMessage.value = `策略已更新到版本 ${latest.revision}。${changed.length ? `其他操作改变了：${changed.join('、')}。` : ''}你的修改已保留，请重新审阅后再保存。`
        } else {
          conflictMessage.value = '策略目录已变化，当前修改仍保留。请放弃修改并刷新，再重新选择。'
        }
      } catch {
        conflictMessage.value = '策略已被其他操作更新，但暂时无法读取最新版本。当前修改仍保留，请稍后重试。'
      }
      saveDialog.value = false
      return
    }
    saveError.value = isApiError(err) ? err.message : '保存通知策略失败'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  if (props.active) void load()
})
watch(() => props.active, (active) => {
  if (active && !catalog.value && !loading.value) void load()
})
onBeforeRouteLeave(() => !dirty.value || window.confirm('有未保存的提醒规则修改，确定离开吗？'))
function warnBeforeUnload(event: BeforeUnloadEvent) {
  if (!dirty.value) return
  event.preventDefault()
  event.returnValue = ''
}
onMounted(() => window.addEventListener('beforeunload', warnBeforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeUnload))
</script>
