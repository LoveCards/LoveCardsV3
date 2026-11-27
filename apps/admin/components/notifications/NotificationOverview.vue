<template>
  <v-card data-testid="notice-overview">
    <v-card-text class="pa-4 pa-sm-6">
      <AdminAsyncState :loading="loading && !stats" :error="!stats ? error : null" @retry="$emit('refresh')">
        <v-alert v-if="error && stats" type="error" variant="tonal" class="mb-5" role="alert">
          {{ error }} 以下保留上次读取的数据。
          <template #append><v-btn variant="text" @click="$emit('refresh')">重试</v-btn></template>
        </v-alert>
        <template v-if="stats">
          <dl class="notice-overview__counts">
            <div v-for="metric in metrics" :key="metric.label">
              <dt class="text-body-2 text-medium-emphasis">{{ metric.label }}</dt>
              <dd class="text-h5 font-weight-medium mt-2">{{ formatNumber(metric.value) }}</dd>
            </div>
          </dl>
          <div class="d-flex flex-wrap align-center ga-2 mt-5 text-caption text-medium-emphasis">
            <span>统计当前保存的通知；已读状态由用户操作更新。</span>
            <span v-if="updatedAt">{{ error ? '上次读取' : '读取于' }} {{ updatedAt }}</span>
          </div>
          <v-divider class="my-6" />
          <h2 class="text-subtitle-1 font-weight-bold mb-3">按类型统计</h2>
          <p v-if="!stats.types.length" class="text-body-2 text-medium-emphasis mb-0">尚未生成站内通知。</p>
          <v-list v-else class="pa-0" density="comfortable" aria-label="通知类型数量">
            <v-list-item v-for="type in stats.types" :key="type.type" class="px-0" :title="typeLabel(type.type)">
              <template #append><span class="text-body-2 font-weight-medium notice-overview__number">{{ formatNumber(type.count) }}</span></template>
            </v-list-item>
          </v-list>
          <p v-if="stats.types.length >= 30" class="text-caption text-medium-emphasis mt-3 mb-0">按数量显示前 30 种类型。</p>
        </template>
      </AdminAsyncState>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import type { OperationsNotificationStats } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'

const props = defineProps<{ stats: OperationsNotificationStats | null; loading: boolean; error: string | null; updatedAt: string }>()
defineEmits<{ refresh: [] }>()
const formatNumber = (value: number) => value.toLocaleString('zh-CN')
const metrics = computed(() => props.stats ? [
  { label: '通知总数', value: props.stats.total },
  { label: '已读通知', value: props.stats.total - props.stats.unread },
  { label: '未读通知', value: props.stats.unread },
  { label: '覆盖用户', value: props.stats.recipients },
] : [])
const typeNames: Record<string, string> = {
  'social.followed': '关注提醒', 'message': '私信提醒',
  'content.card_liked': '卡片获赞', 'content.comment_liked': '评论获赞',
  'library.saved': '收藏提醒', 'content.commented': '卡片评论',
  'content.replied': '评论回复', 'content.mentioned': '提及提醒',
  'system.notice': '系统通知', 'system.security': '安全通知', 'system.moderation': '审核通知',
}
const typeLabel = (type: string) => typeNames[type] ?? type
</script>

<style scoped>
.notice-overview__counts {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 24px;
}
.notice-overview__counts dd { margin-inline: 0; font-variant-numeric: tabular-nums; }
.notice-overview__number { font-variant-numeric: tabular-nums; }
@media (max-width: 599.98px) {
  .notice-overview__counts { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px 16px; }
}
</style>
