<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader :title="`评论 #${route.params.id}`" description="阅读完整评论与管理信息。" :breadcrumbs="breadcrumbs">
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" variant="text" @click="returnToAdminList(router, listPath)">返回列表</v-btn>
        <v-btn v-if="comment && canEdit" color="primary" prepend-icon="mdi-pencil-outline" @click="openEdit">{{ editLabel }}</v-btn>
      </template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading" :error="error" :empty="!comment" @retry="load">
      <template v-if="comment">
        <AdminDetailSection title="评论内容">
          <div class="d-flex flex-wrap align-center ga-3 mb-4">
            <AdminContentStatus :status="comment.status" :pinned="comment.is_pinned" />
            <span v-if="comment.deleted" class="text-body-2 text-medium-emphasis">已删除</span>
          </div>
          <p class="text-body-1 text-pre-wrap mb-0">{{ comment.content || '—' }}</p>
        </AdminDetailSection>
        <AdminDetailSection title="基本信息" class="mt-5">
          <v-row>
            <v-col cols="12" sm="6" md="4"><strong>评论编号</strong><div>#{{ comment.id }}</div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>作者</strong><div>{{ comment.username || `用户 #${comment.user_id}` }}</div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>作者编号</strong><div>#{{ comment.user_id }}</div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>所属卡片</strong><div><AdminResourceLink resource-type="card" :resource-id="comment.card_id" /></div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>父评论</strong><div v-if="comment.parent_id"><AdminResourceLink resource-type="comment" :resource-id="comment.parent_id" /></div><div v-else>—</div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>点赞数</strong><div>{{ comment.goods }}</div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>发布时间</strong><div><AdminTableTime :value="comment.created_at" /></div></v-col>
            <v-col cols="12" sm="6" md="4"><strong>更新时间</strong><div><AdminTableTime :value="comment.updated_at" /></div></v-col>
            <v-col v-if="comment.post_ip" cols="12" sm="6" md="4"><strong>发布 IP</strong><div>{{ comment.post_ip }}</div></v-col>
          </v-row>
        </AdminDetailSection>
      </template>
    </AdminAsyncState>
    <EditCommentDialog v-model:thisDialogState="editOpen" v-model:CommentData="editData" :getTableData="load" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { Comment } from '@lovecards/sdk'
import AdminContentStatus from '~/components/content/AdminContentStatus.vue'
import EditCommentDialog from '~/components/comments/EditCommentDialog.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDetailSection from '~/components/shared/admin/AdminDetailSection.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import { useApi, isApiError } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import { returnToAdminList, validateAdminListSource } from '~/utils/adminListSource'

const route = useRoute()
const router = useRouter()
const client = useApi()
const userStore = useUserStore()
const listPath = '/apps/content/comments'
const listSource = ref(listPath)
const breadcrumbs = computed(() => [{ title: '评论', to: listSource.value }, { title: `#${route.params.id}`, disabled: true }])
const comment = ref<Comment | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const editOpen = ref(false)
const editData = ref<Comment | null>(null)
const canEdit = computed(() => userStore.hasGrant('comments.update', 'all') || userStore.hasGrant('comments.moderate', 'all'))
const editLabel = computed(() => userStore.hasGrant('comments.update', 'all') ? '编辑评论' : '审核评论')

async function load() {
  loading.value = true
  error.value = null
  try {
    comment.value = await client.comments.getManaged(Number(route.params.id))
  } catch (cause) {
    comment.value = null
    error.value = isApiError(cause) ? cause.message : '无法加载评论详情'
  } finally {
    loading.value = false
  }
}
function openEdit() {
  if (!comment.value) return
  editData.value = { id: comment.value.id } as Comment
  editOpen.value = true
}
onMounted(() => {
  listSource.value = validateAdminListSource(window.history.state?.listSource, listPath) ?? listPath
  void load()
})
watch(() => route.params.id, () => { if (import.meta.client) void load() })
</script>
