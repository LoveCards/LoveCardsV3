<!-- 主题配置 -->
<template>
	<NuxtLayout name="root">
		<!-- 标题 -->
		<v-row class="pt-2">
			<v-col cols="6">
				<h1 class="text-primary font-weight-bold">配置主题</h1>
			</v-col>
		</v-row>

		<v-row>
			<v-col cols="12">
				<v-skeleton-loader v-if="loading" type="card" />
				<v-card v-else>
					<v-card-text>
						<div v-for="(schema, key) in configSchema" :key="key" class="mb-4">
							<!-- color -->
							<v-text-field v-if="schema.type === 'color'"
								:label="schema.label"
								v-model="configValues[key]"
								variant="underlined" color="accent"
								:type="'color'"
								hide-details="auto" />

							<!-- text -->
							<v-text-field v-else-if="schema.type === 'text'"
								:label="schema.label"
								v-model="configValues[key]"
								variant="underlined" color="accent"
								hide-details="auto" />

							<!-- select -->
							<v-select v-else-if="schema.type === 'select'"
								:label="schema.label"
								v-model="configValues[key]"
								:items="schema.options || []"
								item-title="label" item-value="value"
								variant="underlined" color="accent"
								hide-details="auto" />

							<!-- toggle -->
							<v-switch v-else-if="schema.type === 'toggle'"
								:label="schema.label"
								v-model="configValues[key]"
								color="accent"
								hide-details="auto" />

							<!-- image -->
							<v-text-field v-else-if="schema.type === 'image'"
								:label="schema.label"
								v-model="configValues[key]"
								variant="underlined" color="accent"
								hide-details="auto"
								placeholder="URL" />
						</div>
					</v-card-text>

					<v-divider></v-divider>

					<v-card-actions class="pa-4">
						<v-btn color="accent" variant="tonal" @click="freezeConfig" :loading="freezeLoading">
							固化配置
						</v-btn>
						<v-spacer></v-spacer>
						<v-btn color="accent" variant="flat" @click="saveConfig" :loading="saveLoading">
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
const loading = ref(true);
const saveLoading = ref(false);
const freezeLoading = ref(false);
const saveResult = ref<{ success: boolean; message: string } | null>(null);

const configSchema = ref<Record<string, any>>({});
const configValues = ref<Record<string, any>>({});

const loadConfig = () => {
	loading.value = true;
	client.theme.config()
		.then((result) => {
			configSchema.value = result.config_schema || {};
			configValues.value = result.config_values || {};
		})
		.finally(() => {
			loading.value = false;
		});
};

const saveConfig = () => {
	saveLoading.value = true;
	saveResult.value = null;
	client.theme.updateConfig(configValues.value)
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

const freezeConfig = () => {
	freezeLoading.value = true;
	client.theme.freeze()
		.then(() => {
			saveResult.value = { success: true, message: '固化成功' };
		})
		.catch(() => {
			saveResult.value = { success: false, message: '固化失败' };
		})
		.finally(() => {
			freezeLoading.value = false;
		});
};

onMounted(() => {
	loadConfig();
});
</script>
