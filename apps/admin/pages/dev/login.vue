<!--
 * @Description: 
 * @Author: github.com/zhiguai
 * @Date: 2026-04-06 20:47:03
 * @Email: 2903074366@qq.com
-->
<!-- DEV 模式 Token 登录页 -->
<template>
	<v-app>
		<v-app-bar :elevation="3" color="primary">
			<v-app-bar-title>LoveCards</v-app-bar-title>
		</v-app-bar>

		<v-main>
			<v-container class="d-flex align-center justify-center" style="height: calc(100vh - 64px)">
				<v-card class="pa-8" max-width="500" elevation="4">
					<v-card-title class="text-primary text-h4 text-center mb-4">
						Token 登录
					</v-card-title>

					<v-divider class="mb-6"></v-divider>

					<v-card-text>
						<v-text-field
							v-model="token"
							label="Token"
							placeholder="请输入后端返回的完整 token"
							variant="outlined"
							clearable
							color="accent"
							hint="从后端登录接口获取的 JWT token"
							persistent-hint
							class="mb-4"
						></v-text-field>

						<v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
							{{ error }}
						</v-alert>
					</v-card-text>

					<v-card-actions class="justify-center">
						<v-btn
							color="accent"
							variant="elevated"
							style="width: 100%"
							:loading="loading"
							@click="handleLogin"
						>
							登录
						</v-btn>
					</v-card-actions>

					<v-divider class="my-4"></v-divider>

					<v-card-text class="text-center text-caption text-medium-emphasis">
						仅限开发环境使用，打包后自动禁用
					</v-card-text>
				</v-card>
			</v-container>
		</v-main>
	</v-app>
</template>

<script setup lang="ts">
import Cookies, { COOKIE_NAMES } from '~/api/utils/cookie';

definePageMeta({
	layout: false
});

const token = ref('');
const loading = ref(false);
const error = ref('');

const handleLogin = () => {
	if (!token.value.trim()) {
		error.value = '请输入 token';
		return;
	}

	loading.value = true;
	error.value = '';

	try {
		Cookies.setCookie(COOKIE_NAMES.USER_TOKEN, token.value.trim(), {
			expires: 7,
			path: '/',
			sameSite: 'lax'
		});
		navigateTo('/apps/dashboard');
	} catch (e) {
		error.value = '登录失败，请检查 token 格式';
	} finally {
		loading.value = false;
	}
};
</script>
