<template>
  <AdminPageContainer>
    <AdminPageHeader
      :title="`${channelName} 配置`"
      description="管理消息渠道参数，并使用已保存的配置发送测试消息。"
      :breadcrumbs="[{ title: '站点设置' }, { title: '消息渠道', to: '/apps/settings/messaging' }, { title: channelName }]"
    />

    <v-row>
      <v-col cols="12" lg="8">
        <AdminConfigSection
          title="渠道参数"
          description="测试发信只读取已保存配置；密钥字段留空表示保留服务端现值。"
          icon="mdi-email-cog-outline"
          risk="high"
        >
          <template #actions><v-chip size="small" variant="outlined">{{ slug }}</v-chip></template>
            <AdminAsyncState
              :empty="fields.length === 0"
              empty-description="该消息渠道没有提供可编辑参数。"
              empty-icon="mdi-email-check-outline"
              empty-title="无需配置"
              :error="loadError"
              error-title="渠道配置加载失败"
              :loading="loading"
              skeleton-type="heading, text@5"
              @retry="loadPage"
            >
              <template #emptyAction>
                <v-btn color="primary" to="/apps/settings/messaging" variant="tonal">返回渠道列表</v-btn>
              </template>

              <v-alert v-if="!canUpdateConfig" class="mb-4" type="info" variant="tonal">
                当前账号只有查看权限，不能修改渠道配置。
              </v-alert>
              <v-alert v-if="saveMessage" class="mb-4" :type="saveMessage.type" variant="tonal">
                {{ saveMessage.text }}
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
                  <AdminActionGuard capability="sender.test" scope="global">
                    <v-btn
                      color="primary"
                      :disabled="configChanged || saveLoading"
                      prepend-icon="mdi-email-fast-outline"
                      variant="tonal"
                      @click="testDialog = true"
                    >
                      测试发信
                    </v-btn>
                  </AdminActionGuard>
                  <AdminActionGuard
                    :all-of="[
                      { capability: 'config.update', scope: 'global' },
                      { capability: 'sender.read', scope: 'global' },
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

    <v-dialog v-model="testDialog" aria-label="测试消息通道" max-width="480" :persistent="testLoading">
      <v-card prepend-icon="mdi-email-fast-outline" title="测试发信">
        <v-card-text class="pt-4">
          <v-alert class="mb-4" type="info" variant="tonal">
            测试使用服务端已保存的渠道配置，不会使用尚未保存的表单内容。
          </v-alert>
          <v-alert v-if="testMessage" class="mb-4" :type="testMessage.type" variant="tonal">
            {{ testMessage.text }}
          </v-alert>
          <v-text-field
            v-model="testTo"
            :disabled="testLoading"
            density="comfortable"
            label="收件邮箱"
            placeholder="name@example.com"
            required
            type="email"
            variant="outlined"
          />
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn :disabled="testLoading" variant="text" @click="testDialog = false">取消</v-btn>
          <v-btn color="primary" :loading="testLoading" variant="flat" @click="sendTest">发送测试</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <AdminUnsavedChangesDialog
      :model-value="unsavedChangesDialog"
      @stay="continueEditing"
      @discard="discardChanges"
    />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { FieldDefinition, SenderChannel } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminConfigSection from '~/components/shared/admin/AdminConfigSection.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminSecretConfigField from '~/components/shared/admin/AdminSecretConfigField.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'
import { isSecretConfigEnvelope, normalizeSecretConfig, settleSecretConfig, type SecretConfigEnvelope } from '~/utils/configSecrets'

type DriverField = FieldDefinition & { key: string; label: string }
type Feedback = { type: 'success' | 'error'; text: string }
type FieldValue = string | number | boolean | null | SecretConfigEnvelope
type FormConfig = Record<string, FieldValue>

const client = useApi()
const route = useRoute()
const userStore = useUserStore()
const slug = String(route.params.slug)
const configGroup = `sender_${slug}`
const config = ref<FormConfig>({})
const originConfig = ref<FormConfig>({})
const fields = shallowRef<DriverField[]>([])
const secretKeys = shallowRef(new Set<string>())
const channelName = ref(slug)
const loading = ref(false)
const loadError = ref('')
const saveLoading = ref(false)
const saveMessage = ref<Feedback | null>(null)
const testDialog = ref(false)
const testTo = ref('')
const testLoading = ref(false)
const testMessage = ref<Feedback | null>(null)
const canUpdateConfig = computed(() => {
  return userStore.hasGrant('config.update', 'global') && userStore.hasGrant('sender.read', 'global')
})
const canTestChannel = computed(() => userStore.hasGrant('sender.test', 'global'))
const configChanged = computed(() => JSON.stringify(config.value) !== JSON.stringify(originConfig.value))
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(configChanged)
const isDriverField = (field: FieldDefinition): field is DriverField => typeof field.key === 'string' && typeof field.label === 'string'
const errorMessage = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : fallback
const fieldValue = (value: unknown): FieldValue => {
  return typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' || value === null ? value : ''
}
const setValue = (key: string, value: unknown) => { config.value[key] = fieldValue(value) }
const secretValue = (key: string) => normalizeSecretConfig(config.value[key])
const setSecretValue = (key: string, value: SecretConfigEnvelope) => { config.value[key] = value }
const isSecretField = (field: DriverField) => field.type === 'password' || secretKeys.value.has(field.key)
const numberValue = (key: string) => typeof config.value[key] === 'number' ? config.value[key] as number : undefined
const textValue = (key: string) => {
  const value = config.value[key]
  return typeof value === 'string' || typeof value === 'number' ? String(value) : ''
}

const loadPage = async () => {
  loading.value = true
  loadError.value = ''
  saveMessage.value = null
  try {
    const [channels, rawConfig] = await Promise.all([client.sender.channels(), client.config.list()])
    const channel = channels.find((item: SenderChannel) => item.slug === slug)
    if (!channel) {
      loadError.value = '未找到该消息渠道，请返回列表重新扫描。'
      fields.value = []
      return
    }
    channelName.value = channel.name
    fields.value = (channel.fields ?? []).filter(isDriverField)
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
    loadError.value = errorMessage(cause, '无法加载消息渠道配置，请稍后重试。')
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
  try {
    await client.config.update({ [configGroup]: configToSave })
    const settled = CommonUtils.deepClone(config.value)
    for (const field of fields.value) {
      if (isSecretField(field)) settled[field.key] = settleSecretConfig(secretValue(field.key))
    }
    config.value = settled
    originConfig.value = CommonUtils.deepClone(settled)
    saveMessage.value = { type: 'success', text: '消息渠道配置已保存。' }
  } catch (cause) {
    saveMessage.value = { type: 'error', text: errorMessage(cause, '消息渠道配置保存失败，请稍后重试。') }
  } finally {
    saveLoading.value = false
  }
}
const sendTest = async () => {
  if (!canTestChannel.value || testLoading.value || configChanged.value || saveLoading.value) return
  if (!testTo.value.trim()) {
    testMessage.value = { type: 'error', text: '请输入收件邮箱。' }
    return
  }
  testLoading.value = true
  testMessage.value = null
  try {
    const result = await client.sender.testChannel({ channel: slug, to: testTo.value.trim() })
    testMessage.value = {
      type: result.success ? 'success' : 'error',
      text: result.message || (result.success ? '测试消息已发送。' : '渠道返回测试失败。'),
    }
  } catch (cause) {
    testMessage.value = { type: 'error', text: errorMessage(cause, '测试发信失败，请检查渠道配置。') }
  } finally {
    testLoading.value = false
  }
}

watch(testDialog, (open) => {
  if (open) testMessage.value = null
})
onMounted(() => {
  void loadPage()
})
</script>
