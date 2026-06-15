<template>
	<NuxtLayout name="root">
		<v-row class="pt-2">
			<v-col cols="12">
				<h1 class="text-primary font-weight-bold">{{ driverName }} 配置</h1>
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
						<v-alert v-else-if="!fields.length" type="info" variant="tonal" density="compact">
							此驱动无需配置
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
								<v-switch v-else-if="field.type === 'checkbox'"
									:label="field.label" v-model="config[field.key]"
									color="accent" hide-details="auto" />
							</div>
						</v-form>
					</v-card-text>

					<v-divider></v-divider>

					<v-card-actions class="pa-4">
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
const fields = ref<Array<{ key: string; label: string; type: string; options?: any[] }>>([]);
const driverName = ref(slug);
const showPassword = ref(false);
const loading = ref(true);
const loadError = ref<string | null>(null);
const saveLoading = ref(false);
const saveResult = ref<{ success: boolean; message: string } | null>(null);

const loadMeta = () => {
	client.captcha.drivers()
		.then((drivers) => {
			const driver = drivers.find((d: any) => d.slug === slug);
			if (driver) {
				driverName.value = driver.name;
				fields.value = driver.fields ?? [];
			}
		});
};

const loadConfig = () => {
	loading.value = true;
	loadError.value = null;

	client.config.list()
		.then((data) => {
			if (data && data[group]) {
				config.value = { ...data[group] };
			} else {
				loadError.value = '驱动配置不存在，请先扫描驱动';
			}
		})
		.catch(() => {
			loadError.value = '加载配置失败';
		})
		.finally(() => {
			loading.value = false;
		});
};

const save = () => {
	saveLoading.value = true;
	saveResult.value = null;
	const group = 'captcha_' + slug;
	const configToSave = { ...config.value };
	client.config.update({ [group]: configToSave })
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

onMounted(() => {
	loadMeta();
	loadConfig();
});
</script>
