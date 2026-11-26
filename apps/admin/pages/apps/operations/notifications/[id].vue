<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader :title="`通知 #${route.params.id}`" description="查看这条通知的生成和读取元数据。" :breadcrumbs="[{ title: '系统' }, { title: '站内通知', to: '/apps/operations/notifications' }, { title: `#${route.params.id}` }]">
      <template #actions><v-btn prepend-icon="mdi-arrow-left" to="/apps/operations/notifications" variant="text">返回列表</v-btn></template>
    </AdminPageHeader>
    <NotificationDetailContent :notification-id="Number(route.params.id)" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import NotificationDetailContent from '~/components/notifications/NotificationDetailContent.vue'
const route = useRoute()
</script>
