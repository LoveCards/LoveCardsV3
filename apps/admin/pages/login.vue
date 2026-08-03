<template>
  <v-app>
    <v-app-bar :elevation="3" color="primary">
      <v-app-bar-title>LoveCards Admin</v-app-bar-title>
    </v-app-bar>

    <v-main>
      <v-container class="d-flex align-center justify-center" style="height: calc(100vh - 64px)">
        <v-card class="pa-8" max-width="500" elevation="4">
          <v-card-title class="text-primary text-h4 text-center mb-4">管理端登录</v-card-title>
          <v-divider class="mb-6" />

          <v-form @submit.prevent="handleLogin">
            <v-text-field
              v-model="account"
              label="账号或邮箱"
              autocomplete="username"
              variant="outlined"
              color="accent"
              class="mb-3"
            />
            <v-text-field
              v-model="password"
              label="密码"
              type="password"
              autocomplete="current-password"
              variant="outlined"
              color="accent"
              class="mb-4"
            />
            <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
            <v-btn type="submit" color="accent" variant="elevated" block :loading="loading">登录</v-btn>
          </v-form>
        </v-card>
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { useApi, isApiError } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

definePageMeta({ layout: false })

const client = useApi()
const userStore = useUserStore()
const account = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

const handleLogin = async () => {
  if (!account.value.trim() || !password.value) {
    error.value = '请输入账号和密码'
    return
  }

  loading.value = true
  error.value = ''
  try {
    const result = await client.session.login({ account: account.value.trim(), password: password.value })
    client.setToken(result.token)
    await userStore.getUserInfo()
    if (!userStore.hasAdminAccess()) {
      client.clearToken()
      userStore.reset()
      error.value = '当前账号没有管理端权限'
      return
    }
    // Force a fresh app navigation so the global auth middleware observes the
    // token that was just persisted by the SDK client.
    window.location.assign('/admin/apps/dashboard')
  } catch (cause) {
    client.clearToken()
    userStore.reset()
    error.value = isApiError(cause) ? cause.message : '登录失败，请稍后重试'
  } finally {
    loading.value = false
  }
}
</script>
