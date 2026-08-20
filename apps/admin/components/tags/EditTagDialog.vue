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
import CommonUtils from "~/utils/common";
import { useApi } from '~/lib/api';
import { useAdminNotifier } from '~/composables/useAdminNotifier';
import SelectUtils from "~/utils/select";
import type { Tag } from '@lovecards/sdk';
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
const TagData = defineModel<Tag | null>('TagData');
const emptyTag = {} as Tag;
const EditTagData = ref<{ edit: Tag; origin: Tag }>({ edit: emptyTag, origin: emptyTag });
type UpdateTagInput = { aid?: number; user_id?: number; name?: string; status?: number };

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
	if (!EditTagData.value.edit.id) return Promise.reject(new Error('标签数据尚未加载'));
	const editTagData = CommonUtils.deepClone(EditTagData.value.edit);
	const originTagData = CommonUtils.deepClone(EditTagData.value.origin);
	const editInput: UpdateTagInput = { aid: editTagData.aid, user_id: editTagData.user_id, name: editTagData.name, status: editTagData.status };
	const originInput: UpdateTagInput = { aid: originTagData.aid, user_id: originTagData.user_id, name: originTagData.name, status: originTagData.status };
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
  return client.tags.updateManaged(editTagData.id, params);
}

//数据初始化
watch(thisDialogState, (newValue, oldValue) => {
	if (newValue) {
		if (TagData.value) {
			EditTagData.value = {
				origin: CommonUtils.deepClone(TagData.value),
				edit: CommonUtils.deepClone(TagData.value),
			};
		}
	}
});
</script>
