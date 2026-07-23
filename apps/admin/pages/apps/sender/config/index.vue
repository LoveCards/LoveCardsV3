<!-- 发信配置 - 渠道列表 -->
<template>
	<NuxtLayout name="root">
		<v-row class="pt-2">
			<v-col cols="6">
				<h1 class="text-primary font-weight-bold">发信配置</h1>
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

		<v-row v-for="group in channelGroups" :key="group.channelType">
			<v-col cols="12">
				<h2 class="text-subtitle-1 font-weight-bold mb-2">{{ group.channelType }}</h2>
			</v-col>
			<v-col cols="12">
				<v-card>
					<v-card-text>
						<v-row>
							<v-col v-for="channel in group.channels" :key="channel.slug" cols="12" md="6" lg="4">
								<v-card variant="outlined" hover
									:color="channel.slug === group.defaultSlug ? 'accent' : undefined"
									:class="{ 'border-accent': channel.slug === group.defaultSlug }">
									<v-card-item>
										<template v-slot:prepend>
											<v-icon size="large"
												:color="channel.slug === group.defaultSlug ? 'accent' : 'grey'">
												{{ channel.icon }}
											</v-icon>
										</template>
										<v-card-title>{{ channel.name }}</v-card-title>
										<v-card-subtitle>{{ channel.slug }}</v-card-subtitle>
										<template v-slot:append>
											<v-chip v-if="channel.slug === group.defaultSlug" size="small" color="accent"
												variant="flat">默认</v-chip>
										</template>
									</v-card-item>

									<v-card-text class="pt-0">
										<div class="text-caption text-medium-emphasis">
											{{ channel.fields.length }} 个配置项
										</div>
										<div v-if="channel.supportedTypes?.length" class="mt-1">
											<v-chip v-for="t in channel.supportedTypes" :key="t" size="x-small"
												variant="tonal" color="accent" class="mr-1">
												{{ t }}
											</v-chip>
										</div>
									</v-card-text>

									<v-divider></v-divider>

									<v-card-actions>
										<v-btn size="small" color="accent" variant="text"
											@click="setDefault(group.channelType, channel.slug)"
											:disabled="channel.slug === group.defaultSlug">
											设为默认
										</v-btn>
										<v-spacer></v-spacer>
										<v-btn size="small" color="accent" variant="flat"
											@click="router.push('/apps/sender/config/' + channel.slug)">
											编辑
											<v-icon end size="small">mdi-arrow-right</v-icon>
										</v-btn>
									</v-card-actions>
								</v-card>
							</v-col>
						</v-row>
					</v-card-text>
				</v-card>
			</v-col>
		</v-row>
	</NuxtLayout>
</template>

<script setup lang="ts">
import { useApi } from '~/lib/api';
import type { SenderChannel } from '@lovecards/sdk';
const client = useApi();

const router = useRouter();

interface ChannelGroup {
	channelType: string;
	channels: SenderChannel[];
	defaultSlug: string;
}

const channelGroups = ref<ChannelGroup[]>([]);
const scanning = ref(false);

const loadChannels = () => {
	client.sender.channels()
		.then((channels) => {
			const groups: Record<string, SenderChannelMeta[]> = {};
			for (const ch of channels) {
				const ct = ch.channelType || ch.slug;
				if (!groups[ct]) groups[ct] = [];
				groups[ct].push(ch);
			}
			channelGroups.value = Object.entries(groups).map(([channelType, chs]) => ({
				channelType,
				channels: chs,
				defaultSlug: '',
			}));
		})
		.catch((error) => {
			console.error('加载渠道列表失败:', error);
		});
};

const loadDefaults = () => {
	client.config.list()
		.then((data) => {
			for (const group of channelGroups.value) {
				const key = 'default_' + group.channelType;
				group.defaultSlug = data.sender?.[key] ?? group.channels[0]?.slug ?? '';
			}
		})
		.catch((error) => {
			console.error('加载配置失败:', error);
		});
};

const setDefault = (channelType: string, slug: string) => {
	const key = 'default_' + channelType;
	client.config.update({ sender: { [key]: slug } })
		.then(() => {
			const group = channelGroups.value.find(g => g.channelType === channelType);
			if (group) group.defaultSlug = slug;
		})
		.catch((error) => {
			console.error('设置默认失败:', error);
		});
};

const scanChannels = () => {
	scanning.value = true;
	client.sender.install()
		.then(() => {
			loadChannels();
			loadDefaults();
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
	loadDefaults();
});
</script>

<style scoped>
.border-accent {
	border-width: 2px;
}
</style>
