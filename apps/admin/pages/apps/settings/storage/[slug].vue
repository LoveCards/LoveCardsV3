<template>
  <AdminPageContainer>
    <AdminPageHeader
      :title="`${channelName} 配置`"
      description="编辑存储渠道参数。保存后可使用服务端已保存的配置测试连接。"
      :breadcrumbs="[{ title: '系统' }, { title: '文件存储', to: '/apps/settings/storage' }, { title: channelName }]"
    />

    <v-row>
      <v-col cols="12" lg="8">
        <AdminConfigSection
          title="渠道参数"
          description="连接测试只读取已保存配置；密钥字段留空表示保留服务端现值。"
          icon="mdi-database-cog-outline"
          risk="high"
        >
          <template #actions><v-chip size="small" variant="outlined">{{ slug }}</v-chip></template>
            <AdminAsyncState
              :empty="fields.length === 0"
              empty-description="该存储渠道没有提供可编辑参数。"
              empty-icon="mdi-cloud-check-outline"
              empty-title="无需配置"
              :error="loadError"
              error-title="渠道配置加载失败"
              :loading="loading"
              skeleton-type="heading, text@5"
              @retry="loadPage"
            >
              <template #emptyAction>
                <v-btn color="primary" to="/apps/settings/storage" variant="tonal">返回渠道列表</v-btn>
              </template>

              <v-alert v-if="!canUpdateConfig" class="mb-4" type="info" variant="tonal">
                当前账号只有查看权限，不能修改渠道配置。
              </v-alert>
              <v-alert v-if="saveMessage" class="mb-4" :type="saveMessage.type" variant="tonal">
                {{ saveMessage.text }}
              </v-alert>
              <v-alert v-if="testMessage" class="mb-4" :type="testMessage.type" variant="tonal">
                {{ testMessage.text }}
              </v-alert>

              <v-form @submit.prevent="save">
                <v-row dense>
                  <v-col v-for="field in fields" :key="field.key" cols="12" md="6">
                    <AdminSecretConfigField
                      v-if="isSecretField(field)"
                      :disabled="!canUpdateConfig || saveLoading"
                      :label="field.label"
                      :model-value="secretValue(field.key)"
                      @update:model-value="setSecretValue(field.key, $event)"
                    />
                    <v-select
                      v-else-if="field.type === 'select'"
                      :disabled="!canUpdateConfig || saveLoading"
                      density="comfortable"
                      item-title="label"
                      item-value="value"
                      :items="field.options ?? []"
                      :label="field.label"
                      :model-value="config[field.key]"
                      variant="outlined"
                      @update:model-value="setValue(field.key, $event)"
                    />
                    <v-switch
                      v-else-if="field.type === 'checkbox' || field.type === 'boolean'"
                      color="primary"
                      :disabled="!canUpdateConfig || saveLoading"
                      inset
                      :label="field.label"
                      :model-value="Boolean(config[field.key])"
                      @update:model-value="setValue(field.key, $event)"
                    />
                    <v-number-input
                      v-else-if="field.type === 'number'"
                      control-variant="split"
                      :disabled="!canUpdateConfig || saveLoading"
                      density="comfortable"
                      :label="field.label"
                      :model-value="numberValue(field.key)"
                      variant="outlined"
                      @update:model-value="setValue(field.key, $event)"
                    />
                    <v-text-field
                      v-else
                      :disabled="!canUpdateConfig || saveLoading"
                      density="comfortable"
                      :label="field.label"
                      :model-value="textValue(field.key)"
                      variant="outlined"
                      @update:model-value="setValue(field.key, $event)"
                    />
                  </v-col>
                </v-row>
                <v-divider class="mb-4" />
                <AdminConfigSaveBar :dirty="configChanged" label="渠道配置">
                  <AdminActionGuard capability="storage.test" scope="global">
                    <v-btn
                      color="primary"
                      :disabled="configChanged || saveLoading"
                      :loading="testLoading"
                      prepend-icon="mdi-connection"
                      variant="tonal"
                      @click="testConnection"
                    >
                      测试连接
                    </v-btn>
                  </AdminActionGuard>
                  <AdminActionGuard
                    :all-of="[
                      { capability: 'config.update', scope: 'global' },
                      { capability: 'storage.read', scope: 'global' },
                    ]"
                  >
                    <v-btn
                      color="primary"
                      :disabled="!configChanged"
                      :loading="saveLoading"
                      prepend-icon="mdi-content-save-outline"
                      type="submit"
                      variant="flat"
                    >
                      保存渠道配置
                    </v-btn>
                  </AdminActionGuard>
                </AdminConfigSaveBar>
              </v-form>
            </AdminAsyncState>
        </AdminConfigSection>
      </v-col>
    </v-row>
    <AdminUnsavedChangesDialog
      :model-value="unsavedChangesDialog"
      @stay="continueEditing"
      @discard="discardChanges"
    />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { FieldDefinition, StorageChannel } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminConfigSection from '~/components/shared/admin/AdminConfigSection.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminSecretConfigField from '~/components/shared/admin/AdminSecretConfigField.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'
import { isSecretConfigEnvelope, normalizeSecretConfig, type SecretConfigEnvelope } from '~/utils/configSecrets'

type ChannelField = FieldDefinition & { key: string; label: string }
type Feedback = { type: 'success' | 'error'; text: string }
type FieldValue = string | number | boolean | null | SecretConfigEnvelope
type FormConfig = Record<string, FieldValue>

const client = useApi()
const configSnapshot = useConfigSnapshot(client)
const route = useRoute()
const userStore = useUserStore()
const slug = String(route.params.slug)
const configGroup = `storage_${slug}`
const config = ref<FormConfig>({})
const originConfig = ref<FormConfig>({})
const fields = shallowRef<ChannelField[]>([])
const secretKeys = shallowRef(new Set<string>())
const channelName = ref(slug)
const loading = ref(false)
const loadError = ref('')
const saveLoading = ref(false)
const saveMessage = ref<Feedback | null>(null)
const testLoading = ref(false)
const testMessage = ref<Feedback | null>(null)
const canUpdateConfig = computed(() => {
  return userStore.hasGrant('config.update', 'global') && userStore.hasGrant('storage.read', 'global')
})
const canTestStorage = computed(() => userStore.hasGrant('storage.test', 'global'))
const configChanged = computed(() => JSON.stringify(config.value) !== JSON.stringify(originConfig.value))
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(configChanged)
const isChannelField = (field: FieldDefinition): field is ChannelField => typeof field.key === 'string' && typeof field.label === 'string'
const errorMessage = configSnapshot.errorMessage
const fieldValue = (value: unknown): FieldValue => {
  return typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' || value === null ? value : ''
}
const setValue = (key: string, value: unknown) => { config.value[key] = fieldValue(value) }
const secretValue = (key: string) => normalizeSecretConfig(config.value[key])
const setSecretValue = (key: string, value: SecretConfigEnvelope) => { config.value[key] = value }
const isSecretField = (field: ChannelField) => field.type === 'password' || secretKeys.value.has(field.key)
const numberValue = (key: string) => typeof config.value[key] === 'number' ? config.value[key] as number : undefined
const textValue = (key: string) => {
  const value = config.value[key]
  return typeof value === 'string' || typeof value === 'number' ? String(value) : ''
}

const loadPage = async () => {
  loading.value = true
  loadError.value = ''
  saveMessage.value = null
  testMessage.value = null
  try {
    const [channels, rawConfig] = await Promise.all([client.storage.channels(), configSnapshot.list()])
    const channel = channels.find((item: StorageChannel) => item.slug === slug)
    if (!channel) {
      loadError.value = '未找到该存储渠道，请返回列表重新扫描。'
      fields.value = []
      return
    }
    channelName.value = channel.name
    fields.value = (channel.fields ?? []).filter(isChannelField)
    const savedConfig = rawConfig[configGroup]
    const savedValues = savedConfig && typeof savedConfig === 'object' && !Array.isArray(savedConfig)
      ? savedConfig as Record<string, unknown>
      : {}
    const nextConfig: FormConfig = {}
    const nextSecretKeys = new Set<string>()
    for (const field of fields.value) {
      if (field.type === 'password' || isSecretConfigEnvelope(savedValues[field.key])) nextSecretKeys.add(field.key)
      nextConfig[field.key] = nextSecretKeys.has(field.key)
        ? normalizeSecretConfig(savedValues[field.key])
        : fieldValue(savedValues[field.key] ?? field.default ?? '')
    }
    secretKeys.value = nextSecretKeys
    config.value = CommonUtils.deepClone(nextConfig)
    originConfig.value = CommonUtils.deepClone(nextConfig)
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载存储渠道配置，请稍后重试。')
  } finally {
    loading.value = false
  }
}
const save = async () => {
  if (!canUpdateConfig.value || !configChanged.value || saveLoading.value) return
  saveLoading.value = true
  saveMessage.value = null
  const configToSave = { ...config.value }
  delete configToSave.type
  delete configToSave.file_count
  delete configToSave.total_size
  try {
    const committed = await configSnapshot.update({ [configGroup]: configToSave })
    const saved = committed[configGroup] as Record<string, unknown>
    const settled = CommonUtils.deepClone(config.value)
    for (const field of fields.value) {
      settled[field.key] = isSecretField(field)
        ? normalizeSecretConfig(saved[field.key])
        : fieldValue(saved[field.key] ?? field.default ?? '')
    }
    config.value = settled
    originConfig.value = CommonUtils.deepClone(settled)
    saveMessage.value = { type: 'success', text: '存储渠道配置已保存。' }
  } catch (cause) {
    saveMessage.value = { type: 'error', text: errorMessage(cause, '存储渠道配置保存失败，请稍后重试。') }
  } finally {
    saveLoading.value = false
  }
}
const testConnection = async () => {
  if (!canTestStorage.value || configChanged.value || saveLoading.value || testLoading.value) return
  testLoading.value = true
  testMessage.value = null
  try {
    const result = await client.storage.testChannel({ channel: slug })
    testMessage.value = {
      type: result.success ? 'success' : 'error',
      text: result.message || (result.success ? '存储渠道连接正常。' : '渠道返回连接失败。'),
    }
  } catch (cause) {
    testMessage.value = { type: 'error', text: errorMessage(cause, '连接测试失败，请检查渠道配置。') }
  } finally {
    testLoading.value = false
  }
}

onMounted(() => {
  void loadPage()
})
</script>
