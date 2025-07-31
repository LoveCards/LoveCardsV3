<!-- 用户管理 -->
<template>
	<NuxtLayout name="root">
		<!-- 标题 -->
		<v-row class="pt-2">
			<v-col cols="6">
				<h1 class="text-primary font-weight-bold">配置主题</h1>
			</v-col>
		</v-row>


		<v-row>
			<v-col cols="12">
				<v-card>
					<v-col v-for="(item, index) in theme.Select" cols="12">
						<v-select v-model="data.Select[index]" :key="index" :label="item.Name" :items="item.Element.map((el: any, idx: any) => ({
							title: el,
							value: idx
						}))" variant="underlined" color="accent">
						</v-select>
						<div class="v-messages mt-n4" v-html="item.Introduction"></div>
					</v-col>

					<v-col v-for="(item, index) in theme.Text" cols="12">
						<v-textarea v-model="data.Text[index]" :key="index" :label="item.Name" variant="underlined"
							color="accent"></v-textarea>
						<div class="v-messages mt-n4" v-html="item.Introduction"></div>
					</v-col>

					<v-card-actions class="float-end">
						<v-btn @click="setThemeConfig" class="bg-accent">
							提交
						</v-btn>
					</v-card-actions>
				</v-card>
			</v-col>
		</v-row>

	</NuxtLayout>
</template>

<style>
.v-messages a {
	text-decoration: none;
	color: rgb(var(--v-theme-accent));
}
</style>

<script setup lang="ts">
import SystemApi from '~/api/app/admin/system';
import ApiCommonUtils from "@/api/utils/common";

const data = ref([] as any);
function splitThemeConfig(
	src: {
		Select: Record<string, { Default: number | string | boolean; Element: any[] }>
		Text: Record<string, { Default: any }>
	}
): { Select: Record<string, any>; Text: Record<string, any> } {
	const out = {
		Select: {} as Record<string, any>,
		Text: {} as Record<string, any>,
	}

	// Select 处理
	Object.entries(src.Select).forEach(([key, cfg]) => {
		out.Select[key] = cfg.Default
	})

	// Text 处理
	Object.entries(src.Text).forEach(([key, cfg]) => {
		out.Text[key] = cfg.Default
	})

	return out
}

const setThemeConfig = () => {
	const params = {
		select: JSON.stringify(data.value.Select),
		text: JSON.stringify(data.value.Text)
	};
	console.log(data.value);
	SystemApi.postThemeConfig(params).then(() => {
		getThemes();
	});
}

const theme = ref([] as any);
const getThemes = () => {
	SystemApi.getThemes().then((result) => {
		theme.value = result.data.theme_config;
		data.value = splitThemeConfig(result.data.theme_config);
	})
}

onMounted(() => {
	getThemes();
});
</script>