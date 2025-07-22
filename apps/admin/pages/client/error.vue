<!-- 主页 -->
<template>
	<v-container fluid>
		<h1 class="text-primary"><v-icon>mdi-heart-pulse</v-icon> LoveCards</h1>
		<v-divider></v-divider>
		<h3>{{ viewData.message }}</h3>
		<v-btn class="mt-2" color="accent" @click="jump()" v-if="viewData.jumpBtn?.text">
			{{ viewData.jumpBtn.text }}
		</v-btn>
	</v-container>
</template>

<script setup lang="ts">
import { useErrorStore } from "@/stores/client/errorStore";
const viewData = useErrorStore().viewData;

const jump = () => {
	if (viewData.jumpBtn?.text) {
		//clickFun优先级更高
		if (viewData.jumpBtn.clickFun) {
			return viewData.jumpBtn.clickFun();
		}
		if (viewData.jumpBtn.url) {
			return navigateTo('viewData.jumpBtn.url');
		}
	}
}
</script>