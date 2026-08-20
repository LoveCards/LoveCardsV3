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
							:items="SelectUtils.Cards.top" variant="underlined" color="accent"></v-select>
					</v-col>
					<v-col cols="12" sm="3">
						<v-select label="封禁状态" item-title="title" item-value="value" v-model="CardData.edit.status"
							:items="SelectUtils.Cards.status" variant="underlined" color="accent"></v-select>
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
import { useApi, isApiError } from '~/lib/api';
import { useAdminNotifier } from '~/composables/useAdminNotifier';
import CommonUtils from "~/utils/common";
import SelectUtils from "~/utils/select";
import type { Card } from '@lovecards/sdk';

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

//页面参数
const tagsStore = useTagsStore();
const ViewTags = computed(() => Array.isArray(tagsStore.tags) ? tagsStore.tags : []);

//Model
//对话框状态
const thisDialogState = defineModel<boolean>('thisDialogState');
const editCardData = defineModel<Card | null>('editCardData');

//图片上传
const ViewCardFiles = ref<File[]>([]); // 用于存储预览的图片文件
// 处理文件选择变化
const viewHandleFilesChange = () => {
	ViewCardFiles.value.forEach(async (file) => {
		if (file.type.startsWith('image/')) { // 检查是否是图片文件
			const url = URL.createObjectURL(file); // 创建图片 URL
			const index = CardImgData.value.edit.length; // 获取当前图片数组的长度作为新图片的索引
			await CardImgData.value.edit.push({ id: 0, url: url }); // 添加到图片 URL 数组
			const result = await postUserImages(file); // 上传图片
			//管理状态
			const image = CardImgData.value.edit[index];
			if (result && image) {
				image.id = result.id; // 更新图片
				image.url = result.url;
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
interface CardImage {
	id: number
	url: string
}

const emptyCard = {} as Card;
const CardData = ref<{ edit: Card; origin: Card }>({ edit: emptyCard, origin: emptyCard });
const CardImgData = ref<{ edit: CardImage[]; origin: CardImage[] }>({ edit: [], origin: [] });
const normalizeStringArray = (value: unknown): string[] => {
	if (Array.isArray(value)) return value.filter((item): item is string => typeof item === 'string');
	if (typeof value === 'string' && value.trim() !== '') {
		try {
			const parsed = JSON.parse(value);
			return normalizeStringArray(parsed);
		} catch {
			return [];
		}
	}
	return [];
};
const getCard = async () => {
	if (!editCardData.value) return;
	const card = await client.cards.getManaged(editCardData.value.id);
	const normalized = { ...card, tags: normalizeStringArray(card.tags) };
	CardData.value = { edit: CommonUtils.deepClone(normalized), origin: CommonUtils.deepClone(normalized) };
};
const getCardImages = () => {
	ViewCardFiles.value = [];
	CardImgData.value = { edit: [], origin: [] };
	// TODO: /card/images 端点已废弃，待后续设计图片管理方案
	// 当前卡片图片通过 card.pictures 字段获取
	const card = CardData.value.edit;
	if (!card.id) return;
	if (card.pictures) {
		const pictures = normalizeStringArray(card.pictures);
		CardImgData.value.edit = pictures.map((url: string, i: number) => ({ id: i + 1, url }));
		CardImgData.value.origin = CommonUtils.deepClone(CardImgData.value.edit);
	}
};
const postUserImages = async (file: File) => {
	try {
		const fd = new FormData();
		fd.append('file', file);
		const result = await client.files.upload(fd);
		return result;
	} catch {
		return false;
	}
}
const patchCard = () => {
	//多次一举是因为没办法比较，主要是mysql输出的json和js的json转字符串格式不一样
	const editCardData = CommonUtils.deepClone(CardData.value.edit);
	const originCardData = CommonUtils.deepClone(CardData.value.origin);
	type CardUpdateInput = { pictures?: unknown[]; content?: string; is_top?: number; status?: number; user_id?: number; comments?: number; views?: number; good?: number; cover?: string; tags?: unknown[] };
	const editInput: CardUpdateInput = { pictures: editCardData.pictures ?? undefined, content: editCardData.content, is_top: editCardData.is_top, status: editCardData.status, user_id: editCardData.user_id, comments: editCardData.comments, views: editCardData.views, good: editCardData.goods, cover: editCardData.cover ?? undefined, tags: editCardData.tags ?? undefined };
	const originInput: CardUpdateInput = { pictures: originCardData.pictures ?? undefined, content: originCardData.content, is_top: originCardData.is_top, status: originCardData.status, user_id: originCardData.user_id, comments: originCardData.comments, views: originCardData.views, good: originCardData.goods, cover: originCardData.cover ?? undefined, tags: originCardData.tags ?? undefined };
	const params = CommonUtils.removeCommonProperties(editInput, originInput);

	//图集数据处理
	let originPictures = CardImgData.value.origin.map((item) => item.id);
	let editPictures = CardImgData.value.edit.map((item) => item.id);
	if (JSON.stringify(originPictures) != JSON.stringify(editPictures)) {
		params.pictures = editPictures;
		//设置封面为第一张图片
		if (CardImgData.value.edit.length > 0) {
			params.cover = CardImgData.value.edit[0]?.url;
		} else {
			params.cover = '';
		}

	}

	if (Object.keys(params).length === 0) {
		return Promise.reject({
			notifier: {
				text: "请修改后再提交",
				type: "warning",
			}
		});
	}
	//返回原生Promise
  return client.cards.updateManaged(editCardData.id, params);
}

//数据初始化
watch(thisDialogState, (newValue, oldValue) => {
	if (newValue) {
		//打开对话框时获取数据
		getCard().then(getCardImages);
	}
});
</script>
