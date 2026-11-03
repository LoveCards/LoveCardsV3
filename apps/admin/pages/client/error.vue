<template>
  <AdminPageContainer max-width="720" class="admin-error-page">
    <v-sheet border rounded="lg" class="admin-error-card" color="surface">
      <v-avatar color="error" variant="tonal" size="56" class="mb-5">
        <v-icon icon="mdi-shield-lock-outline" size="28" />
      </v-avatar>
      <div class="text-overline text-medium-emphasis">{{ viewData.code || 'ERROR' }}</div>
      <h1 class="text-h5 font-weight-bold mb-3">{{ title }}</h1>
      <p class="text-body-1 text-medium-emphasis mb-2">{{ viewData.message || '暂时无法打开此页面。' }}</p>
      <p v-if="viewData.details" class="text-body-2 text-medium-emphasis mb-6">{{ viewData.details }}</p>
      <div class="d-flex flex-wrap ga-3">
        <v-btn color="primary" :prepend-icon="homePath ? 'mdi-arrow-right-circle-outline' : 'mdi-login'" @click="goHome">
          {{ homePath ? '前往可访问页面' : '返回登录' }}
        </v-btn>
        <v-btn v-if="viewData.jumpBtn?.text && viewData.jumpBtn.url && (viewData.jumpBtn.clickFun || viewData.jumpBtn.url !== (homePath ?? '/login'))" variant="outlined" @click="jump">
          {{ viewData.jumpBtn.text }}
        </v-btn>
      </div>
    </v-sheet>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import { useErrorStore } from '~/stores/client/errorStore'
import { useUserStore } from '~/stores/userStore'
import { firstAccessibleAdminPath } from '~/utils/access'

definePageMeta({ layout: 'default' })

const errorStore = useErrorStore()
const userStore = useUserStore()
const viewData = computed(() => errorStore.viewData)
const homePath = computed(() => firstAccessibleAdminPath(userStore.grants))
const title = computed(() => {
  if (viewData.value.kind === 'forbidden') return '无权访问'
  if (viewData.value.kind === 'unauthorized') return '需要登录'
  if (viewData.value.kind === 'not-found') return '页面不存在'
  return '页面暂时不可用'
})

function goHome(): void {
  navigateTo(homePath.value ?? '/login')
}

function jump(): void {
  const button = viewData.value.jumpBtn
  if (button?.clickFun) {
    button.clickFun()
    return
  }
  if (button?.url) navigateTo(String(button.url))
}
</script>

<style scoped>
.admin-error-page {
  min-height: calc(100vh - 180px);
  display: grid;
  place-items: center;
}

.admin-error-card {
  width: 100%;
  padding: 40px;
}

@media (max-width: 600px) {
  .admin-error-card {
    padding: 28px 22px;
  }
}
</style>
