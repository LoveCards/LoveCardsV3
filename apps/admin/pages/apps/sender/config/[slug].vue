<!-- 渠道配置页面 -->
<template>
	<NuxtLayout name="root">
		<v-row class="pt-2">
			<v-col cols="12">
				<h1 class="text-primary font-weight-bold">{{ channelName }} 配置</h1>
			</v-col>
		</v-row>

		<v-row>
			<v-col cols="12" md="8" lg="6">
				<v-card>
					<v-card-text>
						<v-skeleton-loader v-if="loading" type="card" />
						<v-alert v-else-if="loadError" type="error" variant="tonal" density="compact">
							{{ loadError }}
						</v-alert>
						<v-form v-else>
							<div v-for="field in fields" :key="field.key" class="mb-4">
								<v-text-field v-if="field.type === 'text'"
									:label="field.label" v-model="config[field.key]"
									variant="outlined" color="accent" density="comfortable"
									hide-details="auto" />
								<v-text-field v-else-if="field.type === 'password'"
									:label="field.label" v-model="config[field.key]"
									variant="outlined" color="accent" density="comfortable"
									hide-details="auto"
									:type="showPassword ? 'text' : 'password'"
									:append-inner-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
									@click:append-inner="showPassword = !showPassword" />
								<v-text-field v-else-if="field.type === 'number'"
									:label="field.label" v-model.number="config[field.key]"
									variant="outlined" color="accent" density="comfortable"
									hide-details="auto" type="number" />
								<v-select v-else-if="field.type === 'select'"
									:label="field.label" v-model="config[field.key]"
									:items="field.options ?? []" item-title="label" item-value="value"
									variant="outlined" color="accent" density="comfortable"
									hide-details="auto" />
							</div>
						</v-form>
					</v-card-text>

					<v-divider></v-divider>

					<v-card-actions class="pa-4">
						<v-dialog v-model="testDialog" max-width="400">
							<template v-slot:activator="{ props: dialogProps }">
								<v-btn color="accent" variant="tonal" v-bind="dialogProps"
									:disabled="loading" prepend-icon="mdi-email-fast-outline">
									测试发信
								</v-btn>
							</template>
							<v-card>
								<v-card-title class="text-h6">测试发信</v-card-title>
								<v-card-text>
									<v-text-field label="收件邮箱" v-model="testTo"
										variant="outlined" color="accent" density="comfortable"
										placeholder="your@email.com" hide-details="auto" />
								</v-card-text>
								<v-divider></v-divider>
								<v-card-actions class="pa-4">
									<v-chip v-if="testResult" size="small" :color="testResult.success ? 'success' : 'error'">
										{{ testResult.message }}
									</v-chip>
									<v-spacer></v-spacer>
									<v-btn variant="text" @click="testDialog = false">取消</v-btn>
									<v-btn color="accent" variant="flat" @click="sendTest" :loading="testLoading">
										发送
									</v-btn>
								</v-card-actions>
							</v-card>
						</v-dialog>
						<v-spacer></v-spacer>
						<v-btn color="accent" variant="flat" @click="save" :loading="saveLoading"
							:disabled="loading || loadError !== null"
							prepend-icon="mdi-content-save">
							保存
						</v-btn>
						<v-chip v-if="saveResult" size="small" :color="saveResult.success ? 'success' : 'error'" class="ml-2">
							{{ saveResult.message }}
						</v-chip>
					</v-card-actions>
				</v-card>
			</v-col>
		</v-row>
	</NuxtLayout>
</template>

<script setup lang="ts">
import { useApi } from '~/lib/api';
const client = useApi();

const route = useRoute();
const slug = route.params.slug as string;

const config = ref<Record<string, any>>({});
const fields = ref<Array<{ key: string; label: string; type: string; options?: Array<{ value: string; label: string }> }>>([]);
const channelName = ref(slug);
const showPassword = ref(false);
const loading = ref(true);
const loadError = ref<string | null>(null);
const testLoading = ref(false);
const testResult = ref<{ success: boolean; message: string } | null>(null);
const testDialog = ref(false);
const testTo = ref('');
const saveLoading = ref(false);
const saveResult = ref<{ success: boolean; message: string } | null>(null);

const loadChannelMeta = () => {
	return client.sender.channels()
		.then((channels) => {
			const channel = channels.find((c: any) => c.slug === slug);
			if (channel) {
				channelName.value = channel.name;
				fields.value = channel.fields ?? [];
			}
		});
};

const loadConfig = () => {
	loading.value = true;
	loadError.value = null;
	client.config.list()
		.then((data) => {
			if (data && data['sender_' + slug]) {
				config.value = { ...data['sender_' + slug] };
			} else {
				loadError.value = '渠道配置不存在';
			}
		})
		.catch((error) => {
			loadError.value = '加载配置失败: ' + (error.message || '未知错误');
		})
		.finally(() => {
			loading.value = false;
		});
};

const save = () => {
	saveLoading.value = true;
	saveResult.value = null;
	const configToSave = { ...config.value };
	delete configToSave.type;
	client.config.update({ ['sender_' + slug]: configToSave })
		.then(() => {
			saveResult.value = { success: true, message: '保存成功' };
		})
		.catch(() => {
			saveResult.value = { success: false, message: '保存失败' };
		})
		.finally(() => {
			saveLoading.value = false;
		});
};

const sendTest = () => {
	if (!testTo.value) {
		testResult.value = { success: false, message: '请输入收件邮箱' };
		return;
	}
	testLoading.value = true;
	testResult.value = null;
	client.sender.testChannel({ channel: slug, to: testTo.value })
		.then((result) => {
			testResult.value = result;
		})
		.catch(() => {
			testResult.value = { success: false, message: '测试失败' };
		})
		.finally(() => {
			testLoading.value = false;
		});
};

onMounted(() => {
	loadChannelMeta().finally(() => {
		loadConfig();
	});
});
</script>
