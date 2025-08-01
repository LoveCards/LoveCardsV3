<!-- 基础布局 -->
<template>
	<!-- 全局加载进度条 -->
	<NuxtLoadingIndicator :color="theme.current.value.colors.accent" :height=5 />
	<!-- <v-progress-linear indeterminate color="accent" absolute bottom style="z-index: 999999;"></v-progress-linear> -->
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
				<v-img :alt="userInfo.username" :src="userInfo.avatar"></v-img>
			</v-avatar>
			<div>
				{{ userInfo.username }}
			</div>
		</v-sheet>

		<v-divider></v-divider>

		<v-list density="compact" color="accent" nav>
			<v-list-item prepend-icon="mdi-view-dashboard" title="总览" value="overview" to="/apps/dashboard"></v-list-item>

			<v-list-item prepend-icon="mdi-account-multiple" title="用户" value="users" to="/apps/users"
				v-if="superAdminStatus"></v-list-item>

			<v-list-group value="apps">
				<template v-slot:activator="{ props }">
					<v-list-item v-bind="props" prepend-icon="mdi-apps" title="应用"></v-list-item>
				</template>
				<v-list-item title="卡片" value="cards" to="/apps/cards"></v-list-item>
				<v-list-item title="评论" value="comments" to="/apps/comments"></v-list-item>
				<v-list-item title="标签" value="tags" to="/apps/tags"></v-list-item>
			</v-list-group>

			<v-list-group value="settings" v-if="superAdminStatus">
				<template v-slot:activator="{ props }">
					<v-list-item v-bind="props" title="设置">
						<template v-slot:prepend>
							<v-badge v-if="updataStatus" bordered location="top right" color="accent" dot>
								<v-icon>mdi-cog</v-icon>
							</v-badge>
							<v-icon v-if="!updataStatus">mdi-cog</v-icon>
						</template>
					</v-list-item>
				</template>
				<v-list-item title="系统" value="system" to="/apps/system"></v-list-item>
				<v-list-item title="外观" value="undefined" to="/apps/view"></v-list-item>
				<v-list-item title="更新" value="updata" to="/apps/updata">
					<template v-if="updataStatus" v-slot:append>
						<v-badge color="accent" content="新版本" inline></v-badge>
					</template>
				</v-list-item>
			</v-list-group>

			<v-divider></v-divider>

			<v-list-item @click="goHome" class="bg-accent" prepend-icon="mdi-home" title="返回站点"
				value="mainsite"></v-list-item>

		</v-list>
	</v-navigation-drawer>

	<!-- 主内容 -->
	<v-main>
		<v-container :max-width="containerWidth" fluid>
			<slot />
		</v-container>
	</v-main>

</template>

<script setup lang="ts">
import ApiMonitorNotifier from '~/components/public/ApiMonitorNotifier.vue';
import { useUserStore } from '~/stores/userStore';
import { useSystemStore } from '~/stores/api/admin/systemStore';
//import { useTheme,useDisplay } from 'vuetify'
const theme = useTheme();
const { mobile } = useDisplay();
const userStore = useUserStore();


// 桌面端与移动端
const containerWidth = computed(() => (mobile.value ? undefined : '82%'));
const drawerDefault = mobile.value ? false : true

//获取用户信息
const superAdminStatus = ref(false);
const userInfo = ref({} as any);
const setUserInfo = async () => {
	userInfo.value = userStore.userInfo;
	superAdminStatus.value = JSON.parse(userInfo.value.roles_id).includes(0) ? true : false;
}

//抽屉栏控制
const drawer = ref(drawerDefault);
const toggleDrawer = () => {
	drawer.value = !drawer.value
}

//返回主站
const goHome = () => {
	window.location.href = '/';
}

//版本更新提示
const systemStore = useSystemStore();
const updataStatus = computed(() => {
	const updata = systemStore.updata as any;
	if (updata == null) {
		return false;
	}
	const result = ('v' + updata.ver.VerS) < updata.latest.tag_name ? true : false;
	return result;
});


//页面初始化
onMounted(() => {
	setUserInfo();
});
</script>
