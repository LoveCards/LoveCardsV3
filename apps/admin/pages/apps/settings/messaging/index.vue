<template>
  <AdminPageContainer>
    <AdminPageHeader
      title="消息渠道"
      description="管理通知使用的发信渠道。密钥仅在详情页编辑，扫描和默认渠道切换均由服务端校验权限。"
      :breadcrumbs="[{ title: '站点设置' }]"
    >
      <template #actions>
        <AdminActionGuard capability="sender.install" scope="global">
          <v-btn
            color="primary"
            :loading="scanning"
            prepend-icon="mdi-refresh"
            variant="outlined"
            @click="scanChannels"
          >
            扫描渠道
          </v-btn>
        </AdminActionGuard>
      </template>
    </AdminPageHeader>

    <v-alert v-if="scanMessage" class="mb-4" :type="scanMessage.type" variant="tonal">
      {{ scanMessage.text }}
    </v-alert>
    <v-alert v-if="actionMessage" class="mb-4" :type="actionMessage.type" variant="tonal">
      {{ actionMessage.text }}
    </v-alert>

    <AdminAsyncState
      :empty="channelGroups.length === 0"
      empty-description="扫描消息渠道后，可在这里选择默认渠道并进入详细配置。"
      empty-icon="mdi-email-off-outline"
      empty-title="尚未发现消息渠道"
      :error="loadError"
      error-title="消息渠道加载失败"
      :loading="loading"
      skeleton-type="heading, article@3"
      @retry="loadChannels"
    >
      <section v-for="group in channelGroups" :key="group.channelType" class="mb-8">
        <div class="mb-3 d-flex align-center justify-space-between ga-3">
          <h2 class="text-subtitle-1 font-weight-bold">{{ groupTitle(group.channelType) }}</h2>
          <v-chip size="small" variant="outlined">{{ group.channels.length }} 个渠道</v-chip>
        </div>
        <v-row>
          <v-col v-for="channel in group.channels" :key="channel.slug" cols="12" md="6" lg="4">
            <v-card class="d-flex h-100 flex-column">
              <v-card-item>
                <template #prepend>
                  <v-avatar color="primary" variant="tonal">
                    <v-icon>{{ channel.icon || 'mdi-email-outline' }}</v-icon>
                  </v-avatar>
                </template>
                <v-card-title>{{ channel.name }}</v-card-title>
                <v-card-subtitle>{{ channel.slug }}</v-card-subtitle>
                <template #append>
                  <v-chip v-if="channel.slug === group.defaultSlug" color="primary" size="small">默认</v-chip>
                </template>
              </v-card-item>

              <v-card-text class="flex-grow-1">
                <p class="mb-2 text-body-2 text-medium-emphasis">{{ channel.fields.length }} 个配置项</p>
                <div v-if="channel.supportedTypes?.length" class="d-flex flex-wrap ga-1">
                  <v-chip v-for="supportedType in channel.supportedTypes" :key="supportedType" size="small" variant="outlined">
                    {{ supportedType }}
                  </v-chip>
                </div>
              </v-card-text>

              <v-divider />
              <v-card-actions>
                <AdminActionGuard capability="config.update" scope="global">
                  <v-btn
                    color="primary"
                    :disabled="channel.slug === group.defaultSlug"
                    :loading="changingDefault === `${group.channelType}:${channel.slug}`"
                    variant="text"
                    @click="setDefault(group.channelType, channel.slug)"
                  >
                    设为默认
                  </v-btn>
                </AdminActionGuard>
                <v-spacer />
                <v-btn
                  append-icon="mdi-arrow-right"
                  color="primary"
                  variant="text"
                  @click="openConfig(channel.slug)"
                >
                  配置
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-col>
        </v-row>
      </section>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { SenderChannel } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import { readObject, readString } from '~/utils/json'

interface ChannelGroup {
  channelType: string
  channels: SenderChannel[]
  defaultSlug: string
}
type Feedback = { type: 'success' | 'error'; text: string }

const client = useApi()
const router = useRouter()
const userStore = useUserStore()
const channelGroups = shallowRef<ChannelGroup[]>([])
const loading = ref(false)
const loadError = ref('')
const scanning = ref(false)
const scanMessage = ref<Feedback | null>(null)
const actionMessage = ref<Feedback | null>(null)
const changingDefault = ref('')
const canUpdateConfig = computed(() => userStore.hasGrant('config.update', 'global'))
const canInstallSender = computed(() => userStore.hasGrant('sender.install', 'global'))
const errorMessage = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : fallback
const groupTitle = (type: string) => {
  const normalized = type.toLowerCase()
  if (normalized.includes('mail')) return '邮件渠道'
  if (normalized.includes('sms')) return '短信渠道'
  return type
}

const loadChannels = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const [channels, rawConfig] = await Promise.all([client.sender.channels(), client.config.list()])
    const senderConfig = readObject(rawConfig.sender)
    const groups: Record<string, SenderChannel[]> = {}
    for (const channel of channels) {
      const channelType = channel.channelType || channel.slug
      if (!groups[channelType]) groups[channelType] = []
      groups[channelType].push(channel)
    }
    channelGroups.value = Object.entries(groups).map(([channelType, groupChannels]) => ({
      channelType,
      channels: groupChannels,
      defaultSlug: readString(senderConfig[`default_${channelType}`], groupChannels[0]?.slug ?? ''),
    }))
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载消息渠道，请稍后重试。')
  } finally {
    loading.value = false
  }
}
const setDefault = async (channelType: string, slug: string) => {
  if (!canUpdateConfig.value || changingDefault.value) return
  changingDefault.value = `${channelType}:${slug}`
  actionMessage.value = null
  try {
    await client.config.update({ sender: { [`default_${channelType}`]: slug } })
    const group = channelGroups.value.find(item => item.channelType === channelType)
    if (group) group.defaultSlug = slug
    actionMessage.value = { type: 'success', text: `已将 ${slug} 设为默认${groupTitle(channelType)}。` }
  } catch (cause) {
    actionMessage.value = { type: 'error', text: errorMessage(cause, '默认消息渠道切换失败，请稍后重试。') }
  } finally {
    changingDefault.value = ''
  }
}
const openConfig = (slug: string) => {
  void router.push(`/apps/settings/messaging/${encodeURIComponent(slug)}`)
}
const scanChannels = async () => {
  if (!canInstallSender.value || scanning.value) return
  scanning.value = true
  scanMessage.value = null
  try {
    await client.sender.install()
    await loadChannels()
    scanMessage.value = { type: 'success', text: '消息渠道扫描完成，列表已刷新。' }
  } catch (cause) {
    scanMessage.value = { type: 'error', text: errorMessage(cause, '消息渠道扫描失败，请稍后重试。') }
  } finally {
    scanning.value = false
  }
}

onMounted(() => {
  void loadChannels()
})
</script>
