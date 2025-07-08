<template>
	<!-- 删除确认对话框 -->
	<v-dialog v-model="thisDialogState" max-width="400">
		<v-card>
			<v-card-title class="text-h5"> 确认删除 </v-card-title>
			<v-card-text>
				确定要删除用户 "{{ delUserData.username }}"
				吗？此操作不可恢复。
			</v-card-text>
			<v-card-actions>
				<v-spacer></v-spacer>
				<v-btn color="accent" text="取消" variant="text" @click="thisDialogState = false"></v-btn>
				<v-btn color="accent" text="删除" variant="flat" @click="submitDeleteUser"></v-btn>
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
const delUserData = defineModel<any>('delUserData');

//操作
//提交删除用户
const submitDeleteUser = () => {
	UserApi.deleteUser(delUserData.value.id).then(() => {
		//关闭对话框并刷新列表
		thisDialogState.value = false;
		getTableData();
	});
};

//API
</script>