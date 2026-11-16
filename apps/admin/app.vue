<!-- 应用入口 -->
<script setup lang="ts">
useAdminTheme().initialize()
</script>

<template>
	<!-- vuetify -->
	<v-app>
		<NuxtLayout>
			<NuxtPage />
		</NuxtLayout>
	</v-app>
</template>
