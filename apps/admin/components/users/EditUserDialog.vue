<template>
	<!-- 编辑用户对话框 -->
	<v-dialog v-model="thisDialogState" max-width="600">
		<v-card prepend-icon="mdi-account" title="修改用户">
			<v-card-text>
				<v-row dense>
					<v-col cols="12" class="d-flex justify-center">
						<v-btn class="rounded-circle" size="auto" @click="triggerFileInput">
							<input type="file" ref="fileInput" hidden accept="image/*" @change="handleFileUpload" />
							<v-avatar color="grey" size="150">
								<v-img :src="'http://192.168.3.142:7001/' +
									editUserData.edit.avatar
									" cover></v-img>
								<v-icon style="
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    color: rgba(255, 255, 255, 0.5);
                  " class="camera-icon" size="30">mdi-camera</v-icon>
							</v-avatar>
						</v-btn>
					</v-col>

					<v-col cols="12" sm="6">
						<v-select label="帐号状态" :items="ACCOUNT_STATUS" item-title="title" item-value="value"
							v-model="editUserData.edit.status" variant="underlined" color="accent"></v-select>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="账号" v-model="editUserData.edit.number" variant="underlined" color="accent"
							required></v-text-field>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="用户名" v-model="editUserData.edit.username" variant="underlined" color="accent"
							required></v-text-field>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="邮箱" v-model="editUserData.edit.email" variant="underlined" color="accent"
							required></v-text-field>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="手机号" v-model="editUserData.edit.phone" variant="underlined"
							color="accent"></v-text-field>
					</v-col>

					<v-col cols="12" sm="6">
						<v-text-field label="密码" v-model="editUserData.edit.password" variant="underlined" color="accent"
							type="password"></v-text-field>
					</v-col>
				</v-row>

				<small class="text-caption text-medium-emphasis">密码留空默认不修改</small>
			</v-card-text>

			<v-divider></v-divider>

			<v-card-actions>
				<v-spacer></v-spacer>
				<v-btn color="accent" text="取消" variant="text" @click="thisDialogState = false"></v-btn>
				<v-btn color="accent" text="保存" variant="flat" @click="submitEditUser()"></v-btn>
			</v-card-actions>
		</v-card>
	</v-dialog>
</template>

<script setup lang="ts">
import UserApi from "@/api/app/users";
import UploadApi from "@/api/app/upload";
import CommonUtils from "@/api/utils/common";

const notifier = useNotifier();

//Props
const props = defineProps({
	ACCOUNT_STATUS: Array,
	getTableData: Function,
});
const getTableData = () => {
	if (props.getTableData) {
		props.getTableData();
	}
};
const ACCOUNT_STATUS = props.ACCOUNT_STATUS;

//对话框状态
const thisDialogState = defineModel<boolean>('thisDialogState');
//用户数据
interface EditUserData {
	edit: any;
	origin: any;
}
const editUserData = defineModel<EditUserData>('editUserData', { default: { edit: {}, origin: {} } });

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
		uid: editUserData.value.edit.id,
	};

	UploadApi.postUserImages(data)
		.then((response: any) => {
			// 更新头像显示（假设接口返回新的头像路径）
			editUserData.value.edit.avatar = response.data;
		})
		.finally(() => {
			// 清空文件选择
			input.value = "";
		});
};

//页面操作提交修改用户
const submitEditUser = () => {
	patchUser(editUserData.value).then((response) => {
		//退出对话框并刷新列表
		thisDialogState.value = false;
		getTableData();
	});
};

//API
//修改用户资料
const patchUser = (data: EditUserData) => {
	//获取修改参数
	let params = CommonUtils.removeCommonProperties(data.edit, data.origin);
	//密码留空默认不修改
	if (params.password === "") {
		delete params.password;
	}
	if (Object.keys(params).length === 0) {
		//请修改后再提交
		notifier.toast({
			text: "请修改后再提交",
			type: "warning",
		});
		return Promise.reject();
	}
	//插入用户ID
	params.id = data.origin.id;
	// console.log(params);
	//返回原生Promise
	return UserApi.patchUser(params);
};

</script>