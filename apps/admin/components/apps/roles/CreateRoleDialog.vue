<template>
	<v-dialog v-model="thisDialogState" max-width="800">
		<v-card prepend-icon="mdi-shield-plus" title="创建角色">
			<v-card-text>
				<v-row dense>
					<v-col cols="12" sm="6">
						<v-text-field label="名称 *" v-model="CreateData.name" variant="underlined" color="accent"
							hint="1-50字符，支持中文/字母/数字/下划线/横线" persistent-hint></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="Slug *" v-model="CreateData.slug" variant="underlined" color="accent"
							hint="1-50字符，字母/数字/下划线/横线，唯一标识" persistent-hint></v-text-field>
					</v-col>
					<v-col cols="12">
						<v-textarea label="描述" v-model="CreateData.description" variant="underlined" color="accent"
							rows="2" hint="最多255字符" persistent-hint></v-textarea>
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
import { useApi } from '~/lib/api';
const client = useApi();

const props = defineProps({
	getTableData: Function
});
const getTableData = () => {
	if (props.getTableData) props.getTableData();
};

const thisDialogState = defineModel<boolean>('thisDialogState');
const CreateData = ref({ name: '', slug: '', description: '' });

const submit = () => {
	client.roles.create(CreateData.value).then(() => {
		thisDialogState.value = false;
		CreateData.value = { name: '', slug: '', description: '' };
		getTableData();
	}).catch(() => {});
};
</script>
