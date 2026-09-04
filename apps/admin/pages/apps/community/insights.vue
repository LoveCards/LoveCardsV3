<template>
  <AdminPageContainer>
    <AdminPageHeader title="社区洞察" description="观察草稿、收藏与浏览历史的总体规模，不暴露成员私密内容。" :breadcrumbs="[{ title: '内容与社区' }, { title: '社区治理' }, { title: '社区洞察' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <v-alert class="mb-5" type="info" variant="tonal">隐私模式：仅聚合。后台不会返回草稿正文、个人收藏明细或浏览轨迹。</v-alert>
    <AdminAsyncState :loading="loading" :error="error" :empty="!insights" @retry="load">
      <v-sheet v-if="insights" border rounded="lg" overflow-hidden>
        <v-list lines="two">
          <v-list-item prepend-icon="mdi-file-document-edit-outline" title="草稿" :subtitle="`${insights.drafts.active} 条活跃草稿`">
            <template #append><span class="text-h6 font-weight-bold">{{ insights.drafts.total }}</span></template>
          </v-list-item>
          <v-divider />
          <v-list-item prepend-icon="mdi-bookmark-outline" title="收藏" :subtitle="`${insights.library.users} 位参与用户`">
            <template #append><span class="text-h6 font-weight-bold">{{ insights.library.total }}</span></template>
          </v-list-item>
          <v-divider />
          <v-list-item prepend-icon="mdi-history" title="浏览历史" :subtitle="`${insights.history.users} 位参与用户`">
            <template #append><span class="text-h6 font-weight-bold">{{ insights.history.total }}</span></template>
          </v-list-item>
        </v-list>
      </v-sheet>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsCommunityInsights } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
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
