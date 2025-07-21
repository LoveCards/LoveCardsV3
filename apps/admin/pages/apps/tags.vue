<!-- 用户管理 -->
<template>
  <NuxtLayout name="root">
    <v-container max-width="96%" min-width="92%" fluid>
      <!-- 标题 -->
      <v-row class="pt-2">
        <v-col cols="6">
          <h1 class="text-primary font-weight-bold">标签管理</h1>
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
                <v-btn color="accent" variant="flat" @click="openCreateTagDialog()"> 创建标签 </v-btn>
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

                    <!-- 内容 -->
                    <!-- <template v-slot:[`item.content`]="{ item }">
                      <v-text-field width="250" variant="underlined" :model-value="item.content"
                        readonly></v-text-field>
                    </template> -->

                    <!-- 操作 -->
                    <template v-slot:[`item.operate`]="{ item }">
                      <v-btn icon="mdi-pencil" elevation="0" size="small" variant="text"
                        @click="openEditTagDialog(item)"></v-btn>
                      <v-btn icon="mdi-delete" elevation="0" size="small" variant="text"
                        @click="openDeleteTagDialog(item)"></v-btn>
                    </template>

                    <!-- 项目状态 -->
                    <template v-slot:[`item.status`]="{ item }">
                      <v-chip size="small">
                        {{ SelectUtils.getSelect(SelectUtils.Common.Status, item.status).title }}
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
                  v-model="BatchOperate"></v-select>
                <v-btn color="accent" variant="flat" class="ml-2" @click="openBatchTagDialog"
                  :disabled="tableSelected.length === 0 || BatchOperate === ''">批量操作</v-btn>
              </v-col>
              <!-- 分页按钮 -->
              <v-col cols="12" md="6" class="d-flex justify-end align-center">
                <v-menu>
                  <template v-slot:activator="{ props }">
                    <v-btn elevation="0" icon="mdi-table-cog" size="small" v-bind="props" variant="text"></v-btn>
                  </template>
                  <v-list>
                    <v-list-item v-for="(item, index) in SelectUtils.Common.Table.ListRowsOptions" :key="index"
                      :value="index">
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

  <!-- 创建对话框 -->
  <CreateTagDialog v-model:thisDialogState="CreateTagDialog_state" :getTableData="getTableData"></CreateTagDialog>
  <!-- 编辑对话框 -->
  <EditTagDialog v-model:thisDialogState="EditTagDialog_state" v-model:TagData="EditTagDialog_data"
    :getTableData="getTableData"></EditTagDialog>
  <!-- 删除对话框 -->
  <PublicDeleteDialog v-model:thisDialogState="DeleteTagDialog_state" v-model:deleteData="DeleteTagDialog_data"
    :deleteFun="DeleteTagFun"></PublicDeleteDialog>
  <!-- 批量处理用户对话框 -->
  <PublicBatchDialog v-model:thisDialogState="BatchTagDialog_state" v-model:batchData="tableSelected"
    v-model:batchOptions="BatchTagDialog_operate" :batchFun="BatchCardFun"></PublicBatchDialog>
  <!-- 搜索对话框 -->
  <PublicSearchDialog v-model:thisDialogState="SearchTagsDialog_state" :KEYS="SearchKeys"
    :setFilter="setTableSearchFilter" :getTableData="getTableData" KeysMessages="默认[内容]"></PublicSearchDialog>
</template>

<script setup lang="ts">
import TagsApi from "@/api/app/admin/tags";
import CreateTagDialog from "@/components/apps/tags/CreateTagDialog.vue";
import EditTagDialog from "@/components/apps/tags/EditTagDialog.vue";
import PublicDeleteDialog from "@/components/apps/public/Table/DeleteDialog.vue";
import PublicBatchDialog from "@/components/apps/public/Table/BatchDialog.vue";
import PublicSearchDialog from "@/components/apps/public/Table/SearchDialog.vue";
import SelectUtils from "~/api/utils/select";
const notifier = useNotifier();

//表格头部
const TableHeaders = [
  { title: "ID", value: "id" },
  { title: "应用ID", value: "aid" },
  { title: "用户ID", value: "user_id" },
  { title: "标签名", value: "name" },
  { title: "发布时间", value: "created_at" },
  { title: "状态", value: "status" },
  { title: "操作", value: "operate" },
];
//搜索字段
const SearchKeys = [...TableHeaders];
SearchKeys.pop();
//批量操作选项
const TableBatchOptions = [
  { title: '封禁/取消', value: 'ban' },
  { title: "隐藏/取消", value: "hide" },
  { title: "审核/取消", value: "approve" },
  { title: "删除", value: "delete" },
];

const tableItems = ref([{} as any]);//表格数据
const tableSelected = ref([]);//批量选择数据
const tablePaginationLength = ref(0);//页面数量
const tableCurrentPage = ref(1);//当前页面
const tableSearchValue = ref(undefined);//搜索值
const tableSearchFilter = ref({});//搜索过滤器

//每页项目数量
const tableListRows = ref(SelectUtils.Common.Table.ListRowsOptions[0].value);

//CreateTagDialog组件
const CreateTagDialog_state = ref(false);
const openCreateTagDialog = () => {
  CreateTagDialog_state.value = true;
};

//EditTagDialog组件
const EditTagDialog_state = ref(false);
const EditTagDialog_data = ref({} as any);
const openEditTagDialog = (data: any) => {
  EditTagDialog_data.value = data;
  EditTagDialog_state.value = true;
};

//DeleteDialog组件
const DeleteTagFun = (id: any) => {
  TagsApi.deleteTag({ id: id }).then(() => {
    DeleteTagDialog_state.value = false;
    getTableData();
  });
}
const DeleteTagDialog_state = ref(false);
const DeleteTagDialog_data = ref({} as any);
const openDeleteTagDialog = (data: {}) => {
  DeleteTagDialog_data.value = data;
  DeleteTagDialog_state.value = true;
};

// 批量操作相关
const BatchOperate = ref('');
//BatchTagDialog组件
const BatchCardFun = () => {
  const data = {
    ids: tableSelected.value,
    method: BatchOperate.value,
  }
  TagsApi.batchOperate(data).then(() => {
    BatchTagDialog_state.value = false;
    getTableData();
  })
}
const BatchTagDialog_state = ref(false);
//tableSelected
const BatchTagDialog_operate = ref('');
const openBatchTagDialog = () => {
  const headerMap = new Map(TableBatchOptions.map(header => [header.value, header.title]));
  BatchTagDialog_operate.value = headerMap.get(BatchOperate.value) || '';
  if (tableSelected.value.length != 0) {
    BatchTagDialog_state.value = true;
  }
};

//SearchDialog组件
const SearchTagsDialog_state = ref(false);
//子父传参
const setTableSearchFilter = (data: any) => {
  tableSearchFilter.value = data;
}
const openSearchUserDialog = () => {
  SearchTagsDialog_state.value = true;
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
  TagsApi.getTagIndex(params)
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
