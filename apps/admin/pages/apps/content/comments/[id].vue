<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader :title="`评论 #${route.params.id}`" description="阅读完整评论与管理信息。" :breadcrumbs="breadcrumbs">
      <template #actions><v-btn prepend-icon="mdi-arrow-left" variant="text" @click="returnToAdminList(router, listPath)">返回列表</v-btn></template>
    </AdminPageHeader>
    <CommentDetailContent :comment-id="commentId" @dirty-change="dirty = $event" />
    <AdminUnsavedChangesDialog :model-value="unsavedChangesDialog" @stay="continueEditing" @discard="discardChanges" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import CommentDetailContent from '~/components/comments/CommentDetailContent.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { useAdminUnsavedChanges } from '~/composables/useAdminUnsavedChanges'
import { returnToAdminList, validateAdminListSource } from '~/utils/adminListSource'
const route = useRoute()
const router = useRouter()
const listPath = '/apps/content/comments'
const listSource = ref(listPath)
const breadcrumbs = computed(() => [{ title: '评论', to: listSource.value }, { title: `#${route.params.id}`, disabled: true }])
const commentId = computed(() => Number(route.params.id))
const dirty = ref(false)
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(dirty)
onMounted(() => { listSource.value = validateAdminListSource(window.history.state?.listSource, listPath) ?? listPath })
</script>
