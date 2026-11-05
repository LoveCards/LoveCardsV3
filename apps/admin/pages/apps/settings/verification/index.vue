<template>
  <AdminPageContainer>
    <AdminPageHeader
      title="验证配置"
      description="管理登录与敏感操作使用的验证器。扫描、切换和保存均由服务端再次校验权限。"
      :breadcrumbs="[{ title: '系统设置' }]"
    >
      <template #actions>
        <AdminActionGuard capability="captcha.install" scope="global">
          <v-btn
            color="primary"
            :loading="scanning"
            prepend-icon="mdi-refresh"
            variant="outlined"
            @click="scanDrivers"
          >
            扫描驱动
          </v-btn>
        </AdminActionGuard>
      </template>
    </AdminPageHeader>

    <v-alert v-if="scanMessage" class="mb-4" :type="scanMessage.type" variant="tonal">
      {{ scanMessage.text }}
    </v-alert>
    <v-alert v-if="actionMessage" class="mb-4" :type="actionMessage.type" variant="tonal">
      {{ actionMessage.text }}
    </v-alert>

    <div>
      <v-tabs v-model="tab" color="primary">
        <v-tab prepend-icon="mdi-puzzle-outline" value="drivers">驱动管理</v-tab>
        <v-tab prepend-icon="mdi-tune-variant" value="settings">基础设置</v-tab>
      </v-tabs>
      <v-divider />

      <div class="pt-6">
        <AdminAsyncState
          :error="loadError"
          error-title="验证配置加载失败"
          :loading="loading"
          skeleton-type="heading, article@3"
          @retry="loadPage"
        >
          <v-window v-model="tab">
            <v-window-item value="drivers">
              <AdminAsyncState
                :empty="driverGroups.length === 0"
                empty-description="扫描驱动后，可在这里选择默认验证器并进入详细配置。"
                empty-icon="mdi-puzzle-remove-outline"
                empty-title="尚未发现验证驱动"
                :retryable="false"
              >
                <section v-for="group in driverGroups" :key="group.type" class="mb-8">
                  <div class="mb-3 d-flex align-center justify-space-between ga-3">
                    <h2 class="text-subtitle-1 font-weight-bold">{{ groupTitle(group.type) }}</h2>
                    <v-chip size="small" variant="outlined">{{ group.drivers.length }} 个驱动</v-chip>
                  </div>
                  <v-row>
                    <v-col v-for="driver in group.drivers" :key="driver.slug" cols="12" md="6" lg="4">
                      <v-card class="d-flex h-100 flex-column">
                        <v-card-item>
                          <template #prepend>
                            <v-avatar color="primary" variant="tonal">
                              <v-icon>{{ driver.icon || 'mdi-shield-check-outline' }}</v-icon>
                            </v-avatar>
                          </template>
                          <v-card-title>{{ driver.name }}</v-card-title>
                          <v-card-subtitle>{{ driver.slug }}</v-card-subtitle>
                          <template #append>
                            <v-chip v-if="driver.slug === group.defaultSlug" color="primary" size="small">默认</v-chip>
                          </template>
                        </v-card-item>
                        <v-card-text class="flex-grow-1 text-body-2 text-medium-emphasis">
                          {{ driver.fields?.length ?? 0 }} 个配置项
                        </v-card-text>
                        <v-divider />
                        <v-card-actions>
                          <AdminActionGuard capability="config.update" scope="global">
                            <v-btn
                              color="primary"
                              :disabled="driver.slug === group.defaultSlug"
                              :loading="changingDefault === `${group.type}:${driver.slug}`"
                              variant="text"
                              @click="setDefault(group.type, driver.slug)"
                            >
                              设为默认
                            </v-btn>
                          </AdminActionGuard>
                          <v-spacer />
                          <v-btn
                            color="primary"
                            :disabled="!driver.fields?.length"
                            append-icon="mdi-arrow-right"
                            variant="text"
                            @click="openConfig(driver.slug)"
                          >
                            配置
                          </v-btn>
                        </v-card-actions>
                      </v-card>
                    </v-col>
                  </v-row>
                </section>
              </AdminAsyncState>
            </v-window-item>

            <v-window-item value="settings">
              <v-card>
                <v-card-text>
                  <v-alert v-if="!canUpdateConfig" class="mb-4" type="info" variant="tonal">
                    当前账号只有查看权限，不能修改验证设置。
                  </v-alert>
                  <v-alert v-if="settingsMessage" class="mb-4" :type="settingsMessage.type" variant="tonal">
                    {{ settingsMessage.text }}
                  </v-alert>
                  <v-form @submit.prevent="saveSettings">
                    <v-row dense>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="settings.code_enabled"
                          :disabled="!canUpdateConfig || settingsSaving"
                          density="comfortable"
                          item-title="title"
                          item-value="value"
                          :items="switchItems"
                          label="验证码功能"
                          variant="outlined"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="settings.captcha_enabled"
                          :disabled="!canUpdateConfig || settingsSaving"
                          density="comfortable"
                          item-title="title"
                          item-value="value"
                          :items="switchItems"
                          label="人机验证功能"
                          variant="outlined"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="settings.code_channel"
                          :disabled="!canUpdateConfig || settingsSaving"
                          density="comfortable"
                          item-title="title"
                          item-value="value"
                          :items="channelItems"
                          label="验证码发送渠道"
                          variant="outlined"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="settings.user_captcha"
                          :disabled="!canUpdateConfig || settingsSaving"
                          density="comfortable"
                          item-title="title"
                          item-value="value"
                          :items="switchItems"
                          label="注册验证码"
                          variant="outlined"
                        />
                      </v-col>
                    </v-row>
                    <v-divider class="mb-4" />
                    <div class="d-flex justify-end">
                      <AdminActionGuard capability="config.update" scope="global">
                        <v-btn
                          color="primary"
                          :disabled="!settingsChanged"
                          :loading="settingsSaving"
                          prepend-icon="mdi-content-save-outline"
                          type="submit"
                          variant="flat"
                        >
                          保存验证设置
                        </v-btn>
                      </AdminActionGuard>
                    </div>
                  </v-form>
                </v-card-text>
              </v-card>
            </v-window-item>
          </v-window>
        </AdminAsyncState>
      </div>
    </div>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { CaptchaDriver } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'
import { readBoolean, readObject, readString } from '~/utils/json'

interface DriverGroup {
  type: string
  drivers: CaptchaDriver[]
  defaultSlug: string
}
type Feedback = { type: 'success' | 'error'; text: string }

const client = useApi()
const router = useRouter()
const userStore = useUserStore()
const tab = ref('drivers')
const loading = ref(false)
const loadError = ref('')
const scanning = ref(false)
const scanMessage = ref<Feedback | null>(null)
const actionMessage = ref<Feedback | null>(null)
const changingDefault = ref('')
const settingsSaving = ref(false)
const settingsMessage = ref<Feedback | null>(null)
const driverGroups = shallowRef<DriverGroup[]>([])
const settings = ref({ code_enabled: true, captcha_enabled: true, code_channel: 'smtp', user_captcha: false })
const originSettings = ref(CommonUtils.deepClone(settings.value))
const canUpdateConfig = computed(() => userStore.hasGrant('config.update', 'global'))
const canInstallCaptcha = computed(() => userStore.hasGrant('captcha.install', 'global'))
const settingsChanged = computed(() => JSON.stringify(settings.value) !== JSON.stringify(originSettings.value))
const switchItems = [{ title: '开启', value: true }, { title: '关闭', value: false }]
const channelItems = [{ title: '邮箱（SMTP）', value: 'smtp' }, { title: '短信（SMS）', value: 'sms' }]
const groupTitle = (type: string) => type === 'code' ? '验证码' : type === 'captcha' ? '人机验证' : type
const errorMessage = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : fallback

const loadPage = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const [drivers, rawConfig] = await Promise.all([client.captcha.drivers(), client.config.list()])
    const captchaConfig = readObject(rawConfig.captcha)
    const userConfig = readObject(rawConfig.user)
    const groups: Record<string, CaptchaDriver[]> = {}
    for (const driver of drivers) {
      const type = driver.type || '其他'
      if (!groups[type]) groups[type] = []
      groups[type].push(driver)
    }
    driverGroups.value = Object.entries(groups).map(([type, groupDrivers]) => ({
      type,
      drivers: groupDrivers,
      defaultSlug: readString(captchaConfig[`default_${type}`], groupDrivers[0]?.slug ?? ''),
    }))
    settings.value = {
      code_enabled: readBoolean(captchaConfig.code_enabled, true),
      captcha_enabled: readBoolean(captchaConfig.captcha_enabled, true),
      code_channel: readString(captchaConfig.code_channel, 'smtp'),
      user_captcha: readBoolean(userConfig.captcha, false),
    }
    originSettings.value = CommonUtils.deepClone(settings.value)
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载验证驱动和配置，请稍后重试。')
  } finally {
    loading.value = false
  }
}

const setDefault = async (type: string, slug: string) => {
  if (!canUpdateConfig.value || changingDefault.value) return
  changingDefault.value = `${type}:${slug}`
  actionMessage.value = null
  try {
    await client.config.update({ captcha: { [`default_${type}`]: slug } })
    const group = driverGroups.value.find(item => item.type === type)
    if (group) group.defaultSlug = slug
    actionMessage.value = { type: 'success', text: `已将 ${slug} 设为${groupTitle(type)}默认驱动。` }
  } catch (cause) {
    actionMessage.value = { type: 'error', text: errorMessage(cause, '默认驱动切换失败，请稍后重试。') }
  } finally {
    changingDefault.value = ''
  }
}
const openConfig = (slug: string) => {
  void router.push(`/apps/settings/verification/${encodeURIComponent(slug)}`)
}
const saveSettings = async () => {
  if (!canUpdateConfig.value || !settingsChanged.value || settingsSaving.value) return
  settingsSaving.value = true
  settingsMessage.value = null
  try {
    await client.config.update({
      captcha: {
        code_enabled: settings.value.code_enabled,
        captcha_enabled: settings.value.captcha_enabled,
        code_channel: settings.value.code_channel,
      },
      user: { captcha: settings.value.user_captcha },
    })
    originSettings.value = CommonUtils.deepClone(settings.value)
    settingsMessage.value = { type: 'success', text: '验证设置已保存。' }
  } catch (cause) {
    settingsMessage.value = { type: 'error', text: errorMessage(cause, '验证设置保存失败，请稍后重试。') }
  } finally {
    settingsSaving.value = false
  }
}
const scanDrivers = async () => {
  if (!canInstallCaptcha.value || scanning.value) return
  scanning.value = true
  scanMessage.value = null
  try {
    await client.captcha.install()
    await loadPage()
    scanMessage.value = { type: 'success', text: '验证驱动扫描完成，列表已刷新。' }
  } catch (cause) {
    scanMessage.value = { type: 'error', text: errorMessage(cause, '验证驱动扫描失败，请稍后重试。') }
  } finally {
    scanning.value = false
  }
}

onMounted(() => {
  void loadPage()
})
</script>
