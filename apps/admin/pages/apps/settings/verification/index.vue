<template>
  <AdminPageContainer :max-width="960">
    <AdminPageHeader
      title="验证中心"
      description="查看哪些操作使用验证，并在这里管理验证要求、服务和驱动。"
      :breadcrumbs="[{ title: '系统' }]"
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
      <v-tabs :model-value="tab" color="primary" @update:model-value="selectTab">
        <v-tab prepend-icon="mdi-shield-check-outline" value="usages">使用场景</v-tab>
        <v-tab prepend-icon="mdi-account-check-outline" value="requirements">验证要求</v-tab>
        <v-tab prepend-icon="mdi-puzzle-outline" value="drivers">驱动管理</v-tab>
        <v-tab prepend-icon="mdi-tune-variant" value="settings">服务设置</v-tab>
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
          <v-window :model-value="tab" @update:model-value="selectTab">
            <v-window-item value="usages">
              <v-alert v-if="siteSettingsChanged || settingsChanged" class="mb-4" type="info" variant="tonal">
                以下状态以最近一次读取或保存的配置为准；未保存的修改尚未生效。
              </v-alert>
              <AdminConfigSection
                description="接入说明以当前版本为准；外部发送和第三方验证的连通性不由配置状态推断。"
                icon="mdi-shield-check-outline"
                title="验证使用场景"
              >
                <v-list bg-color="transparent" density="comfortable">
                  <v-list-item
                    title="新用户注册：邮件验证码"
                    :subtitle="registrationStatus('email')"
                  />
                  <v-list-item title="新用户注册：短信验证码" :subtitle="registrationStatus('phone')" />
                  <v-list-item
                    title="新用户注册：人机验证"
                    :subtitle="originSiteSettings.humanVerify ? humanStatus : '未要求'"
                  />
                  <v-list-item
                    title="卡片创建与编辑：人机验证"
                    :subtitle="originSiteSettings.humanVerify ? humanStatus : '未要求；只对写入请求检查'"
                  />
                  <v-list-item title="修改邮箱：邮件验证码" :subtitle="`始终要求；${mailStatus}`" />
                  <v-list-item title="登录：人机验证" subtitle="当前未接入，不能在这里开启" />
                </v-list>
              </AdminConfigSection>
            </v-window-item>

            <v-window-item value="requirements">
              <AdminConfigSection
                :dirty="siteSettingsChanged"
                description="选择新用户可用的注册方式，并分别设置邮箱和短信验证。"
                icon="mdi-account-check-outline"
                title="站点验证要求"
              >
                <v-alert v-if="siteMessage" class="mb-4" :type="siteMessage.type" variant="tonal">
                  {{ siteMessage.text }}
                </v-alert>
                <v-alert v-if="siteDependencyWarning" class="mb-4" type="warning" variant="tonal">
                  {{ siteDependencyWarning }}
                </v-alert>
                <v-row dense>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="siteSettings.mode"
                      :disabled="!canUpdateConfig || siteSaving"
                      :items="registrationModes"
                      label="注册方式"
                      variant="outlined"
                      density="comfortable"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="siteSettings.smsVerify"
                      :disabled="!canUpdateConfig || siteSaving || siteSettings.mode === 'email'"
                      :items="switchItems"
                      :hint="siteSettings.mode === 'email' ? '当前未开放手机号注册，保留原设置。' : undefined"
                      persistent-hint
                      label="注册短信验证"
                      variant="outlined"
                      density="comfortable"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="siteSettings.emailVerify"
                      :hint="siteSettings.mode === 'phone' ? '当前未开放邮箱注册，保留原设置。' : undefined"
                      persistent-hint
                      :disabled="!canUpdateConfig || siteSaving || siteSettings.mode === 'phone'"
                      density="comfortable"
                      item-title="title"
                      item-value="value"
                      :items="switchItems"
                      label="注册邮件验证"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="siteSettings.humanVerify"
                      :disabled="!canUpdateConfig || siteSaving"
                      density="comfortable"
                      item-title="title"
                      item-value="value"
                      :items="switchItems"
                      label="注册与卡片写入的人机验证"
                      variant="outlined"
                    />
                  </v-col>
                </v-row>
                <p class="text-body-2 text-medium-emphasis">
                  邮件和短信使用对应的站外发送渠道。修改邮箱仍要求邮件验证码。
                  <NuxtLink v-if="canReadSender" to="/apps/settings/messaging">查看站外发送</NuxtLink>
                </p>
              </AdminConfigSection>
              <AdminConfigSaveBar v-if="canUpdateConfig" class="mt-4" :dirty="siteSettingsChanged" label="站点验证要求">
                <AdminActionGuard capability="config.update" scope="global">
                  <v-btn
                    color="primary"
                    :disabled="!siteSettingsChanged || siteEnableBlocked || settingsChanged"
                    :loading="siteSaving"
                    prepend-icon="mdi-content-save-outline"
                    variant="flat"
                    @click="saveSiteSettings"
                  >
                    保存站点验证要求
                  </v-btn>
                </AdminActionGuard>
              </AdminConfigSaveBar>
            </v-window-item>

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
                          label="验证码服务"
                          variant="outlined"
                        />
                      </v-col>
                    </v-row>
                    <p class="mt-2 text-body-2 text-medium-emphasis">
                      关闭服务会使要求验证码的操作不可用。配置齐全后，可在站外发送中测试实际投递。
                      <NuxtLink v-if="canReadSender" to="/apps/settings/messaging">查看站外发送</NuxtLink>
                    </p>
                    <AdminConfigSaveBar v-if="canUpdateConfig" :dirty="settingsChanged" label="验证服务设置">
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
                    </AdminConfigSaveBar>
                  </v-form>
                </v-card-text>
              </v-card>
            </v-window-item>
          </v-window>
        </AdminAsyncState>
      </div>
    </div>
  </AdminPageContainer>
  <AdminUnsavedChangesDialog :model-value="unsavedChangesDialog" subject="验证设置" @stay="continueEditing" @discard="discardChanges" />
</template>

<script setup lang="ts">
import type { AuthPolicy, CaptchaDriver, JsonObject } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminConfigSaveBar from '~/components/shared/admin/AdminConfigSaveBar.vue'
import AdminConfigSection from '~/components/shared/admin/AdminConfigSection.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { useAdminUnsavedChanges } from '~/composables/useAdminUnsavedChanges'
import { useApi } from '~/lib/api'
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
const configSnapshot = useConfigSnapshot(client)
const router = useRouter()
const userStore = useUserStore()
const tab = ref('usages')
const loading = ref(false)
const loadError = ref('')
const scanning = ref(false)
const scanMessage = ref<Feedback | null>(null)
const actionMessage = ref<Feedback | null>(null)
const changingDefault = ref('')
const settingsSaving = ref(false)
const settingsMessage = ref<Feedback | null>(null)
const siteSaving = ref(false)
const siteMessage = ref<Feedback | null>(null)
const driverGroups = shallowRef<DriverGroup[]>([])
const settings = ref({ code_enabled: true })
const originSettings = ref(CommonUtils.deepClone(settings.value))
const siteSettings = ref({ mode: 'email_or_phone', emailVerify: false, smsVerify: false, humanVerify: false })
const registration = shallowRef<AuthPolicy['registration'] | null>(null)
const originSiteSettings = ref(CommonUtils.deepClone(siteSettings.value))
const humanReady = ref<boolean | null>(null)
const canUpdateConfig = computed(() => userStore.hasGrant('config.update', 'global'))
const canInstallCaptcha = computed(() => userStore.hasGrant('captcha.install', 'global'))
const canReadSender = computed(() => userStore.hasGrant('sender.read', 'global'))
const settingsChanged = computed(() => JSON.stringify(settings.value) !== JSON.stringify(originSettings.value))
const siteSettingsChanged = computed(() => JSON.stringify(siteSettings.value) !== JSON.stringify(originSiteSettings.value))
const anySettingsChanged = computed(() => settingsChanged.value || siteSettingsChanged.value)
const { unsavedChangesDialog, continueEditing, discardChanges, confirmUnsavedChanges } = useAdminUnsavedChanges(anySettingsChanged)
async function selectTab(value: unknown) {
  if (typeof value !== 'string' || value === tab.value) return
  if (!(await confirmUnsavedChanges())) return
  if (anySettingsChanged.value) {
    settings.value = CommonUtils.deepClone(originSettings.value)
    siteSettings.value = CommonUtils.deepClone(originSiteSettings.value)
  }
  tab.value = value
}
const mailStatus = computed(() => originSettings.value.code_enabled
  ? '验证码服务已开启，邮件发送状态请在站外发送中核对'
  : '验证码服务已关闭，邮件验证不可用')
const registrationStatus = (type: 'email' | 'phone') => {
  const policy = registration.value
  if (!policy) return '注册策略状态未知'
  if (!policy.account_types.includes(type)) return '未开放此注册方式'
  const required = type === 'email' ? policy.email_verification_required : policy.sms_verification_required
  const ready = type === 'email' ? policy.email_verification_available : policy.sms_verification_available
  if (!required) return '无需验证码'
  return ready ? '要求验证码；配置已就绪，外部投递未验证' : '要求验证码；发送渠道或验证码服务未就绪'
}

const humanStatus = computed(() => humanReady.value === null
  ? '驱动状态未知，不能确认是否可用'
  : humanReady.value ? '本地驱动已就绪；第三方连通未验证' : '驱动未就绪，当前无法完成验证')
const siteEnableBlocked = computed(() => siteSettings.value.humanVerify && !originSiteSettings.value.humanVerify && humanReady.value !== true)
const siteDependencyWarning = computed(() => {
  if (settingsChanged.value && siteSettingsChanged.value) return '请先保存服务设置，再保存站点验证要求。'
  if (siteEnableBlocked.value) return '人机验证驱动尚未确认就绪。'
  return ''
})
const switchItems = [{ title: '开启', value: true }, { title: '关闭', value: false }]
const registrationModes = [
  { title: '邮箱或手机号', value: 'email_or_phone' },
  { title: '仅邮箱', value: 'email' },
  { title: '仅手机号', value: 'phone' },
]
const readRegistration = (user: JsonObject) => {
  const emailVerify = readBoolean(user.captcha)
  return {
    mode: readString(user.registration_mode) || (emailVerify ? 'email' : 'email_or_phone'),
    emailVerify,
    smsVerify: readBoolean(user.registration_sms_verification),
  }
}
const refreshRegistration = async () => {
  registration.value = null
  try { registration.value = (await client.session.policy()).registration } catch { /* 保留未知状态 */ }
}
const groupTitle = (type: string) => type === 'code' ? '验证码' : type === 'captcha' ? '人机验证' : type
const errorMessage = configSnapshot.errorMessage

const loadPage = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const [drivers, rawConfig] = await Promise.all([client.captcha.drivers(), configSnapshot.list()])
    if (!rawConfig.captcha || !rawConfig.user) throw new Error('验证配置缺失')
    const captchaConfig = readObject(rawConfig.captcha)
    const groups: Record<string, CaptchaDriver[]> = {}
    for (const driver of drivers) {
      const type = driver.type || '其他'
      if (!groups[type]) groups[type] = []
      groups[type].push(driver)
    }
    driverGroups.value = Object.entries(groups).map(([type, groupDrivers]) => ({
      type,
      drivers: groupDrivers,
      defaultSlug: readString(captchaConfig[`default_${type}_driver`], groupDrivers[0]?.slug ?? ''),
    }))
    settings.value = {
      code_enabled: readBoolean(captchaConfig.code_enabled, true),
    }
    originSettings.value = CommonUtils.deepClone(settings.value)
    siteSettings.value = {
      ...readRegistration(readObject(rawConfig.user)),
      humanVerify: readBoolean(captchaConfig.captcha_enabled),
    }
    originSiteSettings.value = CommonUtils.deepClone(siteSettings.value)
    humanReady.value = null
    await refreshRegistration()
    try {
      const status = await client.captcha.config()
      humanReady.value = readBoolean(status.human_ready)
    } catch {
      // 状态未知时不根据配置推断驱动健康。
    }
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载验证驱动和配置，请稍后重试。')
  } finally {
    loading.value = false
  }
}

const setDefault = async (type: string, slug: string) => {
  if (!canUpdateConfig.value || changingDefault.value || !(await confirmUnsavedChanges())) return
  if (anySettingsChanged.value) {
    settings.value = CommonUtils.deepClone(originSettings.value)
    siteSettings.value = CommonUtils.deepClone(originSiteSettings.value)
  }
  changingDefault.value = `${type}:${slug}`
  actionMessage.value = null
  try {
    const committed = await configSnapshot.update({ captcha: { [`default_${type}_driver`]: slug } })
    const saved = readObject(committed.captcha)
    if (readString(saved[`default_${type}_driver`]) !== slug) throw new Error('默认驱动未保存')
    driverGroups.value = driverGroups.value.map(group => group.type === type ? { ...group, defaultSlug: slug } : group)
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
    const committed = await configSnapshot.update({
      captcha: {
        code_enabled: settings.value.code_enabled,
      },
    })
    const saved = readObject(committed.captcha)
    settings.value = { code_enabled: readBoolean(saved.code_enabled, true) }
    originSettings.value = CommonUtils.deepClone(settings.value)
    await refreshRegistration()
    settingsMessage.value = { type: 'success', text: '验证设置已保存。' }
  } catch (cause) {
    settingsMessage.value = { type: 'error', text: errorMessage(cause, '验证设置保存失败，请稍后重试。') }
  } finally {
    settingsSaving.value = false
  }
}
const saveSiteSettings = async () => {
  if (!canUpdateConfig.value || !siteSettingsChanged.value || siteEnableBlocked.value || settingsChanged.value || siteSaving.value) return
  siteSaving.value = true
  siteMessage.value = null
  const update: JsonObject = {}
  const { mode, emailVerify, smsVerify } = siteSettings.value
  const previous = originSiteSettings.value
  if (mode !== previous.mode || emailVerify !== previous.emailVerify || smsVerify !== previous.smsVerify) {
    update.user = { registration_mode: mode, captcha: emailVerify, registration_sms_verification: smsVerify }
  }
  if (siteSettings.value.humanVerify !== originSiteSettings.value.humanVerify) update.captcha = { captcha_enabled: siteSettings.value.humanVerify }
  try {
    const committed = await configSnapshot.update(update)
    if (committed.user) Object.assign(siteSettings.value, readRegistration(readObject(committed.user)))
    if (committed.captcha) siteSettings.value.humanVerify = readBoolean(readObject(committed.captcha).captcha_enabled)
    originSiteSettings.value = CommonUtils.deepClone(siteSettings.value)
    await refreshRegistration()
    siteMessage.value = { type: 'success', text: '站点验证要求已保存。' }
  } catch (cause) {
    siteMessage.value = { type: 'error', text: errorMessage(cause, '保存站点验证要求失败，请稍后重试。') }
  } finally {
    siteSaving.value = false
  }
}
const scanDrivers = async () => {
  if (!canInstallCaptcha.value || scanning.value || !(await confirmUnsavedChanges())) return
  if (anySettingsChanged.value) {
    settings.value = CommonUtils.deepClone(originSettings.value)
    siteSettings.value = CommonUtils.deepClone(originSiteSettings.value)
  }
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
