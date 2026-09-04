<template>
  <AdminPageHeader
    title="权限管理"
    description="查看服务端能力目录与作用域。权限定义由后端维护，管理端仅提供只读检索。"
    :breadcrumbs="[{ title: '访问控制' }, { title: '权限' }]"
  >
    <template #actions>
      <v-chip color="info" variant="tonal" size="small">只读目录</v-chip>
    </template>
  </AdminPageHeader>

  <AdminPageContainer fluid>
    <AdminResourceTableShell
      v-model:visible-columns="visibleColumns"
      :columns="columnOptions"
      :default-columns="defaultColumns"
      :result-count="tableTotalItems"
      preference-key="access-permissions"
      @reset-view="resetTableView"
    >
      <template #toolbar>
        <v-row dense align="center">
          <v-col cols="12" sm="auto">
            <v-chip prepend-icon="mdi-shield-key-outline" size="small" variant="tonal">
              {{ tableTotalItems }} 条能力授权
            </v-chip>
          </v-col>
          <v-spacer />
          <v-col cols="12" sm="6" md="4">
            <v-text-field
              v-model="tableSearchValue"
              append-inner-icon="mdi-magnify"
              clearable
              density="compact"
              hide-details
              label="搜索能力标识或描述"
              single-line
              variant="outlined"
              @update:model-value="searchTableData"
            />
          </v-col>
        </v-row>
      </template>

      <v-alert
        v-if="tableError && tableRows.length"
        class="ma-3"
        type="error"
        variant="tonal"
        title="刷新权限目录失败"
        :text="tableError"
      >
        <template #append>
          <v-btn variant="outlined" @click="getTableData">重试</v-btn>
        </template>
      </v-alert>

      <v-data-table-server
        v-model:items-per-page="tableListRows"
        v-model:page="tableCurrentPage"
        v-model:sort-by="tableSortBy"
        class="text-no-wrap"
        density="comfortable"
        hover
        item-value="key"
        items-per-page-text="每页行数"
        :headers="TableHeaders"
        :items="tableRows"
        :items-length="tableTotalItems"
        :items-per-page-options="TableListRowsOptions"
        :loading="tableLoading"
      >
        <template #loading>
          <v-skeleton-loader type="table-row-divider@6" />
        </template>

        <template #[`item.capability`]="{ item }">
          <v-btn
            class="text-none px-2"
            min-height="44"
            prepend-icon="mdi-key-outline"
            :to="`/apps/access/permissions/${encodeURIComponent(item.capability)}/${encodeURIComponent(item.scope)}`"
            variant="text"
          >
            {{ item.capability }}
          </v-btn>
        </template>

        <template #[`item.scope`]="{ item }">
          <v-chip size="small" variant="tonal">{{ scopeLabel(item.scope) }}</v-chip>
        </template>

        <template #[`item.description`]="{ item }">
          <v-list-item class="px-0 py-1" width="480">
            <v-list-item-subtitle class="text-high-emphasis text-wrap">
              {{ item.description || '—' }}
            </v-list-item-subtitle>
          </v-list-item>
        </template>

        <template #no-data>
          <v-empty-state
            :icon="tableError ? 'mdi-cloud-alert-outline' : 'mdi-shield-search-outline'"
            :title="tableError ? '无法加载权限目录' : '没有匹配的能力授权'"
            :text="tableError || '权限定义由服务端维护，请调整搜索关键词。'"
          >
            <template #actions>
              <v-btn v-if="tableError" color="primary" variant="outlined" @click="getTableData">重试</v-btn>
              <v-btn v-else prepend-icon="mdi-filter-remove-outline" variant="text" @click="clearSearch">清除搜索</v-btn>
            </template>
          </v-empty-state>
        </template>
      </v-data-table-server>
    </AdminResourceTableShell>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { CapabilityItem } from '@lovecards/sdk';
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue';
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue';
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue';
import { useApi } from '~/lib/api';
import CommonUtils from '@/utils/common';
import SelectUtils from '~/utils/select';

type TableSortItem = { key: string; order?: boolean | 'asc' | 'desc' };

const client = useApi();
const route = useRoute();
const router = useRouter();

const positiveQueryInt = (value: unknown, fallback: number) => {
  const parsed = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN;
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
};

const AllTableHeaders = [
  { title: '能力标识', value: 'capability', sortable: true },
  { title: '作用域', value: 'scope', sortable: true },
  { title: '描述', value: 'description', sortable: true },
];
const defaultColumns = ['capability', 'scope', 'description'];
const visibleColumns = ref([...defaultColumns]);
const TableHeaders = computed(() => AllTableHeaders.filter((header) => visibleColumns.value.includes(header.value)));
const columnOptions = AllTableHeaders.map((header) => ({ title: header.title, key: header.value, required: header.value === 'capability' }));
const TableListRowsOptions = SelectUtils.Common.Table.ListRowsOptions;
const scopeLabel = (scope: CapabilityItem['scope']) => ({ own: '本人资源', all: '全部资源', global: '全局' }[scope]);

const tableItems = ref<CapabilityItem[]>([]);
const tableRows = computed(() => tableItems.value.map((item) => ({
  ...item,
  key: `${item.capability}:${item.scope}`,
})));
const tableTotalItems = ref(0);
const defaultListRows = Number(TableListRowsOptions[0]?.value ?? 10);
const requestedListRows = positiveQueryInt(route.query.list_rows, defaultListRows);
const tableCurrentPage = ref(positiveQueryInt(route.query.page, 1));
const tableListRows = ref(TableListRowsOptions.some((option) => option.value === requestedListRows) ? requestedListRows : defaultListRows);
const tableSortBy = ref<TableSortItem[]>(typeof route.query.order_key === 'string'
  ? [{ key: route.query.order_key, order: ['1', 'true', 'desc'].includes(String(route.query.order_desc)) ? 'desc' : 'asc' }]
  : []);
const tableSearchValue = ref<string | undefined>(typeof route.query.search_value === 'string' ? route.query.search_value : undefined);
const tableLoading = ref(false);
const tableError = ref<string | null>(null);
let latestRequest = 0;

const errorMessage = (error: unknown) => {
  if (error instanceof Error && error.message) return error.message;
  if (typeof error === 'object' && error && 'message' in error && typeof error.message === 'string') return error.message;
  return '请求失败，请检查网络连接后重试。';
};

const getTableData = async () => {
  const requestId = ++latestRequest;
  tableLoading.value = true;
  tableError.value = null;
  const searchValue = tableSearchValue.value?.trim();
  const sort = tableSortBy.value[0];
  const params: Record<string, unknown> = {
    page: tableCurrentPage.value,
    list_rows: tableListRows.value,
  };
  if (searchValue) params.search_value = searchValue;
  if (sort) {
    params.order_key = sort.key;
    params.order_desc = sort.order === 'desc';
  }

  try {
    const result = await client.permissions.list(params as Parameters<typeof client.permissions.list>[0]);
    if (requestId !== latestRequest) return;
    tableItems.value = result.data;
    tableCurrentPage.value = result.pagination?.currentPage ?? tableCurrentPage.value;
    tableTotalItems.value = result.pagination?.totalItems ?? result.data.length;
  } catch (error) {
    if (requestId === latestRequest) tableError.value = errorMessage(error);
  } finally {
    if (requestId === latestRequest) tableLoading.value = false;
  }
};

const syncRouteQuery = () => {
  const next: Record<string, string | null | (string | null)[]> = { ...route.query };
  const sort = tableSortBy.value[0];
  next.page = String(tableCurrentPage.value);
  next.list_rows = String(tableListRows.value);
  if (tableSearchValue.value?.trim()) next.search_value = tableSearchValue.value.trim();
  else delete next.search_value;
  if (sort) {
    next.order_key = sort.key;
    next.order_desc = sort.order === 'desc' || sort.order === true ? '1' : '0';
  } else {
    delete next.order_key;
    delete next.order_desc;
  }
  void router.replace({ query: next, hash: route.hash });
};

const refreshFromFirstPage = () => {
  syncRouteQuery();
  if (tableCurrentPage.value === 1) void getTableData();
  else tableCurrentPage.value = 1;
};
const searchTableData = CommonUtils.lodash.debounce(refreshFromFirstPage, 500);
const clearSearch = () => {
  tableSearchValue.value = undefined;
  refreshFromFirstPage();
};
const resetTableView = () => {
  tableSearchValue.value = undefined;
  tableSortBy.value = [];
  tableListRows.value = defaultListRows;
  if (tableCurrentPage.value === 1) { syncRouteQuery(); void getTableData(); }
  else tableCurrentPage.value = 1;
};

watch([tableCurrentPage, tableListRows, tableSortBy], () => {
  syncRouteQuery();
  void getTableData();
}, { deep: true, immediate: true });
</script>
