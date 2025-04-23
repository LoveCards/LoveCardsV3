<!-- 基础布局 -->
<template>

	<!-- 通知队列 -->
	<VNotifierContainer />
	<!-- Api监控 -->
	<ApiMonitorNotifier />

	<!-- 系统栏 -->
	<!-- <v-system-bar>
			<v-spacer></v-spacer>
					<v-icon>mdi-square</v-icon>
					<v-icon>mdi-circle</v-icon>
					<v-icon>mdi-triangle</v-icon>
	</v-system-bar>  -->

	<!-- 应用栏 -->
	<v-app-bar :elevation="3" color="primary">
		<template v-slot:prepend>
			<!-- 绑定点击事件 -->
			<v-app-bar-nav-icon @click="toggleDrawer"></v-app-bar-nav-icon>
		</template>

		<v-app-bar-title>LoveCards</v-app-bar-title>

		<!-- <template v-slot:append>
			<v-btn icon="mdi-heart"></v-btn>
			<v-btn icon="mdi-magnify"></v-btn>
			<v-btn icon="mdi-dots-vertical"></v-btn>
		</template> -->
	</v-app-bar>

	<!-- 抽屉栏 -->
	<v-navigation-drawer floating v-model="drawer">
		<v-sheet class="pa-4" color="grey-lighten-4">
			<v-avatar class="mb-4" color="grey-darken-1" size="64">
				<v-img :alt="userInfo.username" :src="'http://192.168.3.142:7001/' + userInfo.avatar"></v-img>
			</v-avatar>
			<div>
				{{ userInfo.username }}
			</div>
		</v-sheet>

		<v-divider></v-divider>

		<v-list density="compact" color="accent" nav>
			<v-list-item prepend-icon="mdi-view-dashboard" title="总览" value="overview" to="/"></v-list-item>

			<v-list-item prepend-icon="mdi-account-multiple" title="用户" value="users" to="users"></v-list-item>

			<v-list-group value="apps">
				<template v-slot:activator="{ props }">
					<v-list-item v-bind="props" prepend-icon="mdi-apps" title="应用"></v-list-item>
				</template>
				<v-list-item title="卡片" value="cards"></v-list-item>
				<v-list-item title="评论" value="comments"></v-list-item>
				<v-list-item title="标签" value="tags"></v-list-item>
			</v-list-group>

			<v-list-group value="system">
				<template v-slot:activator="{ props }">
					<v-list-item v-bind="props" prepend-icon="mdi-cog" title="系统"></v-list-item>
				</template>
				<v-list-item title="关于" value="about"></v-list-item>
				<v-list-item title="外观" value="undefined"></v-list-item>
				<v-list-item title="更新" value="updata"></v-list-item>
			</v-list-group>

			<v-divider></v-divider>

			<v-list-item class="bg-accent" prepend-icon="mdi-home" title="返回站点" value="mainsite" color=""></v-list-item>

		</v-list>
	</v-navigation-drawer>

	<!-- 主内容 -->
	<v-main>
		<slot />
	</v-main>

</template>

<script setup lang="ts">
import ApiMonitorNotifier from '~/components/public/ApiMonitorNotifier.vue';
import { useUserStore } from '~/stores/userStore';
const userStore = useUserStore();

//获取用户信息
const userInfo = ref({} as any);
const setUserInfo = () => {
	//await userStore.init();
	userInfo.value = userStore.userInfo;
}

//抽屉栏控制
const drawer = ref(true)
const toggleDrawer = () => {
	drawer.value = !drawer.value
}

//页面初始化
onMounted(() => {
	setUserInfo();
});
</script>