<template>
	<NuxtLayout name="root">
		<v-row class="pt-2">
			<v-col cols="6">
				<h1 class="text-primary font-weight-bold">验证配置</h1>
			</v-col>
			<v-col cols="6" class="text-right">
				<v-btn size="small" variant="tonal" color="accent"
					prepend-icon="mdi-refresh"
					:loading="scanning"
					@click="scanDrivers">
					扫描驱动
				</v-btn>
			</v-col>
		</v-row>

		<v-row>
			<v-col cols="12">
				<v-card>
					<v-tabs v-model="tab" color="accent">
						<v-tab value="drivers">驱动管理</v-tab>
						<v-tab value="settings">基础设置</v-tab>
					</v-tabs>

					<v-divider></v-divider>

					<v-tabs-window v-model="tab">
						<!-- 驱动管理 -->
						<v-tabs-window-item value="drivers">
							<v-card-text>
								<v-row v-for="group in driverGroups" :key="group.type">
									<v-col cols="12">
										<h2 class="text-subtitle-1 font-weight-bold mb-2">
											{{ group.type === 'code' ? '验证码' : '人机验证' }}
										</h2>
									</v-col>
									<v-col cols="12">
										<v-card>
											<v-card-text>
												<v-row>
													<v-col v-for="driver in group.drivers" :key="driver.slug" cols="12" md="6" lg="4">
														<v-card variant="outlined" hover
															:color="driver.slug === group.defaultSlug ? 'accent' : undefined"
															:class="{ 'border-accent': driver.slug === group.defaultSlug }">
															<v-card-item>
																<template v-slot:prepend>
																	<v-icon size="large"
																		:color="driver.slug === group.defaultSlug ? 'accent' : 'grey'">
																		{{ driver.icon }}
																	</v-icon>
																</template>
																<v-card-title>{{ driver.name }}</v-card-title>
																<v-card-subtitle>{{ driver.slug }}</v-card-subtitle>
																<template v-slot:append>
																	<v-chip v-if="driver.slug === group.defaultSlug" size="small" color="accent"
																		variant="flat">默认</v-chip>
																</template>
															</v-card-item>

															<v-card-text class="pt-0">
																<div class="text-caption text-medium-emphasis">
																	{{ driver.fields?.length ?? 0 }} 个配置项
																</div>
															</v-card-text>

															<v-divider></v-divider>

															<v-card-actions>
																<v-btn size="small" color="accent" variant="text"
																	@click="setDefault(group.type, driver.slug)"
																	:disabled="driver.slug === group.defaultSlug">
																	设为默认
																</v-btn>
																<v-spacer></v-spacer>
																<v-btn size="small" color="accent" variant="flat"
																	:disabled="!driver.fields?.length"
																	@click="openConfig(driver.slug)">
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
							</v-card-text>
						</v-tabs-window-item>

						<!-- 基础设置 -->
						<v-tabs-window-item value="settings">
							<v-card-text>
								<v-row dense>
									<v-col cols="12" sm="6">
										<v-select label="验证码功能" item-title="title" item-value="value"
											v-model="settings.code_enabled" :items="switchItems"
											variant="underlined">
										</v-select>
									</v-col>
									<v-col cols="12" sm="6">
										<v-select label="人机验证功能" item-title="title" item-value="value"
											v-model="settings.captcha_enabled" :items="switchItems"
											variant="underlined">
										</v-select>
									</v-col>
									<v-col cols="12" sm="6">
										<v-select label="验证码发送渠道" item-title="title" item-value="value"
											v-model="settings.code_channel" :items="channelItems"
											variant="underlined">
										</v-select>
									</v-col>
									<v-col cols="12" sm="6">
										<v-select label="注册验证码" item-title="title" item-value="value"
											v-model="settings.user_captcha" :items="switchItems"
											variant="underlined">
										</v-select>
									</v-col>
								</v-row>
								<v-row>
									<v-col cols="12">
										<v-btn @click="saveSettings" class="float-right" color="accent">提交</v-btn>
									</v-col>
								</v-row>
							</v-card-text>
						</v-tabs-window-item>
					</v-tabs-window>
				</v-card>
			</v-col>
		</v-row>
	</NuxtLayout>
</template>

<script setup lang="ts">
import CaptchaApi from '~/api/app/admin/captcha';
import SystemApi from '~/api/app/admin/system';

const router = useRouter();
const tab = ref('drivers');
const scanning = ref(false);

interface DriverMeta {
	slug: string;
	type: string;
	name: string;
	icon: string;
	fields?: Array<{ key: string; label: string; type: string }>;
}

interface DriverGroup {
	type: string;
	drivers: DriverMeta[];
	defaultSlug: string;
}

const driverGroups = ref<DriverGroup[]>([]);
const settings = ref({
	code_enabled: true,
	captcha_enabled: true,
	code_channel: 'smtp',
	user_captcha: false,
});

const switchItems = [
	{ title: '开启', value: true },
	{ title: '关闭', value: false },
];

const channelItems = [
	{ title: '邮箱 (SMTP)', value: 'smtp' },
	{ title: '短信 (SMS)', value: 'sms' },
];

const loadDrivers = () => {
	CaptchaApi.getDrivers()
		.then((result) => {
			const drivers: DriverMeta[] = result.data ?? [];
			const groups: Record<string, DriverMeta[]> = {};
			for (const d of drivers) {
				const t = d.type || 'unknown';
				if (!groups[t]) groups[t] = [];
				groups[t].push(d);
			}
			driverGroups.value = Object.entries(groups).map(([type, drs]) => ({
				type,
				drivers: drs,
				defaultSlug: '',
			}));
		});
};

const loadSettings = () => {
	SystemApi.getConfig('captcha')
		.then((result) => {
			const data = result.data;
			settings.value.code_enabled = data.captcha?.code_enabled ?? true;
			settings.value.captcha_enabled = data.captcha?.captcha_enabled ?? true;
			settings.value.code_channel = data.captcha?.code_channel ?? 'smtp';
		});
	SystemApi.getConfig('user')
		.then((result) => {
			settings.value.user_captcha = result.data?.user?.captcha ?? false;
		});
};

const loadDefaults = () => {
	SystemApi.getConfig('captcha')
		.then((result) => {
			const data = result.data;
			for (const group of driverGroups.value) {
				const key = 'default_' + group.type;
				group.defaultSlug = data.captcha?.[key] ?? group.drivers[0]?.slug ?? '';
			}
		});
};

const setDefault = (type: string, slug: string) => {
	const key = 'default_' + type;
	SystemApi.postConfig({ captcha: { [key]: slug } })
		.then(() => {
			const group = driverGroups.value.find(g => g.type === type);
			if (group) group.defaultSlug = slug;
		});
};

const openConfig = (slug: string) => {
	router.push('/apps/captcha/config/' + slug);
};

const saveSettings = () => {
	SystemApi.postConfig({
		captcha: {
			code_enabled: settings.value.code_enabled,
			captcha_enabled: settings.value.captcha_enabled,
			code_channel: settings.value.code_channel,
		},
		user: {
			captcha: settings.value.user_captcha,
		},
	});
};

const scanDrivers = () => {
	scanning.value = true;
	CaptchaApi.install()
		.then(() => {
			loadDrivers();
			loadDefaults();
		})
		.finally(() => {
			scanning.value = false;
		});
};

onMounted(async () => {
	await loadDrivers();
	loadDefaults();
	loadSettings();
});
</script>

<style scoped>
.border-accent {
	border-width: 2px;
}
</style>
