<template>
  <v-app class="admin-app">
    <NuxtLoadingIndicator :color="theme.current.value.colors.primary" :height="3" />
    <VNotifierContainer />
    <NotificationNotifier />
    <a class="admin-skip-link" href="#admin-main-content" @click.prevent="focusMainContent">跳到主要内容</a>

    <v-app-bar color="surface" elevation="0" height="64" border="b">
      <template #prepend>
        <v-tooltip text="打开导航" location="bottom">
          <template #activator="{ props }">
            <v-app-bar-nav-icon
              v-bind="props"
              class="d-lg-none"
              aria-label="打开导航"
              @click="drawer = !drawer"
            />
          </template>
        </v-tooltip>
      </template>

      <v-btn
        class="admin-brand"
        variant="text"
        :to="adminNavigation.dashboard.path"
        aria-label="返回管理工作台"
      >
        <v-icon icon="mdi-heart-box-outline" size="24" />
        <span class="admin-brand__name">LoveCards</span>
        <v-chip
          class="admin-brand__environment"
          color="primary"
          size="x-small"
          variant="tonal"
        >
          {{ environmentLabel }}
        </v-chip>
      </v-btn>

      <v-spacer />

      <v-menu
        v-model="searchOpen"
        :close-on-content-click="false"
        location="bottom end"
        :min-width="smAndDown ? 304 : 420"
      >
        <template #activator="{ props: menuProps }">
          <v-tooltip text="搜索管理页面" location="bottom">
            <template #activator="{ props: tooltipProps }">
              <v-btn
                v-bind="mergeProps(menuProps, tooltipProps)"
                icon="mdi-magnify"
                variant="text"
                aria-label="搜索管理页面"
              />
            </template>
          </v-tooltip>
        </template>

        <v-card rounded="lg" elevation="4">
          <v-card-text>
            <v-text-field
              ref="searchInput"
              v-model="searchQuery"
              clearable
              density="compact"
              hide-details
              label="搜索页面"
              placeholder="输入页面名称"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
            />
          </v-card-text>
          <v-divider />
          <v-list v-if="searchResults.length" density="compact" nav>
            <v-list-item
              v-for="result in searchResults"
              :key="result.path"
              :prepend-icon="result.icon || 'mdi-chevron-right'"
              :subtitle="result.groupTitle"
              :title="result.title"
              :to="result.path"
              @click="searchOpen = false"
            />
          </v-list>
          <v-list v-else density="compact">
            <v-list-item
              prepend-icon="mdi-text-search"
              :title="searchQuery ? '没有匹配的管理页面' : '输入关键词查找页面'"
            />
          </v-list>
        </v-card>
      </v-menu>

      <v-menu location="bottom end">
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            class="admin-account-trigger"
            variant="text"
            :aria-label="`打开账号菜单（${userInfo.username || '管理员'}）`"
          >
            <v-avatar size="32" color="primary">
              <v-img
                v-if="userInfo.avatar"
                :src="userInfo.avatar"
                :alt="userInfo.username || '管理员头像'"
              />
              <span v-else class="text-on-primary text-caption">{{ initials }}</span>
            </v-avatar>
            <span v-if="!smAndDown" class="admin-account-trigger__name text-truncate">
              {{ userInfo.username || '管理员' }}
            </span>
            <v-icon v-if="!smAndDown" icon="mdi-chevron-down" size="18" />
          </v-btn>
        </template>

        <v-card min-width="256" rounded="lg" elevation="4">
          <v-list density="compact">
            <v-list-item
              :title="userInfo.username || '管理员'"
              :subtitle="userInfo.email || '管理端账号'"
            >
              <template #prepend>
                <v-avatar size="40" color="primary">
                  <v-img
                    v-if="userInfo.avatar"
                    :src="userInfo.avatar"
                    :alt="userInfo.username || '管理员头像'"
                  />
                  <span v-else class="text-on-primary">{{ initials }}</span>
                </v-avatar>
              </template>
            </v-list-item>
          </v-list>
          <v-divider />
          <v-list density="compact" nav>
            <v-list-item
              prepend-icon="mdi-shield-account-outline"
              title="当前角色"
              :subtitle="roleLabel"
            />
            <v-list-item prepend-icon="mdi-home-outline" title="返回站点" @click="goHome" />
            <v-list-item prepend-icon="mdi-logout" title="退出登录" @click="logout" />
          </v-list>
        </v-card>
      </v-menu>
    </v-app-bar>

    <v-navigation-drawer
      v-model="drawer"
      color="surface"
      :expand-on-hover="rail && !mdAndDown"
      :permanent="!mdAndDown"
      :rail="rail && !mdAndDown"
      :rail-width="72"
      :temporary="mdAndDown"
      :width="264"
      border="e"
    >
      <v-list density="compact">
        <v-list-item
          class="admin-drawer__profile"
          :title="userInfo.username || '管理员'"
          :subtitle="roleLabel"
        >
          <template #prepend>
            <v-avatar size="40" color="primary">
              <v-img
                v-if="userInfo.avatar"
                :src="userInfo.avatar"
                :alt="userInfo.username || '管理员头像'"
              />
              <span v-else class="text-on-primary">{{ initials }}</span>
            </v-avatar>
          </template>
        </v-list-item>
      </v-list>
      <v-divider />

      <v-list
        :opened="openedGroups"
        class="admin-navigation-list"
        density="compact"
        nav
        @click:open="updateOpenedGroup"
      >
        <v-list-group
          v-for="group in visibleNavigationGroups"
          :key="group.key"
          :value="group.key"
        >
          <template #activator="{ props: groupProps }">
            <v-tooltip
              :disabled="!rail || mdAndDown"
              :text="group.title"
              location="end"
            >
              <template #activator="{ props: tooltipProps }">
                <v-list-item
                  v-bind="mergeProps(groupProps, tooltipProps)"
                  :prepend-icon="group.icon"
                  :title="group.title"
                />
              </template>
            </v-tooltip>
          </template>

          <template v-for="(item, index) in group.items" :key="item.path">
            <v-list-subheader
              v-if="shouldShowSection(group, item, index)"
              class="admin-navigation-section"
            >
              {{ item.section }}
            </v-list-subheader>
            <v-list-item
              :active="activeNavigationItem?.path === item.path"
              :prepend-icon="item.icon || 'mdi-chevron-right'"
              :title="item.title"
              :to="item.path"
              color="primary"
              @click="closeMobileDrawer"
            >
              <template v-if="item.riskLevel === 'high'" #append>
                <v-tooltip text="高风险页面" location="end">
                  <template #activator="{ props }">
                    <v-icon
                      v-bind="props"
                      color="warning"
                      icon="mdi-alert-outline"
                      size="18"
                      role="img"
                      aria-label="高风险页面"
                    />
                  </template>
                </v-tooltip>
              </template>
            </v-list-item>
          </template>
        </v-list-group>
      </v-list>

      <template #append>
        <v-divider />
        <v-list class="admin-drawer__actions" density="compact" nav>
          <v-list-item
            prepend-icon="mdi-home-outline"
            title="返回站点"
            @click="goHome"
          />
          <v-tooltip
            :disabled="!rail"
            :text="rail ? '展开导航' : '收起导航'"
            location="end"
          >
            <template #activator="{ props }">
              <v-list-item
                v-if="!mdAndDown"
                v-bind="props"
                :prepend-icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
                :title="rail ? '展开导航' : '收起导航'"
                @click="rail = !rail"
              />
            </template>
          </v-tooltip>
        </v-list>
      </template>
    </v-navigation-drawer>

    <v-main id="admin-main-content" class="admin-main" tabindex="-1">
      <div class="admin-main__content">
        <slot />
      </div>
      <v-footer class="admin-footer" color="transparent">
        <span>© {{ currentYear }} LoveCards</span>
        <span aria-hidden="true">·</span>
        <a href="https://lovecards.cn" target="_blank" rel="noreferrer">站点</a>
        <span aria-hidden="true">·</span>
        <span>GPL-3.0</span>
        <span v-if="runtimeConfig.public.gitSha" class="font-weight-medium">
          {{ runtimeConfig.public.gitSha }}
        </span>
      </v-footer>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { mergeProps } from 'vue'
import NotificationNotifier from '~/components/shared/NotificationNotifier.vue'
import { useUserStore } from '~/stores/userStore'
import { useApi } from '~/lib/api'
import {
  activeNavigationItemForPath,
  adminNavigation,
  canAccessNavigationItem,
} from '~/config/navigation'
import type {
  AdminNavigationGroup,
  AdminNavigationItem,
} from '~/config/navigation'
import type { User } from '@lovecards/sdk'

const theme = useTheme()
const { mdAndDown, smAndDown } = useDisplay()
const route = useRoute()
const userStore = useUserStore()
const runtimeConfig = useRuntimeConfig()
const client = useApi()
const drawer = ref(!mdAndDown.value)
const rail = ref(false)
const collapsedGroups = ref<string[]>([])
const searchOpen = ref(false)
const searchInput = ref<{ focus: () => void } | null>(null)
const searchQuery = ref('')
const currentYear = new Date().getFullYear()

const userInfo = computed(() => userStore.userInfo ?? ({
  username: '',
  email: '',
  avatar: '',
  roles: [],
} as Pick<User, 'username' | 'email' | 'avatar' | 'roles'>))

const initials = computed(() => (userInfo.value.username?.trim() || 'LC').slice(0, 2).toUpperCase())
const roleLabel = computed(() => {
  const roles = userInfo.value.roles?.map((role) => role.name || role.slug).filter(Boolean) ?? []
  return roles.length ? roles.join(' · ') : '管理端成员'
})
const environmentLabel = computed(() => {
  const environment = String(runtimeConfig.public.environment || 'development').trim() || 'development'
  const normalizedEnvironment = environment.toLocaleLowerCase()
  const labels: Readonly<Record<string, string>> = {
    dev: '本地开发',
    development: '本地开发',
    local: '本地开发',
    test: '测试环境',
    testing: '测试环境',
    staging: '预发布',
    preproduction: '预发布',
    preview: '预发布',
    prod: '生产环境',
    production: '生产环境',
  }
  return labels[normalizedEnvironment] ?? environment
})

const visibleNavigationGroups = computed(() => adminNavigation.groups
  .map((group) => ({
    ...group,
    items: group.items.filter((item) => canAccessNavigationItem(item, userStore.grants)),
  }))
  .filter((group) => group.items.length > 0))
const openedGroups = computed(() => visibleNavigationGroups.value
  .map((group) => group.key)
  .filter((key) => !collapsedGroups.value.includes(key)))
const allVisibleItems = computed(() => visibleNavigationGroups.value.flatMap((group) => group.items))
const activeNavigationItem = computed(() => activeNavigationItemForPath(route.path, allVisibleItems.value))
const searchResults = computed(() => {
  const query = searchQuery.value.trim().toLocaleLowerCase()
  if (!query) return [] as Array<AdminNavigationItem & { groupTitle: string }>

  return allVisibleItems.value
    .filter((item) => item.title.toLocaleLowerCase().includes(query) || item.path.includes(query))
    .slice(0, 8)
    .map((item) => ({
      ...item,
      groupTitle: visibleNavigationGroups.value.find((group) => group.items.some((candidate) => candidate.path === item.path))?.title ?? '',
    }))
})

function shouldShowSection(
  group: AdminNavigationGroup,
  item: AdminNavigationItem,
  index: number,
): boolean {
  return Boolean(
    item.section
    && item.section !== item.title
    && item.section !== group.title
    && item.section !== group.items[index - 1]?.section,
  )
}

function expandActiveGroup(): void {
  const active = visibleNavigationGroups.value.find((group) => group.items.some((item) => (
    item.path === activeNavigationItem.value?.path
  )))
  if (active && collapsedGroups.value.includes(active.key)) {
    collapsedGroups.value = collapsedGroups.value.filter((key) => key !== active.key)
  }
}

function updateOpenedGroup(event: { id: unknown; value: boolean }): void {
  const key = String(event.id)
  if (event.value) {
    collapsedGroups.value = collapsedGroups.value.filter((item) => item !== key)
  } else if (!collapsedGroups.value.includes(key)) {
    collapsedGroups.value = [...collapsedGroups.value, key]
  }
}

function closeMobileDrawer(): void {
  if (mdAndDown.value) drawer.value = false
}

function focusMainContent(): void {
  if (import.meta.client) document.getElementById('admin-main-content')?.focus()
}

watch([() => route.path, visibleNavigationGroups], expandActiveGroup, { immediate: true })
watch(searchOpen, async (isOpen) => {
  if (!isOpen) return
  await nextTick()
  searchInput.value?.focus()
})
watch(mdAndDown, (isCompact) => {
  drawer.value = !isCompact
  if (isCompact) rail.value = false
})

function goHome(): void {
  if (import.meta.client) window.location.href = '/'
}

async function logout(): Promise<void> {
  try {
    await client.session.logout()
  } catch {
    // 服务端会话已过期时仍需清理本地凭据。
  } finally {
    client.clearToken()
    userStore.reset()
    await navigateTo('/login')
  }
}
</script>

<style scoped>
.admin-app {
  background: rgb(var(--v-theme-background));
  color: rgb(var(--v-theme-on-background));
  font-family: Roboto, 'Noto Sans SC', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.admin-skip-link {
  position: fixed;
  z-index: 3000;
  top: 8px;
  left: 12px;
  padding: 10px 14px;
  border-radius: 4px;
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
  font-weight: 600;
  text-decoration: none;
  transform: translateY(-160%);
}

.admin-skip-link:focus {
  transform: translateY(0);
}

.admin-brand {
  min-width: 0;
  min-height: 44px;
  color: rgb(var(--v-theme-primary));
  letter-spacing: 0;
}

.admin-brand__name {
  margin-inline: 8px 10px;
  font-size: 1rem;
  font-weight: 700;
}

.admin-brand__environment {
  font-size: 0.6875rem;
  font-weight: 500;
}

.admin-account-trigger {
  min-width: 44px;
  min-height: 44px;
  gap: 8px;
  padding-inline: 8px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.admin-account-trigger__name {
  max-width: 160px;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.875rem;
}

.admin-drawer__profile {
  min-height: 76px;
}

.admin-navigation-list,
.admin-drawer__actions {
  padding: 8px;
}

.admin-navigation-list :deep(.v-list-item),
.admin-drawer__actions :deep(.v-list-item) {
  min-height: 44px;
}

.admin-navigation-list :deep(.v-list-item--active) {
  background: rgb(var(--v-theme-primary-container));
  color: rgb(var(--v-theme-on-primary-container));
  font-weight: 500;
}

.admin-navigation-list :deep(.v-list-group__items .v-list-item) {
  padding-inline-start: 48px !important;
}

.admin-navigation-section {
  min-height: 32px;
  padding-inline-start: 48px;
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 0.75rem;
  font-weight: 600;
}

.admin-main {
  background: rgb(var(--v-theme-background));
}

.admin-main__content {
  width: min(100%, 1344px);
  min-height: calc(100dvh - 120px);
  margin-inline: auto;
  padding: 24px 32px 8px;
}

.admin-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 8px;
  min-height: 56px;
  padding: 16px 24px 24px;
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 0.75rem;
}

.admin-footer a {
  color: rgb(var(--v-theme-primary));
  text-decoration: none;
}

.admin-footer a:hover,
.admin-footer a:focus-visible {
  text-decoration: underline;
  text-underline-offset: 2px;
}

@media (max-width: 1279.98px) {
  .admin-main__content {
    width: 100%;
    padding-inline: 24px;
  }
}

@media (max-width: 599.98px) {
  .admin-brand {
    padding-inline: 4px;
  }

  .admin-brand__name {
    margin-inline: 6px 0;
  }

  .admin-brand__environment {
    display: none;
  }

  .admin-main__content {
    padding: 16px 16px 4px;
  }

  .admin-footer {
    padding-inline: 16px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .admin-app :deep(*) {
    scroll-behavior: auto !important;
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
  }
}
</style>
