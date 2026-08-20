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
import CommonUtils from "~/utils/common";
import { useApi } from '~/lib/api';
import { useAdminNotifier } from '~/composables/useAdminNotifier';
import SelectUtils from "~/utils/select";
import type { Comment } from '@lovecards/sdk';
const client = useApi();
const notifier = useAdminNotifier();

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
const CommentData = defineModel<Comment | null>('CommentData');
const emptyComment = {} as Comment;
const EditCommentData = ref<{ edit: Comment; origin: Comment }>({ edit: emptyComment, origin: emptyComment });
type CommentUpdate = { aid?: number; pid?: number; is_top?: number; status?: number; user_id?: number; good?: number; content?: string; data?: unknown[] };

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
	if (!EditCommentData.value.edit.id) return Promise.reject(new Error('评论数据尚未加载'));
	const editCommentData = CommonUtils.deepClone(EditCommentData.value.edit);
	const originCommentData = CommonUtils.deepClone(EditCommentData.value.origin);
	const editInput: CommentUpdate = { aid: editCommentData.aid, pid: editCommentData.pid, is_top: editCommentData.is_top, status: editCommentData.status, user_id: editCommentData.user_id, good: editCommentData.goods, content: editCommentData.content, data: editCommentData.data };
	const originInput: CommentUpdate = { aid: originCommentData.aid, pid: originCommentData.pid, is_top: originCommentData.is_top, status: originCommentData.status, user_id: originCommentData.user_id, good: originCommentData.goods, content: originCommentData.content, data: originCommentData.data };
	//清除不需要提交的属性
	const params = CommonUtils.removeCommonProperties(editInput, originInput);

	if (Object.keys(params).length === 0) {
		return Promise.reject({
			notifier: {
				text: "请修改后再提交",
				type: "warning",
			}
		});
	}
	//返回原生Promise
  return client.comments.updateManaged(editCommentData.id, params);
}

//数据初始化
watch(thisDialogState, (newValue, oldValue) => {
	if (newValue) {
		if (CommentData.value) {
			EditCommentData.value = {
				origin: CommonUtils.deepClone(CommentData.value),
				edit: CommonUtils.deepClone(CommentData.value),
			};
		}
	}
});
</script>
