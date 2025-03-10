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

										<!-- 头像 -->
										<template v-slot:[`item.avatar`]="{ item }">
											<v-avatar>
												<v-img :alt="item.username" :src="'http://192.168.3.142:7001/' + item.avatar"></v-img>
											</v-avatar>
										</template>

										<!-- 操作 -->
										<template v-slot:[`item.operate`]="{ item }">
											<v-btn icon="mdi-pencil" elevation="0" size="small" color="accent" variant="text"
												@click="openEditUserDialog(item)"></v-btn>
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
									color="accent" :items="TableBatchOptions" item-title="title" item-value="value"></v-select>
								<v-btn color="accent" variant="flat" class="ml-2">批量操作</v-btn>
							</v-col>
							<!-- 分页按钮 -->
							<v-col cols="12" md="6" class="d-flex justify-end align-center">
								<v-menu>
									<template v-slot:activator="{ props }">
										<v-btn elevation="0" icon="mdi-table-row-plus-after" size="small" v-bind="props"
											variant="text"></v-btn>
									</template>
									<v-list>
										<v-list-item v-for="(item, index) in TableListRowsOptions" :key="index" :value="index">
											<v-list-item-title @click="tableListRows = item.value">{{ item.title }}</v-list-item-title>
										</v-list-item>
									</v-list>
								</v-menu>
								<v-divider vertical></v-divider>
								<!-- <v-select density="compact" label="行/页" max-width="80px" hide-details variant="outlined" color="accent"
									:items="TableListRowsOptions" v-model="tableListRows"></v-select> -->
								<v-pagination v-model="tableCurrentPage" :length="tablePaginationLength" :total-visible="5"
									color="accent" size="small" variant="elevated"></v-pagination>
							</v-col>
						</v-row>
					</v-card>
				</v-col>
			</v-row>
		</v-container>
	</NuxtLayout>

	<!-- 编辑用户对话框 -->
	<v-dialog v-model="editUserDialogState" max-width="600">
		<v-card prepend-icon="mdi-account" title="修改用户">
			<v-card-text>
				<v-row dense>

					<v-col cols="12" class="d-flex justify-center">

						<v-btn class="rounded-circle" size="auto" @click="triggerFileInput">
							<input type="file" ref="fileInput" hidden accept="image/*" @change="handleFileUpload">
							<v-avatar color="grey" size="150">
								<v-img :src="'http://192.168.3.142:7001/' + editUserDialogData.edit.avatar" cover></v-img>
								<v-icon
									style="position: absolute;top: 50%;left: 50%;transform: translate(-50%, -50%);color: rgba(255, 255, 255, 0.5);"
									class="camera-icon" size="30">mdi-camera</v-icon>
							</v-avatar>
						</v-btn>
					</v-col>

					<v-col cols="12" sm="6">
						<v-select label="帐号状态" :items="[
							{ title: '封禁', value: -1 },
							{ title: '正常', value: 0 },
							{ title: '冻结', value: 1 }
						]" item-title="title" item-value="value" v-model="editUserDialogData.edit.status" variant="underlined"
							color="accent"></v-select>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="账号" v-model="editUserDialogData.edit.number" variant="underlined" color="accent"
							required></v-text-field>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="用户名" v-model="editUserDialogData.edit.username" variant="underlined" color="accent"
							required></v-text-field>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="邮箱" v-model="editUserDialogData.edit.email" variant="underlined" color="accent"
							required></v-text-field>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="手机号" v-model="editUserDialogData.edit.phone" variant="underlined"
							color="accent"></v-text-field>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="密码" v-model="editUserDialogData.edit.password" variant="underlined" color="accent"
							type="password"></v-text-field>
					</v-col>
				</v-row>

				<small class="text-caption text-medium-emphasis">密码留空默认不修改</small>
			</v-card-text>

			<v-divider></v-divider>

			<v-card-actions>
				<v-spacer></v-spacer>
				<v-btn color="accent" text="取消" variant="text" @click="editUserDialogState = false"></v-btn>
				<v-btn color="accent" text="保存" variant="flat" @click="submitEditUser()"></v-btn>
			</v-card-actions>
		</v-card>
	</v-dialog>

</template>

<script setup lang="ts">
import UserApi from '@/api/app/users';
import UploadApi from '@/api/app/upload';
import CommonUtils from '@/api/utils/common';
//import { useNotifier } from 'vuetify-notifier';


const notifier = useNotifier();

//组件状态
const editUserDialogState = ref(false);
const editUserDialogData = ref({ origin: {}, edit: {} } as any);//后面API完善类型待规范
const openEditUserDialog = (data: {}) => {
	editUserDialogData.value.origin = CommonUtils.deepClone(data);
	editUserDialogData.value.edit = CommonUtils.deepClone(data);
	editUserDialogState.value = true
};

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
	{ title: '封禁/解封', value: 'delete' },
	{ title: '删除', value: 'disable' },
	{ title: '启用', value: 'enable' },
];
//每一页项目数量
const TableListRowsOptions = [
	{ title: '10 / 页', value: 10 },
	{ title: '20 / 页', value: 20 },
	{ title: '50 / 页', value: 50 },
	{ title: '100 / 页', value: 100 },
];


//表格数据
const tableItems = ref([{} as any]);
//批量选择数据
const tableSelected = ref([]);
//页面数量
const tablePaginationLength = ref(0);
//当前页面
const tableCurrentPage = ref(1);

//每页项目数量
const tableListRows = ref(TableListRowsOptions[0].value);

//获取表格数据
const getTableData = () => {
	UserApi.getUserIndex(tableCurrentPage.value, tableListRows.value).then((response) => {
		const data = response.data;
		tableCurrentPage.value = data.current_page;
		tablePaginationLength.value = data.last_page;
		tableItems.value = data.data;
	}).catch(err => console.error(err));
}; getTableData();

//修改用户资料
const patchUser = (data: { edit: any, origin: any }) => {
	//获取修改参数
	let params = CommonUtils.removeCommonProperties(data.edit, data.origin);
	//密码留空默认不修改
	if (params.password === '') {
		delete params.password;
	}
	if (Object.keys(params).length === 0) {
		//请修改后再提交
		return Promise.reject('请修改后再提交');
	};
	//插入用户ID
	params.id = data.origin.id;
	console.log(params);
	//返回原生Promise
	return UserApi.patchUser(params);
};

// 触发文件选择
const fileInput = ref<HTMLInputElement>();
const triggerFileInput = () => {
	fileInput.value?.click();
};
// 处理文件上传
const handleFileUpload = (e: Event) => {
	const input = e.target as HTMLInputElement;
	if (!input.files?.length) return;

	const file = input.files[0];

	const data = {
		file: file,
		aid: 0,
		pid: 0,
		uid: editUserDialogData.value.edit.id,
	}

	UploadApi.postUserImages(data).then((response: any) => {
		// 更新头像显示（假设接口返回新的头像路径）
		editUserDialogData.value.edit.avatar = response.data;
		notifier.toast({ text: "头像上传成功", type: 'success' });
	}).catch((error) => {
		console.error('上传失败:', error);
		notifier.toast({ text: "头像上传失败", type: 'error' });
	}).finally(() => {
		// 清空文件选择
		input.value = '';
	})
};

//页面操作提交修改用户
const submitEditUser = () => {
	patchUser(editUserDialogData.value).then((response) => {
		notifier.toast({
			"text": "修改成功",
			"color": "success",
			"type": "success",
		})
		//弹出正确提示并退出对话框并刷新列表
		editUserDialogState.value = false;
		getTableData();
	}).catch((err) => {
		notifier.toast({
			"text": "修改失败",
			"color": "error",
			"type": "error",
		})
		//弹出错误提示
		console.error(err)
	});
}

//数据获取监控
watch([tableCurrentPage, tableListRows], (newValue, oldValue) => {
	getTableData();
});
</script>