<template>
	<v-dialog v-model="thisDialogState" max-width="800">
		<v-card prepend-icon="mdi-shield-edit" title="编辑角色">
			<v-card-text>
				<v-row dense>
					<v-col cols="12" sm="6">
						<v-text-field label="名称" v-model="EditData.edit.name" variant="underlined" color="accent"
							hint="1-50字符，支持中文/字母/数字/下划线/横线" persistent-hint></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="Slug" v-model="EditData.edit.slug" variant="underlined" color="accent"
							hint="1-50字符，字母/数字/下划线/横线，唯一标识" persistent-hint></v-text-field>
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
import RolesApi from "~/api/app/admin/roles";

const notifier = useNotifier();

const props = defineProps({
	getTableData: Function
});
const getTableData = () => {
	if (props.getTableData) props.getTableData();
};

const thisDialogState = defineModel<boolean>('thisDialogState');
const RoleData = defineModel('RoleData');
const EditData = ref({} as any);

const submit = () => {
	let editData = CommonUtils.deepClone(EditData.value.edit);
	let originData = CommonUtils.deepClone(EditData.value.origin);
	let params = CommonUtils.removeCommonProperties(editData, originData);

	if (Object.keys(params).length === 0) {
		notifier.toast({ type: 'warning', text: '请修改后再提交' });
		return;
	}
	params.id = editData.id;

	RolesApi.patchRole(params).then(() => {
		thisDialogState.value = false;
		getTableData();
	}).catch(() => {});
};

watch(thisDialogState, (val) => {
	if (val) {
		EditData.value.origin = CommonUtils.deepClone(RoleData.value);
		EditData.value.edit = CommonUtils.deepClone(RoleData.value);
	}
});
</script>
