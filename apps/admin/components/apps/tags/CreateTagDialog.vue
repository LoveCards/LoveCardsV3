<template>
	<!-- 创建对话框 -->
	<v-dialog v-model="thisDialogState" max-width="800">
		<v-card prepend-icon="mdi-file-edit" title="创建标签">
			<v-card-text>
				<v-row dense>
					<v-col cols="6" sm="6">
						<v-text-field label="应用ID" v-model="CreateTagData.aid" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>

					<v-col cols="6" sm="6">
						<v-text-field label="用户ID" v-model="CreateTagData.user_id" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>

					<v-col cols="12" sm="12">
						<v-text-field label="标签名" v-model="CreateTagData.name" variant="underlined" color="accent"></v-text-field>
					</v-col>

				</v-row>
				<!-- <small class="text-caption text-medium-emphasis"> * 为必填字段</small> -->
			</v-card-text>

			<v-divider></v-divider>

			<v-card-actions>
				<v-spacer></v-spacer>
				<v-btn color="accent" text="取消" variant="text" @click="thisDialogState = false"></v-btn>
				<v-btn color="accent" text="保存" variant="flat" @click="viewSubmitEditComment()"></v-btn>
			</v-card-actions>
		</v-card>
	</v-dialog>
</template>

<script setup lang="ts">
import TagsApi from "~/api/app/admin/tags";

const notifier = useNotifier();

//Props
const props = defineProps({
	getTableData: Function
});
const getTableData = () => {
	if (props.getTableData) {
		props.getTableData();
	}
};

//Model
//对话框状态
const thisDialogState = defineModel<boolean>('thisDialogState');
const CreateTagData = ref({ aid: 1 } as any);

//页面操作提交修改
const viewSubmitEditComment = () => {
	createComment().then((response) => {
		thisDialogState.value = false;
		getTableData();
	}).catch((error) => {
		if (error.notifier) {
			notifier.toast(error.notifier);
		}
	});
};

//API
//数据
const createComment = () => {
	// if (Object.keys(params).length === 0) {
	// 	return Promise.reject({
	// 		notifier: {
	// 			text: "请修改后再提交",
	// 			type: "warning",
	// 		}
	// 	});
	// }
	const params = CreateTagData.value;
	//返回原生Promise
	return TagsApi.createTag(params);
}
</script>