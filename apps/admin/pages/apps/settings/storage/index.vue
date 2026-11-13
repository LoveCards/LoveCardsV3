<template>
  <AdminPageContainer>
    <AdminPageHeader
      title="文件存储"
      description="管理存储渠道与上传策略。切换默认渠道只影响后续上传，不会自动迁移已有文件。"
      :breadcrumbs="[{ title: '系统' }]"
    >
      <template #actions>
        <AdminActionGuard capability="storage.install" scope="global">
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

    <div>
      <v-tabs v-model="tab" color="primary">
        <v-tab prepend-icon="mdi-cloud-outline" value="channels">渠道管理</v-tab>
        <v-tab prepend-icon="mdi-tune-variant" value="settings">基础设置</v-tab>
      </v-tabs>
      <v-divider />

      <div class="pt-6">
        <AdminAsyncState
          :error="loadError"
          error-title="存储配置加载失败"
          :loading="loading"
          skeleton-type="heading, article@3"
          @retry="loadPage"
        >
          <v-window v-model="tab">
            <v-window-item value="channels">
              <AdminAsyncState
                :empty="channelList.length === 0"
                empty-description="扫描存储渠道后，可在这里查看状态并进入详细配置。"
                empty-icon="mdi-cloud-off-outline"
                empty-title="尚未发现存储渠道"
                :retryable="false"
              >
                <v-row>
                  <v-col v-for="channel in channelList" :key="channel.slug" cols="12" md="6" lg="4">
                    <v-card class="d-flex h-100 flex-column">
                      <v-card-item>
                        <template #prepend>
                          <v-avatar color="primary" variant="tonal">
                            <v-icon>{{ channel.icon || 'mdi-cloud-outline' }}</v-icon>
                          </v-avatar>
                        </template>
                        <v-card-title>{{ channel.name }}</v-card-title>
                        <v-card-subtitle>{{ channel.slug }}</v-card-subtitle>
                        <template #append>
                          <v-chip v-if="channel.slug === settings.default" color="primary" size="small">默认</v-chip>
                        </template>
                      </v-card-item>

                      <v-card-text class="flex-grow-1">
                        <div class="mb-2 d-flex flex-wrap ga-2">
                          <v-chip v-if="channel.configured === false" color="warning" size="small" variant="tonal">
                            未初始化
                          </v-chip>
                          <v-chip v-if="channel.available === false" color="error" size="small" variant="tonal">
                            不可用
                          </v-chip>
                        </div>
                        <v-list bg-color="transparent" density="compact">
                          <v-list-item prepend-icon="mdi-file-outline" title="文件数量" :subtitle="String(stats[channel.slug]?.file_count ?? 0)" />
                          <v-list-item prepend-icon="mdi-database-outline" title="已用容量" :subtitle="formatSize(stats[channel.slug]?.total_size ?? 0)" />
                          <v-list-item prepend-icon="mdi-upload-outline" title="单文件上限" :subtitle="formatSize(channelLimit(channel.slug))" />
                        </v-list>
                      </v-card-text>

                      <v-divider />
                      <v-card-actions>
                        <AdminActionGuard capability="config.update" scope="global">
                          <v-btn
                            color="primary"
                            :disabled="channel.slug === settings.default || channel.available === false"
                            :loading="changingDefault === channel.slug"
                            variant="text"
                            @click="setDefault(channel.slug)"
                          >
                            设为默认
                          </v-btn>
                        </AdminActionGuard>
                        <v-spacer />
                        <v-btn
                          append-icon="mdi-arrow-right"
                          color="primary"
                          :disabled="channel.configured === false"
                          variant="text"
                          @click="openConfig(channel.slug)"
                        >
                          配置
                        </v-btn>
                      </v-card-actions>
                    </v-card>
                  </v-col>
                </v-row>
              </AdminAsyncState>
            </v-window-item>

            <v-window-item value="settings">
              <v-card>
                <v-card-text>
                  <v-alert v-if="!canUpdateConfig" class="mb-4" type="info" variant="tonal">
                    当前账号只有查看权限，不能修改存储设置。
                  </v-alert>
                  <v-alert v-if="settingsMessage" class="mb-4" :type="settingsMessage.type" variant="tonal">
                    {{ settingsMessage.text }}
                  </v-alert>
                  <v-alert class="mb-4" type="info" variant="tonal">
                    默认渠道只用于新文件；速率限制和直传凭证有效期会影响所有上传客户端。
                  </v-alert>

                  <v-form @submit.prevent="saveSettings">
                    <v-row>
                      <v-col cols="12" md="6">
                        <section>
                          <h2 class="mb-3 text-subtitle-1 font-weight-bold">默认渠道</h2>
                          <v-select
                            v-model="settings.default"
                            :disabled="!canUpdateConfig || settingsSaving"
                            density="comfortable"
                            item-title="title"
                            item-value="value"
                            :items="channelOptions"
                            label="新上传文件使用的渠道"
                            prepend-inner-icon="mdi-cloud-outline"
                            variant="outlined"
                          />
                        </section>
                      </v-col>
                      <v-col cols="12" md="6">
                        <section>
                          <h2 class="mb-3 text-subtitle-1 font-weight-bold">速率限制</h2>
                          <v-row dense>
                            <v-col cols="12" sm="6">
                              <v-number-input
                                v-model="settings.rate_limit_max"
                                control-variant="split"
                                :disabled="!canUpdateConfig || settingsSaving"
                                density="comfortable"
                                label="最大请求数"
                                :min="1"
                                variant="outlined"
                              />
                            </v-col>
                            <v-col cols="12" sm="6">
                              <v-number-input
                                v-model="settings.rate_limit_window"
                                control-variant="split"
                                :disabled="!canUpdateConfig || settingsSaving"
                                density="comfortable"
                                label="时间窗口（秒）"
                                :min="1"
                                variant="outlined"
                              />
                            </v-col>
                          </v-row>
                        </section>
                      </v-col>
                      <v-col cols="12" md="6">
                        <section>
                          <h2 class="mb-3 text-subtitle-1 font-weight-bold">直传配置</h2>
                          <v-number-input
                            v-model="settings.direct_upload_expire"
                            control-variant="split"
                            :disabled="!canUpdateConfig || settingsSaving"
                            density="comfortable"
                            label="凭证有效期（秒）"
                            :min="60"
                            variant="outlined"
                          />
                        </section>
                      </v-col>
                    </v-row>
                    <v-divider class="mb-4" />
                    <div class="d-flex justify-end">
                      <AdminActionGuard capability="config.update" scope="global">
                        <v-btn
                          color="primary"
                          :disabled="!settingsChanged"
                          :loading="settingsSaving"
                          prepend-icon="mdi-content-save-outline"
                          type="submit"
                          variant="flat"
                        >
                          保存存储设置
                        </v-btn>
                      </AdminActionGuard>
                    </div>
                  </v-form>
                </v-card-text>
              </v-card>
            </v-window-item>
          </v-window>
        </AdminAsyncState>
      </div>
    </div>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { JsonObject, StorageChannel } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import { useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'
import { readNumber, readObject, readString } from '~/utils/json'
import { formatSize } from '~/utils/storage'

type Feedback = { type: 'success' | 'error'; text: string }
interface StorageSettings {
  default: string
  rate_limit_max: number
  rate_limit_window: number
  direct_upload_expire: number
}

const client = useApi()
const configSnapshot = useConfigSnapshot(client)
const router = useRouter()
const userStore = useUserStore()
const tab = ref('channels')
const channelList = shallowRef<StorageChannel[]>([])
const channels = shallowRef<Record<string, JsonObject>>({})
const stats = shallowRef<Record<string, { file_count: number; total_size: number }>>({})
const settings = ref<StorageSettings>({ default: 'local', rate_limit_max: 10, rate_limit_window: 60, direct_upload_expire: 3600 })
const originSettings = ref(CommonUtils.deepClone(settings.value))
const loading = ref(false)
const loadError = ref('')
const scanning = ref(false)
const scanMessage = ref<Feedback | null>(null)
const actionMessage = ref<Feedback | null>(null)
const changingDefault = ref('')
const settingsSaving = ref(false)
const settingsMessage = ref<Feedback | null>(null)
const canUpdateConfig = computed(() => userStore.hasGrant('config.update', 'global'))
const canInstallStorage = computed(() => userStore.hasGrant('storage.install', 'global'))
const settingsChanged = computed(() => JSON.stringify(settings.value) !== JSON.stringify(originSettings.value))
const channelOptions = computed(() => channelList.value.map(channel => ({ title: channel.name, value: channel.slug })))
const channelLimit = (slug: string) => readNumber(channels.value[slug]?.max_file_size)
const errorMessage = configSnapshot.errorMessage

const loadPage = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const [availableChannels, rawConfig, channelStats] = await Promise.all([
      client.storage.channels(),
      configSnapshot.list(),
      client.storage.channelStats(),
    ])
    channelList.value = availableChannels
    stats.value = channelStats
    const storageConfig = readObject(rawConfig.storage)
    settings.value = {
      default: readString(storageConfig.default, availableChannels[0]?.slug ?? 'local'),
      rate_limit_max: readNumber(storageConfig.rate_limit_max, 10),
      rate_limit_window: readNumber(storageConfig.rate_limit_window, 60),
      direct_upload_expire: readNumber(storageConfig.direct_upload_expire, 3600),
    }
    originSettings.value = CommonUtils.deepClone(settings.value)
    channels.value = Object.fromEntries(
      availableChannels.map(channel => [channel.slug, readObject(rawConfig[`storage_${channel.slug}`])]),
    )
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载存储渠道和配置，请稍后重试。')
  } finally {
    loading.value = false
  }
}
const setDefault = async (slug: string) => {
  if (!canUpdateConfig.value || changingDefault.value) return
  changingDefault.value = slug
  actionMessage.value = null
  try {
    const committed = await configSnapshot.update({ storage: { default: slug } })
    settings.value.default = readString(readObject(committed.storage).default)
    originSettings.value.default = settings.value.default
    actionMessage.value = { type: 'success', text: `已将 ${slug} 设为新上传文件的默认渠道。` }
  } catch (cause) {
    actionMessage.value = { type: 'error', text: errorMessage(cause, '默认存储渠道切换失败，请稍后重试。') }
  } finally {
    changingDefault.value = ''
  }
}
const openConfig = (slug: string) => {
  void router.push(`/apps/settings/storage/${encodeURIComponent(slug)}`)
}
const saveSettings = async () => {
  if (!canUpdateConfig.value || !settingsChanged.value || settingsSaving.value) return
  settingsSaving.value = true
  settingsMessage.value = null
  try {
    const committed = await configSnapshot.update({ storage: settings.value })
    const saved = readObject(committed.storage)
    settings.value = {
      default: readString(saved.default),
      rate_limit_max: readNumber(saved.rate_limit_max, 10),
      rate_limit_window: readNumber(saved.rate_limit_window, 60),
      direct_upload_expire: readNumber(saved.direct_upload_expire, 3600),
    }
    originSettings.value = CommonUtils.deepClone(settings.value)
    settingsMessage.value = { type: 'success', text: '存储设置已保存。' }
  } catch (cause) {
    settingsMessage.value = { type: 'error', text: errorMessage(cause, '存储设置保存失败，请稍后重试。') }
  } finally {
    settingsSaving.value = false
  }
}
const scanChannels = async () => {
  if (!canInstallStorage.value || scanning.value) return
  scanning.value = true
  scanMessage.value = null
  try {
    await client.storage.install()
    await loadPage()
    scanMessage.value = { type: 'success', text: '存储渠道扫描完成，列表已刷新。' }
  } catch (cause) {
    scanMessage.value = { type: 'error', text: errorMessage(cause, '存储渠道扫描失败，请稍后重试。') }
  } finally {
    scanning.value = false
  }
}

onMounted(() => {
  void loadPage()
})
</script>
