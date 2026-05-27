<!-- 存储配置 - 渠道列表 + 基础设置 -->
<template>
	<NuxtLayout name="root">
		<v-row class="pt-2">
			<v-col cols="6">
				<h1 class="text-primary font-weight-bold">存储配置</h1>
			</v-col>
			<v-col cols="6" class="text-right">
				<v-btn size="small" variant="tonal" color="accent"
					prepend-icon="mdi-refresh"
					:loading="scanning"
					@click="scanChannels">
					扫描渠道
				</v-btn>
			</v-col>
		</v-row>

		<v-row>
			<v-col cols="12">
				<v-card>
					<v-tabs v-model="tab" color="accent">
						<v-tab value="channels">渠道管理</v-tab>
						<v-tab value="settings">基础设置</v-tab>
					</v-tabs>

					<v-divider></v-divider>

					<v-tabs-window v-model="tab">
						<!-- 渠道管理 -->
						<v-tabs-window-item value="channels">
							<v-card-text>
								<v-row>
									<v-col v-for="channel in channelList" :key="channel.slug" cols="12" md="6" lg="4">
										<v-card variant="outlined" hover
											:color="channel.slug === settings.default ? 'accent' : undefined"
											:class="{ 'border-accent': channel.slug === settings.default }">
											<v-card-item>
												<template v-slot:prepend>
													<v-icon size="large"
														:color="channel.slug === settings.default ? 'accent' : 'grey'">
														{{ channel.icon }}
													</v-icon>
												</template>
												<v-card-title>{{ channel.name }}</v-card-title>
												<v-card-subtitle>{{ channel.slug }}</v-card-subtitle>
												<template v-slot:append>
													<v-chip v-if="channel.slug === settings.default" size="small" color="accent"
														variant="flat">默认</v-chip>
												</template>
											</v-card-item>

											<v-card-text class="pt-0">
												<div class="d-flex align-center text-caption text-medium-emphasis mb-2">
													<v-icon size="small" class="mr-1">mdi-file-outline</v-icon>
													{{ stats[channel.slug]?.file_count ?? 0 }} 文件
													<v-icon size="small" class="ml-3 mr-1">mdi-database</v-icon>
													{{ formatSize(stats[channel.slug]?.total_size ?? 0) }}
												</div>
												<div class="text-caption text-medium-emphasis">
													限制: {{ formatSize(channels[channel.slug]?.max_file_size ?? 0) }}
												</div>
											</v-card-text>

											<v-divider></v-divider>

											<v-card-actions>
												<v-btn size="small" color="accent" variant="text"
													@click="setDefault(channel.slug)"
													:disabled="channel.slug === settings.default">
													设为默认
												</v-btn>
												<v-spacer></v-spacer>
												<v-btn size="small" color="accent" variant="flat"
													@click="router.push('/apps/storage/config/' + channel.slug)">
													编辑
													<v-icon end size="small">mdi-arrow-right</v-icon>
												</v-btn>
											</v-card-actions>
										</v-card>
									</v-col>
								</v-row>
							</v-card-text>
						</v-tabs-window-item>

						<!-- 基础设置 -->
						<v-tabs-window-item value="settings">
							<v-card-text>
								<v-row>
									<v-col cols="12" md="6">
										<v-card variant="outlined">
											<v-card-item>
												<v-card-title>基本参数</v-card-title>
											</v-card-item>
											<v-card-text>
												<v-select label="默认渠道" item-title="title" item-value="value"
													:items="channelOptions" v-model="settings.default"
													variant="outlined" color="accent" density="comfortable"
													prepend-inner-icon="mdi-cloud-outline" />
											</v-card-text>
										</v-card>
									</v-col>
									<v-col cols="12" md="6">
										<v-card variant="outlined">
											<v-card-item>
												<v-card-title>速率限制</v-card-title>
											</v-card-item>
											<v-card-text>
												<v-row dense>
													<v-col cols="12" sm="6">
														<v-text-field label="最大请求数"
															v-model.number="settings.rate_limit_max"
															variant="outlined" color="accent" density="comfortable"
															type="number" prepend-inner-icon="mdi-speedometer" />
													</v-col>
													<v-col cols="12" sm="6">
														<v-text-field label="时间窗口(秒)"
															v-model.number="settings.rate_limit_window"
															variant="outlined" color="accent" density="comfortable"
															type="number" prepend-inner-icon="mdi-timer-outline" />
													</v-col>
												</v-row>
											</v-card-text>
										</v-card>
									</v-col>
									<v-col cols="12" md="6">
										<v-card variant="outlined">
											<v-card-item>
												<v-card-title>直传配置</v-card-title>
											</v-card-item>
											<v-card-text>
												<v-text-field label="凭证有效期(秒)"
													v-model.number="settings.direct_upload_expire"
													variant="outlined" color="accent" density="comfortable"
													type="number" prepend-inner-icon="mdi-clock-outline" />
											</v-card-text>
										</v-card>
									</v-col>
								</v-row>
							</v-card-text>
							<v-divider></v-divider>
							<v-card-actions class="pa-4">
								<v-spacer></v-spacer>
								<v-btn color="accent" variant="flat" @click="saveSettings"
									prepend-icon="mdi-content-save">保存设置</v-btn>
							</v-card-actions>
						</v-tabs-window-item>
					</v-tabs-window>
				</v-card>
			</v-col>
		</v-row>
	</NuxtLayout>
</template>

<script setup lang="ts">
import SystemApi from '~/api/app/admin/system';
import StorageApi from '~/api/app/admin/storage';
import { formatSize } from '~/utils/storage';

interface ChannelMeta {
	slug: string;
	name: string;
	icon: string;
	fields: Array<{ key: string; label: string; type: string }>;
}

const router = useRouter();
const tab = ref('channels');

const channelList = ref<ChannelMeta[]>([]);
const channels = ref<Record<string, any>>({});
const settings = ref({
	default: 'local',
	rate_limit_max: 10,
	rate_limit_window: 60,
	direct_upload_expire: 3600,
});
const stats = ref<Record<string, { file_count: number; total_size: number }>>({});
const scanning = ref(false);

const channelOptions = computed(() =>
	channelList.value.map(ch => ({ title: ch.name, value: ch.slug }))
);

const loadChannels = () => {
	StorageApi.getStorageChannels()
		.then((result) => {
			channelList.value = result.data ?? [];
		})
		.catch((error) => {
			console.error('加载渠道列表失败:', error);
		});
};

const loadConfig = () => {
	SystemApi.getConfig('')
		.then((result) => {
			const data = result.data;
			settings.value = { ...settings.value, ...data.storage };
			channels.value = {};
			channelList.value.forEach(ch => {
				channels.value[ch.slug] = data['storage_' + ch.slug] ?? {};
			});
		})
		.catch((error) => {
			console.error('加载配置失败:', error);
		});
};

const loadStats = () => {
	StorageApi.getChannelStats()
		.then((result) => {
			stats.value = result.data;
		})
		.catch((error) => {
			console.error('加载统计失败:', error);
		});
};

const setDefault = (slug: string) => {
	SystemApi.postConfig({ storage: { default: slug } })
		.then(() => {
			settings.value.default = slug;
		})
		.catch((error) => {
			console.error('设置默认失败:', error);
		});
};

const saveSettings = () => {
	SystemApi.postConfig({ storage: settings.value })
		.catch((error) => {
			console.error('保存设置失败:', error);
		});
};

const scanChannels = () => {
	scanning.value = true;
	StorageApi.install()
		.then(() => {
			loadChannels();
			loadConfig();
		})
		.catch((error) => {
			console.error('扫描失败:', error);
		})
		.finally(() => {
			scanning.value = false;
		});
};

onMounted(async () => {
	await loadChannels();
	await loadConfig();
	loadStats();
});
</script>

<style scoped>
.border-accent {
	border-width: 2px;
}
</style>
