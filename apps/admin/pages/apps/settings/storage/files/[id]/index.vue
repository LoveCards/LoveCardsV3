<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader :title="`文件 #${route.params.id}`" description="查看文件元数据与业务引用，并执行授权的文件操作。" :breadcrumbs="breadcrumbs">
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" variant="text" to="/apps/settings/storage/files">返回文件列表</v-btn>
      </template>
    </AdminPageHeader>
    <FileDetailContent :file-id="fileId" @removed="returnToDeletedFiles" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import FileDetailContent from '~/components/storage/FileDetailContent.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'

const route = useRoute()
const router = useRouter()
const fileId = computed(() => Number(route.params.id))
const breadcrumbs = computed(() => [{ title: '文件管理', to: '/apps/settings/storage/files' }, { title: `文件 #${route.params.id}`, disabled: true }])
const returnToDeletedFiles = () => router.push('/apps/settings/storage/files?view=deleted')
</script>
