<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader
      :title="`审计记录 #${route.params.id}`"
      description="核对一次管理操作的操作者、目标资源、状态前后值和请求链路。"
      :breadcrumbs="[
        { title: '系统' },
        { title: '审计日志', to: '/apps/operations/audit' },
        { title: `#${route.params.id}` },
      ]"
    >
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" to="/apps/operations/audit" variant="text">返回列表</v-btn>
      </template>
    </AdminPageHeader>

    <AuditDetailContent :audit-id="auditId" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import AuditDetailContent from '~/components/operations/AuditDetailContent.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'

const route = useRoute()
const auditId = computed(() => Number(route.params.id))
</script>
