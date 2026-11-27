<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader title="站内通知" description="查看通知数量，向站内用户发布系统通知。" :breadcrumbs="[{ title: '系统' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="refreshing" :disabled="activeTab === 'send' && Boolean(composer?.refreshDisabled)" @click="refreshCurrent">刷新</v-btn></template>
    </AdminPageHeader>
    <v-tabs :model-value="activeTab" class="mb-5" color="primary" aria-label="站内通知视图" @update:model-value="changeTab">
      <v-tab v-if="canReadStats" value="overview">概览</v-tab>
      <v-tab v-if="canPublish" value="send">发送系统通知</v-tab>
    </v-tabs>
    <v-window :model-value="activeTab">
      <v-window-item v-if="canReadStats" value="overview">
        <NotificationOverview :stats="stats" :loading="statsLoading" :error="statsError" :updated-at="statsUpdatedAt" @refresh="loadStats" />
      </v-window-item>
      <v-window-item v-if="canPublish" value="send">
        <SystemNoticeComposer ref="composer" />
      </v-window-item>
    </v-window>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsNotificationStats } from '@lovecards/sdk'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import NotificationOverview from '~/components/notifications/NotificationOverview.vue'
import SystemNoticeComposer from '~/components/notifications/SystemNoticeComposer.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

const client = useApi()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const canReadStats = computed(() => userStore.hasGrant('notifications.read', 'global') || userStore.hasGrant('communications.read', 'global'))
const canPublish = computed(() => userStore.hasGrant('notifications.publish', 'global'))
const activeTab = computed(() => canPublish.value && (route.query.view === 'send' || !canReadStats.value) ? 'send' : 'overview')
const composer = ref<InstanceType<typeof SystemNoticeComposer> | null>(null)
const stats = ref<OperationsNotificationStats | null>(null)
const statsLoading = ref(false)
const statsError = ref<string | null>(null)
const statsUpdatedAt = ref('')
const refreshing = computed(() => activeTab.value === 'overview' ? statsLoading.value : Boolean(composer.value?.loading))
let statsRequest = 0
async function loadStats() {
  if (!canReadStats.value) return
  const request = ++statsRequest
  statsLoading.value = true
  statsError.value = null
  try {
    const result = await client.operations.notificationStats()
    if (request !== statsRequest) return
    stats.value = result
    statsUpdatedAt.value = new Date().toLocaleString('zh-CN')
  } catch (cause) {
    if (request === statsRequest) statsError.value = isApiError(cause) ? cause.message : '无法读取通知统计，请重试。'
  } finally { if (request === statsRequest) statsLoading.value = false }
}
function changeTab(value: unknown) {
  const view = value === 'send' && canPublish.value ? 'send' : undefined
  void router.replace({ query: { ...route.query, view } })
}
function refreshCurrent() {
  if (activeTab.value === 'overview') void loadStats()
  else void composer.value?.refresh()
}
watch(canReadStats, value => { if (value) void loadStats() }, { immediate: true })
onBeforeUnmount(() => { statsRequest++ })
</script>
