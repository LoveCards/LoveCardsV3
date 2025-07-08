<template>
	<!-- 编辑用户对话框 -->
	<v-dialog v-model="thisDialogState" max-width="1200">
		<v-card prepend-icon="mdi-file-edit" title="修改评论">
			<v-card-text>
				<v-row dense>
					<v-col cols="12" sm="6">
						<v-text-field label="用户ID" v-model="EditCommentData.edit.user_id" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="发布时间" v-model="EditCommentData.edit.created_at" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="发布IP" v-model="EditCommentData.edit.post_ip" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>

					<v-col cols="12" sm="3">
						<v-select label="置顶状态" item-title="title" item-value="value" v-model="EditCommentData.edit.is_top"
							:items="SelectUtils.Common.Top" variant="underlined" color="accent"></v-select>
					</v-col>
					<v-col cols="12" sm="3">
						<v-select label="封禁状态" item-title="title" item-value="value" v-model="EditCommentData.edit.status"
							:items="SelectUtils.Common.Status" variant="underlined" color="accent"></v-select>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="喜欢" v-model="EditCommentData.edit.goods" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6"></v-col>

					<v-col cols="12" sm="12">
						<v-textarea label="自定字段" v-model="EditCommentData.edit.data" variant="underlined"
							color="accent"></v-textarea>
					</v-col>
					<v-col cols="12">
						<v-textarea label="内容" v-model="EditCommentData.edit.content" variant="underlined"
							color="accent"></v-textarea>
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
import CommentsApi from "~/api/app/admin/comments";
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
const CommentData = defineModel('CommentData');
const EditCommentData = ref({} as any);

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
	let editCommentData = CommonUtils.deepClone(EditCommentData.value.edit);
	let originCommentData = CommonUtils.deepClone(EditCommentData.value.origin);
	editCommentData.tags = JSON.stringify(editCommentData.tags);
	originCommentData.tags = JSON.stringify(originCommentData.tags);
	//清除不需要提交的属性
	let params = CommonUtils.removeCommonProperties(editCommentData, originCommentData);

	if (Object.keys(params).length === 0) {
		return Promise.reject({
			notifier: {
				text: "请修改后再提交",
				type: "warning",
			}
		});
	}
	params.id = editCommentData.id;

	//返回原生Promise
	return CommentsApi.patchComment(params);
}

//数据初始化
watch(thisDialogState, (newValue, oldValue) => {
	if (newValue) {
		EditCommentData.value.origin = CommonUtils.deepClone(CommentData.value);
		EditCommentData.value.edit = CommonUtils.deepClone(CommentData.value);
	}
});
</script>