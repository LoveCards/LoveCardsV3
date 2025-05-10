<!-- 用户管理 -->
<template>
  <NuxtLayout name="root">
    <v-container max-width="sm">
      <!-- 标题 -->
      <v-row class="pt-2">
        <v-col cols="6">
          <h1 class="text-primary font-weight-bold">卡片管理</h1>
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
                <!-- <v-btn color="accent" variant="flat"> 添加用户 </v-btn> -->
              </v-col>
              <!-- 搜索栏 -->
              <v-col cols="8" md="4" class="d-flex justify-end">
                <v-btn elevation="0" icon="mdi-filter" size="small" variant="text"
                  @click="openSearchUserDialog()"></v-btn>
                <v-text-field color="accent" append-inner-icon="mdi-magnify" density="compact" label="搜索"
                  variant="outlined" v-model="tableSearchValue" @input="searchTableData" hide-details
                  single-line></v-text-field>
              </v-col>
            </v-row>

            <!-- 表格身体 -->
            <v-row>
              <v-col cols="12">
                <v-card variant="outlined" color="#E0E0E0">
                  <v-data-table :style="{ 'white-space': 'nowrap' }" :headers="TableHeaders" :items="tableItems"
                    :items-per-page="tableListRows" item-value="id" v-model="tableSelected" show-select>
                    <!-- 头部选择框 -->
                    <template v-slot:header.data-table-select="{
                      allSelected,
                      selectAll,
                      someSelected,
                    }">
                      <v-checkbox-btn :indeterminate="someSelected && !allSelected" :model-value="allSelected"
                        color="accent" @update:model-value="selectAll(!allSelected)"></v-checkbox-btn>
                    </template>

                    <!-- 内容选择框 -->
                    <template v-slot:item.data-table-select="{
                      internalItem,
                      isSelected,
                      toggleSelect,
                    }">
                      <v-checkbox-btn :model-value="isSelected(internalItem)" color="accent"
                        @update:model-value="toggleSelect(internalItem)"></v-checkbox-btn>
                    </template>

                    <!-- 标签 -->
                    <template v-slot:[`item.tag`]="{ item }">
                      <v-chip size="x-small" v-for="(value, index) in renderTags(item.tag)" :key="index">
                        {{ value }}
                      </v-chip>
                    </template>

                    <!-- 内容 -->
                    <template v-slot:[`item.content`]="{ item }">
                      <v-text-field width="250" variant="underlined" :model-value="item.content"
                        readonly></v-text-field>
                    </template>

                    <!-- 封面 -->
                    <template v-slot:[`item.img`]="{ item }">
                      <v-img :width="100" aspect-ratio="16/9" cover :alt="item.username"
                        :src="'http://192.168.3.142:7001/' + item.img"></v-img>
                    </template>

                    <!-- 操作 -->
                    <template v-slot:[`item.operate`]="{ item }">
                      <v-btn icon="mdi-pencil" elevation="0" size="small" color="accent" variant="text"
                        @click="openEditUserDialog(item)"></v-btn>
                      <v-btn icon="mdi-delete" elevation="0" size="small" color="accent" variant="text"
                        @click="openDeleteUserDialog(item)"></v-btn>
                    </template>

                    <!-- 置顶状态 -->
                    <template v-slot:[`item.top`]="{ item }">
                      <v-chip size="small">
                        {{ renderTop(item.top) }}
                      </v-chip>
                    </template>

                    <!-- 模型状态 -->
                    <template v-slot:[`item.model`]="{ item }">
                      <v-chip size="small">
                        {{ renderModel(item.model) }}
                      </v-chip>
                    </template>

                    <!-- 卡片状态 -->
                    <template v-slot:[`item.status`]="{ item }">
                      <v-chip :color="item.status === 0
                        ? 'success'
                        : item.status === -1
                          ? 'error'
                          : 'warning'
                        " variant="flat" size="small">
                        {{
                          CardStates.find(
                            (state) => state.value === item.status
                          )?.title
                        }}
                      </v-chip>
                    </template>

                    <!-- 无结果时的显示内容 -->
                    <template v-slot:no-data>
                      没有找到相关数据
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
                  color="accent" :items="TableBatchOptions" item-title="title" item-value="value"
                  v-model="BatchUserBtnStatus"></v-select>
                <v-btn color="accent" variant="flat" class="ml-2" @click="openBatchUserDialog"
                  :disabled="tableSelected.length === 0">批量操作</v-btn>
              </v-col>
              <!-- 分页按钮 -->
              <v-col cols="12" md="6" class="d-flex justify-end align-center">
                <v-menu>
                  <template v-slot:activator="{ props }">
                    <v-btn elevation="0" icon="mdi-table-cog" size="small" v-bind="props" variant="text"></v-btn>
                  </template>
                  <v-list>
                    <v-list-item v-for="(item, index) in TableListRowsOptions" :key="index" :value="index">
                      <v-list-item-title @click="tableListRows = item.value">{{
                        item.title
                      }}</v-list-item-title>
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
  <EditUserDialog v-model:thisDialogState="EditUserDialog_state" v-model:editUserData="EditUserDialog_data"
    :getTableData="getTableData" :ACCOUNT_STATUS="CardStates" :USER_ROLES="UserRoles"></EditUserDialog>
  <!-- 删除用户对话框 -->
  <DeleteUserDialog v-model:thisDialogState="DeleteUserDialog_state" v-model:delUserData="DeleteUserDialog_data"
    :getTableData="getTableData"></DeleteUserDialog>
  <!-- 批量处理用户对话框 -->
  <BatchUserDialog v-model:thisDialogState="BatchUserDialog_state" v-model:batchUserData="tableSelected"
    :getTableData="getTableData"></BatchUserDialog>
  <!-- 搜索用户对话框 -->
  <SearchUserDialog v-model:thisDialogState="SearchUserDialog_state" :KEYS="SearchKeys"
    :setFilter="setTableSearchFilter" :getTableData="getTableData"></SearchUserDialog>
</template>

<script setup lang="ts">
import CardsApi from "@/api/app/cards";
import CommonUtils from "@/api/utils/common";
import DeleteUserDialog from "@/components/users/DeleteUserDialog.vue";
import EditUserDialog from "@/components/users/EditUserDialog.vue";
import BatchUserDialog from "@/components/users/BatchUserDialog.vue";
import SearchUserDialog from "@/components/users/SearchUserDialog.vue";
import { useTagStore } from "@/stores/TagStore";
const notifier = useNotifier();

//状态
const CardStates = [
  { title: "正常", value: 0 },
  { title: "封禁", value: 1 },
];
const TopStates = [
  { title: "正常", value: 0 },
  { title: "置顶", value: 1 },
];
const ModelStates = [
  { title: "表白卡", value: 0 },
  { title: "交流卡", value: 1 },
];
//渲染数据预处理
const renderTop = (data: any) => {
  if (data !== '' && data !== undefined) {
    let result = "";
    const top = TopStates.find((item: any) => item.value == data);
    if (top) {
      result = top.title;
    }
    return result;
  } else {
    return "";
  }
};
const renderModel = (data: any) => {
  if (data != '' && data !== undefined) {
    let result = "";
    const model = ModelStates.find((item: any) => item.value == data);
    if (model) {
      result = model.title;
    }
    return result;
  } else {
    return "无";
  }
};
//表格头部
const TableHeaders = [
  { title: "ID", value: "id" },
  { title: "用户ID", value: "uid" },
  { title: "模型", value: "model" },
  { title: "内容", value: "content" },
  { title: "封面", value: "img" },
  { title: "主字段1", value: "woName" },
  { title: "副字段1", value: "woContact" },
  { title: "主字段2", value: "taName" },
  { title: "副字段2", value: "taContact" },
  { title: "点赞数", value: "good" },
  { title: "评论数", value: "comments" },
  { title: "浏览量", value: "look" },
  { title: "标签", value: "tag" },
  { title: "发布时间", value: "time" },
  { title: "IP", value: "ip" },
  { title: "置顶", value: "top" },
  { title: "状态", value: "status" },
  { title: "操作", value: "operate" },
];
//搜索字段
const SearchKeys = [...TableHeaders];
SearchKeys.pop();
//批量操作选项
const TableBatchOptions = [
  // { title: '封禁/解封', value: 'delete' },
  { title: "删除", value: "disable" },
  // { title: '启用', value: 'enable' },
];
//每一页项目数量
const TableListRowsOptions = [
  { title: "10 / 页", value: 10 },
  { title: "20 / 页", value: 20 },
  { title: "50 / 页", value: 50 },
  { title: "100 / 页", value: 100 },
];
//标签
const tagStore = useTagStore();
const Tags = ref([] as any);
Tags.value = tagStore.tags;
//渲染用户标签数据预处理
const renderTags = (tags: any) => {
  if (tags != '' && tags !== undefined) {
    tags = JSON.parse(tags);
    let TagsValue: string[] = [];
    tags.forEach((item: any) => {
      const tag = Tags.value.find((tagItem: any) => tagItem.id === item);
      if (tag) {
        TagsValue.push(tag.name);
      }
    });
    return TagsValue;
  } else {
    return [];
  }
};

const tableItems = ref([{} as any]);//表格数据
const tableSelected = ref([]);//批量选择数据
const tablePaginationLength = ref(0);//页面数量
const tableCurrentPage = ref(1);//当前页面
const tableSearchValue = ref(undefined);//搜索值
const tableSearchFilter = ref({});//搜索过滤器

//每页项目数量
const tableListRows = ref(TableListRowsOptions[0].value);

//EditUserDialog组件
const EditUserDialog_state = ref(false);
const EditUserDialog_data = ref({ origin: {}, edit: {} } as any);
const openEditUserDialog = (data: {}) => {
  EditUserDialog_data.value.origin = CommonUtils.deepClone(data);
  EditUserDialog_data.value.edit = CommonUtils.deepClone(data);
  EditUserDialog_state.value = true;
};

//DeleteUserDialog组件
const DeleteUserDialog_state = ref(false);
const DeleteUserDialog_data = ref({} as any);
const openDeleteUserDialog = (data: {}) => {
  DeleteUserDialog_data.value = data;
  DeleteUserDialog_state.value = true;
};

// 批量操作相关
const BatchUserBtnStatus = ref("");
//BatchUserDialog组件
const BatchUserDialog_state = ref(false);
const openBatchUserDialog = () => {
  if (tableSelected.value.length === 0) {
    notifier.toast({
      text: "请选择要操作的用户",
      type: "warning",
    });
    return;
  }

  if (BatchUserBtnStatus.value === "disable") {
    BatchUserDialog_state.value = true;
  }
};

//SearchUserDialog组件
const SearchUserDialog_state = ref(false);
//子父传参
const setTableSearchFilter = (data: any) => {
  tableSearchFilter.value = data;
}
const openSearchUserDialog = () => {
  SearchUserDialog_state.value = true;
};

//获取表格数据
const getTableData = () => {
  //合并参数
  const params = {
    page: tableCurrentPage.value,
    list_rows: tableListRows.value,
    search_value: tableSearchValue.value,
    ...tableSearchFilter.value
  };
  //获取数据
  CardsApi.getCardIndex(params)
    .then((response) => {
      const data = response.data;
      tableCurrentPage.value = data.current_page;
      tablePaginationLength.value = data.last_page;
      tableItems.value = data.data;
    })
};

//搜索防抖
const searchTableData = useDebounce(() => {
  getTableData();
}, 500);

//数据获取监控
watch([tableCurrentPage, tableListRows], (newValue, oldValue) => {
  getTableData();
});

//页面加载完成后获取数据
onMounted(() => {
  getTableData();
});
</script>
