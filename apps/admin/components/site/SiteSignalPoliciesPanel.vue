<template>
  <section aria-labelledby="site-signals-title" class="mt-8">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-3">
      <div>
        <h2 id="site-signals-title" class="text-h6">主动更新</h2>
        <p class="text-body-2 text-medium-emphasis mt-1">这些开关只控制实时提示。关闭后，私信和站内通知仍会保存，用户手动刷新仍可查看。</p>
      </div>
      <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" :disabled="dirty" @click="load">刷新</v-btn>
    </div>
    <AdminAsyncState :loading="loading && !catalog" :error="error && !catalog ? error : null" :empty="!catalog" @retry="load">
      <template v-if="catalog">
        <v-alert v-if="error" type="warning" variant="tonal" class="mb-4">刷新失败，以下为上次读取的数据：{{ error }}</v-alert>
        <v-alert v-if="conflict" type="warning" variant="tonal" class="mb-4">{{ conflict }}</v-alert>
        <AdminResourceTableShell>
          <v-data-table :headers="headers" :items="rows" item-value="key" :items-per-page="-1" hide-default-footer>
            <template #item.key="{ item }">
              <strong>{{ item.display_name }}</strong>
              <div class="text-body-2 text-medium-emphasis">{{ item.impact_text }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.owner }} · {{ item.topic }}</div>
            </template>
            <template #item.enabled="{ item }">
              <AdminActionGuard capability="communications.configure">
                <v-switch :model-value="item.enabled" :aria-label="`${item.display_name}：${item.enabled ? '已开启' : '已关闭'}`" :label="item.enabled ? '已开启' : '已关闭'" density="compact" hide-details color="primary" inset @update:model-value="item.enabled = Boolean($event)" />
                <template #fallback><span>{{ item.enabled ? '已开启' : '已关闭' }}</span></template>
              </AdminActionGuard>
            </template>
          </v-data-table>
        </AdminResourceTableShell>
        <p class="text-body-2 text-medium-emphasis mt-3">策略版本 {{ catalog.revision }}。修改只影响之后的主动提示，不改变业务数据和通知生成规则。</p>
        <AdminActionGuard capability="communications.configure">
          <AdminConfigSaveBar v-if="dirty" class="mt-4" :dirty="dirty" label="主动更新">
            <v-btn variant="text" @click="revert">放弃修改</v-btn>
            <v-btn color="primary" variant="flat" @click="dialogOpen = true">审阅并保存</v-btn>
          </AdminConfigSaveBar>
        </AdminActionGuard>
        <AdminDangerActionDialog v-model="dialogOpen" title="确认主动更新变更" action-label="确认保存" color="primary" :impact="impact" :loading="saving" :error="saveError" @confirm="save" />
      </template>
    </AdminAsyncState>
  </section>
</template>

<script setup lang="ts">
import type { SignalPolicyCatalog, SignalPolicyItem } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminConfigSaveBar from '~/components/shared/admin/AdminConfigSaveBar.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import { useApi, isApiError } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'

const props = defineProps<{ active: boolean }>()
const emit = defineEmits<{ 'dirty-change': [value: boolean] }>()
const client = useApi()
const notifier = useAdminNotifier()
const catalog = ref<SignalPolicyCatalog | null>(null)
const rows = ref<Array<SignalPolicyItem & { original: boolean }>>([])
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const conflict = ref('')
const dialogOpen = ref(false)
const saveError = ref('')
const headers = [{ title: '主动提示与影响', key: 'key' }, { title: '当前设置', key: 'enabled', sortable: false }]
const dirty = computed(() => rows.value.some(row => row.enabled !== row.original))
const impact = computed(() => rows.value.filter(row => row.enabled !== row.original)
  .map(row => `${row.display_name}：${row.original ? '开启 → 关闭' : '关闭 → 开启'}`)
  .concat('仅影响主动提示，不影响已保存的数据。操作会写入审计记录。').join('；'))
watch(dirty, value => emit('dirty-change', value), { immediate: true })

async function load() {
  loading.value = true
  error.value = null
  try {
    catalog.value = await client.communications.signals()
    rows.value = catalog.value.signals.map(row => ({ ...row, original: row.enabled }))
    conflict.value = ''
  } catch (cause) {
    error.value = isApiError(cause) ? cause.message : '加载主动更新目录失败'
  } finally { loading.value = false }
}

function revert() {
  rows.value = rows.value.map(row => ({ ...row, enabled: row.original }))
  conflict.value = ''
}

async function save() {
  if (!catalog.value || !dirty.value || saving.value) return
  saving.value = true
  saveError.value = ''
  try {
    const result = await client.communications.applySignals({
      expected_revision: catalog.value.revision,
      signals: rows.value.filter(row => row.enabled !== row.original).map(row => ({ key: row.key, enabled: row.enabled })),
    })
    catalog.value = result
    rows.value = result.signals.map(row => ({ ...row, original: row.enabled }))
    dialogOpen.value = false
    conflict.value = ''
    notifier.success('主动更新设置已保存')
  } catch (cause) {
    if (isApiError(cause) && cause.status === 409) {
      dialogOpen.value = false
      conflict.value = '设置已被其他管理员修改。你的修改仍保留；请放弃修改并刷新后重新审阅。'
    } else saveError.value = isApiError(cause) ? cause.message : '保存主动更新设置失败'
  } finally { saving.value = false }
}

onMounted(() => { if (props.active) void load() })
watch(() => props.active, active => { if (active && !catalog.value && !loading.value) void load() })
onBeforeRouteLeave(() => !dirty.value || window.confirm('有未保存的主动更新设置，确定离开吗？'))
function warnBeforeUnload(event: BeforeUnloadEvent) {
  if (!dirty.value) return
  event.preventDefault()
  event.returnValue = ''
}
onMounted(() => window.addEventListener('beforeunload', warnBeforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeUnload))
</script>
