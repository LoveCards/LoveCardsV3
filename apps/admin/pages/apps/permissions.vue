<!-- 权限管理 -->
<template>
  <NuxtLayout name="root">
    <!-- 标题 -->
    <v-row class="pt-2">
      <v-col cols="6">
        <h1 class="text-primary font-weight-bold">权限管理</h1>
      </v-col>
    </v-row>

    <!-- 内容 -->
    <v-row>
      <v-col cols="12">
        <v-card class="pa-4">
          <!-- 表格头部 -->
          <v-row>
            <v-col cols="4" md="8" class="d-flex align-center">
              <v-btn color="accent" variant="flat" @click="CreateDialog_state = true">创建权限</v-btn>
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

                  <!-- Slug -->
                  <template v-slot:[`item.slug`]="{ item }">
                    <code class="text-caption bg-grey-lighten-4 pa-1 rounded">{{ item.slug }}</code>
                  </template>

                  <!-- Path -->
                  <template v-slot:[`item.path`]="{ item }">
                    <code class="text-caption">{{ item.path }}</code>
                  </template>

                  <!-- Method -->
                  <template v-slot:[`item.method`]="{ item }">
                    <v-chip size="x-small" :color="methodColor(item.method)">{{ item.method }}</v-chip>
                  </template>

                  <!-- 操作 -->
                  <template v-slot:[`item.operate`]="{ item }">
                    <v-btn icon="mdi-pencil" elevation="0" size="small" variant="text"
                      @click="openEditDialog(item)"></v-btn>
                    <v-btn icon="mdi-delete" elevation="0" size="small" variant="text"
                      @click="openDeleteDialog(item)"></v-btn>
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

  <CreatePermissionDialog v-model:thisDialogState="CreateDialog_state"
    :getTableData="getTableData"></CreatePermissionDialog>
  <EditPermissionDialog v-model:thisDialogState="EditDialog_state" v-model:PermissionData="EditDialog_data"
    :getTableData="getTableData"></EditPermissionDialog>
  <PublicDeleteDialog v-model:thisDialogState="DeleteDialog_state" v-model:deleteData="DeleteDialog_data"
    :deleteFun="DeleteFun"></PublicDeleteDialog>
  <PublicBatchDialog v-model:thisDialogState="BatchDialog_state" v-model:batchData="tableSelected"
    v-model:batchOptions="BatchDialog_operate" :batchFun="BatchFun"></PublicBatchDialog>
  <PublicSearchDialog v-model:thisDialogState="SearchDialog_state" :KEYS="SearchKeys"
    :setFilter="setTableSearchFilter" :getTableData="getTableData" KeysMessages="默认[名称]"></PublicSearchDialog>
</template>

<script setup lang="ts">
import PermissionsApi from "@/api/app/admin/permissions";
import CommonUtils from "@/utils/common";
import PublicDeleteDialog from "@/components/apps/public/Table/DeleteDialog.vue";
import PublicBatchDialog from "@/components/apps/public/Table/BatchDialog.vue";
import PublicSearchDialog from "@/components/apps/public/Table/SearchDialog.vue";
import CreatePermissionDialog from "@/components/apps/permissions/CreatePermissionDialog.vue";
import EditPermissionDialog from "@/components/apps/permissions/EditPermissionDialog.vue";
import SelectUtils from "~/api/utils/select";

const TableHeaders = [
  { title: "ID", value: "id" },
  { title: "名称", value: "name" },
  { title: "Slug", value: "slug" },
  { title: "Path", value: "path" },
  { title: "Method", value: "method" },
  { title: "描述", value: "description" },
  { title: "操作", value: "operate", sortable: false },
];

const SearchKeys = [
  { title: "ID", value: "id" },
  { title: "名称", value: "name" },
  { title: "Slug", value: "slug" },
  { title: "Path", value: "path" },
  { title: "Method", value: "method" },
];

const TableBatchOptions = [
  { title: '删除', value: 'delete' },
];

const methodColor = (method: string) => {
  const map: Record<string, string> = {
    GET: 'green', POST: 'blue', PATCH: 'orange', PUT: 'amber',
    DELETE: 'red', '*': 'grey',
  };
  return map[method] || 'grey';
};

// 表格状态
const tableItems = ref<any[]>([]);
const tableSelected = ref<number[]>([]);
const tablePaginationLength = ref(0);
const tableCurrentPage = ref(1);
const tableSearchValue = ref<string | undefined>(undefined);
const tableSearchFilter = ref<any>({});
const tableListRows = ref(SelectUtils.Common.Table.ListRowsOptions[0]?.value);

const getTableData = () => {
  const params = {
    page: tableCurrentPage.value,
    list_rows: tableListRows.value,
    search_value: tableSearchValue.value,
    ...tableSearchFilter.value,
  };
  PermissionsApi.getPermissionIndex(params).then((response) => {
    const data = response.data;
    tableCurrentPage.value = data.current_page;
    tablePaginationLength.value = data.last_page;
    tableItems.value = data.data;
  });
};

const searchTableData = CommonUtils.lodash.debounce(() => getTableData(), 500);

watch([tableCurrentPage, tableListRows], () => getTableData());

// 创建
const CreateDialog_state = ref(false);

// 编辑
const EditDialog_state = ref(false);
const EditDialog_data = ref<any>({});
const openEditDialog = (item: any) => {
  EditDialog_data.value = item;
  EditDialog_state.value = true;
};

// 删除
const DeleteDialog_state = ref(false);
const DeleteDialog_data = ref<any>({});
const DeleteFun = (id: number) => {
  PermissionsApi.deletePermission({ id }).then(() => {
    DeleteDialog_state.value = false;
    getTableData();
  }).catch(() => {});
};
const openDeleteDialog = (item: any) => {
  DeleteDialog_data.value = { id: item.id, display: item.name };
  DeleteDialog_state.value = true;
};

// 批量操作
const BatchOperate = ref('');
const BatchDialog_state = ref(false);
const BatchDialog_operate = ref('');
const openBatchDialog = () => {
  const headerMap = new Map(TableBatchOptions.map(h => [h.value, h.title]));
  BatchDialog_operate.value = headerMap.get(BatchOperate.value) || '';
  if (tableSelected.value.length !== 0) BatchDialog_state.value = true;
};
const BatchFun = () => {
  Promise.all(tableSelected.value.map(id => PermissionsApi.deletePermission({ id }))).then(() => {
    BatchDialog_state.value = false;
    tableSelected.value = [];
    getTableData();
  }).catch(() => {});
};

// 搜索
const SearchDialog_state = ref(false);
const setTableSearchFilter = (data: any) => { tableSearchFilter.value = data; };

onMounted(() => getTableData());
</script>
