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
        当前账号只有查看权限，不能修改访问设置。
      </v-alert>
      <v-alert v-if="message" class="mb-4" :type="message.type" variant="tonal">
        {{ message.text }}
      </v-alert>

      <AdminConfigSection
        class="mb-4"
        :dirty="changed"
        description="访客浏览决定未登录用户能否访问公开内容。"
        icon="mdi-account-key-outline"
        title="访问设置"
      >
        <v-select
          v-model="visitorMode"
          :disabled="!canUpdateConfig || saving"
          density="comfortable"
          item-title="title"
          item-value="value"
          :items="switchItems"
          label="访客浏览"
          variant="outlined"
        />
      </AdminConfigSection>
      <AdminConfigSaveBar v-if="canUpdateConfig" class="mb-5" :dirty="changed" label="访问设置">
        <AdminActionGuard capability="config.update" scope="global">
          <v-btn
            color="primary"
            :disabled="!changed"
            :loading="saving"
            prepend-icon="mdi-content-save-outline"
            variant="flat"
            @click="saveSettings"
          >
            保存访问设置
          </v-btn>
        </AdminActionGuard>
      </AdminConfigSaveBar>

      <AdminConfigSection
        description="验证要求在验证中心统一修改；这里仅显示当前要求及依赖。"
        icon="mdi-shield-account-outline"
        title="验证要求"
      >
        <v-alert v-if="!canReadCaptcha" type="info" variant="tonal">
          当前账号无权查看验证设置。
        </v-alert>
        <template v-else>
          <v-alert v-if="summaryError" class="mb-4" type="warning" variant="tonal">
            {{ summaryError }}
          </v-alert>
          <v-list v-if="summary" bg-color="transparent" density="compact">
            <v-list-item
              title="注册邮件验证"
              :subtitle="summary.registration.email_verification_required ? '已要求；新用户注册仅接受邮箱账号' : '未要求'"
            />
            <v-list-item
              title="注册与卡片写入的人机验证"
              :subtitle="summary.human_verification.enabled ? '已要求' : '未要求；登录尚未接入人机验证'"
            />
            <v-list-item
              title="修改邮箱"
              subtitle="始终要求邮件验证码，不能在站点策略中关闭"
            />
            <v-list-item
              title="验证码服务与邮件渠道"
              :subtitle="emailVerificationStatus(summary)"
            />
            <v-list-item
              title="人机验证驱动"
              :subtitle="summary.human_verification.available ? '本地配置已就绪；未检测第三方连通' : '本地配置未就绪'"
            />
          </v-list>
          <p v-if="!summary" class="text-body-2 text-medium-emphasis">验证设置尚未加载，不能推断当前要求。</p>
          <NuxtLink to="/apps/settings/verification?tab=requirements">查看验证要求</NuxtLink>
        </template>
      </AdminConfigSection>

    </AdminAsyncState>

    <AdminUnsavedChangesDialog
      :model-value="unsavedChangesDialog"
      @stay="continueEditing"
      @discard="discardChanges"
    />
  </div>
</template>

<script setup lang="ts">
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminConfigSaveBar from '~/components/shared/admin/AdminConfigSaveBar.vue'
import AdminConfigSection from '~/components/shared/admin/AdminConfigSection.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import type { AuthPolicy } from '@lovecards/sdk'
import { emailVerificationStatus } from '~/lib/verification-status'
import { readBoolean, readObject } from '~/utils/json'
type Feedback = { type: 'success' | 'error'; text: string }

const client = useApi()
const configSnapshot = useConfigSnapshot(client)
const userStore = useUserStore()
const props = withDefaults(defineProps<{ active?: boolean }>(), { active: true })
const emit = defineEmits<{ 'dirty-change': [dirty: boolean] }>()
const canUpdateConfig = computed(() => userStore.hasGrant('config.update', 'global'))
const canReadCaptcha = computed(() => userStore.hasGrant('captcha.read', 'global'))
const loading = ref(false)
const saving = ref(false)
const loadError = ref('')
const summaryError = ref('')
const message = ref<Feedback | null>(null)
const switchItems = [{ title: '开启', value: true }, { title: '关闭', value: false }]
const visitorMode = ref(false)
const originalVisitorMode = ref(false)
const summary = ref<AuthPolicy | null>(null)
const changed = computed(() => visitorMode.value !== originalVisitorMode.value)
watch(changed, value => emit('dirty-change', value), { immediate: true })
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(changed)
const errorMessage = configSnapshot.errorMessage

async function loadConfig() {
  loading.value = true
  loadError.value = ''
  summaryError.value = ''
  summary.value = null
  try {
    const raw = await configSnapshot.list()
    if (!raw.core) throw new Error('访问配置缺失')
    visitorMode.value = readBoolean(readObject(raw.core).visitor_mode)
    originalVisitorMode.value = visitorMode.value

    if (canReadCaptcha.value) {
      try {
        const policy = await client.session.policy()
        if (!policy?.registration || !policy.human_verification) throw new Error('验证状态缺失')
        summary.value = policy
      } catch (cause) {
        summaryError.value = errorMessage(cause, '无法读取当前验证要求。')
      }
    }
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载访问设置，请稍后重试。')
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  if (!canUpdateConfig.value || !changed.value || saving.value) return
  saving.value = true
  message.value = null
  try {
    const committed = await configSnapshot.update({ core: { visitor_mode: visitorMode.value } })
    visitorMode.value = readBoolean(readObject(committed.core).visitor_mode)
    originalVisitorMode.value = visitorMode.value
    message.value = { type: 'success', text: '访问设置已保存。' }
  } catch (cause) {
    message.value = { type: 'error', text: errorMessage(cause, '保存访问设置失败，请稍后重试。') }
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  if (props.active) void loadConfig()
})
watch(() => props.active, active => {
  if (active && !loading.value && !changed.value) void loadConfig()
})
</script>
