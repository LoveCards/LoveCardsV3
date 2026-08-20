<!-- 基础布局 -->
<template>
	<!-- 全局加载进度条 -->
	<NuxtLoadingIndicator :color="theme.current.value.colors.accent" :height=5 />
	<!-- <v-progress-linear indeterminate color="accent" absolute bottom style="z-index: 999999;"></v-progress-linear> -->
	<!-- 通知队列 -->
	<VNotifierContainer />
	<!-- 统一错误通知 -->
	<NotificationNotifier />

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
		<template v-slot:append>
			<v-btn icon="mdi-logout" aria-label="退出登录" @click="logout"></v-btn>
		</template>

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
			<v-list-item
				:prepend-icon="adminNavigation.dashboard.icon"
				:title="adminNavigation.dashboard.title"
				:to="adminNavigation.dashboard.path"
			></v-list-item>

			<v-list-group v-for="group in visibleNavigationGroups" :key="group.key" :value="group.key">
				<template v-slot:activator="{ props }">
					<v-list-item v-bind="props" :prepend-icon="group.icon" :title="group.title"></v-list-item>
				</template>
				<v-list-item
					v-for="item in group.items"
					:key="item.path"
					:title="item.title"
					:to="item.path"
				>
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
		<v-footer class="d-flex align-center justify-center ga-2 flex-wrap flex-grow-1 py-3 mt-6">
			© 2025 <a href="https://lovecards.cn" class="text-decoration-none text-accent">LoveCards</a>.
			GPL-3.0 license — Crafted by <a href="https://github.com/zhiguai"
				class="text-decoration-none text-accent">吃纸怪</a>.
			{{ runtimeConfig.public.gitSha }}
		</v-footer>
	</v-main>
</template>

<script setup lang="ts">
import NotificationNotifier from '~/components/shared/NotificationNotifier.vue';
import { useUserStore } from '~/stores/userStore';
import { useApi } from '~/lib/api';
import { adminNavigation, canAccessNavigationItem } from '~/config/navigation';
import type { User } from '@lovecards/sdk';
//import { useTheme,useDisplay } from 'vuetify'
const theme = useTheme();
const { mobile } = useDisplay();
const userStore = useUserStore();
const runtimeConfig = useRuntimeConfig();
const client = useApi();


// 桌面端与移动端
const containerWidth = computed(() => (mobile.value ? undefined : '82%'));
const drawerDefault = mobile.value ? false : true

// 获取用户信息与可见导航
const userInfo = computed(() => userStore.userInfo ?? ({ username: '', avatar: '' } as Pick<User, 'username' | 'avatar'>));
const visibleNavigationGroups = computed(() => adminNavigation.groups
	.map((group) => ({
		...group,
		items: group.items.filter((item) => canAccessNavigationItem(item, userStore.grants)),
	}))
	.filter((group) => group.items.length > 0));

//抽屉栏控制
const drawer = ref(drawerDefault);
const toggleDrawer = () => {
	drawer.value = !drawer.value
}

//返回主站
const goHome = () => {
	window.location.href = '/';
}

const logout = async () => {
	try {
		await client.session.logout();
	} catch {
		// The local session must still be cleared if the server token is expired.
	} finally {
		client.clearToken();
		userStore.reset();
		await navigateTo('/login');
	}
}

</script>
