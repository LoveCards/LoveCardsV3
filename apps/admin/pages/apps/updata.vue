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
				<v-card v-if="updata.verlog">
					<v-card-text>

						<v-icon>mdi-zip-box</v-icon>当前版本：
						<v-chip size="x-small" variant="elevated" label>
							{{ updata.ver.VerS }}[{{ updata.ver.Ver }}]
						</v-chip><br>

						<v-icon>mdi-new-box</v-icon>最新版本：
						<v-chip v-if="('v' + updata.ver.VerS) >= updata.latest.tag_name" :href="updata.latest.url" size="x-small"
							variant="elevated" label>
							{{ updata.latest.name }}
						</v-chip>
						<v-chip v-if="('v' + updata.ver.VerS) < updata.latest.tag_name" :href="updata.latest.url" size="x-small"
							variant="elevated" label>
							前往更新{{ updata.latest.name }}
						</v-chip>

						<v-textarea v-model="updata.verlog" class="mt-2" label="更新日志" row-height="25" rows="3" variant="outlined"
							readonly auto-grow></v-textarea>
					</v-card-text>
				</v-card>
			</v-col>
		</v-row>
	</NuxtLayout>
</template>

<script setup lang="ts">
import { useSystemStore } from '~/stores/api/admin/systemStore';

const systemStore = useSystemStore();

const updata = ref({} as any);

onMounted(() => {
	//初始化
	updata.value = systemStore.updata;
});
</script>