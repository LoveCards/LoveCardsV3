<template>
  <v-container class="d-flex fill-height align-center justify-center">
    <v-sheet class="pa-8 text-center" max-width="520" rounded="lg">
      <v-progress-circular v-if="loading" :size="64" :width="6" color="primary" indeterminate>
        <v-icon size="large">mdi-heart-pulse</v-icon>
      </v-progress-circular>
      <template v-else>
        <v-icon color="warning" icon="mdi-shield-alert-outline" size="48" />
        <h1 class="text-h6 mt-4">没有可访问的管理页面</h1>
        <p class="text-body-2 text-medium-emphasis mt-2 mb-5">{{ error }}</p>
        <v-btn color="primary" to="/login" prepend-icon="mdi-login">返回登录</v-btn>
      </template>
    </v-sheet>
  </v-container>
</template>

<script setup lang="ts">
import { useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import { firstAccessibleAdminPath } from '~/utils/access'

definePageMeta({ layout: false })

const client = useApi()
const userStore = useUserStore()
const loading = ref(true)
const error = ref('请联系超级管理员为当前账号分配管理职责。')

onMounted(async () => {
  if (!localStorage.getItem('token')) {
    await navigateTo('/login')
    return
  }
  try {
    await userStore.init()
    if (!userStore.hasAdminAccess()) throw new Error('当前账号没有进入管理端的权限。')
    const destination = firstAccessibleAdminPath(userStore.grants)
    if (!destination) throw new Error('当前账号尚未分配任何可访问的管理页面。')
    await navigateTo(destination, { replace: true })
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : '无法确认当前账号的管理权限。'
    if (!userStore.userInfo) {
      client.clearToken()
      userStore.reset()
      await navigateTo('/login')
      return
    }
    loading.value = false
  }
})
</script>
