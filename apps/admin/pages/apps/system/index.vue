<!-- 系统设置 -->
<template>
		<!-- 标题 -->
		<v-row class="pt-2">
			<v-col cols="6">
				<h1 class="text-primary font-weight-bold">系统</h1>
			</v-col>
		</v-row>

		<v-row>
			<v-col cols="12">
				<v-card>
					<v-tabs v-model="ViewTab" color="primary">
						<v-tab value="tab1">基本信息</v-tab>
						<v-tab value="tab4">其他配置</v-tab>
					</v-tabs>

					<v-card-text>
						<v-tabs-window v-model="ViewTab" v-if="systemConfig.system">
							<v-tabs-window-item value="tab1">
								<v-row dense>
									<v-col cols="12" sm="6">
										<v-text-field label="站点域名" placeholder="www.lovecards.cn" v-model="systemConfig.system.siteUrl"
											variant="underlined" color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="站点名" placeholder="倾心倾意网" v-model="systemConfig.system.siteName"
											variant="underlined" color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="站点标题" placeholder="LoveCards" v-model="systemConfig.system.siteTitle"
											variant="underlined" color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="站点关键词" hint="(meta keywords)" placeholder="论坛,社区,小圈子,表白墙"
											v-model="systemConfig.system.siteKeywords" variant="underlined" color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="站点介绍" hint="(meta description)" placeholder="这是一个由LoveCards强力驱动的社区"
											variant="underlined" v-model="systemConfig.system.siteDes" color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="站点备案信息" placeholder="ICPxxxxxxx" v-model="systemConfig.system.siteICPId"
											variant="underlined" color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="站点版权信息" placeholder="lovecards.cn版权所有"
											v-model="systemConfig.system.siteCopyright" variant="underlined" color="accent"></v-text-field>
									</v-col>
								</v-row>
								<v-row>
									<v-col cols="12">
										<v-btn @click="setSite" class="float-right" color="accent">提交</v-btn>
									</v-col>
							</v-row>
						</v-tabs-window-item>


							<v-tabs-window-item value="tab4">
								<v-row dense>
									<v-col cols="12" sm="6">
										<v-select label="访客模式" item-title="title" item-value="value" subtitle="tip"
											:items="ViewMasterSwitchItems" v-model="systemConfig.master.System.VisitorMode"
											variant="underlined">
										</v-select>
									</v-col>
									<v-col cols="12" sm="6">
										<v-select label="注册验证(邮件)" item-title="title" item-value="value" subtitle="tip"
											:items="ViewMasterSwitchItems" v-model="systemConfig.master.UserAuth.Captcha"
											variant="underlined">
										</v-select>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="图片大小限制" hint="(单位：M/每张)" placeholder="2"
											v-model="systemConfig.master.Upload.UserImageSize" variant="underlined"
											color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="图片格式限制" hint="(使用逗号[,]分割)" placeholder="jpg,png,gif,webp"
											v-model="systemConfig.master.Upload.UserImageExt" variant="underlined"
											color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="6">
										<v-number-input :reverse="false" controlVariant="split" label="卡片图集上限" :hideInput="false"
											:inset="false" v-model="systemConfig.master.Cards.PictureLimit"></v-number-input>
									</v-col>
									<v-col cols="12" sm="6">
										<v-number-input :reverse="false" controlVariant="split" label="卡片标签上限" :hideInput="false"
											:inset="false" v-model="systemConfig.master.Cards.TagLimit"></v-number-input>
									</v-col>
									<v-col cols="12" sm="6">
										<v-select label="卡片审核" item-title="title" item-value="value" subtitle="tip"
											:items="ViewMasterSwitchItems" v-model="systemConfig.master.Cards.Approve" variant="underlined">
										</v-select>
									</v-col>
									<v-col cols="12" sm="6">
										<v-select label="评论审核" item-title="title" item-value="value" subtitle="tip"
											:items="ViewMasterSwitchItems" v-model="systemConfig.master.Comments.Approve"
											variant="underlined">
										</v-select>
									</v-col>
									<v-col cols="12">
										<v-btn @click="setConfig" class="float-right" color="accent">提交</v-btn>
									</v-col>
								</v-row>
							</v-tabs-window-item>
						</v-tabs-window>
					</v-card-text>
				</v-card>
			</v-col>
		</v-row>
</template>

<script setup lang="ts">
import { useApi } from '~/lib/api';
import CommonUtils from "~/utils/common";
import { readBoolean, readNumber, readObject, readString } from '~/utils/json';
import type { JsonObject } from '@lovecards/sdk';
const client = useApi();

interface SiteSettings {
	siteUrl: string;
	siteName: string;
	siteTitle: string;
	siteKeywords: string;
	siteDes: string;
	siteICPId: string;
	siteCopyright: string;
}

interface MasterSettings {
	System: { VisitorMode: boolean };
	UserAuth: { Captcha: boolean };
	Upload: { UserImageSize: number; UserImageExt: string };
	Cards: { PictureLimit: number; TagLimit: number; Approve: boolean };
	Comments: { Approve: boolean };
}

interface FrontendConfig {
	system: SiteSettings;
	master: MasterSettings;
}

const ViewTab = ref('');
const ViewMasterSwitchItems = [
	{ title: "开启", value: true },
	{ title: "关闭", value: false },
]

// 后端 snake_case → 前端 camelCase/PascalCase 转换
const toFrontendFormat = (raw: JsonObject): FrontendConfig => {
	const core = readObject(raw.core);
	const user = readObject(raw.user);
	const upload = readObject(raw.upload);
	const cards = readObject(raw.cards);
	const comments = readObject(raw.comments);
	return {
		system: {
			siteUrl: readString(core.url),
			siteName: readString(core.name),
			siteTitle: readString(core.title),
			siteKeywords: readString(core.keywords),
			siteDes: readString(core.description),
			siteICPId: readString(core.icp_id),
			siteCopyright: readString(core.copyright),
		},
		master: {
			System: {
				VisitorMode: readBoolean(core.visitor_mode),
			},
			UserAuth: {
				Captcha: readBoolean(user.captcha),
			},
			Upload: {
				UserImageSize: readNumber(upload.user_image_size, 2),
				UserImageExt: readString(upload.user_image_ext),
			},
			Cards: {
				PictureLimit: readNumber(cards.picture_limit, 15),
				TagLimit: readNumber(cards.tag_limit, 3),
				Approve: readBoolean(cards.approve),
			},
			Comments: {
				Approve: readBoolean(comments.approve),
			},
		},
	};
};

// 前端 master → 后端格式 反向转换
const toBackendConfig = (master: MasterSettings): JsonObject => {
	return {
		core: {
			visitor_mode: master.System?.VisitorMode ?? false,
		},
		user: {
			captcha: master.UserAuth?.Captcha ?? false,
		},
		upload: {
			user_image_size: master.Upload?.UserImageSize ?? 2,
			user_image_ext: master.Upload?.UserImageExt ?? '',
		},
		cards: {
			picture_limit: master.Cards?.PictureLimit ?? 15,
			tag_limit: master.Cards?.TagLimit ?? 3,
			approve: master.Cards?.Approve ?? false,
		},
		comments: {
			approve: master.Comments?.Approve ?? false,
		},
	};
};

// 前端 system → 后端 core 格式
const toBackendSite = (system: SiteSettings): JsonObject => {
	return {
		core: {
			url: system.siteUrl ?? '',
			name: system.siteName ?? '',
			title: system.siteTitle ?? '',
			keywords: system.siteKeywords ?? '',
			description: system.siteDes ?? '',
			icp_id: system.siteICPId ?? '',
			copyright: system.siteCopyright ?? '',
		},
	};
};

const emptyConfig: FrontendConfig = {
	system: { siteUrl: '', siteName: '', siteTitle: '', siteKeywords: '', siteDes: '', siteICPId: '', siteCopyright: '' },
	master: { System: { VisitorMode: false }, UserAuth: { Captcha: false }, Upload: { UserImageSize: 2, UserImageExt: '' }, Cards: { PictureLimit: 15, TagLimit: 3, Approve: false }, Comments: { Approve: false } },
};
const systemConfig = ref<FrontendConfig>(CommonUtils.deepClone(emptyConfig));
const OriginSystemConfig = ref<FrontendConfig>(CommonUtils.deepClone(emptyConfig));
const getConfig = () => {
	client.config.list().then((result) => {
		const converted = toFrontendFormat(result);
		systemConfig.value = CommonUtils.deepClone(converted);
		OriginSystemConfig.value = CommonUtils.deepClone(converted);
	})
}

const setConfig = () => {
	const backendParams = toBackendConfig(systemConfig.value.master);
	client.config.update(backendParams).then(() => {
		getConfig();
	});
}


const setSite = () => {
	const backendParams = toBackendSite(systemConfig.value.system);
	client.config.update(backendParams).then(() => {
		getConfig();
	});
}

onMounted(() => {
	getConfig();
});
</script>
