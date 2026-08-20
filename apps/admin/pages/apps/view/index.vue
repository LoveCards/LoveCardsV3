<!-- 主题管理 -->
<template>
		<!-- 标题 -->
		<v-row class="pt-2">
			<v-col cols="6">
				<h1 class="text-primary font-weight-bold">外观</h1>
			</v-col>
		</v-row>

		<v-row>
			<!-- 主题列表 -->
			<v-col cols="12" md="4" v-for="item in themes" :key="`${item.id}:${item.release}`">
				<v-card>
					<v-card-item>
						<v-card-title class="text-primary">
							{{ item.name }}<v-chip class="float-end mt-2" size="x-small" label>V{{ item.version }}</v-chip>
						</v-card-title>
						<v-card-subtitle>
							作者：{{ item.author || '未知' }}
						</v-card-subtitle>
					</v-card-item>

					<v-card-text>
						{{ item.description }}
					</v-card-text>
					<v-card-actions class="float-right">
						<v-chip size="small" :color="item.mode === 'spa' ? 'blue' : 'green'" label>{{ item.mode.toUpperCase() }}</v-chip>
						<v-btn v-if="!item.active" @click="setTheme(item.id, item.release)" class="bg-accent">设为主题</v-btn>
						<v-btn v-if="item.active" @click="themeConfig(item.id, item.release)" class="bg-green">配置主题</v-btn>
					</v-card-actions>
				</v-card>
			</v-col>
		</v-row>
</template>

<script setup lang="ts">
import { useApi } from '~/lib/api';
import type { ThemeItem } from '@lovecards/sdk';
const client = useApi();

const themes = ref<ThemeItem[]>([]);
const getThemes = () => {
	client.theme.list().then((result) => {
		themes.value = result;
	})
}
const setTheme = (id: string, release: string) => {
	client.theme.activate({ id, release }).then(() => {
		getThemes();
	});
}
const themeConfig = (id: string, release: string) => {
	navigateTo({
		path: '/apps/view/theme-config',
		query: { id, release }
	})
}

onMounted(() => {
	getThemes();
});
</script>
