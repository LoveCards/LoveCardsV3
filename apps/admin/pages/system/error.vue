<!-- 主页 -->
<template>
	<v-container fill-height class="error-page">
		<v-row align="center" justify="center">
			<v-col cols="12" sm="8" md="6" lg="4">
				<v-card class="pa-4" elevation="4" rounded>
					<v-card-title class="text-h4 text-center">
						<v-icon large color="error" class="mr-4">mdi-alert-circle</v-icon>
						出错了
					</v-card-title>
					<v-card-text class="text-center">
						<!-- <p class="text-subtitle-1">{{ error.statusCode }}</p>
						<p class="text-body-1">{{ error.message || '未知错误' }}</p> -->
					</v-card-text>
					<v-card-actions class="justify-center">
						<!-- <v-btn color="primary" @click="goHome">返回首页</v-btn> -->
					</v-card-actions>
				</v-card>
			</v-col>
		</v-row>
	</v-container>
</template>

<script setup lang="ts">

</script>