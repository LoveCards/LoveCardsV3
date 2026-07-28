<template>
	<v-dialog v-model="thisDialogState" max-width="600">
		<v-card prepend-icon="mdi-file-edit" title="修改文件">
			<v-card-text>
				<v-row dense>
					<v-col cols="12" sm="6">
						<v-text-field label="文件名" :model-value="FileData.edit.original_name"
							variant="underlined" color="accent" readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="大小" :model-value="formatSize(FileData.edit.file_size)"
							variant="underlined" color="accent" readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="场景" :model-value="FileData.edit.scene"
							variant="underlined" color="accent" readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="渠道" :model-value="FileData.edit.channel_slug"
							variant="underlined" color="accent" readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="上传者" :model-value="FileData.edit.user_id ? '#' + FileData.edit.user_id : '-'"
							variant="underlined" color="accent" readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="创建时间" :model-value="FileData.edit.created_at"
							variant="underlined" color="accent" readonly></v-text-field>
					</v-col>

					<v-col cols="12">
						<v-divider class="my-2"></v-divider>
					</v-col>

					<v-col cols="12" sm="6">
						<v-select label="审核状态" item-title="title" item-value="value"
							v-model="editStatus" :items="SelectUtils.Files.status"
							variant="underlined" color="accent"></v-select>
					</v-col>
					<v-col cols="12" sm="6">
						<v-select label="公开状态" item-title="title" item-value="value"
							v-model="editIsPublic" :items="publicOptions"
							variant="underlined" color="accent"></v-select>
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
import SelectUtils from "~/api/utils/select";
const client = useApi();

const props = defineProps({
	getTableData: Function
});
const getTableData = () => {
	if (props.getTableData) props.getTableData();
};

const thisDialogState = defineModel<boolean>('thisDialogState');
const FileData = defineModel<any>('FileData');

const editStatus = ref(0);
const editIsPublic = ref(0);

const publicOptions = [
	{ title: '私有', value: 0 },
	{ title: '公开', value: 1 },
];

const formatSize = (bytes: number) => {
	if (!bytes) return '0 B';
	if (bytes < 1024) return bytes + ' B';
	if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
	return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const submit = () => {
	const ids = [FileData.value.edit.id];
	const ops: Promise<any>[] = [];

	// 审核状态变更
	if (editStatus.value !== FileData.value.origin.status) {
		ops.push(client.files.batch({ ids, method: editStatus.value === 1 ? 'ban' : 'approve' }));
	}

	// 公开状态变更
	if (editIsPublic.value !== FileData.value.origin.is_public) {
		ops.push(client.files.batch({ ids, method: 'toggle_public' }));
	}

	if (ops.length === 0) {
		thisDialogState.value = false;
		return;
	}

	Promise.all(ops).then(() => {
		thisDialogState.value = false;
		getTableData();
	});
};

watch(thisDialogState, (val) => {
	if (val && FileData.value?.edit) {
		editStatus.value = FileData.value.edit.status;
		editIsPublic.value = FileData.value.edit.is_public;
	}
});
</script>
