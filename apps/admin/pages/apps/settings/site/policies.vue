<template>
  <AdminPageContainer>
    <AdminPageHeader
      title="站点策略"
      description="集中管理访问要求、内容发布规则、站内提醒和主动更新。各项设置分别读取和保存。"
      :breadcrumbs="[{ title: '站点' }]"
    />

    <v-tabs v-model="tab" color="primary" class="mb-4" density="comfortable">
      <v-tab v-if="canAccessTab('access')" prepend-icon="mdi-account-check-outline" value="access">访问与验证<span v-if="dirtyTabs.access" class="ml-2">· 未保存</span></v-tab>
      <v-tab v-if="canAccessTab('content')" prepend-icon="mdi-shield-check-outline" value="content">内容规则<span v-if="dirtyTabs.content" class="ml-2">· 未保存</span></v-tab>
      <v-tab v-if="canAccessTab('notifications')" prepend-icon="mdi-bell-cog-outline" value="notifications">通知策略<span v-if="dirtyTabs.notifications" class="ml-2">· 未保存</span></v-tab>
    </v-tabs>
    <v-divider class="mb-6" />

    <v-window v-if="ready && userStore.userInfo && availableTabs.length" v-model="tab">
      <v-window-item value="access">
        <SiteAccessPolicyPanel v-if="canAccessTab('access') && mountedTabs.has('access')" :active="tab === 'access'" @dirty-change="dirtyTabs.access = $event" />
      </v-window-item>
      <v-window-item value="content">
        <SiteContentRulesPanel v-if="canAccessTab('content') && mountedTabs.has('content')" :active="tab === 'content'" @dirty-change="dirtyTabs.content = $event" />
      </v-window-item>
      <v-window-item value="notifications">
        <template v-if="canAccessTab('notifications') && mountedTabs.has('notifications')">
          <SiteNotificationPoliciesPanel :active="tab === 'notifications'" @dirty-change="notificationDirty = $event" />
          <SiteSignalPoliciesPanel :active="tab === 'notifications'" @dirty-change="signalDirty = $event" />
        </template>
      </v-window-item>
    </v-window>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import SiteAccessPolicyPanel from '~/components/site/SiteAccessPolicyPanel.vue'
import SiteContentRulesPanel from '~/components/site/SiteContentRulesPanel.vue'
import SiteNotificationPoliciesPanel from '~/components/site/SiteNotificationPoliciesPanel.vue'
import SiteSignalPoliciesPanel from '~/components/site/SiteSignalPoliciesPanel.vue'
import { useUserStore } from '~/stores/userStore'

type SitePolicyTab = 'access' | 'content' | 'notifications'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const dirtyTabs = reactive({ access: false, content: false, notifications: false })
const notificationDirty = ref(false)
const signalDirty = ref(false)
watch([notificationDirty, signalDirty], () => { dirtyTabs.notifications = notificationDirty.value || signalDirty.value })
const ready = ref(false)

const canAccessTab = (candidate: SitePolicyTab): boolean => {
  if (candidate === 'access') {
    return userStore.hasGrant('config.read', 'global') && userStore.hasGrant('captcha.read', 'global')
  }
  if (candidate === 'content') return userStore.hasGrant('config.read', 'global')
  return userStore.hasGrant('communications.read', 'global')
}

const availableTabs = computed(() => (['access', 'content', 'notifications'] as SitePolicyTab[]).filter(canAccessTab))
const queryTab = typeof route.query.tab === 'string' && ['access', 'content', 'notifications'].includes(route.query.tab)
  ? route.query.tab as SitePolicyTab
  : null
const requestedTab = (): SitePolicyTab => {
  const value = typeof route.query.tab === 'string' ? route.query.tab : ''
  return availableTabs.value.includes(value as SitePolicyTab) ? value as SitePolicyTab : availableTabs.value[0] ?? 'content'
}
const tab = ref<SitePolicyTab>(queryTab ?? 'content')
const mountedTabs = ref(new Set<SitePolicyTab>(queryTab ? [queryTab] : []))

watch(availableTabs, () => {
  if (!ready.value || !userStore.userInfo || !availableTabs.value.length) return
  if (!availableTabs.value.includes(tab.value)) {
    tab.value = requestedTab()
    mountedTabs.value = new Set([tab.value])
  } else if (!mountedTabs.value.size) {
    mountedTabs.value = new Set([tab.value])
  }
}, { immediate: true })

watch(tab, async (value) => {
  if (!userStore.userInfo || !availableTabs.value.includes(value)) return
  mountedTabs.value = new Set([...mountedTabs.value, value])
  if (route.query.tab === value) return
  await router.replace({ query: { ...route.query, tab: value } })
})

watch(() => route.query.tab, (value) => {
  if (typeof value !== 'string' || !availableTabs.value.includes(value as SitePolicyTab)) return
  if (tab.value !== value) tab.value = value as SitePolicyTab
})

onMounted(() => {
  tab.value = requestedTab()
  mountedTabs.value = new Set([tab.value])
  ready.value = true
})
</script>
