<!-- 权限管理（只读，数据来自路由扫描） -->
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
              <v-chip color="accent" variant="tonal" size="small">数据来源：路由定义（只读）</v-chip>
            </v-col>
            <v-col cols="8" md="4" class="d-flex justify-end">
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
                  :items-per-page="tableListRows" item-value="hash">
                  <!-- Group -->
                  <template v-slot:[`item.group`]="{ item }">
                    <v-chip size="x-small" color="primary" variant="tonal">{{ item.group }}</v-chip>
                  </template>

                  <!-- Route Name -->
                  <template v-slot:[`item.route_name`]="{ item }">
                    <code class="text-caption">{{ item.route_name }}</code>
                  </template>

                  <!-- Method -->
                  <template v-slot:[`item.method`]="{ item }">
                    <v-chip size="x-small" :color="methodColor(item.method)">{{ item.method }}</v-chip>
                  </template>

                  <!-- URL Path -->
                  <template v-slot:[`item.path`]="{ item }">
                    <code class="text-caption bg-grey-lighten-4 pa-1 rounded">{{ item.path }}</code>
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
              <span class="text-caption text-grey">共 {{ totalItems }} 条权限</span>
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
</template>

<script setup lang="ts">
import { useApi } from '~/lib/api';
import SelectUtils from "~/api/utils/select";
const client = useApi();

const TableHeaders = [
  { title: "分组", value: "group" },
  { title: "名称", value: "name" },
  { title: "路由标识", value: "route_name" },
  { title: "Method", value: "method" },
  { title: "URL", value: "path" },
];

const methodColor = (method: string) => {
  const map: Record<string, string> = {
    GET: 'green', POST: 'blue', PATCH: 'orange', PUT: 'amber',
    DELETE: 'red',
  };
  return map[method] || 'grey';
};

// 表格状态
const tableItems = ref<any[]>([]);
const tablePaginationLength = ref(0);
const tableCurrentPage = ref(1);
const tableSearchValue = ref<string | undefined>(undefined);
const tableListRows = ref(SelectUtils.Common.Table.ListRowsOptions[0]?.value);
const totalItems = ref(0);

const getTableData = () => {
  const params = {
    page: tableCurrentPage.value,
    list_rows: tableListRows.value,
    search_value: tableSearchValue.value,
  };
  client.permissions.list(params).then((result) => {
    tableCurrentPage.value = result.pagination!.currentPage;
    tablePaginationLength.value = result.pagination!.totalPages;
    tableItems.value = result.data;
    totalItems.value = result.pagination!.totalItems;
  });
};

const searchTableData = () => {
  tableCurrentPage.value = 1;
  getTableData();
};

watch([tableCurrentPage, tableListRows], () => getTableData());

onMounted(() => getTableData());
</script>
