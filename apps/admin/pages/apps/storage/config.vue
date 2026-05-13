<!-- 存储配置 -->
<template>
  <NuxtLayout name="root">
    <!-- 标题 -->
    <v-row class="pt-2">
      <v-col cols="6">
        <h1 class="text-primary font-weight-bold">存储配置</h1>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12">
        <v-card class="pa-4">
          <v-tabs v-model="tab" color="accent">
            <v-tab value="channels">渠道管理</v-tab>
            <v-tab value="settings">基础设置</v-tab>
          </v-tabs>

          <v-divider></v-divider>

          <v-tabs-window v-model="tab">
            <!-- 渠道管理 -->
            <v-tabs-window-item value="channels">
              <v-row class="mt-4">
                <v-col v-for="(channel, key) in channels" :key="key" cols="12" md="6" lg="4">
                  <v-card variant="outlined" :color="key === settings.default ? 'accent' : undefined"
                    :class="{ 'border-accent': key === settings.default }">
                    <v-card-title class="d-flex align-center">
                      <v-icon class="mr-2" :color="key === settings.default ? 'accent' : ''">
                        {{ channelIcon(channel.type) }}
                      </v-icon>
                      {{ channelName(key as string) }}
                      <v-chip v-if="key === settings.default" size="x-small" color="accent" class="ml-2">
                        默认
                      </v-chip>
                    </v-card-title>
                    <v-card-text>
                      <v-list density="compact">
                        <v-list-item>
                          <v-list-item-title class="text-caption">类型</v-list-item-title>
                          <v-list-item-subtitle>{{ channel.type }}</v-list-item-subtitle>
                        </v-list-item>
                        <v-list-item>
                          <v-list-item-title class="text-caption">MIME 限制</v-list-item-title>
                          <v-list-item-subtitle class="text-wrap">{{ channel.allow_mime_types }}</v-list-item-subtitle>
                        </v-list-item>
                        <v-list-item>
                          <v-list-item-title class="text-caption">大小限制</v-list-item-title>
                          <v-list-item-subtitle>{{ formatSize(channel.max_file_size) }}</v-list-item-subtitle>
                        </v-list-item>
                      </v-list>
                    </v-card-text>
                  </v-card>
                </v-col>
              </v-row>
            </v-tabs-window-item>

            <!-- 基础设置 -->
            <v-tabs-window-item value="settings">
              <v-row class="mt-4">
                <v-col cols="12" md="6">
                  <v-card variant="outlined">
                    <v-card-title>基本参数</v-card-title>
                    <v-card-text>
                      <v-list density="compact">
                        <v-list-item>
                          <v-list-item-title>默认渠道</v-list-item-title>
                          <template v-slot:append>
                            <v-chip size="small" color="accent">{{ settings.default }}</v-chip>
                          </template>
                        </v-list-item>
                      </v-list>
                    </v-card-text>
                  </v-card>
                </v-col>
                <v-col cols="12" md="6">
                  <v-card variant="outlined">
                    <v-card-title>速率限制</v-card-title>
                    <v-card-text>
                      <v-list density="compact">
                        <v-list-item>
                          <v-list-item-title>最大请求数</v-list-item-title>
                          <template v-slot:append>
                            <v-chip size="small">{{ settings.rate_limit?.max ?? '-' }}</v-chip>
                          </template>
                        </v-list-item>
                        <v-list-item>
                          <v-list-item-title>时间窗口（秒）</v-list-item-title>
                          <template v-slot:append>
                            <v-chip size="small">{{ settings.rate_limit?.window ?? '-' }}</v-chip>
                          </template>
                        </v-list-item>
                      </v-list>
                    </v-card-text>
                  </v-card>
                </v-col>
                <v-col cols="12" md="6">
                  <v-card variant="outlined">
                    <v-card-title>直传配置</v-card-title>
                    <v-card-text>
                      <v-list density="compact">
                        <v-list-item>
                          <v-list-item-title>凭证过期时间（秒）</v-list-item-title>
                          <template v-slot:append>
                            <v-chip size="small">{{ settings.direct_upload?.expire ?? '-' }}</v-chip>
                          </template>
                        </v-list-item>
                      </v-list>
                    </v-card-text>
                  </v-card>
                </v-col>
              </v-row>
            </v-tabs-window-item>
          </v-tabs-window>
        </v-card>
      </v-col>
    </v-row>
  </NuxtLayout>
</template>

<script setup lang="ts">
const tab = ref('channels');

// 存储渠道配置（静态展示，后续对接 API）
const channels = ref<Record<string, any>>({
  local: {
    type: 'local',
    root: 'public/storage',
    url_prefix: '/storage',
    allow_mime_types: 'image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    max_file_size: 10485760,
  },
  oss: {
    type: 'oss',
    allow_mime_types: 'image/jpeg,image/png,image/gif,image/webp',
    max_file_size: 52428800,
  },
  cos: {
    type: 'cos',
    allow_mime_types: 'image/jpeg,image/png,image/gif,image/webp',
    max_file_size: 52428800,
  },
  qiniu: {
    type: 'qiniu',
    allow_mime_types: 'image/jpeg,image/png,image/gif,image/webp',
    max_file_size: 52428800,
  },
  smms: {
    type: 'api',
    allow_mime_types: 'image/jpeg,image/png,image/gif,image/webp',
    max_file_size: 10485760,
  },
});

// 基础设置（静态展示）
const settings = ref({
  default: 'cos',
  rate_limit: { max: 10, window: 60 },
  direct_upload: { expire: 3600 },
});

// 辅助函数
const channelName = (key: string) => {
  const map: Record<string, string> = {
    local: '本地存储',
    oss: '阿里云 OSS',
    cos: '腾讯云 COS',
    qiniu: '七牛云',
    smms: 'SM.MS 图床',
  };
  return map[key] || key;
};

const channelIcon = (type: string) => {
  const map: Record<string, string> = {
    local: 'mdi-harddisk',
    oss: 'mdi-cloud',
    cos: 'mdi-cloud',
    qiniu: 'mdi-cloud',
    api: 'mdi-api',
  };
  return map[type] || 'mdi-cloud-outline';
};

const formatSize = (bytes: number) => {
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(0) + ' MB';
};
</script>

<style scoped>
.border-accent {
  border-width: 2px;
}
</style>
