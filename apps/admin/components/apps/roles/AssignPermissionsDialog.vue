<template>
	<v-dialog v-model="thisDialogState" max-width="900" scrollable>
		<v-card prepend-icon="mdi-shield-key" title="分配权限">
			<v-card-text>
				<v-row dense v-if="loading">
					<v-col cols="12" class="d-flex justify-center pa-4">
						<v-progress-circular indeterminate color="accent"></v-progress-circular>
					</v-col>
				</v-row>
				<template v-else>
					<v-row dense class="mb-2">
						<v-col cols="12" class="d-flex justify-end">
							<v-btn size="x-small" variant="tonal" color="accent" @click="toggleSelectAll">
								{{ isAllSelected ? '取消全选' : '全选' }}
							</v-btn>
						</v-col>
					</v-row>
					<v-divider class="mb-2"></v-divider>
					<v-row dense>
						<v-col cols="12" v-for="(group, resource) in groupedPermissions" :key="resource">
							<div class="text-subtitle-2 mb-1 font-weight-bold" style="color: #3F51B5;">{{ resource }}</div>
							<div class="d-flex flex-wrap ga-2 mb-2">
								<v-checkbox v-for="perm in group" :key="perm.capability" v-model="selectedCapabilities"
									:value="perm.capability" density="compact" hide-details color="accent">
									<template v-slot:label>
										<span class="text-caption font-weight-medium">{{ perm.description }}</span>
										<code class="text-caption ml-1 text-grey">{{ perm.capability }}</code>
									</template>
								</v-checkbox>
							</div>
							<v-divider class="mb-1"></v-divider>
						</v-col>
					</v-row>
				</template>
			</v-card-text>
			<v-divider></v-divider>
			<v-card-actions>
				<v-spacer></v-spacer>
				<v-btn color="accent" text="取消" variant="text" @click="thisDialogState = false"></v-btn>
				<v-btn color="accent" text="保存" variant="flat" :loading="saving" @click="submit()"></v-btn>
			</v-card-actions>
		</v-card>
	</v-dialog>
</template>

<script setup lang="ts">
import type { CapabilityItem } from '@lovecards/sdk';
import { useApi } from '~/lib/api';
const client = useApi();
const notifier = useNotifier();

const props = defineProps({
	getTableData: Function
});
const getTableData = () => {
	if (props.getTableData) props.getTableData();
};

const thisDialogState = defineModel<boolean>('thisDialogState');
const RoleId = defineModel<number>('RoleId');

const loading = ref(false);
const saving = ref(false);
const allPermissions = ref<CapabilityItem[]>([]);
const selectedCapabilities = ref<string[]>([]);

const groupedPermissions = computed(() => {
	const groups: Record<string, CapabilityItem[]> = {};
	allPermissions.value.forEach((p) => {
		const prefix = p.capability.split('.')[0] || 'other';
		if (!groups[prefix]) {
			groups[prefix] = [];
		}
		groups[prefix]!.push(p);
	});
	return groups;
});

const isAllSelected = computed(() => {
	return allPermissions.value.length > 0 && allPermissions.value.every(p => selectedCapabilities.value.includes(p.capability));
});

const toggleSelectAll = () => {
	if (isAllSelected.value) {
		selectedCapabilities.value = [];
	} else {
		selectedCapabilities.value = allPermissions.value.map(p => p.capability);
	}
};

const submit = () => {
	if (!RoleId.value) return;
	saving.value = true;
	client.roles.assignCapabilities(RoleId.value, {
		capabilities: selectedCapabilities.value,
	}).then(() => {
		thisDialogState.value = false;
		notifier.toast({ type: 'success', text: '权限分配成功' });
		getTableData();
	}).catch(() => {}).finally(() => {
		saving.value = false;
	});
};

watch(thisDialogState, async (val) => {
	if (val && RoleId.value) {
		loading.value = true;
		selectedCapabilities.value = [];
		allPermissions.value = [];
		try {
			const [roleHashes, allPerms] = await Promise.all([
				client.roles.getCapabilities(RoleId.value),
				client.permissions.all(),
			]);
			allPermissions.value = Array.isArray(allPerms) ? allPerms : Object.values(allPerms);
			selectedCapabilities.value = Array.isArray(roleHashes) ? roleHashes : Object.values(roleHashes);
		} catch (e) {
			notifier.toast({ type: 'error', text: '加载权限数据失败' });
		} finally {
			loading.value = false;
		}
	}
});
</script>
