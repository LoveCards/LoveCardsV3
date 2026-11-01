<template>
  <AdminPageContainer>
    <AdminPageHeader
      :title="configTheme.id ? `主题配置 · ${configTheme.id}` : '主题配置'"
      description="按当前主题提供的配置定义调整外观。保存后将影响所有访问该主题的用户。"
      :breadcrumbs="[{ title: '站点设置' }, { title: '主题外观', to: '/apps/settings/appearance' }, { title: requestedSlug }]"
    />

    <AdminConfigSection title="主题参数" description="保存后对当前主题生效；校验失败时保留本次输入。" icon="mdi-palette-outline">
      <template v-if="configTheme.id" #actions>
        <v-chip size="small" variant="outlined">{{ configTheme.id }}</v-chip>
        <v-chip size="small" variant="outlined">发布 {{ configTheme.release }}</v-chip>
      </template>
        <AdminAsyncState
          :empty="noActiveTheme || fieldEntries.length === 0"
          :empty-description="noActiveTheme ? '请先从主题列表启用一个主题。' : '当前主题没有提供可编辑的配置项。'"
          empty-icon="mdi-palette-outline"
          :empty-title="noActiveTheme ? '尚未启用主题' : '无需配置'"
          :error="loadError"
          error-title="主题配置加载失败"
          :loading="loading"
          skeleton-type="heading, text@5"
          @retry="loadConfig"
        >
          <template #emptyAction>
            <v-btn color="primary" prepend-icon="mdi-view-grid-outline" to="/apps/settings/appearance" variant="tonal">
              返回主题列表
            </v-btn>
          </template>

          <v-alert v-if="saveMessage" class="mb-4" :type="saveMessage.type" variant="tonal">
            {{ saveMessage.text }}
          </v-alert>
          <v-alert v-if="!canUpdateTheme" class="mb-4" type="info" variant="tonal">
            当前账号只有查看权限，不能修改主题配置。
          </v-alert>

          <v-form @submit.prevent="saveConfig">
            <v-row dense>
              <v-col v-for="[key, schema] in fieldEntries" :key="key" cols="12" md="6">
                <v-select
                  v-if="schema.options?.length"
                  :disabled="!canUpdateTheme || saveLoading"
                  density="comfortable"
                  item-title="label"
                  item-value="value"
                  :items="schema.options"
                  :label="fieldLabel(key, schema)"
                  :model-value="configValues[key]"
                  variant="outlined"
                  @update:model-value="setConfigValue(key, $event)"
                />
                <v-switch
                  v-else-if="isBooleanField(schema)"
                  color="primary"
                  :disabled="!canUpdateTheme || saveLoading"
                  hide-details
                  inset
                  :label="fieldLabel(key, schema)"
                  :model-value="Boolean(configValues[key])"
                  @update:model-value="setConfigValue(key, $event)"
                />
                <v-number-input
                  v-else-if="isNumberField(schema)"
                  control-variant="split"
                  :disabled="!canUpdateTheme || saveLoading"
                  density="comfortable"
                  :label="fieldLabel(key, schema)"
                  :model-value="numberValue(key)"
                  variant="outlined"
                  @update:model-value="setConfigValue(key, $event)"
                />
                <v-text-field
                  v-else
                  :disabled="!canUpdateTheme || saveLoading"
                  density="comfortable"
                  :label="fieldLabel(key, schema)"
                  :model-value="textValue(key)"
                  :type="inputType(schema)"
                  variant="outlined"
                  @update:model-value="setConfigValue(key, $event)"
                />
              </v-col>
            </v-row>
            <v-divider class="mb-4" />
            <AdminConfigSaveBar :dirty="configChanged" label="主题配置">
              <AdminActionGuard capability="theme.update" scope="global">
                <v-btn
                  color="primary"
                  :disabled="!configChanged"
                  :loading="saveLoading"
                  prepend-icon="mdi-content-save-outline"
                  type="submit"
                  variant="flat"
                >
                  保存主题配置
                </v-btn>
              </AdminActionGuard>
            </AdminConfigSaveBar>
          </v-form>
        </AdminAsyncState>
    </AdminConfigSection>
    <AdminUnsavedChangesDialog
      :model-value="unsavedChangesDialog"
      @stay="continueEditing"
      @discard="discardChanges"
    />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { FieldDefinition, JsonObject, JsonValue } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'

type SaveMessage = { type: 'success' | 'error'; text: string }
type ExtendedFieldDefinition = FieldDefinition & { format?: string }

const route = useRoute()
const client = useApi()
const userStore = useUserStore()
const loading = ref(false)
const saveLoading = ref(false)
const loadError = ref('')
const saveMessage = ref<SaveMessage | null>(null)
const noActiveTheme = ref(false)
const configSchema = ref<Record<string, FieldDefinition>>({})
const configValues = ref<JsonObject>({})
const originValues = ref<JsonObject>({})
const configTheme = ref({ id: '', release: '' })
const requestedSlug = computed(() => String(route.params.slug || 'current'))
const canUpdateTheme = computed(() => userStore.hasGrant('theme.update', 'global'))
const fieldEntries = computed(() => Object.entries(configSchema.value))
const configChanged = computed(() => JSON.stringify(configValues.value) !== JSON.stringify(originValues.value))
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(configChanged)

const fieldLabel = (key: string, schema: FieldDefinition) => schema.label || schema.title || key
const isBooleanField = (schema: FieldDefinition) => ['boolean', 'checkbox', 'switch'].includes(schema.type)
const isNumberField = (schema: FieldDefinition) => ['number', 'integer'].includes(schema.type)
const inputType = (schema: FieldDefinition) => {
  if (schema.type === 'password') return 'password'
  if ((schema as ExtendedFieldDefinition).format === 'color') return 'color'
  return 'text'
}
const numberValue = (key: string) => {
  const value = configValues.value[key]
  return typeof value === 'number' ? value : undefined
}
const textValue = (key: string) => {
  const value = configValues.value[key]
  return typeof value === 'string' || typeof value === 'number' ? String(value) : ''
}
const setConfigValue = (key: string, value: unknown) => {
  configValues.value[key] = value as JsonValue
}
const errorMessage = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : fallback

const loadConfig = async () => {
  loading.value = true
  loadError.value = ''
  saveMessage.value = null
  noActiveTheme.value = false
  try {
    const result = await client.theme.config()
    if (requestedSlug.value !== 'current' && requestedSlug.value !== result.id) {
      configSchema.value = {}
      configValues.value = {}
      originValues.value = {}
      configTheme.value = { id: '', release: '' }
      loadError.value = `主题 ${requestedSlug.value} 当前未启用，无法读取其配置。`
      return
    }
    configSchema.value = result.config_schema || {}
    const values = CommonUtils.deepClone(result.config_values || {})
    for (const [key, schema] of Object.entries(configSchema.value)) {
      if (values[key] === undefined && schema.default !== undefined) values[key] = schema.default
    }
    configValues.value = CommonUtils.deepClone(values)
    originValues.value = CommonUtils.deepClone(values)
    configTheme.value = { id: result.id, release: result.release }
  } catch (cause) {
    if (isApiError(cause) && cause.status === 404) {
      noActiveTheme.value = true
      configSchema.value = {}
      configValues.value = {}
      originValues.value = {}
      configTheme.value = { id: '', release: '' }
    } else {
      loadError.value = errorMessage(cause, '无法加载主题配置，请稍后重试。')
    }
  } finally {
    loading.value = false
  }
}
const saveConfig = async () => {
  if (!canUpdateTheme.value || !configChanged.value || saveLoading.value) return
  saveLoading.value = true
  saveMessage.value = null
  try {
    await client.theme.updateConfig({
      id: configTheme.value.id,
      release: configTheme.value.release,
      values: configValues.value,
    })
    originValues.value = CommonUtils.deepClone(configValues.value)
    saveMessage.value = { type: 'success', text: '主题配置已保存。' }
  } catch (cause) {
    saveMessage.value = { type: 'error', text: errorMessage(cause, '主题配置保存失败，请稍后重试。') }
  } finally {
    saveLoading.value = false
  }
}

onMounted(() => {
  void loadConfig()
})

watch(() => route.params.slug, (slug, previousSlug) => {
  if (previousSlug !== undefined && slug !== previousSlug) void loadConfig()
})
</script>
