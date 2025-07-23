<!-- 用户管理 -->
<template>
	<NuxtLayout name="root">
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
						<v-tab value="tab2">极验配置</v-tab>
						<v-tab value="tab3">邮箱配置</v-tab>
						<v-tab value="tab4">其他配置</v-tab>
					</v-tabs>

					<v-card-text>
						<v-tabs-window v-model="ViewTab" v-if="systemConfig.lovecards">
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
										<v-btn class="float-right" color="accent">提交</v-btn>
									</v-col>
								</v-row>
							</v-tabs-window-item>

							<v-tabs-window-item value="tab2">
								<v-row dense>
									<v-col cols="12" sm="4">
										<v-text-field label="验证 ID" placeholder="id"
											v-model="systemConfig.lovecards.class.geetest.DefSetGeetestId" variant="underlined"
											color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="4">
										<v-text-field label="验证 Key" placeholder="key"
											v-model="systemConfig.lovecards.class.geetest.DefSetGeetestKey" variant="underlined"
											color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="4">
										<v-select label="验证模块状态" item-title="title" item-value="value"
											v-model="systemConfig.lovecards.class.geetest.DefSetValidatesStatus" subtitle="tip"
											:items="ViewGeetestStatusItems" variant="underlined">
										</v-select>
									</v-col>
								</v-row>
								<v-row>
									<v-col cols="12">
										<a class="text-accent mt-2 d-inline-block" href="https://forum.lovecards.cn/d/26"
											style="text-decoration: none; ">不会配置？</a>
										<v-btn class="float-right" color="accent">提交</v-btn>
									</v-col>
								</v-row>
							</v-tabs-window-item>

							<v-tabs-window-item value="tab3">
								<v-row dense>
									<v-col cols="12" sm="6">
										<v-select label="邮件驱动" item-title="title" item-value="value" subtitle="tip"
											:items="ViewMailSmtpDriverItems" v-model="systemConfig.mail.driver" variant="underlined">
										</v-select>
									</v-col>
									<v-col cols="12" sm="6">
										<v-select label="加密方式" item-title="title" item-value="value" subtitle="tip"
											:items="ViewMailSmtpSecurityItems" v-model="systemConfig.mail.security" variant="underlined">
										</v-select>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="SMTPHost" placeholder="mail.lovecard.cn" v-model="systemConfig.mail.host"
											variant="underlined" color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="SMTPPort" placeholder="25" v-model="systemConfig.mail.port"
											variant="underlined" color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="SMTP邮箱地址" placeholder="xxx@lovecards.cn" v-model="systemConfig.mail.addr"
											variant="underlined" color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="SMTP密码" placeholder="" v-model="systemConfig.mail.pass" variant="underlined"
											color="accent"></v-text-field>
									</v-col>
									<v-col cols="12" sm="6">
										<v-text-field label="发件人昵称" v-model="systemConfig.mail.name" placeholder="倾心倾意邮递员"
											variant="underlined" color="accent"></v-text-field>
									</v-col>
								</v-row>
								<v-row>
									<v-col cols="12">
										<v-btn class="float-right" color="accent">提交</v-btn>
									</v-col>
								</v-row>
							</v-tabs-window-item>

							<v-tabs-window-item value="tab4">
								<v-row dense>
									<v-col cols="12" sm="6">
										<v-select label="游客模式" item-title="title" item-value="value" subtitle="tip"
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
									<v-col cols="12">
										<v-btn class="float-right" color="accent">提交</v-btn>
									</v-col>
								</v-row>
							</v-tabs-window-item>
						</v-tabs-window>
					</v-card-text>
				</v-card>
			</v-col>
		</v-row>
	</NuxtLayout>
</template>

<script setup lang="ts">
import SystemApi from '~/api/app/admin/system';

const ViewTab = ref('');
const ViewGeetestStatusItems = [
	{ title: "开启", value: 1 },
	{ title: "关闭", value: 0 },
]
const ViewMasterSwitchItems = [
	{ title: "开启", value: true },
	{ title: "关闭", value: false },
]
const ViewMailSmtpDriverItems = [
	{ title: "smtp", value: "smtp" },
	{ title: "sendmail", value: "sendmail" },
	{ title: "mail", value: "mail" },
]
const ViewMailSmtpSecurityItems = [
	{ title: "null", value: "null" },
	{ title: "ssl", value: "ssl" },
	{ title: "tls", value: "tls" },
]

const systemConfig = ref([] as any);
const getConfig = () => {
	SystemApi.getConfig().then((result) => {
		systemConfig.value = result.data;
		console.log(systemConfig.value);
	})
}

onMounted(() => {
	getConfig();
});
</script>