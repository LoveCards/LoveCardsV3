<template>
  <div class="site-policy-panel">

    <AdminAsyncState
      :error="loadError"
      error-title="访问设置加载失败"
      :loading="loading"
      skeleton-type="heading, paragraph, text@5"
      @retry="loadConfig"
    >
      <v-alert v-if="!canUpdateConfig" class="mb-4" type="info" variant="tonal">
        当前账号只有查看权限，不能修改访问与验证设置。
      </v-alert>
      <v-alert v-if="message" class="mb-4" :type="message.type" variant="tonal">
        {{ message.text }}
      </v-alert>
      <v-alert v-if="dependencyWarning" class="mb-4" type="warning" variant="tonal">
        {{ dependencyWarning }}
        <NuxtLink to="/apps/settings/verification">前往验证服务</NuxtLink>
      </v-alert>

      <AdminConfigSection
        class="mb-4"
        :dirty="accessChanged"
        description="访客浏览和注册邮件验证是站点对用户的访问要求。开启注册邮件验证后，注册仅接受邮箱账号。"
        icon="mdi-account-key-outline"
        title="访问与注册"
      >
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="settings.visitorMode"
              :disabled="!canUpdateConfig || saving"
              density="comfortable"
              item-title="title"
              item-value="value"
              :items="switchItems"
              label="访客浏览"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="settings.emailVerify"
              :disabled="!canUpdateConfig || saving"
              density="comfortable"
              hint="开启后，新用户注册必须接收并输入邮件验证码"
              item-title="title"
              item-value="value"
              :items="switchItems"
              label="注册邮件验证"
              persistent-hint
              variant="outlined"
            />
          </v-col>
        </v-row>
      </AdminConfigSection>

      <AdminConfigSection
        :dirty="humanChanged"
        description="当前接入注册及卡片写入流程；登录尚未接入人机验证。"
        icon="mdi-shield-account-outline"
        title="人机验证"
      >
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="settings.humanVerify"
              :disabled="!canUpdateConfig || saving"
              density="comfortable"
              hint="还需要系统验证服务中的人机验证驱动可用"
              item-title="title"
              item-value="value"
              :items="switchItems"
              label="启用人机验证"
              persistent-hint
              variant="outlined"
            />
          </v-col>
        </v-row>
        <p class="mt-2 text-body-2 text-medium-emphasis">
          当前驱动：{{ service.humanDriver || '未配置' }} · {{ service.humanReady ? '可用' : '未就绪' }}
          · <NuxtLink to="/apps/settings/verification">配置验证服务</NuxtLink>
        </p>
      </AdminConfigSection>

      <AdminConfigSaveBar class="mt-4" :dirty="changed" label="访问与验证">
        <AdminActionGuard capability="config.update" scope="global">
          <v-btn
            color="primary"
            :disabled="!changed || Boolean(blockingDependency)"
            :loading="saving"
            prepend-icon="mdi-content-save-outline"
            variant="flat"
            @click="saveSettings"
          >
            保存访问设置
          </v-btn>
        </AdminActionGuard>
      </AdminConfigSaveBar>
    </AdminAsyncState>

    <AdminUnsavedChangesDialog
      :model-value="unsavedChangesDialog"
      @stay="continueEditing"
      @discard="discardChanges"
    />
  </div>
</template>

<script setup lang="ts">
import type { JsonObject } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminConfigSaveBar from '~/components/shared/admin/AdminConfigSaveBar.vue'
import AdminConfigSection from '~/components/shared/admin/AdminConfigSection.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'
import { readBoolean, readObject, readString } from '~/utils/json'

interface Settings {
  visitorMode: boolean
  emailVerify: boolean
  humanVerify: boolean
}
interface Service {
  codeEnabled: boolean
  codeChannel: string
  humanDriver: string
  humanReady: boolean
}
type Feedback = { type: 'success' | 'error'; text: string }

const client = useApi()
const userStore = useUserStore()
const props = withDefaults(defineProps<{ active?: boolean }>(), { active: true })
const emit = defineEmits<{ 'dirty-change': [dirty: boolean] }>()
const canUpdateConfig = computed(() => userStore.hasGrant('config.update', 'global'))
const loading = ref(false)
const loaded = ref(false)
const saving = ref(false)
const loadError = ref('')
const message = ref<Feedback | null>(null)
const switchItems = [{ title: '开启', value: true }, { title: '关闭', value: false }]
const settings = ref<Settings>({ visitorMode: false, emailVerify: false, humanVerify: false })
const origin = ref<Settings>(CommonUtils.deepClone(settings.value))
const service = ref<Service>({ codeEnabled: false, codeChannel: '', humanDriver: '', humanReady: false })
const accessChanged = computed(() => settings.value.visitorMode !== origin.value.visitorMode || settings.value.emailVerify !== origin.value.emailVerify)
const humanChanged = computed(() => settings.value.humanVerify !== origin.value.humanVerify)
const changed = computed(() => accessChanged.value || humanChanged.value)
watch(changed, value => emit('dirty-change', value), { immediate: true })
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(changed)
const dependencyWarning = computed(() => {
  if (settings.value.emailVerify && !service.value.codeEnabled) return '注册邮件验证需要先启用系统验证码服务。'
  if (settings.value.emailVerify && service.value.codeChannel !== 'smtp') return '注册邮件验证需要通过邮件渠道发送验证码。'
  if (settings.value.humanVerify && !service.value.humanReady) return '当前人机验证驱动未就绪，启用后仍无法完成验证。'
  return ''
})
const blockingDependency = computed(() => (
  (settings.value.emailVerify && !origin.value.emailVerify && (!service.value.codeEnabled || service.value.codeChannel !== 'smtp'))
  || (settings.value.humanVerify && !origin.value.humanVerify && !service.value.humanReady)
))
const errorMessage = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : fallback

async function loadConfig() {
  loading.value = true
  loadError.value = ''
  try {
    const [raw, captchaStatus] = await Promise.all([client.config.list(), client.captcha.config()])
    const core = readObject(raw.core)
    const user = readObject(raw.user)
    if (!raw.captcha) {
      loadError.value = '当前账号无权读取验证配置，请联系管理员授予 captcha.read 权限。'
      return
    }
    const captcha = readObject(raw.captcha)
    const geetest = readObject(captchaStatus.captcha_geetest_v4)
    const humanDriver = readString(captcha.default_captcha_driver, 'geetest_v4')
    const current: Settings = {
      visitorMode: readBoolean(core.visitor_mode),
      emailVerify: readBoolean(user.captcha),
      humanVerify: readBoolean(captcha.captcha_enabled, true),
    }
    settings.value = CommonUtils.deepClone(current)
    origin.value = CommonUtils.deepClone(current)
    service.value = {
      codeEnabled: readBoolean(captcha.code_enabled, true),
      codeChannel: readString(captcha.code_channel, 'smtp'),
      humanDriver,
      humanReady: humanDriver === 'gd_image' || (humanDriver === 'geetest_v4' && readBoolean(geetest.status)),
    }
    loaded.value = true
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载访问与验证设置，请稍后重试。')
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  if (!canUpdateConfig.value || !changed.value || blockingDependency.value || saving.value) return
  saving.value = true
  message.value = null
  const update: JsonObject = {}
  if (settings.value.visitorMode !== origin.value.visitorMode) update.core = { visitor_mode: settings.value.visitorMode }
  if (settings.value.emailVerify !== origin.value.emailVerify) update.user = { captcha: settings.value.emailVerify }
  if (humanChanged.value) update.captcha = { captcha_enabled: settings.value.humanVerify }
  try {
    await client.config.update(update)
    origin.value = CommonUtils.deepClone(settings.value)
    message.value = { type: 'success', text: '访问与验证设置已保存。' }
  } catch (cause) {
    message.value = { type: 'error', text: errorMessage(cause, '保存访问与验证设置失败，请稍后重试。') }
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  if (props.active) void loadConfig()
})
watch(() => props.active, (active) => {
  if (active && !loaded.value && !loading.value) void loadConfig()
})
</script>
