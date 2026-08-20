<template>
  <div>
    <AdminPageHeader title="社区聚合" description="观察草稿、收藏与浏览历史的总体规模，不暴露成员私密内容。">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <v-alert class="mb-5" type="info" variant="tonal">隐私模式：仅聚合。后台不会返回草稿正文、个人收藏明细或浏览轨迹。</v-alert>
    <AdminAsyncState :loading="loading" :error="error" :empty="!insights" @retry="load">
      <v-row v-if="insights">
        <v-col cols="12" md="4"><v-sheet border rounded="lg" class="pa-5"><h2 class="text-h6">草稿</h2><dl class="insight-list"><dt>总计</dt><dd>{{ insights.drafts.total }}</dd><dt>活跃</dt><dd>{{ insights.drafts.active }}</dd></dl></v-sheet></v-col>
        <v-col cols="12" md="4"><v-sheet border rounded="lg" class="pa-5"><h2 class="text-h6">收藏</h2><dl class="insight-list"><dt>总计</dt><dd>{{ insights.library.total }}</dd><dt>参与用户</dt><dd>{{ insights.library.users }}</dd></dl></v-sheet></v-col>
        <v-col cols="12" md="4"><v-sheet border rounded="lg" class="pa-5"><h2 class="text-h6">浏览历史</h2><dl class="insight-list"><dt>总计</dt><dd>{{ insights.history.total }}</dd><dt>参与用户</dt><dd>{{ insights.history.users }}</dd></dl></v-sheet></v-col>
      </v-row>
    </AdminAsyncState>
  </div>
</template>

<script setup lang="ts">
import type { OperationsCommunityInsights } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import { useApi, isApiError } from '~/lib/api'
const client = useApi()
const insights = ref<OperationsCommunityInsights | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
async function load() {
  loading.value = true; error.value = null
  try { insights.value = await client.community.insights() }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法加载社区聚合数据' }
  finally { loading.value = false }
}
onMounted(load)
</script>

<style scoped>
.insight-list { display: grid; grid-template-columns: 1fr auto; gap: 12px; margin: 20px 0 0; }
.insight-list dd { margin: 0; font-size: 1.25rem; font-weight: 600; }
</style>
