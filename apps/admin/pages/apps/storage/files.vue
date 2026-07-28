<!-- 文件管理 -->
<template>
  <NuxtLayout name="root">
    <!-- 标题 -->
    <v-row class="pt-2">
      <v-col cols="6">
        <h1 class="text-primary font-weight-bold">文件管理</h1>
      </v-col>
    </v-row>

    <!-- 视图切换 -->
    <v-tabs v-model="viewMode" color="accent" density="compact" align-tabs="end" class="mb-2">
      <v-tab value="normal">文件列表</v-tab>
      <v-tab value="deleted">回收站</v-tab>
    </v-tabs>

    <!-- 内容 -->
    <v-row>
      <v-col cols="12">
        <v-card class="pa-4">
          <!-- 表格头部 -->
          <v-row>
            <v-col cols="4" md="8" class="d-flex align-center">
              <v-btn color="accent" variant="flat" @click="openCleanupDialog">清理过期文件</v-btn>
            </v-col>
            <v-col cols="8" md="4" class="d-flex justify-end">
              <v-btn elevation="0" icon="mdi-filter" size="small" variant="text"
                @click="SearchDialog_state = true"></v-btn>
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
                  <template v-slot:header.data-table-select="{ allSelected, selectAll, someSelected }">
                    <v-checkbox-btn :indeterminate="someSelected && !allSelected" :model-value="allSelected"
                      color="accent" @update:model-value="selectAll(!allSelected)"></v-checkbox-btn>
                  </template>
                  <template v-slot:item.data-table-select="{ internalItem, isSelected, toggleSelect }">
                    <v-checkbox-btn :model-value="isSelected(internalItem)" color="accent"
                      @update:model-value="toggleSelect(internalItem)"></v-checkbox-btn>
                  </template>

                  <!-- 预览 -->
                  <template v-slot:[`item.preview`]="{ item }">
                    <v-img v-if="isImage(item.mime_type)" class="rounded-lg" :width="40" :height="40" cover
                      :src="item.file_url" :alt="item.original_name"></v-img>
                    <v-icon v-else size="40" color="grey">mdi-file-outline</v-icon>
                  </template>

                  <!-- 文件大小 -->
                  <template v-slot:[`item.file_size`]="{ item }">
                    {{ formatSize(item.file_size) }}
                  </template>

                  <!-- 场景 -->
                  <template v-slot:[`item.scene`]="{ item }">
                    <v-chip size="x-small" :color="sceneColor(item.scene)">{{ item.scene }}</v-chip>
                  </template>

                  <!-- 状态 -->
                  <template v-slot:[`item.status`]="{ item }">
                    <v-chip size="small" :color="SelectUtils.getSelect(SelectUtils.Files.status, item.status)?.color">
                      {{ SelectUtils.getSelect(SelectUtils.Files.status, item.status)?.title }}
                    </v-chip>
                  </template>

                  <!-- 上传状态 -->
                  <template v-slot:[`item.upload_status`]="{ item }">
                    <v-chip size="small" :color="SelectUtils.getSelect(SelectUtils.Files.uploadStatus, item.upload_status)?.color">
                      {{ SelectUtils.getSelect(SelectUtils.Files.uploadStatus, item.upload_status)?.title }}
                    </v-chip>
                  </template>

                  <!-- 公开 -->
                  <template v-slot:[`item.is_public`]="{ item }">
                    <v-icon size="small">
                      {{ item.is_public ? 'mdi-eye' : 'mdi-eye-off' }}
                    </v-icon>
                  </template>

                  <!-- 操作 -->
                  <template v-slot:[`item.operate`]="{ item }">
                    <!-- 正常文件视图 -->
                    <template v-if="viewMode === 'normal'">
                      <v-btn icon="mdi-pencil" elevation="0" size="small" variant="text"
                        @click="openEditDialog(item)"></v-btn>
                      <v-btn icon="mdi-delete" elevation="0" size="small" variant="text"
                        @click="handleBatchOp([item.id], 'trash')"></v-btn>
                    </template>
                    <!-- 回收站视图 -->
                    <template v-else>
                      <v-btn icon="mdi-restore" elevation="0" size="small" variant="text"
                        @click="handleBatchOp([item.id], 'restore')"></v-btn>
                      <v-btn icon="mdi-delete-forever" elevation="0" size="small" variant="text"
                        @click="openHardDeleteDialog(item)"></v-btn>
                    </template>
                  </template>

                  <template v-slot:no-data>没有找到相关数据</template>
                  <template v-slot:bottom></template>
                </v-data-table>
              </v-card>
            </v-col>
          </v-row>

          <!-- 表格脚部 -->
          <v-row>
            <v-col cols="12" md="6" class="d-flex justify-start align-center">
              <v-select density="compact" label="请选择操作" max-width="160px" hide-details variant="outlined"
                color="accent" :items="TableBatchOptions" item-title="title" item-value="value"
                v-model="BatchOperate"></v-select>
              <v-btn color="accent" variant="flat" class="ml-2" @click="openBatchDialog"
                :disabled="tableSelected.length === 0 || BatchOperate === ''">批量操作</v-btn>
            </v-col>
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
              <v-pagination v-model="tableCurrentPage" :length="tablePaginationLength" :total-visible="5" color="accent"
                size="small" variant="elevated"></v-pagination>
            </v-col>
          </v-row>
        </v-card>
      </v-col>
    </v-row>
  </NuxtLayout>

  <!-- 编辑文件对话框 -->
  <EditFileDialog v-model:thisDialogState="EditDialog_state" v-model:FileData="EditDialog_data"
    :getTableData="getTableData"></EditFileDialog>
  <!-- 永久删除确认对话框 -->
  <PublicDeleteDialog v-model:thisDialogState="DeleteDialog_state" v-model:deleteData="DeleteDialog_data"
    :deleteFun="HardDeleteFun"></PublicDeleteDialog>
  <!-- 批量操作对话框 -->
  <PublicBatchDialog v-model:thisDialogState="BatchDialog_state" v-model:batchData="tableSelected"
    v-model:batchOptions="BatchDialog_operate" :batchFun="BatchFun"></PublicBatchDialog>
  <!-- 搜索对话框 -->
  <PublicSearchDialog v-model:thisDialogState="SearchDialog_state" :KEYS="SearchKeys"
    :setFilter="setTableSearchFilter" :getTableData="getTableData" KeysMessages="默认[文件名]"></PublicSearchDialog>
</template>

<script setup lang="ts">
import { useApi } from '~/lib/api';
import type { FilesBatchMethod } from '@lovecards/sdk';
import CommonUtils from "~/api/utils/common";
import PublicDeleteDialog from "@/components/apps/public/Table/DeleteDialog.vue";
import PublicBatchDialog from "@/components/apps/public/Table/BatchDialog.vue";
import PublicSearchDialog from "@/components/apps/public/Table/SearchDialog.vue";
import EditFileDialog from "@/components/apps/storage/EditFileDialog.vue";
import SelectUtils from "~/api/utils/select";
const client = useApi();

// 视图模式
const viewMode = ref('normal');

// 表格头部
const TableHeaders = [
  { title: "ID", value: "id" },
  { title: "预览", value: "preview", sortable: false },
  { title: "文件名", value: "original_name" },
  { title: "大小", value: "file_size" },
  { title: "场景", value: "scene" },
  { title: "渠道", value: "channel_slug" },
  { title: "上传者", value: "user_id" },
  { title: "状态", value: "status" },
  { title: "上传状态", value: "upload_status" },
  { title: "公开", value: "is_public" },
  { title: "创建时间", value: "created_at" },
  { title: "操作", value: "operate", sortable: false },
];

const SearchKeys = [
  { title: "ID", value: "id" },
  { title: "文件名", value: "original_name" },
  { title: "场景", value: "scene" },
  { title: "渠道", value: "channel_slug" },
  { title: "上传者", value: "user_id" },
  { title: "状态", value: "status" },
  { title: "上传状态", value: "upload_status" },
];

// 批量操作选项
const TableBatchOptions = computed(() => {
  if (viewMode.value === 'deleted') {
    return [
      { title: '恢复', value: 'restore' },
      { title: '永久删除', value: 'hard_delete' },
    ];
  }
  return [
    { title: '审核通过', value: 'approve' },
    { title: '封禁', value: 'ban' },
    { title: '切换公开', value: 'toggle_public' },
    { title: '移入回收站', value: 'trash' },
    { title: '永久删除', value: 'hard_delete' },
  ];
});

// 渲染辅助
const isImage = (mime: string | null) => mime?.startsWith('image/') ?? false;

const formatSize = (bytes: number) => {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const sceneColor = (scene: string) => {
  const map: Record<string, string> = { card: 'blue', comment: 'cyan', avatar: 'purple', direct: 'grey' };
  return map[scene] || 'grey';
};

// 表格状态
const tableItems = ref<any[]>([]);
const tableSelected = ref<number[]>([]);
const tablePaginationLength = ref(0);
const tableCurrentPage = ref(1);
const tableSearchValue = ref<string | undefined>(undefined);
const tableSearchFilter = ref<any>({});
const tableListRows = ref(SelectUtils.Common.Table.ListRowsOptions[0]?.value);

// 获取表格数据
const getTableData = () => {
  const params = {
    page: tableCurrentPage.value,
    list_rows: tableListRows.value,
    search_value: tableSearchValue.value,
    show_deleted: viewMode.value === 'deleted' ? 2 : 0,
    ...tableSearchFilter.value,
  };
  client.files.list(params).then((result) => {
    tableCurrentPage.value = result.pagination!.currentPage;
    tablePaginationLength.value = result.pagination!.totalPages;
    tableItems.value = result.data;
  });
};

// 搜索防抖
const searchTableData = CommonUtils.lodash.debounce(() => {
  getTableData();
}, 500);

// 监控分页变化
watch([tableCurrentPage, tableListRows], () => {
  getTableData();
});

// 监控视图模式变化
watch(viewMode, () => {
  tableCurrentPage.value = 1;
  tableSelected.value = [];
  getTableData();
});

// 统一操作方法
const handleBatchOp = (ids: number[], method: FilesBatchMethod) => {
  client.files.batch({ ids, method }).then(() => {
    getTableData();
  });
};

// 编辑对话框
const EditDialog_state = ref(false);
const EditDialog_data = ref<any>({});
const openEditDialog = (item: any) => {
  EditDialog_data.value = {
    origin: CommonUtils.deepClone(item),
    edit: CommonUtils.deepClone(item),
  };
  EditDialog_state.value = true;
};

// 永久删除
const DeleteDialog_state = ref(false);
const DeleteDialog_data = ref<any>({});
const HardDeleteFun = (id: number) => {
  client.files.batch({ ids: [id], method: 'hard_delete' }).then(() => {
    DeleteDialog_state.value = false;
    getTableData();
  });
};
const openHardDeleteDialog = (item: any) => {
  DeleteDialog_data.value = { id: item.id, display: item.original_name || item.file_url };
  DeleteDialog_state.value = true;
};

// 清理过期文件
const openCleanupDialog = () => {
  client.files.cleanup().then(() => {
    getTableData();
  });
};

// 批量操作
const BatchOperate = ref<FilesBatchMethod | ''>('');
const BatchDialog_state = ref(false);
const BatchDialog_operate = ref('');
const openBatchDialog = () => {
  const headerMap = new Map(TableBatchOptions.value.map(h => [h.value, h.title]));
  BatchDialog_operate.value = headerMap.get(BatchOperate.value) || '';
  if (tableSelected.value.length !== 0) {
    BatchDialog_state.value = true;
  }
};
const BatchFun = () => {
  if (BatchOperate.value === '') return;
  handleBatchOp(tableSelected.value, BatchOperate.value);
  BatchDialog_state.value = false;
  tableSelected.value = [];
};

// 搜索
const SearchDialog_state = ref(false);
const setTableSearchFilter = (data: any) => { tableSearchFilter.value = data; };

onMounted(() => getTableData());
</script>
