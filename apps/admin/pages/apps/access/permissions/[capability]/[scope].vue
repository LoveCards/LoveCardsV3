<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader
      :title="`权限 · ${capability}`"
      description="查看由服务端维护的能力含义与作用域；能力目录在管理端保持只读。"
      :breadcrumbs="[
        { title: '用户' },
        { title: '权限目录', to: '/apps/access/permissions' },
        { title: capability },
      ]"
    >
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" to="/apps/access/permissions" variant="outlined">返回目录</v-btn>
      </template>
    </AdminPageHeader>
    <PermissionDetailContent :capability="capability" :scope="scope" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import PermissionDetailContent from '~/components/permissions/PermissionDetailContent.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'

const route = useRoute()
const capability = computed(() => String(route.params.capability || ''))
const scope = computed(() => String(route.params.scope || ''))
</script>
