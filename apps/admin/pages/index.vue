<!-- 主页 -->
<template>
	<NuxtLayout name="root">
		<v-container max-width="96%" min-width="92%" fluid>
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

							<v-list-item v-ripple subtitle="点赞总数" :title="dashboard.count.goods">
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
							<!-- <v-row>
								<v-col cols="3" class="text-center">
									<v-avatar class="h-100 w-100 hidden-md-and-down"
										image="http://192.168.3.142:7001/view/admin/assets/img/lcl.png"></v-avatar>
									<v-avatar class="w-100 hidden-lg-and-up"
										image="http://192.168.3.142:7001/view/admin/assets/img/lcl.png"></v-avatar>
								</v-col>

								<v-col cols="9">
									<h6 class="text-h6 font-weight-black">LoveCards</h6>
									<div class="text-subtitle-1">2.3.2</div>
								</v-col>
							</v-row> -->
							<v-list-item title="LoveCards" subtitle="2.3.2"
								prepend-avatar="http://192.168.3.142:7001/view/admin/assets/img/lcl.png">
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
							<v-list-item href="https://github.com/LoveCards/LoveCardsV2" target="_blank">
								<template v-slot:prepend>
									<v-icon>mdi-github</v-icon>
								</template>
								<v-list-item-title>Github</v-list-item-title>
							</v-list-item>
							<v-list-item href="https://jq.qq.com/?_wv=1027&k=qM8f2RMg" target="_blank">
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
							<v-list-item title="更新通知" subtitle="LoveCards2.3.2[19]修复2.3.1版本多处BUG并优化细节，更新登入后自动填写评论名"
								prepend-avatar="http://192.168.3.142:7001/view/admin/assets/img/lcl.png">
							</v-list-item>
						</v-list>
					</v-card>
				</v-col>
			</v-row>
		</v-container>
	</NuxtLayout>
</template>

<script setup lang="ts">
import TrendCharts from '~/components/index/TrendCharts.vue';
import { useDashboardStore } from '~/stores/dashboardStore';

//仪表盘数据
const dashboard = ref({} as any);
const dashboardStore = useDashboardStore();
dashboard.value = dashboardStore.dashboard;
</script>