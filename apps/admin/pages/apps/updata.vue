<!-- 主页 -->
<template>
	<NuxtLayout name="root">
		<!-- 标题 -->
		<v-row class="pt-2">
			<v-col cols="6">
				<h1 class="text-primary font-weight-bold">系统更新</h1>
			</v-col>
		</v-row>

		<!-- 内容 -->
		<v-row>
			<!-- 左侧趋势卡片 -->
			<v-col cols="12">
				<!-- 加载状态 -->
				<v-card v-if="systemStore.loading">
					<v-card-text class="text-center">
						<v-progress-circular indeterminate color="primary"></v-progress-circular>
					</v-card-text>
				</v-card>

				<!-- 错误状态 -->
				<v-card v-else-if="systemStore.error">
					<v-card-text>
						<v-alert type="error" variant="tonal">
							{{ systemStore.error }}
						</v-alert>
						<v-btn class="mt-2" color="primary" @click="refreshUpdata">重试</v-btn>
					</v-card-text>
				</v-card>

				<!-- 数据展示 -->
				<v-card v-else-if="updata && updata.verlog">
					<v-card-text>

						<v-icon>mdi-zip-box</v-icon>当前版本：
						<v-chip size="x-small" variant="elevated" label>
							{{ updata.ver?.VerS }}[{{ updata.ver?.Ver }}]
						</v-chip><br>

						<v-icon>mdi-new-box</v-icon>最新版本：
						<v-chip v-if="updata.latest && ('v' + updata.ver?.VerS) >= updata.latest.tag_name" :href="updata.latest.url"
							size="x-small" variant="elevated" label>
							{{ updata.latest.name }}
						</v-chip>
						<v-chip v-else-if="updata.latest && ('v' + updata.ver?.VerS) < updata.latest.tag_name"
							:href="updata.latest.url" size="x-small" variant="elevated" label>
							前往更新{{ updata.latest.name }}
						</v-chip>

						<v-textarea :model-value="updata.verlog" class="mt-2" label="更新日志" row-height="25" rows="3"
							variant="outlined" readonly auto-grow></v-textarea>
					</v-card-text>
				</v-card>

				<!-- 无数据状态 -->
				<v-card v-else>
					<v-card-text>
						<v-alert type="info" variant="tonal">
							暂无更新信息
						</v-alert>
					</v-card-text>
				</v-card>
			</v-col>
		</v-row>
	</NuxtLayout>
</template>

<script setup lang="ts">
import { useSystemStore, type UpdataInfo } from '~/stores/api/admin/systemStore';

const systemStore = useSystemStore();

// 使用computed从Store获取数据，自动响应变化
const updata = computed<UpdataInfo | null>(() => systemStore.updata);

// 刷新更新信息
const refreshUpdata = async () => {
	await systemStore.getUpdata();
};

onMounted(async () => {
	// 初始化Store（如果还没有数据）
	if (!systemStore.updata && !systemStore.loading) {
		await systemStore.init();
	}
});
</script>