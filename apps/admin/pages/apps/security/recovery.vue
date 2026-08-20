<template>
  <div>
    <AdminPageHeader title="账号恢复" description="只展示恢复挑战的状态与次数，不显示验证码、Token 或密钥。">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <v-alert class="mb-4" type="info" variant="tonal">恢复凭证不会在管理端返回；需要调查具体用户时进入用户安全详情。</v-alert>
    <AdminAsyncState :loading="loading" :error="error" :empty="!challenges.length" @retry="load">
      <v-sheet border rounded="lg">
        <v-data-table :headers="headers" :items="challenges" item-value="id">
          <template #item.user_id="{ item }"><AdminResourceLink v-if="item.user_id" resource-type="user" :resource-id="item.user_id" /></template>
          <template #item.state="{ item }"><AdminStatusChip :status="item.state" /></template>
        </v-data-table>
      </v-sheet>
      <v-pagination v-if="totalPages > 1" v-model="page" class="mt-4" :length="totalPages" @update:model-value="load" />
    </AdminAsyncState>
  </div>
</template>

<script setup lang="ts">
import type { RecoveryChallenge } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'

const client = useApi()
const challenges = ref<RecoveryChallenge[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const totalPages = ref(1)
const headers = [
  { title: '编号', key: 'id' }, { title: '用户', key: 'user_id' }, { title: '用途', key: 'purpose' },
  { title: '状态', key: 'state' }, { title: '尝试次数', key: 'attempts' }, { title: '到期时间', key: 'expires_at' }, { title: '创建时间', key: 'created_at' },
]
async function load() {
  loading.value = true; error.value = null
  try { const result = await client.users.recovery({ page: page.value, list_rows: 20 }); challenges.value = result.data; totalPages.value = result.pagination?.totalPages ?? 1 }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法加载恢复挑战' }
  finally { loading.value = false }
}
onMounted(load)
</script>
