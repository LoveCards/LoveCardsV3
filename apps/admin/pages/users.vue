<!-- 用户管理 -->
<template>
	<NuxtLayout name="root">
		<v-container max-width="sm">
			<!-- 标题 -->
			<v-row class="pt-2">
				<v-col cols="6">
					<h1 class="text-primary font-weight-bold">用户管理</h1>
				</v-col>
			</v-row>

			<!-- 内容 -->
			<v-row>
				<v-col cols="12">
					<v-card class="pa-4">
						<!-- 表格头部 -->
						<v-row>
							<!-- 工具栏 -->
							<v-col cols="4" md="8" class="d-flex align-center">
								<v-btn color="accent" variant="flat">
									添加用户
								</v-btn>
							</v-col>
							<!-- 搜索栏 -->
							<v-col cols="8" md="4" class="d-flex justify-end">
								<v-text-field color="accent" append-inner-icon="mdi-magnify" density="compact" label="搜索用户"
									variant="outlined" hide-details single-line></v-text-field>
							</v-col>
						</v-row>

						<!-- 表格身体 -->
						<v-row>
							<v-col cols="12">
								<v-card variant="outlined" color="#E0E0E0">

									<v-data-table :style="{ 'white-space': 'nowrap' }" :headers="TableHeaders" :items="tableItems"
										item-value="id" v-model="tableSelected" show-select>
										<!-- 头部选择框 -->
										<template v-slot:header.data-table-select="{ allSelected, selectAll, someSelected }">
											<v-checkbox-btn :indeterminate="someSelected && !allSelected" :model-value="allSelected"
												color="accent" @update:model-value="selectAll(!allSelected)"></v-checkbox-btn>
										</template>

										<!-- 内容选择框 -->
										<template v-slot:item.data-table-select="{ internalItem, isSelected, toggleSelect }">
											<v-checkbox-btn :model-value="isSelected(internalItem)" color="accent"
												@update:model-value="toggleSelect(internalItem)"></v-checkbox-btn>
										</template>

										<!-- 操作 -->
										<template v-slot:[`item.operate`]="{ item }">
											<v-btn icon="mdi-pencil" elevation="0" size="small" color="accent" variant="text"></v-btn>
											<v-btn icon="mdi-delete" elevation="0" size="small" color="accent" variant="text"></v-btn>
										</template>

										<template v-slot:bottom>
											<!-- 隐藏默认分页器 -->
										</template>
									</v-data-table>

								</v-card>
							</v-col>
						</v-row>

						<!-- 表格脚部 -->
						<v-row>
							<!-- 批量操作 -->
							<v-col cols="12" md="6" class="d-flex justify-start align-center">
								<v-select density="compact" label="请选择操作" max-width="160px" hide-details variant="outlined"
									color="accent" :items="TableBatchOptions" item-title="text" item-value="value"></v-select>
								<v-btn color="accent" variant="flat" class="ml-2">批量操作</v-btn>
							</v-col>
							<!-- 分页按钮 -->
							<v-col cols="12" md="6" class="d-flex justify-end align-center">
								<v-menu>
									<template v-slot:activator="{ props }">
										<v-btn elevation="0" icon="mdi-table-row-plus-after" size="small" v-bind="props" variant="text" color="accent"></v-btn>
									</template>
									<v-list>
										<v-list-item v-for="(item, index) in TableListRowsOptions" :key="index" :value="index">
											<v-list-item-title @click="tableListRows = item.value">{{ item.text }}</v-list-item-title>
										</v-list-item>
									</v-list>
								</v-menu>
								<v-divider vertical></v-divider>
								<!-- <v-select density="compact" label="行/页" max-width="80px" hide-details variant="outlined" color="accent"
									:items="TableListRowsOptions" v-model="tableListRows"></v-select> -->
								<v-pagination v-model="tableCurrentPage" :length="tablePaginationLength" :total-visible="5"
									color="accent" size="small" variant="flat"></v-pagination>
							</v-col>
						</v-row>
					</v-card>
				</v-col>
			</v-row>
		</v-container>
	</NuxtLayout>
</template>

<script setup lang="ts">
import { getUserIndex } from '@/api/app/users';

//表格头部
const TableHeaders = [
	{ title: 'ID', value: 'id' },
	{ title: '头像', value: 'avatar' },
	{ title: '账号', value: 'number' },
	{ title: '用户名', value: 'username' },
	{ title: '邮箱', value: 'email' },
	{ title: '手机号', value: 'phone' },
	{ title: '注册时间', value: 'created_at' },
	{ title: '状态', value: 'status' },
	{ title: '操作', value: 'operate' },
];
//批量操作选项
const TableBatchOptions = [
	{ text: '封禁/解封', value: 'delete' },
	{ text: '删除', value: 'disable' },
	{ text: '启用', value: 'enable' },
];
//每一页项目数量
const TableListRowsOptions = [
	{ text: '10 / 页', value: 10 },
	{ text: '20 / 页', value: 20 },
	{ text: '50 / 页', value: 50 },
	{ text: '100 / 页', value: 100 },
];


//表格数据
const tableItems = ref([]);
//批量选择数据
const tableSelected = ref([]);
//页面数量
const tablePaginationLength = ref(0);
//当前页面
const tableCurrentPage = ref(1);

//每页项目数量
const tableListRows = ref(TableListRowsOptions[0].value);


const getTableData = () => {
	getUserIndex(tableCurrentPage.value, tableListRows.value).then((response) => {
		const data = response.data;
		tableCurrentPage.value = data.current_page;
		tablePaginationLength.value = data.last_page;
		tableItems.value = data.data;
	}).catch(err => console.error(err));
}; getTableData();

//数据获取监控
watch([tableCurrentPage, tableListRows], (newValue, oldValue) => {
	getTableData();
});
</script>