<!-- 主页 -->
<template>
	<v-container fluid>
		<h1 class="text-primary"><v-icon>mdi-heart-pulse</v-icon> LoveCards</h1>
		<v-divider></v-divider>
		<h3>{{ viewData.message }}</h3>
		<v-btn class="mt-2" color="accent" v-if="viewData.jumpBtn.url != null">
			{{ viewData.jumpBtn.text }}
		</v-btn>
	</v-container>
</template>

<script setup lang="ts">
import { useErrorStore } from "@/stores/client/errorStore";
const viewData = useErrorStore().viewData;
</script>