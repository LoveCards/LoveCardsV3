<template>
  <AdminPageContainer>
    <AdminPageHeader
      title="Redis 服务"
      description="管理系统共用的 Redis 连接。默认关闭；实时主动更新启用前会检查连接和常驻服务。"
      :breadcrumbs="[{ title: '系统' }]"
    />

    <AdminAsyncState :loading="loading" :error="loadError" error-title="Redis 配置加载失败" @retry="load">
      <v-alert v-if="message" :type="message.type" variant="tonal" class="mb-4">{{ message.text }}</v-alert>

      <AdminConfigSection
        title="连接设置"
        description="这份连接供实时信号发布和事件唤醒使用；开启配置不会自动启动 Redis 或 Gateway。"
        icon="mdi-database-outline"
        :dirty="dirty"
      >
        <v-switch v-model="form.enabled" label="启用 Redis 连接" color="primary" :disabled="!canEdit || saving" class="mb-3" />
        <v-row dense>
          <v-col cols="12" md="6">
            <v-text-field v-model.trim="form.host" label="主机地址" variant="outlined" density="comfortable" :disabled="!canEdit || saving" />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-text-field v-model.number="form.port" label="端口" type="number" min="1" max="65535" variant="outlined" density="comfortable" :disabled="!canEdit || saving" />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-text-field v-model.number="form.database" label="数据库编号" type="number" min="0" max="15" variant="outlined" density="comfortable" :disabled="!canEdit || saving" />
          </v-col>
        </v-row>
        <v-switch v-model="form.tls" label="使用 TLS 加密连接并验证证书" color="primary" :disabled="!canEdit || saving" class="mb-3" />
        <v-text-field
          v-if="form.tls"
          v-model.trim="form.tls_server_name"
          label="TLS 证书名称"
          hint="留空时验证主机地址；连接地址为 IP 时必须填写证书上的 DNS 名称。自建 CA 请在部署环境安装。"
          persistent-hint
          variant="outlined"
          density="comfortable"
          :disabled="!canEdit || saving"
          class="mb-3"
        />
        <div class="text-subtitle-2 mb-2">认证密码</div>
        <div class="text-body-2 text-medium-emphasis mb-3">{{ passwordConfigured ? '已配置密码。' : '未配置密码。' }} 留空不会清除已保存的密码。</div>
        <v-btn-toggle v-model="secretAction" mandatory divided density="comfortable" class="mb-3" :disabled="!canEdit || saving">
          <v-btn value="keep">保留</v-btn>
          <v-btn value="replace">替换</v-btn>
          <v-btn value="clear">清空</v-btn>
        </v-btn-toggle>
        <v-text-field
          v-if="secretAction === 'replace'"
          v-model="replacementPassword"
          label="新密码"
          type="password"
          autocomplete="new-password"
          variant="outlined"
          density="comfortable"
          :disabled="!canEdit || saving"
          hint="保存后仅显示是否已配置，不回显密码。"
          persistent-hint
        />
        <v-alert v-if="secretAction === 'clear'" type="warning" variant="tonal">保存后将移除 Redis 认证密码。</v-alert>
      </AdminConfigSection>

      <AdminConfigSaveBar class="my-5" :dirty="dirty" label="Redis 配置">
        <AdminActionGuard capability="operations.runtime.configure">
          <v-btn variant="outlined" prepend-icon="mdi-connection" :loading="testing" :disabled="dirty || testing" @click="testConnection">测试当前连接</v-btn>
        </AdminActionGuard>
        <AdminActionGuard capability="config.update">
          <v-btn color="primary" variant="flat" prepend-icon="mdi-content-save-outline" :loading="saving" :disabled="!canEdit || !dirty || saving || (secretAction === 'replace' && !replacementPassword)" @click="save">保存设置</v-btn>
        </AdminActionGuard>
      </AdminConfigSaveBar>

      <v-alert v-if="testResult" :type="testResult.ready ? 'success' : 'warning'" variant="tonal">
        {{ testResult.ready ? '连接测试通过。' : `连接测试未通过：${reasonLabel(testResult.reason_code)}` }}
      </v-alert>
    </AdminAsyncState>
    <AdminUnsavedChangesDialog :model-value="unsavedChangesDialog" @stay="continueEditing" @discard="discardChanges" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { JsonObject } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminConfigSaveBar from '~/components/shared/admin/AdminConfigSaveBar.vue'
import AdminConfigSection from '~/components/shared/admin/AdminConfigSection.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import { readBoolean, readNumber, readObject, readString } from '~/utils/json'

type RedisForm = { enabled: boolean; host: string; port: number; database: number; tls: boolean; tls_server_name: string }
type TestResult = { ready: boolean; reason_code: string | null }

const client = useApi()
const userStore = useUserStore()
const loading = ref(true)
const loadError = ref('')
const saving = ref(false)
const testing = ref(false)
const message = ref<{ type: 'success' | 'error'; text: string } | null>(null)
const testResult = ref<TestResult | null>(null)
const form = reactive<RedisForm>({ enabled: false, host: '', port: 6379, database: 0, tls: false, tls_server_name: '' })
const original = ref<RedisForm>({ ...form })
const passwordConfigured = ref(false)
const secretAction = ref<'keep' | 'replace' | 'clear'>('keep')
const replacementPassword = ref('')
const canEdit = computed(() => userStore.hasGrant('config.update', 'global') && userStore.hasGrant('operations.runtime.configure', 'global'))
const dirty = computed(() => JSON.stringify(form) !== JSON.stringify(original.value) || secretAction.value !== 'keep')
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(dirty)

const errorMessage = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : fallback
const reasonLabel = (reason: string | null) => ({
  'redis-disabled': '尚未启用 Redis',
  'redis-not-configured': '连接参数无效',
  'redis-extension-unavailable': 'PHP Redis 扩展不可用',
  'redis-unreachable': '无法连接 Redis',
  'redis-auth-failed': '认证失败',
  'redis-database-unavailable': '数据库不可用',
  'redis-tls-name-invalid': 'TLS 证书名称无效',
  'redis-tls-failed': 'TLS 连接或证书校验失败',
  'redis-tls-extension-unsupported': 'PHP Redis 扩展版本过低，需要 PhpRedis 5.3 或更高版本',
}[reason ?? ''] ?? '请检查连接参数和服务状态')

const parseTest = (raw: Record<string, unknown>): TestResult => ({
  ready: raw.ready === true,
  reason_code: typeof raw.reason_code === 'string' ? raw.reason_code : null,
})

const load = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const raw = await client.config.list()
    const redis = readObject(raw.redis)
    const values: RedisForm = {
      enabled: readBoolean(redis.enabled),
      host: readString(redis.host),
      port: readNumber(redis.port, 6379),
      database: readNumber(redis.database),
      tls: readBoolean(redis.tls),
      tls_server_name: readString(redis.tls_server_name),
    }
    Object.assign(form, values)
    original.value = { ...values }
    passwordConfigured.value = readBoolean(readObject(redis.password).configured)
    secretAction.value = 'keep'
    replacementPassword.value = ''
    testResult.value = null
    return true
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载 Redis 配置。')
    return false
  } finally {
    loading.value = false
  }
}

const save = async () => {
  if (!canEdit.value || !dirty.value || saving.value || (secretAction.value === 'replace' && !replacementPassword.value)) return
  saving.value = true
  message.value = null
  try {
    const redis: JsonObject = { ...form }
    if (secretAction.value === 'replace') redis.password = { $secret: 'replace', value: replacementPassword.value }
    if (secretAction.value === 'clear') redis.password = { $secret: 'clear' }
    await client.config.update({ redis })
    if (await load()) message.value = { type: 'success', text: 'Redis 配置已保存。请测试连接，再检查实时模式依赖。' }
  } catch (cause) {
    message.value = { type: 'error', text: errorMessage(cause, 'Redis 配置保存失败。') }
  } finally {
    saving.value = false
  }
}

const testConnection = async () => {
  if (dirty.value || testing.value) return
  testing.value = true
  message.value = null
  try {
    const raw = await client.config.redisTest()
    testResult.value = parseTest(raw)
  } catch (cause) {
    message.value = { type: 'error', text: errorMessage(cause, '连接测试失败。') }
  } finally {
    testing.value = false
  }
}

onMounted(() => { void load() })
</script>
