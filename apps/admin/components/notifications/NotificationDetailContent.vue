<template>
  <AdminAsyncState :loading="loading" :error="error" :empty="!notification" @retry="load">
    <AdminDetailSection v-if="notification" title="通知记录" description="这里只读取通知元数据，不会将用户的通知标记为已读。">
      <v-row>
        <v-col cols="12" sm="6"><AdminDetailField label="通知类型"><span>{{ notification.type_label }}</span><span class="text-body-2 text-medium-emphasis ml-2">{{ notification.type }}</span></AdminDetailField></v-col>
        <v-col cols="12" sm="6"><AdminDetailField label="读取状态" :value="notification.read_at ? '已读' : '未读'" /></v-col>
        <v-col cols="12" sm="6"><AdminDetailField label="接收用户"><AdminResourceLink resource-type="user" :resource-id="notification.recipient_id" /></AdminDetailField></v-col>
        <v-col cols="12" sm="6"><AdminDetailField label="触发用户"><AdminResourceLink v-if="notification.actor_id" resource-type="user" :resource-id="notification.actor_id" /><span v-else>系统或未记录</span></AdminDetailField></v-col>
        <v-col cols="12" sm="6"><AdminDetailField label="关联对象"><AdminResourceLink v-if="notification.resource_type && notification.resource_id" :resource-type="notification.resource_type" :resource-id="notification.resource_id" /><span v-else>—</span></AdminDetailField></v-col>
        <v-col cols="12" sm="6"><AdminDetailField label="来源事件" value="当前记录未保存直接来源关联" /></v-col>
        <v-col cols="12" sm="6"><AdminDetailField label="生成时间"><AdminTableTime :value="notification.created_at" seconds /></AdminDetailField></v-col>
        <v-col cols="12" sm="6"><AdminDetailField label="已读时间"><AdminTableTime :value="notification.read_at" seconds /></AdminDetailField></v-col>
      </v-row>
    </AdminDetailSection>
  </AdminAsyncState>
</template>

<script setup lang="ts">
import type { AdminNotification } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDetailSection from '~/components/shared/admin/AdminDetailSection.vue'
import AdminDetailField from '~/components/shared/admin/AdminDetailField.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue'
import { useApi, isApiError } from '~/lib/api'

const props = defineProps<{ notificationId: number }>()
const client = useApi()
const notification = ref<AdminNotification | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
let latestRequest = 0
async function load() {
  const requestId = ++latestRequest
  notification.value = null
  error.value = null
  if (!Number.isSafeInteger(props.notificationId) || props.notificationId < 1) {
    loading.value = false
    error.value = '通知编号无效。'
    return
  }
  loading.value = true
  try {
    const result = await client.notifications.getManaged(props.notificationId)
    if (requestId === latestRequest) notification.value = result
  } catch (cause) {
    if (requestId === latestRequest) error.value = isApiError(cause)
      ? cause.status === 404 ? '通知记录不存在，可能已被清理。' : cause.message
      : '无法读取通知记录，请重试。'
  } finally { if (requestId === latestRequest) loading.value = false }
}
watch(() => props.notificationId, load, { immediate: true })
onBeforeUnmount(() => { latestRequest += 1 })
</script>
