<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader :title="`卡片 #${route.params.id}`" description="阅读完整卡片与管理信息。" :breadcrumbs="breadcrumbs">
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" variant="text" @click="returnToAdminList(router, listPath)">返回列表</v-btn>
        <v-btn v-if="card && canEdit" color="primary" prepend-icon="mdi-pencil-outline" @click="openEdit">{{ editLabel }}</v-btn>
      </template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading" :error="error" :empty="!card" @retry="load">
      <template v-if="card">
        <AdminDetailSection title="卡片内容">
          <div class="d-flex flex-wrap align-center ga-3 mb-4">
            <AdminContentStatus :status="card.status" :pinned="Number(card.is_top) === 1" />
            <span class="text-body-2 text-medium-emphasis">{{ visibilityLabel }}</span>
          </div>
          <p class="text-body-1 text-pre-wrap mb-0">{{ card.content || '—' }}</p>
          <div v-if="card.media.length || card.cover" class="d-flex flex-wrap ga-3 mt-5">
            <v-img v-if="card.cover" :src="card.cover" alt="卡片封面" class="rounded" cover max-width="260" width="260" aspect-ratio="16/9" />
            <a v-for="media in card.media" :key="`${media.position}-${media.file_id ?? media.url}`" :href="media.url" target="_blank" rel="noopener noreferrer">{{ media.type === 'video' ? '查看视频' : '查看图片' }} {{ media.position + 1 }}</a>
          </div>
        </AdminDetailSection>
        <AdminDetailSection title="基本信息" class="mt-5">
          <v-row>
            <v-col cols="12" sm="6" md="4"><strong>卡片编号</strong><div>#{{ card.id }}</div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>作者</strong><div>{{ card.username || `用户 #${card.user_id}` }}</div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>作者编号</strong><div>#{{ card.user_id }}</div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>点赞数</strong><div>{{ card.goods }}</div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>评论数</strong><div>{{ card.comments }}</div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>浏览量</strong><div>{{ card.views }}</div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>发布时间</strong><div><AdminTableTime :value="card.created_at" /></div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>更新时间</strong><div><AdminTableTime :value="card.updated_at" /></div></v-col>
            <v-col v-if="card.post_ip" cols="12" sm="6" md="4"><strong>发布 IP</strong><div>{{ card.post_ip }}</div></v-col>
            <v-col cols="12"><strong>标签</strong><div>{{ card.tags.length ? card.tags.map(id => `#${id}`).join('、') : '—' }}</div></v-col>
            <v-col cols="12"><strong>话题</strong><div>{{ card.topics.length ? card.topics.map(topic => topic.name).join('、') : '—' }}</div></v-col>
          </v-row>
        </AdminDetailSection>
      </template>
    </AdminAsyncState>
    <EditCardDialog v-model:thisDialogState="editOpen" v-model:editCardData="editData" :getTableData="load" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { Card } from '@lovecards/sdk'
import AdminContentStatus from '~/components/content/AdminContentStatus.vue'
import EditCardDialog from '~/components/cards/EditCardDialog.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDetailSection from '~/components/shared/admin/AdminDetailSection.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue'
import { useApi, isApiError } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import { returnToAdminList, validateAdminListSource } from '~/utils/adminListSource'

const route = useRoute()
const router = useRouter()
const client = useApi()
const userStore = useUserStore()
const listPath = '/apps/content/cards'
const listSource = ref(listPath)
const breadcrumbs = computed(() => [{ title: '卡片', to: listSource.value }, { title: `#${route.params.id}`, disabled: true }])
const card = ref<Card | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const editOpen = ref(false)
const editData = ref<Card | null>(null)
const canEdit = computed(() => userStore.hasGrant('cards.update', 'all') || userStore.hasGrant('cards.approve', 'all') || userStore.hasGrant('cards.pin', 'all'))
const editLabel = computed(() => userStore.hasGrant('cards.update', 'all') ? '编辑卡片' : userStore.hasGrant('cards.approve', 'all') ? '审核卡片' : '调整置顶')
const visibilityLabel = computed(() => ({ public: '公开', followers: '仅关注者', private: '仅自己' })[card.value?.visibility ?? 'public'])

async function load() {
  loading.value = true
  error.value = null
  try {
    card.value = await client.cards.getManaged(Number(route.params.id))
  } catch (cause) {
    card.value = null
    error.value = isApiError(cause) ? cause.message : '无法加载卡片详情'
  } finally {
    loading.value = false
  }
}
function openEdit() {
  if (!card.value) return
  editData.value = { id: card.value.id } as Card
  editOpen.value = true
}
onMounted(() => {
  listSource.value = validateAdminListSource(window.history.state?.listSource, listPath) ?? listPath
  void load()
})
watch(() => route.params.id, () => { if (import.meta.client) void load() })
</script>
