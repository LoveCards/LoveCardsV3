<template>
	<!-- 批量删除确认对话框 -->
	<v-dialog v-model="thisDialogState" max-width="400">
		<v-card>
			<v-card-title class="text-h5"> 批量删除确认 </v-card-title>
			<v-card-text>
				确定要删除选中的 {{ batchUserData.length }} 个用户吗？此操作不可恢复。
			</v-card-text>
			<v-card-actions>
				<v-spacer></v-spacer>
				<v-btn color="accent" text="取消" variant="text" @click="thisDialogState = false"></v-btn>
				<v-btn color="accent" text="删除" variant="flat" @click="submitBatchDeleteUsers"></v-btn>
			</v-card-actions>
		</v-card>
	</v-dialog>
</template>

<script setup lang="ts">
import UserApi from "@/api/app/admin/users";

//Props
const props = defineProps({
	getTableData: Function,
});
const getTableData = () => {
	if (props.getTableData) {
		props.getTableData();
	}
};

//Model
const thisDialogState = defineModel<boolean>('thisDialogState');
const batchUserData = defineModel<any>('batchUserData');

//操作
// 提交批量删除用户
const submitBatchDeleteUsers = () => {
	// 提取选中用户的ID
	const selectedIds = batchUserData.value;

	UserApi.deleteUser(selectedIds).then(() => {
		// 关闭对话框并刷新列表
		thisDialogState.value = false;
		// 清空选择
		batchUserData.value = [];
		getTableData();
	});
};
</script>