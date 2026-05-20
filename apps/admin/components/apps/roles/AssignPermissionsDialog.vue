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
								<v-checkbox v-for="perm in group" :key="perm.hash" v-model="selectedHashes"
									:value="perm.hash" density="compact" hide-details color="accent"
									:disabled="perm.public">
									<template v-slot:label>
										<span class="text-caption">{{ perm.name }}</span>
										<v-chip size="x-small" class="ml-1" :color="methodColor(perm.method)">
											{{ perm.method }}
										</v-chip>
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
import RolesApi from "~/api/app/admin/roles";
import PermissionsApi from "~/api/app/admin/permissions";

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
const allPermissions = ref<any[]>([]);
const selectedHashes = ref<string[]>([]);

const groupedPermissions = computed(() => {
	const groups: Record<string, any[]> = {};
	allPermissions.value.forEach((p) => {
		const resource = p.group || 'other';
		if (!groups[resource]) {
			groups[resource] = [];
		}
		groups[resource]!.push(p);
	});
	return groups;
});

const methodColor = (method: string) => {
	const map: Record<string, string> = {
		GET: 'green', POST: 'blue', PATCH: 'orange', PUT: 'amber',
		DELETE: 'red',
	};
	return map[method] || 'grey';
};

const isAllSelected = computed(() => {
	return allPermissions.value.length > 0 && selectedHashes.value.length === allPermissions.value.length;
});

const toggleSelectAll = () => {
	if (isAllSelected.value) {
		selectedHashes.value = allPermissions.value
			.filter(p => p.public)
			.map(p => p.hash);
	} else {
		selectedHashes.value = allPermissions.value.map((p) => p.hash);
	}
};

const submit = () => {
	if (!RoleId.value) return;
	saving.value = true;
	RolesApi.assignPermissions(RoleId.value, {
		permission_hashes: JSON.stringify(selectedHashes.value),
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
		selectedHashes.value = [];
		allPermissions.value = [];
		try {
			const [roleHashes, allPerms] = await Promise.all([
				RolesApi.getRolePermissionHashes(RoleId.value),
				PermissionsApi.getAllPermissions(),
			]);
			const rawAll = allPerms.data || [];
			allPermissions.value = Array.isArray(rawAll) ? rawAll : Object.values(rawAll);
			const rawHashes = roleHashes.data || [];
			selectedHashes.value = Array.isArray(rawHashes) ? rawHashes : Object.values(rawHashes);
			// 公开路由自动选中
			allPermissions.value.forEach(p => {
				if (p.public && !selectedHashes.value.includes(p.hash)) {
					selectedHashes.value.push(p.hash);
				}
			});
		} catch (e) {
			notifier.toast({ type: 'error', text: '加载权限数据失败' });
		} finally {
			loading.value = false;
		}
	}
});
</script>
