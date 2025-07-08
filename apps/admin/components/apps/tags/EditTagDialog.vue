<template>
	<!-- 编辑用户对话框 -->
	<v-dialog v-model="thisDialogState" max-width="800">
		<v-card prepend-icon="mdi-file-edit" title="修改标签">
			<v-card-text>
				<v-row dense>
					<v-col cols="6" sm="3">
						<v-text-field label="用户ID" v-model="EditTagData.edit.user_id" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="6" sm="3">
						<v-text-field label="应用ID" v-model="EditTagData.edit.aid" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="发布时间" v-model="EditTagData.edit.created_at" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="标签名" v-model="EditTagData.edit.name" variant="underlined"
							color="accent"></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-select label="状态" item-title="title" item-value="value" v-model="EditTagData.edit.status"
							:items="SelectUtils.Common.Status" variant="underlined" color="accent"></v-select>
					</v-col>

					<v-col cols="12">

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
import CommonUtils from "@/api/utils/common";
import TagsApi from "~/api/app/admin/tags";
import SelectUtils from "~/api/utils/select";

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
const TagData = defineModel('TagData');
const EditTagData = ref({} as any);

//页面操作提交修改
const viewSubmitEditComment = () => {
	patchComment().then((response) => {
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
const patchComment = () => {
	//多次一举是因为没办法比较，主要是mysql输出的json和js的json转字符串格式不一样
	let editTagData = CommonUtils.deepClone(EditTagData.value.edit);
	let originTagData = CommonUtils.deepClone(EditTagData.value.origin);
	editTagData.tags = JSON.stringify(editTagData.tags);
	originTagData.tags = JSON.stringify(originTagData.tags);
	//清除不需要提交的属性
	let params = CommonUtils.removeCommonProperties(editTagData, originTagData);

	if (Object.keys(params).length === 0) {
		return Promise.reject({
			notifier: {
				text: "请修改后再提交",
				type: "warning",
			}
		});
	}
	params.id = editTagData.id;

	//返回原生Promise
	return TagsApi.patchTag(params);
}

//数据初始化
watch(thisDialogState, (newValue, oldValue) => {
	if (newValue) {
		EditTagData.value.origin = CommonUtils.deepClone(TagData.value);
		EditTagData.value.edit = CommonUtils.deepClone(TagData.value);
	}
});
</script>