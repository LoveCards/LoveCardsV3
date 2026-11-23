<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader :title="`标签 #${route.params.id}`" description="查看标签信息与治理状态。" :breadcrumbs="breadcrumbs">
      <template #actions><v-btn prepend-icon="mdi-arrow-left" variant="text" @click="returnToAdminList(router, listPath)">返回列表</v-btn></template>
    </AdminPageHeader>
    <TagDetailContent :tag-id="tagId" @dirty-change="dirty = $event" />
    <AdminUnsavedChangesDialog :model-value="unsavedChangesDialog" @stay="continueEditing" @discard="discardChanges" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import TagDetailContent from '~/components/tags/TagDetailContent.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { useAdminUnsavedChanges } from '~/composables/useAdminUnsavedChanges'
import { returnToAdminList, validateAdminListSource } from '~/utils/adminListSource'

const route = useRoute()
const router = useRouter()
const listPath = '/apps/content/tags'
const listSource = ref(listPath)
const breadcrumbs = computed(() => [{ title: '标签', to: listSource.value }, { title: `#${route.params.id}`, disabled: true }])
const tagId = computed(() => Number(route.params.id))
const dirty = ref(false)
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(dirty)
onMounted(() => { listSource.value = validateAdminListSource(window.history.state?.listSource, listPath) ?? listPath })
</script>
