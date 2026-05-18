<template>
	<v-dialog v-model="thisDialogState" max-width="800">
		<v-card prepend-icon="mdi-key-edit" title="编辑权限">
			<v-card-text>
				<v-row dense>
					<v-col cols="12" sm="6">
						<v-text-field label="名称" v-model="EditData.edit.name" variant="underlined" color="accent"></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="Slug" v-model="EditData.edit.slug" variant="underlined" color="accent"></v-text-field>
					</v-col>
					<v-col cols="12" sm="8">
						<v-text-field label="路由标识" v-model="EditData.edit.route_name" variant="underlined" color="accent"></v-text-field>
					</v-col>
					<v-col cols="12" sm="4">
						<v-select label="Method" v-model="EditData.edit.method" :items="methodOptions"
							item-title="title" item-value="value" variant="underlined" color="accent"></v-select>
					</v-col>
					<v-col cols="12">
						<v-textarea label="描述" v-model="EditData.edit.description" variant="underlined" color="accent"
							rows="2"></v-textarea>
					</v-col>
				</v-row>
			</v-card-text>
			<v-divider></v-divider>
			<v-card-actions>
				<v-spacer></v-spacer>
				<v-btn color="accent" text="取消" variant="text" @click="thisDialogState = false"></v-btn>
				<v-btn color="accent" text="保存" variant="flat" @click="submit()"></v-btn>
			</v-card-actions>
		</v-card>
	</v-dialog>
</template>

<script setup lang="ts">
import CommonUtils from "@/api/utils/common";
import PermissionsApi from "~/api/app/admin/permissions";

const notifier = useNotifier();

const props = defineProps({
	getTableData: Function
});
const getTableData = () => {
	if (props.getTableData) props.getTableData();
};

const thisDialogState = defineModel<boolean>('thisDialogState');
const PermissionData = defineModel('PermissionData');
const EditData = ref({} as any);

const methodOptions = [
	{ title: 'GET', value: 'GET' },
	{ title: 'POST', value: 'POST' },
	{ title: 'PUT', value: 'PUT' },
	{ title: 'PATCH', value: 'PATCH' },
	{ title: 'DELETE', value: 'DELETE' },
	{ title: '* (通配)', value: '*' },
];

const submit = () => {
	let editData = CommonUtils.deepClone(EditData.value.edit);
	let originData = CommonUtils.deepClone(EditData.value.origin);
	let params = CommonUtils.removeCommonProperties(editData, originData);

	if (Object.keys(params).length === 0) {
		notifier.toast({ type: 'warning', text: '请修改后再提交' });
		return;
	}
	params.id = editData.id;

	PermissionsApi.patchPermission(params).then(() => {
		thisDialogState.value = false;
		getTableData();
	}).catch(() => {});
};

watch(thisDialogState, (val) => {
	if (val) {
		EditData.value.origin = CommonUtils.deepClone(PermissionData.value);
		EditData.value.edit = CommonUtils.deepClone(PermissionData.value);
	}
});
</script>
