<!-- 用户管理 -->
<template>
	<NuxtLayout name="root">
		<!-- 标题 -->
		<v-row class="pt-2">
			<v-col cols="6">
				<h1 class="text-primary font-weight-bold">外观</h1>
			</v-col>
		</v-row>

		<v-row>
			<!-- 主题列表 -->
			<v-col cols="12" md="4" v-for="(item, index) in themes" :key="index">
				<v-card>
					<v-card-item>
						<v-card-title class="text-primary">
							{{ item.Name }}<v-chip class="float-end mt-2" size="x-small" label>V{{ item.Version }}</v-chip>
						</v-card-title>
						<v-card-subtitle>
							作者：{{ item.Author }}
						</v-card-subtitle>
					</v-card-item>

					<v-img class="bg-grey-lighten-2" height="240" :src="item.Cover"></v-img>
					<v-card-text>
						{{ item.Introduce }}
					</v-card-text>
					<v-card-actions class="float-right">
						<v-btn v-if="!item.Status" @click="setTheme(item.DirectoryName)" class="bg-accent">设为主题</v-btn>
						<v-btn v-if="item.Status && item.Config" @click="themeConfig(item.DirectoryName)"
							class="bg-green">配置主题</v-btn>
					</v-card-actions>
				</v-card>
			</v-col>
		</v-row>
	</NuxtLayout>
</template>

<script setup lang="ts">
import SystemApi from '~/api/app/admin/system';

const themes = ref([] as any);
const getThemes = () => {
	SystemApi.getThemes().then((result) => {
		themes.value = result.data.theme_list;
	})
}
const setTheme = (DirectoryName: any) => {
	const params = {
		dir: DirectoryName
	};
	SystemApi.postSetTheme(params).then(() => {
		getThemes();
	});
}
const themeConfig = (DirectoryName: any) => {
	navigateTo({
		path: '/apps/view/theme-config',
		query: { theme: DirectoryName }
	})
}

onMounted(() => {
	getThemes();
});
</script>