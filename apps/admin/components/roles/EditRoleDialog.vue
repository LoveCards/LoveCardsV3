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
import CommonUtils from "~/utils/common";
import { useApi } from '~/lib/api';
import { useAdminNotifier } from '~/composables/useAdminNotifier';
import type { Role } from '@lovecards/sdk';
const client = useApi();
const notifier = useAdminNotifier();

const props = defineProps({
	getTableData: Function
});
const getTableData = () => {
	if (props.getTableData) props.getTableData();
};

const thisDialogState = defineModel<boolean>('thisDialogState');
const RoleData = defineModel<Role | null>('RoleData');
const emptyRole = {} as Role;
const EditData = ref<{ edit: Role; origin: Role }>({ edit: emptyRole, origin: emptyRole });

const submit = () => {
	if (!EditData.value.edit.id) return;
	const editData = CommonUtils.deepClone(EditData.value.edit);
	const originData = CommonUtils.deepClone(EditData.value.origin);
	const editInput = { name: editData.name, slug: editData.slug, description: editData.description ?? undefined };
	const originInput = { name: originData.name, slug: originData.slug, description: originData.description ?? undefined };
	const params = CommonUtils.removeCommonProperties(editInput, originInput);

	if (Object.keys(params).length === 0) {
		notifier.toast({ type: 'warning', text: '请修改后再提交' });
		return;
	}
	client.roles.update(editData.id, params).then(() => {
		thisDialogState.value = false;
		getTableData();
	}).catch(() => {});
};

watch(thisDialogState, (val) => {
	if (val) {
		if (RoleData.value) {
			EditData.value = {
				origin: CommonUtils.deepClone(RoleData.value),
				edit: CommonUtils.deepClone(RoleData.value),
			};
		}
	}
});
</script>
