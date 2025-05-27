<template>
	<!-- 编辑用户对话框 -->
	<v-dialog v-model="thisDialogState" max-width="1200">
		<v-card prepend-icon="mdi-file-edit" title="修改卡片">
			<v-card-text>
				<v-row dense>
					<v-col cols="12">
						<v-select clearable chips label="标签" item-title="name" item-value="id" subtitle="tip"
							v-model="CardData.edit.tag" :items="ViewTags" variant="underlined" multiple>
						</v-select>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="用户" v-model="CardData.edit.uid" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="发布时间" v-model="CardData.edit.time" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="发布IP" v-model="CardData.edit.ip" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>

					<v-col cols="12" sm="2">
						<v-select label="模型" item-title="title" item-value="value" v-model="CardData.edit.model"
							:items="SelectUtils.Cards.model" variant="underlined" multiple></v-select>
					</v-col>
					<v-col cols="12" sm="2">
						<v-select label="指定状态" item-title="title" item-value="value" v-model="CardData.edit.top"
							:items="SelectUtils.Cards.top" variant="underlined" multiple></v-select>
					</v-col>
					<v-col cols="12" sm="2">
						<v-select label="封禁状态" item-title="title" item-value="value" v-model="CardData.edit.status"
							:items="SelectUtils.Cards.status" variant="underlined" multiple></v-select>
					</v-col>

					<v-col cols="12" sm="3">
						<v-text-field label="点赞" v-model="CardData.edit.good" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="3">
						<v-text-field label="浏览" v-model="CardData.edit.look" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6"></v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="主字段1" v-model="CardData.edit.woName" variant="underlined"
							color="accent"></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="副字段1" v-model="CardData.edit.woContact" variant="underlined"
							color="accent"></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="主字段2 *" v-model="CardData.edit.taName" variant="underlined"
							color="accent"></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="副字段2" v-model="CardData.edit.taContact" variant="underlined"
							color="accent"></v-text-field>
					</v-col>
					<v-col cols="12">
						<v-textarea label="内容 *" :model-value="CardData.edit.content" variant="underlined"
							color="accent"></v-textarea>
					</v-col>

					<v-col cols="12">
						<v-row>

							<v-col v-for="(item, index) in CardImgData.edit" :key="index" class="d-flex child-flex" cols="4">
								<v-img :lazy-src="item.url" :src="item.url" aspect-ratio="1" class="bg-grey-lighten-2 rounded">
									<!-- 删除按钮 -->
									<v-btn variant="plain" class="float-right ma-2" density="compact" icon="mdi-close"
										@click="viewDeleteCardImg(index)"></v-btn>
									<template v-slot:placeholder>
										<div class="d-flex align-center justify-center fill-height">
											<v-progress-circular color="grey-lighten-4" indeterminate></v-progress-circular>
										</div>
									</template>
								</v-img>
							</v-col>

							<!-- 触发文件选择的按钮 -->
							<v-col cols="12">
								<v-file-input label="上传图片" v-model="ViewCardFiles" @change="viewHandleFilesChange" class="d-none"
									multiple></v-file-input>
								<v-btn variant="text" width="100%" color="accent" @click="viewTriggerFileInput">上传图片</v-btn>
							</v-col>
						</v-row>
					</v-col>
				</v-row>
				<!-- <small class="text-caption text-medium-emphasis"> * 为必填字段</small> -->
			</v-card-text>

			<v-divider></v-divider>

			<v-card-actions>
				<v-spacer></v-spacer>
				<v-btn color="accent" text="取消" variant="text" @click="thisDialogState = false"></v-btn>
				<v-btn color="accent" text="保存" variant="flat" @click="viewSubmitEditCard()"></v-btn>
			</v-card-actions>
		</v-card>
	</v-dialog>
</template>

<script setup lang="ts">
import UploadApi from "@/api/app/upload";
import CommonUtils from "@/api/utils/common";
import AdminCardsApi from "~/api/app/cards";
import UserCardsApi from "~/api/app/user/cards";
import SelectUtils from "~/api/utils/select";

const notifier = useNotifier();

//Props
const props = defineProps({
	USER_ROLES: Array,
	ACCOUNT_STATUS: Array,
	getTableData: Function
});
const getTableData = () => {
	if (props.getTableData) {
		props.getTableData();
	}
};
const ACCOUNT_STATUS = props.ACCOUNT_STATUS;
const USER_ROLES = props.USER_ROLES;

//页面参数
const tagsStore = useTagsStore();
const ViewTags = ref([] as any);
ViewTags.value = tagsStore.tags;

//Model
//对话框状态
const thisDialogState = defineModel<boolean>('thisDialogState');
const editCardData = defineModel<any>('editCardData');

//图片上传
const ViewCardFiles = ref([] as any); // 用于存储预览的图片文件
// 处理文件选择变化
const viewHandleFilesChange = () => {
	console.log(ViewCardFiles.value);
	console.log(CardImgData.value);
	ViewCardFiles.value.forEach((file: any) => {
		if (file.type.startsWith('image/')) { // 检查是否是图片文件
			const url = URL.createObjectURL(file); // 创建图片 URL
			CardImgData.value.edit.push({ id: 0, url: url }); // 添加到图片 URL 数组
		}
	});
};
// 触发文件选择对话框
const viewTriggerFileInput = () => {
	const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
	if (fileInput) {
		fileInput.click();
	}
};
// 删除图片
const viewDeleteCardImg = (index: number) => {
	// 删除指定索引的图片
	if (index >= 0 && index < CardImgData.value.edit.length) {
		CardImgData.value.edit.splice(index, 1);
	}
};

//页面操作提交修改
const viewSubmitEditCard = () => {
	// patchUser(CardData.value).then((response) => {
	// 	//退出对话框并刷新列表
	// 	thisDialogState.value = false;
	// 	getTableData();
	// });
};

//API
//卡片数据
const CardData = ref({
	edit: {} as any,
	origin: {} as any
});
const CardImgData = ref({
	edit: {} as any,
	origin: {} as any
});
const getCard = () => {
	console.log(editCardData.value);
	//重置
	CardData.value = {
		edit: {} as any,
		origin: {} as any
	};
	//构建参数
	const params = {
		id: editCardData.value.id,
	};
	//请求
	AdminCardsApi.getCard(params as any).then((response) => {
		response.data.tag = response.data.tag ? JSON.parse(response.data.tag) : [];
		CardData.value.edit = CommonUtils.deepClone(response.data);
		CardData.value.origin = CommonUtils.deepClone(response.data);
	});
};
const getCardImages = () => {
	//重置
	ViewCardFiles.value = [];
	CardImgData.value = {
		edit: [] as any,
		origin: [] as any
	};
	//构建参数
	const params = {
		card_id: editCardData.value.id,
	};
	//请求
	UserCardsApi.getCardImages(params as any).then((response) => {
		CardImgData.value.edit = CommonUtils.deepClone(response.data);
		CardImgData.value.origin = CommonUtils.deepClone(response.data);
	});
};
// 处理文件上传
const handleFileUpload = (e: Event) => {
	const input = e.target as HTMLInputElement;
	if (!input.files?.length) return;

	const file = input.files[0];

	const data = {
		file: file,
		aid: 0,
		pid: 0,
		uid: CardData.value.edit.id,
	};

	UploadApi.postUserImages(data)
		.then((response: any) => {
			// 更新头像显示（假设接口返回新的头像路径）
			CardData.value.edit.avatar = response.data;
		})
		.finally(() => {
			// 清空文件选择
			input.value = "";
		});
};


//数据初始化
watch(thisDialogState, (newValue, oldValue) => {
	if (newValue) {
		//打开对话框时获取数据
		getCard();
		getCardImages();
	}
});
</script>