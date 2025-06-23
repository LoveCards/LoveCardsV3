<template>
	<!-- 编辑用户对话框 -->
	<v-dialog v-model="thisDialogState" max-width="1200">
		<v-card prepend-icon="mdi-file-edit" title="修改卡片">
			<v-card-text>
				<v-row dense>
					<v-col cols="12">
						<v-select clearable chips label="标签" item-title="name" item-value="id" subtitle="tip"
							v-model="CardData.edit.tags" :items="ViewTags" variant="underlined" multiple>
						</v-select>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="用户ID" v-model="CardData.edit.user_id" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="发布时间" v-model="CardData.edit.created_at" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field label="发布IP" v-model="CardData.edit.post_ip" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>

					<v-col cols="12" sm="3">
						<v-select label="置顶状态" item-title="title" item-value="value" v-model="CardData.edit.is_top"
							:items="SelectUtils.Cards.top" variant="underlined"></v-select>
					</v-col>
					<v-col cols="12" sm="3">
						<v-select label="封禁状态" item-title="title" item-value="value" v-model="CardData.edit.status"
							:items="SelectUtils.Cards.status" variant="underlined"></v-select>
					</v-col>

					<v-col cols="12" sm="3">
						<v-text-field label="喜欢" v-model="CardData.edit.goods" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="3">
						<v-text-field label="浏览" v-model="CardData.edit.views" variant="underlined" color="accent"
							readonly></v-text-field>
					</v-col>
					<v-col cols="12" sm="6"></v-col>

					<v-col cols="12" sm="12">
						<v-textarea label="自定字段" v-model="CardData.edit.data" variant="underlined" color="accent"></v-textarea>
					</v-col>
					<v-col cols="12">
						<v-textarea label="内容" v-model="CardData.edit.content" variant="underlined" color="accent"></v-textarea>
					</v-col>

					<v-col cols="12">
						<v-row>

							<v-col v-for="(item, index) in CardImgData.edit" :key="index" class="d-flex child-flex" cols="4">
								<v-img :lazy-src="item.url" :src="item.url" aspect-ratio="1" class="bg-grey-lighten-2 rounded">

									<!-- 删除按钮 -->
									<v-progress-circular :size="25" class="float-right ma-2" color="accent" indeterminate
										v-show="item.id == 0"></v-progress-circular>
									<v-btn variant="plain" class="float-right ma-2" density="compact" icon="mdi-close"
										@click="viewDeleteCardImg(index)" v-show="item.id != 0"></v-btn>
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
import CardsApi from "~/api/app/cards";
import AdminCardsApi from "~/api/app/cards";
import UserCardsApi from "~/api/app/user/cards";
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
	ViewCardFiles.value.forEach(async (file: any) => {
		if (file.type.startsWith('image/')) { // 检查是否是图片文件
			const url = URL.createObjectURL(file); // 创建图片 URL
			const index = CardImgData.value.edit.length; // 获取当前图片数组的长度作为新图片的索引
			await CardImgData.value.edit.push({ id: 0, url: url }); // 添加到图片 URL 数组
			const resultId = await postUserImages(file); // 上传图片
			//管理状态
			if (resultId) {
				CardImgData.value.edit[index].id = resultId; // 更新图片 ID
			} else {
				CardImgData.value.edit.splice(index, 1);
			}
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
	patchCard().then((response) => {
		//退出对话框并刷新列表
		thisDialogState.value = false;
		getTableData();
	}).catch((error) => {
		//处理错误
		if (error.notifier) {
			notifier.toast(error.notifier);
		}
	});
};

//API
//卡片数据
const CardData = ref({
	edit: {} as any,
	origin: {} as any
});
const CardImgData = ref({
	edit: [] as any,
	origin: [] as any
});
const getCard = () => {
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
		response.data.tags = response.data.tags ? JSON.parse(response.data.tags) : [];//将标签转换为数组
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
const postUserImages = async (file: any) => {
	const data = {
		file: file,
		aid: 0,
		pid: 0,
		user_id: CardData.value.edit.user_id,
	};
	return UploadApi.postUserImages(data).then((respones) => {
		return respones.data.id;
	}).catch((error) => {
		return false;
	});
}
const patchCard = () => {
	//多次一举是因为没办法比较，主要是mysql输出的json和js的json转字符串格式不一样
	let editCardData = CommonUtils.deepClone(CardData.value.edit);
	let originCardData = CommonUtils.deepClone(CardData.value.origin);
	editCardData.tags = JSON.stringify(editCardData.tags);//将标签转换为字符串
	originCardData.tags = JSON.stringify(originCardData.tags);//将标签转换为字符串
	//清除不需要提交的属性
	console.log(CardData.value.edit, CardData.value.origin);
	console.log(editCardData, originCardData);
	let params = CommonUtils.removeCommonProperties(editCardData, originCardData);

	//图集数据处理
	let originPictures = CardImgData.value.origin.map((item: any) => item.id);
	let editPictures = CardImgData.value.edit.map((item: any) => item.id);

	if (JSON.stringify(originPictures) != JSON.stringify(editPictures)) {
		params['pictures'] = JSON.stringify(editPictures);
	}

	if (Object.keys(params).length === 0) {
		return Promise.reject({
			notifier: {
				text: "请修改后再提交",
				type: "warning",
			}
		});
	}
	params.id = editCardData.id; //插入卡片ID
	console.log(params);
	//返回原生Promise
	return CardsApi.patchCard(params);
}

//数据初始化
watch(thisDialogState, (newValue, oldValue) => {
	if (newValue) {
		//打开对话框时获取数据
		getCard();
		getCardImages();
	}
});
</script>