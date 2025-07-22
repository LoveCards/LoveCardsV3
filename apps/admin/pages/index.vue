<!-- 主页 -->
<template>
	<v-container class="d-flex align-center justify-center" style="height: 100%">
		<v-progress-circular :size="70" :width="7" color="primary" indeterminate>
			<v-icon size="x-large">mdi-heart-pulse</v-icon>
		</v-progress-circular>

	</v-container>
</template>

<script setup lang="ts">
navigateTo('/apps/dashboard');
</script>