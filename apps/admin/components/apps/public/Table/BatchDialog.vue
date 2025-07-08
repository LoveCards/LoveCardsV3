<template>
	<v-dialog v-model="thisDialogState" max-width="400">
		<v-card>
			<v-card-title class="text-h5"> 确认批量操作 </v-card-title>
			<v-card-text>
				确定要 {{ batchOptions }} 选中的 {{ batchData.length }} 个数据吗？此操作不可恢复。
			</v-card-text>
			<v-card-actions>
				<v-spacer></v-spacer>
				<v-btn color="accent" text="取消" variant="text" @click="thisDialogState = false"></v-btn>
				<v-btn color="accent" text="确认" variant="flat" @click="batchFun"></v-btn>
			</v-card-actions>
		</v-card>
	</v-dialog>
</template>

<script setup lang="ts">
//Props
const props = defineProps({
	batchFun: Function
});
const batchFun = () => {
	if (props.batchFun) {
		props.batchFun();
	}
};

//Model
const thisDialogState = defineModel<boolean>('thisDialogState');
const batchData = defineModel<any>('batchData');
const batchOptions = defineModel<any>('batchOptions');
</script>