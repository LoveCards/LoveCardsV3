<!-- 主页 -->
<template>
		<!-- 标题 -->
		<v-row class="pt-2">
			<v-col cols="6">
				<h1 class="text-primary font-weight-bold">总览</h1>
			</v-col>
		</v-row>
		<!-- 内容 -->
		<v-row>
			<!-- 左侧趋势卡片 -->
			<v-col cols="12" sm="9">
				<v-card>
					<v-card-text>
						<div class="text-grey">趋势</div>
					</v-card-text>
					<TrendCharts></TrendCharts>
				</v-card>
			</v-col>

			<!-- 右侧总计卡片 -->
			<v-col cols="12" sm="3">
				<v-card>

					<v-card-text>
						<div class="text-grey">总计</div>
					</v-card-text>

					<v-list lines="two">
						<v-list-item v-ripple subtitle="卡片总数" :title="dashboard.count.cards">
							<template v-slot:prepend>
								<v-avatar color="blue">
									<v-icon color="white">mdi-note</v-icon>
								</v-avatar>
							</template>
						</v-list-item>

						<v-list-item v-ripple subtitle="评论总数" :title="dashboard.count.comments">
							<template v-slot:prepend>
								<v-avatar color="yellow">
									<v-icon color="white">mdi-comment</v-icon>
								</v-avatar>
							</template>
						</v-list-item>

						<v-list-item v-ripple subtitle="点赞总数" :title="dashboard.count.good">
							<template v-slot:prepend>
								<v-avatar color="red">
									<v-icon color="white">mdi-heart</v-icon>
								</v-avatar>
							</template>
						</v-list-item>
					</v-list>
				</v-card>
			</v-col>

			<!-- 左侧 LoveCards 信息卡片 -->
			<v-col cols="12" sm="4">
				<v-card>

					<v-card-text>
						<v-list-item :title="dashboard.ver.app_name" :subtitle="dashboard.ver.version"
							prepend-avatar="~/assets/images/logo.png">
						</v-list-item>
					</v-card-text>

					<v-divider></v-divider>

					<v-list>
						<v-list-item href="https://lovecards.cn" target="_blank">
							<template v-slot:prepend>
								<v-icon>mdi-home</v-icon>
							</template>
							<v-list-item-title>项目主页</v-list-item-title>
						</v-list-item>
						<v-list-item href="https://forum.lovecards.cn/" target="_blank">
							<template v-slot:prepend>
								<v-icon>mdi-forum</v-icon>
							</template>
							<v-list-item-title>论坛</v-list-item-title>
						</v-list-item>
						<v-list-item :href="dashboard.ver.github" target="_blank">
							<template v-slot:prepend>
								<v-icon>mdi-github</v-icon>
							</template>
							<v-list-item-title>Github</v-list-item-title>
						</v-list-item>
						<v-list-item :href="dashboard.ver.qgroup" target="_blank">
							<template v-slot:prepend>
								<v-icon>mdi-qqchat</v-icon>
							</template>
							<v-list-item-title>QQ交流群</v-list-item-title>
						</v-list-item>
					</v-list>
				</v-card>
			</v-col>

			<!-- 右侧通知卡片 -->
			<v-col cols="12" sm="8">
				<v-card>
					<v-list lines="three">
						<v-list-item v-for="(item, index) in dashboard.notice" :title="item.title" :href="item.url" target="_blank"
							prepend-avatar="~/assets/images/logo.png">
							<v-list-item-subtitle>
								{{ item.content }}
								<br><small class="text-grey">{{ item.date }}</small>
							</v-list-item-subtitle>
						</v-list-item>
					</v-list>
				</v-card>
			</v-col>
		</v-row>
</template>

<script setup lang="ts">
import TrendCharts from '~/components/dashboard/TrendCharts.vue';
import { useDashboardStore } from '~/stores/dashboardStore';
import type { DashboardData } from '@lovecards/sdk';

//仪表盘数据
const dashboardStore = useDashboardStore();
const dashboard = computed<DashboardData>(() => dashboardStore.dashboard ?? {
	count: { cards: 0, comments: 0, good: 0 },
	chart: [],
	ver: { app_name: '', homepage: '', version: '', build: 0, github: '', qgroup: '' },
	notice: [],
});
</script>
