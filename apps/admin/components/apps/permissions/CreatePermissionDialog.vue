<template>
	<v-dialog v-model="thisDialogState" max-width="800">
		<v-card prepend-icon="mdi-key-plus" title="创建权限">
			<v-card-text>
				<v-row dense>
					<v-col cols="12" sm="6">
						<v-text-field label="名称 *" v-model="CreateData.name" variant="underlined" color="accent"
							hint="1-100字符" persistent-hint></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="Slug *" v-model="CreateData.slug" variant="underlined" color="accent"
							hint="1-100字符，字母/数字/下划线/横线，唯一标识" persistent-hint></v-text-field>
					</v-col>
					<v-col cols="12" sm="8">
						<v-text-field label="Path *" v-model="CreateData.path" variant="underlined" color="accent"
							hint="API 路径，如 /api/admin/users" persistent-hint></v-text-field>
					</v-col>
					<v-col cols="12" sm="4">
						<v-select label="Method *" v-model="CreateData.method" :items="methodOptions"
							item-title="title" item-value="value" variant="underlined" color="accent"></v-select>
					</v-col>
					<v-col cols="12">
						<v-textarea label="描述" v-model="CreateData.description" variant="underlined" color="accent"
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
import PermissionsApi from "~/api/app/admin/permissions";

const props = defineProps({
	getTableData: Function
});
const getTableData = () => {
	if (props.getTableData) props.getTableData();
};

const thisDialogState = defineModel<boolean>('thisDialogState');
const CreateData = ref({ name: '', slug: '', path: '', method: 'GET', description: '' });

const methodOptions = [
	{ title: 'GET', value: 'GET' },
	{ title: 'POST', value: 'POST' },
	{ title: 'PUT', value: 'PUT' },
	{ title: 'PATCH', value: 'PATCH' },
	{ title: 'DELETE', value: 'DELETE' },
	{ title: '* (通配)', value: '*' },
];

const submit = () => {
	PermissionsApi.createPermission(CreateData.value).then(() => {
		thisDialogState.value = false;
		CreateData.value = { name: '', slug: '', path: '', method: 'GET', description: '' };
		getTableData();
	}).catch(() => {});
};
</script>
