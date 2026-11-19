<template>
  <v-dialog :model-value="open" aria-label="用户详情" :fullscreen="xs" max-width="880" @update:model-value="onDialogChange">
    <v-card>
      <v-card-title class="d-flex align-center ga-3">
        <span>{{ initialMode === 'edit' ? '编辑用户' : '查看用户' }}</span>
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" aria-label="关闭" @click="close" />
      </v-card-title>
      <v-divider />
      <v-card-text class="admin-user-detail-body">
        <UserDetailContent
          v-if="open && userId"
          :key="userId + '-' + initialMode"
          ref="content"
          :user-id="userId"
          :initial-mode="initialMode"
          @loaded="loadedUser = $event"
          @updated="$emit('updated')"
        />
      </v-card-text>
      <v-divider v-if="loadedUser" />
      <v-card-actions v-if="loadedUser" class="pa-4">
        <v-btn v-if="loadedUser && canReadSecurity" variant="text" @click="navigateAfterConfirm('/apps/users/' + loadedUser.id + '/security')">账号安全</v-btn>

        <v-btn v-if="loadedUser" variant="text" @click="navigateAfterConfirm('/apps/users/' + loadedUser.id, true)">打开完整页面</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { User } from '@lovecards/sdk'
import { useDisplay } from 'vuetify'
import UserDetailContent from '~/components/users/UserDetailContent.vue'
import { useUserStore } from '~/stores/userStore'

withDefaults(defineProps<{ userId: number | null; initialMode?: 'view' | 'edit' }>(), { initialMode: 'view' })
defineEmits<{ updated: [] }>()
const open = defineModel<boolean>({ default: false })
const { xs } = useDisplay()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const canReadSecurity = computed(() => userStore.hasGrant('users.security.read', 'global'))
const loadedUser = ref<User | null>(null)
const content = ref<InstanceType<typeof UserDetailContent> | null>(null)
watch([open, () => loadedUser.value?.id], () => { if (!open.value) loadedUser.value = null })
async function close() {
  if (await content.value?.requestClose() ?? true) open.value = false
}
async function navigateAfterConfirm(path: string, withSource = false) {
  if (!(await content.value?.requestClose() ?? true)) return
  open.value = false
  await router.push(withSource ? { path, state: { adminSource: route.fullPath } } : { path })
}
function onDialogChange(value: boolean) {
  if (value) open.value = true
  else void close()
}
</script>

<style scoped>
.admin-user-detail-body { max-height: min(70vh, 640px); overflow-y: auto; }
@media (max-width: 599.98px) {
  .admin-user-detail-body { max-height: none; flex: 1 1 auto; }
}
</style>
