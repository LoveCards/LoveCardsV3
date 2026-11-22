<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader
      :title="`处理任务 #${route.params.id}`"
      description="查看这条事件处理任务的结果、失败原因与重试条件。"
      :breadcrumbs="[
        { title: '系统' },
        { title: '处理任务', to: '/apps/operations/deliveries' },
        { title: `#${route.params.id}` },
      ]"
    >
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" to="/apps/operations/deliveries" variant="text">返回列表</v-btn>
      </template>
    </AdminPageHeader>
    <DeliveryDetailContent :delivery-id="deliveryId" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import DeliveryDetailContent from '~/components/operations/DeliveryDetailContent.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'

const route = useRoute()
const deliveryId = computed(() => Number(route.params.id))
</script>
