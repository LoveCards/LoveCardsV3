<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader
      :title="role ? `角色 · ${role.name}` : `角色 #${route.params.id}`"
      description="查看角色职责与授权范围；基础信息与能力授权通过底部操作区进入各自任务。"
      :breadcrumbs="[{ title: '用户' }, { title: '角色', to: '/apps/access/roles' }, { title: role?.name || `#${route.params.id}` }]"
    >
      <template #actions><v-btn prepend-icon="mdi-arrow-left" to="/apps/access/roles" variant="text">返回列表</v-btn></template>
    </AdminPageHeader>
    <RoleDetailContent :role-id="roleId" @loaded="role = $event" @dirty-change="dirty = $event" />
    <AdminUnsavedChangesDialog :model-value="unsavedChangesDialog" subject="角色尚未保存的修改" @stay="continueEditing" @discard="discardChanges" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { Role } from '@lovecards/sdk'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import RoleDetailContent from '~/components/roles/RoleDetailContent.vue'
import { useAdminUnsavedChanges } from '~/composables/useAdminUnsavedChanges'

const route = useRoute()
const roleId = computed(() => Number(route.params.id))
const role = ref<Role | null>(null)
const dirty = ref(false)
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(dirty)
</script>
