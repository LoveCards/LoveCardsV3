<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader
      :title="user ? '用户 · ' + user.username : '用户 #' + route.params.id"
      description="查看并管理账号资料；账号安全和会话操作在独立受限页面。"
      :breadcrumbs="[
        { title: '用户' },
        { title: '用户管理', to: '/apps/users' },
        { title: user?.username || '#' + route.params.id },
      ]"
    >
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" variant="text" @click="returnToAdminSource(router, '/apps/users')">{{ sourcePath ? '返回来源' : '返回列表' }}</v-btn>
      </template>
    </AdminPageHeader>
    <UserDetailContent v-if="userId" :user-id="userId" @loaded="user = $event" />
    <v-alert v-else type="error" variant="tonal">用户 ID 无效。</v-alert>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { User } from '@lovecards/sdk'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import UserDetailContent from '~/components/users/UserDetailContent.vue'
import { returnToAdminSource, validateAdminSource } from '~/utils/adminListSource'

const route = useRoute()
const router = useRouter()
const parsedId = computed(() => Number(route.params.id))
const userId = computed(() => Number.isSafeInteger(parsedId.value) && parsedId.value > 0 ? parsedId.value : null)
const user = ref<User | null>(null)
const sourcePath = ref<string | null>(null)
onMounted(() => { sourcePath.value = validateAdminSource(window.history.state?.adminSource) })
</script>
