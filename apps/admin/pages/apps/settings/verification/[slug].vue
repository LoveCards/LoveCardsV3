<template>
  <AdminPageContainer>
    <AdminPageHeader
      :title="`${driverName} 配置`"
      description="配置验证码驱动。修改后应确认登录、注册和敏感操作仍能正常校验。"
      :breadcrumbs="[{ title: '站点设置' }, { title: '验证配置', to: '/apps/settings/verification' }, { title: driverName }]"
    />

    <v-row>
      <v-col cols="12" lg="8">
        <AdminConfigSection
          title="驱动参数"
          description="配置变更会影响登录、注册与敏感操作校验。"
          icon="mdi-shield-check-outline"
        >
          <template #actions><v-chip size="small" variant="outlined">{{ slug }}</v-chip></template>
            <AdminAsyncState
              :empty="fields.length === 0"
              empty-description="该验证驱动不需要额外参数，可直接返回驱动列表选择使用。"
              empty-icon="mdi-check-decagram-outline"
              empty-title="无需配置"
              :error="loadError"
              error-title="驱动配置加载失败"
              :loading="loading"
              skeleton-type="heading, text@5"
              @retry="loadPage"
            >
              <template #emptyAction>
                <v-btn color="primary" to="/apps/settings/verification" variant="tonal">返回驱动列表</v-btn>
              </template>

              <v-alert v-if="!canUpdateConfig" class="mb-4" type="info" variant="tonal">
                当前账号只有查看权限，不能修改驱动配置。
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
                <AdminConfigSaveBar :dirty="configChanged" label="驱动配置">
                  <AdminActionGuard
                    :all-of="[
                      { capability: 'config.update', scope: 'global' },
                      { capability: 'captcha.read', scope: 'global' },
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
                      保存驱动配置
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
import type { CaptchaDriver, FieldDefinition } from '@lovecards/sdk'
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
const group = `captcha_${slug}`
const config = ref<FormConfig>({})
const originConfig = ref<FormConfig>({})
const fields = shallowRef<DriverField[]>([])
const secretKeys = shallowRef(new Set<string>())
const driverName = ref(slug)
const loading = ref(false)
const loadError = ref('')
const saveLoading = ref(false)
const saveMessage = ref<Feedback | null>(null)
const canUpdateConfig = computed(() => {
  return userStore.hasGrant('config.update', 'global') && userStore.hasGrant('captcha.read', 'global')
})
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
    const [drivers, rawConfig] = await Promise.all([client.captcha.drivers(), client.config.list()])
    const driver = drivers.find((item: CaptchaDriver) => item.slug === slug)
    if (!driver) {
      loadError.value = '未找到该验证驱动，请返回列表重新扫描。'
      fields.value = []
      return
    }
    driverName.value = driver.name
    fields.value = (driver.fields ?? []).filter(isDriverField)
    const savedConfig = rawConfig[group]
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
    loadError.value = errorMessage(cause, '无法加载验证驱动配置，请稍后重试。')
  } finally {
    loading.value = false
  }
}
const save = async () => {
  if (!canUpdateConfig.value || !configChanged.value || saveLoading.value) return
  saveLoading.value = true
  saveMessage.value = null
  try {
    await client.config.update({ [group]: { ...config.value } })
    const settled = CommonUtils.deepClone(config.value)
    for (const field of fields.value) {
      if (isSecretField(field)) settled[field.key] = settleSecretConfig(secretValue(field.key))
    }
    config.value = settled
    originConfig.value = CommonUtils.deepClone(settled)
    saveMessage.value = { type: 'success', text: '验证驱动配置已保存。' }
  } catch (cause) {
    saveMessage.value = { type: 'error', text: errorMessage(cause, '验证驱动配置保存失败，请稍后重试。') }
  } finally {
    saveLoading.value = false
  }
}

onMounted(() => {
  void loadPage()
})
</script>
