<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader
      :title="'举报 #' + route.params.id"
      description="查看举报证据与处理记录；授权后可在详情中处理状态或追加备注。"
      :breadcrumbs="breadcrumbs"
    >
      <template #actions>
        <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="returnToAdminList(router, reportListPath)">返回列表</v-btn>
      </template>
    </AdminPageHeader>
    <ReportDetailContent v-if="reportId" :report-id="reportId" />
    <v-alert v-else type="error" variant="tonal">举报 ID 无效。</v-alert>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import ReportDetailContent from '~/components/reports/ReportDetailContent.vue'
import { returnToAdminList, validateAdminListSource } from '~/utils/adminListSource'

const route = useRoute()
const router = useRouter()
const reportListPath = '/apps/community/reports'
const listSource = ref(reportListPath)
const parsedId = computed(() => Number(route.params.id))
const reportId = computed(() => Number.isSafeInteger(parsedId.value) && parsedId.value > 0 ? parsedId.value : null)
const breadcrumbs = computed(() => [{ title: '举报处理', to: listSource.value }, { title: '#' + route.params.id, disabled: true }])
onMounted(() => { listSource.value = validateAdminListSource(window.history.state?.listSource, reportListPath) ?? reportListPath })
</script>
