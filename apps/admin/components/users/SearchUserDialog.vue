<template>
	<!-- 编辑用户对话框 -->
	<v-dialog v-model="thisDialogState" max-width="600">
		<v-card prepend-icon="mdi-filter" title="搜索过滤">
			<v-card-text>
				<v-row dense>
					<v-col cols="12">
						<v-select clearable chips label="搜索字段" item-title="title" item-value="value" :items="KEYS" multiple
							variant="underlined" v-model="filter.search_keys" messages="默认用户名"></v-select>
					</v-col>
					<v-col cols="12" sm="8">
						<v-select clearable label="排序字段" item-title="title" item-value="value" :items="KEYS" variant="underlined"
							v-model="filter.order_key" messages="默认ID"></v-select>
					</v-col>
					<v-col cols="12" sm="4">
						<v-select label="排序规则" item-title="title" item-value="value" :items="ORDER_DESC" variant="underlined"
							v-model="filter.order_desc"></v-select>
					</v-col>
				</v-row>
			</v-card-text>

			<v-divider></v-divider>

			<v-card-actions>
				<v-spacer></v-spacer>
				<v-btn color="accent" text="取消" variant="text" @click="thisDialogState = false"></v-btn>
				<v-btn color="accent" text="保存" variant="flat" @click="submit()"></v-btn>
			</v-card-actions>
		</v-card>
	</v-dialog>
</template>

<script setup lang="ts">
const ORDER_DESC = [
	{ title: "升序", value: false },
	{ title: "降序", value: true },
];

//Props
const props = defineProps({
	KEYS: Array,
	getTableData: Function,
	setFilter: Function,
});

const getTableData = () => {
	if (props.getTableData) {
		props.getTableData();
	}
};
const setFilter = (data: any) => {
	if (props.setFilter) {
		props.setFilter(data);
	}
};
const KEYS = props.KEYS;

//Model
//对话框状态
const thisDialogState = defineModel<boolean>('thisDialogState');

//提交
const filter = ref({
	//搜索字段
	search_keys: [],
	//排序字段
	order_key: [],
	//排序规则
	order_desc: true,
});
const submit = () => {
	setFilter(filter.value);
	getTableData();
	thisDialogState.value = false;
};
</script>