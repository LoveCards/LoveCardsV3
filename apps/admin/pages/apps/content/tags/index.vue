<template>
  <AdminPageHeader
    title="标签"
    description="维护内容分类与标签状态。"
    :breadcrumbs="[{ title: '内容' }]"
  >
    <template #actions>
      <AdminActionGuard capability="tags.create" scope="global">
        <v-btn color="primary" prepend-icon="mdi-tag-plus-outline" variant="flat" @click="openCreateTagDialog">
          创建标签
        </v-btn>
      </AdminActionGuard>
    </template>
  </AdminPageHeader>

  <AdminPageContainer fluid>
    <AdminResourceTableShell
      v-model:visible-columns="visibleColumns"
      :columns="columnOptions"
      :default-columns="defaultColumns"
      :result-count="tableHasResult ? tableTotalItems : null"
      :result-state="tableResultState"
      :view-query-keys="['search_value', 'order_key', 'order_desc', 'filters', 'list_rows']" preference-key="content-tags"
      @reset-view="resetTableView" @apply-view="applySavedView"
    >
      <template #search>
        <div class="d-flex flex-wrap align-center ga-2">
          <v-text-field v-model="tableSearchValue" class="flex-grow-1" clearable :label="searchInputLabel" prepend-inner-icon="mdi-magnify" single-line @keyup.enter="submitSearch" @update:model-value="onSearchDraftChange" />
          <v-btn variant="outlined" @click="submitSearch">查询</v-btn>
        </div>
      </template>
      <template #actions>

        <v-btn prepend-icon="mdi-filter-variant" variant="text" @click="openSearchDialog">搜索与排序</v-btn>
      </template>
      <template v-if="tableSelected.length" #selection>
        <v-row dense align="center" aria-live="polite">
          <v-col cols="12" sm="auto"><span class="text-body-2">已选择 {{ tableSelected.length }} 项</span></v-col>
          <v-col cols="12" sm="4" md="3">
            <v-select v-model="BatchOperate" :items="TableBatchOptions" :disabled="!allowRowMutation" item-title="title" item-value="value" label="批量操作" />
          </v-col>
          <v-col cols="auto">
            <AdminActionGuard :capability="batchCapability" scope="all">
              <v-btn variant="outlined" :disabled="!allowRowMutation || BatchOperate === ''" @click="openBatchTagDialog">应用操作</v-btn>
            </AdminActionGuard>
          </v-col>
          <v-col cols="auto"><v-btn variant="text" @click="clearSelection">取消选择</v-btn></v-col>
        </v-row>
      </template>

      <v-alert
        v-if="tableError && tableItems.length"
        class="ma-3"
        type="error"
        variant="tonal"
        title="刷新标签列表失败"
        :text="tableError"
      >
        <template #append>
          <v-btn variant="outlined" @click="getTableData">重试</v-btn>
        </template>
      </v-alert>

      <v-data-table-server
        v-model="tableSelected"
        v-model:items-per-page="tableListRows"
        v-model:page="tableCurrentPage"
        v-model:sort-by="tableSortBy"
        class="admin-tags-table"
        density="comfortable"
        hover
        item-value="id"
        items-per-page-text="每页行数"
        :headers="TableHeaders"
        :item-selectable="allowRowMutation"
        :items="tableItems"
        :items-length="tableTotalItems"
        :items-per-page-options="TableListRowsOptions"
        :loading="tableLoading"
        show-select
      >
        <template #loading>
          <v-skeleton-loader type="table-row-divider@6" />
        </template>

        <template #[`item.id`]="{ item }">
          <span class="text-body-2 text-medium-emphasis">#<AdminTableSearchHighlight :text="item.id" :query="highlightQuery('id')" /></span>
        </template>

        <template #[`item.name`]="{ item }">
          <div class="d-flex align-center ga-2 admin-tag-name">
            <v-icon icon="mdi-tag-outline" size="small" />
            <AdminTableSearchHighlight :text="item.name" :query="highlightQuery('name')" />
          </div>
        </template>

        <template #[`item.operate`]="{ item }">
          <div class="d-flex align-center ga-1">
            <AdminActionGuard capability="tags.read" scope="all">
              <v-btn size="small" variant="text" :disabled="!allowRowMutation && canUpdateTag" @click="openTagPanel(item.id)">
                {{ tagPanelActionLabel }}
              </v-btn>
            </AdminActionGuard>
            <v-menu v-if="canDeleteTag && allowRowMutation" location="bottom end">
              <template #activator="{ props }">
                <v-btn v-bind="props" aria-label="更多标签操作" icon="mdi-dots-horizontal" size="small" variant="text" />
              </template>
              <v-list density="compact">
                <v-list-item prepend-icon="mdi-delete-outline" title="删除标签" @click="openDeleteTagDialog(item)" />
              </v-list>
            </v-menu>
          </div>
        </template>

        <template #[`item.status`]="{ item }">
          <v-chip size="small" variant="tonal" :color="tagStatus(item.status).color">
            <AdminTableSearchHighlight :text="tagStatus(item.status).title" :query="highlightQuery('status')" />
          </v-chip>
        </template>

        <template #[`item.created_at`]="{ item }"><AdminTableTime :value="item.created_at" :query="highlightQuery('created_at')" /></template>
        <template v-for="field in ['aid', 'user_id']" :key="field" #[`item.${field}`]="{ item }">
          <AdminTableSearchHighlight :text="tableSearchCell(item, field)" :query="highlightQuery(field)" />
        </template>

        <template #no-data>
          <v-empty-state
            :icon="tableError ? 'mdi-cloud-alert-outline' : 'mdi-tag-off-outline'"
            :title="tableError ? '无法加载标签' : hasActiveFilters ? '没有匹配的标签' : '暂无标签'"
            :text="tableError || (hasActiveFilters ? '没有标签符合当前搜索与排序条件。' : '创建标签后，可在这里维护名称与状态。')"
          >
            <template #actions>
              <v-btn v-if="tableError" color="primary" variant="outlined" @click="getTableData">重试</v-btn>
              <v-btn v-else-if="hasActiveFilters" color="primary" variant="outlined" @click="resetTableView">清除条件</v-btn>
              <AdminActionGuard v-else capability="tags.create" scope="global">
                <v-btn color="primary" prepend-icon="mdi-tag-plus-outline" variant="flat" @click="openCreateTagDialog">创建标签</v-btn>
              </AdminActionGuard>
            </template>
          </v-empty-state>
        </template>
      </v-data-table-server>
    </AdminResourceTableShell>
  </AdminPageContainer>

  <CreateTagDialog v-model:thisDialogState="CreateTagDialog_state" :getTableData="getTableData" />
  <EditTagDialog
    v-model:thisDialogState="EditTagDialog_state"
    v-model:TagData="EditTagDialog_data"
    :getTableData="getTableData"
  />
  <PublicDeleteDialog
    v-model:thisDialogState="DeleteTagDialog_state"
    v-model:deleteData="DeleteTagDialog_data"
    :deleteFun="DeleteTagFun"
  />
  <PublicBatchDialog
    v-model:thisDialogState="BatchTagDialog_state"
    v-model:batchData="tableSelected"
    v-model:batchOptions="BatchTagDialog_operate"
    :batchFun="BatchTagFun"
  />
  <PublicSearchDialog
    v-model:thisDialogState="SearchTagsDialog_state"
    :KEYS="SearchKeys"
    :initial-filter="searchDialogFilter"
    :setFilter="setTableSearchFilter"
    :getTableData="getTableData"
    KeysMessages="默认[标签名]"
  />
</template>

<script setup lang="ts">
import type { Tag, TagsBatchMethod } from '@lovecards/sdk';
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue';
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue';
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue';
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue';
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue';
import CreateTagDialog from '@/components/tags/CreateTagDialog.vue';
import EditTagDialog from '@/components/tags/EditTagDialog.vue';
import PublicBatchDialog from '@/components/shared/Table/BatchDialog.vue';
import PublicDeleteDialog from '@/components/shared/Table/DeleteDialog.vue';
import PublicSearchDialog from '@/components/shared/Table/SearchDialog.vue';
import { useApi } from '~/lib/api';
import { useUserStore } from '~/stores/userStore';
import CommonUtils from '@/utils/common';
import SelectUtils from '~/utils/select';
import { tableSearchValue as mapTableSearchValue } from '~/utils/tableSearchValue';
import { tableSearchCell } from '~/composables/useAdminTableSearch';

type TableSortItem = { key: string; order?: boolean | 'asc' | 'desc' };

const client = useApi();
const route = useRoute();
const router = useRouter();

const positiveQueryInt = (value: unknown, fallback: number) => {
  const parsed = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN;
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
};
const normalizeFilters = (value: unknown): Record<string, unknown> => {
  let source: Record<string, unknown>;
  if (typeof value === 'string') {
    try {
      const parsed: unknown = JSON.parse(value);
      source = typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)
        ? parsed as Record<string, unknown> : {};
    } catch {
      source = {};
    }
  } else {
    source = typeof value === 'object' && value !== null && !Array.isArray(value)
      ? value as Record<string, unknown> : {};
  }
  const result: Record<string, unknown> = {};
  if (Array.isArray(source.search_keys)) {
    const keys = [...new Set(source.search_keys.filter((key): key is string =>
      typeof key === 'string' && SearchKeys.some((option) => option.value === key)))];
    if (keys.length) result.search_keys = keys;
  }
  const orderKey = source.order_key;
  if (typeof orderKey === 'string' && AllTableHeaders.some((header) => header.value === orderKey && header.sortable)) {
    result.order_key = orderKey;
    if (typeof source.order_desc === 'boolean') result.order_desc = source.order_desc;
  }
  return result;
};

const AllTableHeaders = [
  { title: '标签名', value: 'name', sortable: true },
  { title: '状态', value: 'status', sortable: true },
  { title: '创建时间', value: 'created_at', sortable: true },
  { title: 'ID', value: 'id', sortable: true },
  { title: '应用 ID', value: 'aid', sortable: true },
  { title: '创建用户 ID', value: 'user_id', sortable: true },
  { title: '操作', value: 'operate', sortable: false },
];
const defaultColumns = ['name', 'status', 'created_at', 'operate'];
const visibleColumns = ref([...defaultColumns]);
const TableHeaders = computed(() => AllTableHeaders.filter((header) => visibleColumns.value.includes(header.value)));
const columnOptions = AllTableHeaders.map((header) => ({ title: header.title, key: header.value, required: ['name', 'operate'].includes(header.value) }));
const SearchKeys = AllTableHeaders.slice(0, -1);
const BaseTableBatchOptions = [
  { title: '封禁/取消', value: 'ban' },
  { title: '隐藏/取消', value: 'hide' },
  { title: '审核/取消', value: 'approve' },
  { title: '删除', value: 'delete' },
] satisfies Array<{ title: string; value: TagsBatchMethod }>;
const TableListRowsOptions = SelectUtils.Common.Table.ListRowsOptions;
const userStore = useUserStore();
const tagStatus = (status: number) => {
  const option = SelectUtils.getSelect(SelectUtils.Common.Status, status);
  return { ...option, title: option.title.replace(/^#\d+\s*/, '') || '未知状态' };
};
const canUpdateTag = computed(() => userStore.hasGrant('tags.update', 'all'));
const canDeleteTag = computed(() => userStore.hasGrant('tags.delete', 'all'));
const tagPanelActionLabel = computed(() => canUpdateTag.value ? '编辑' : '查看');
const batchCapabilityFor = (method: TagsBatchMethod | '') => method === 'delete' ? 'tags.delete' : 'tags.update';
const TableBatchOptions = computed(() => BaseTableBatchOptions.filter((option) => userStore.hasGrant(batchCapabilityFor(option.value), 'all')));
const batchCapability = computed(() => batchCapabilityFor(BatchOperate.value));

const tableItems = ref<Tag[]>([]);
const tableSelected = ref<number[]>([]);
const tableTotalItems = ref(0);
const defaultListRows = Number(TableListRowsOptions[0]?.value ?? 10);
const requestedListRows = positiveQueryInt(route.query.list_rows, defaultListRows);
const tableCurrentPage = ref(positiveQueryInt(route.query.page, 1));
const tableListRows = ref(TableListRowsOptions.some((option) => option.value === requestedListRows) ? requestedListRows : defaultListRows);
const tableSortBy = ref<TableSortItem[]>(typeof route.query.order_key === 'string'
  ? [{ key: route.query.order_key, order: ['1', 'true', 'desc'].includes(String(route.query.order_desc)) ? 'desc' : 'asc' }]
  : []);
const tableSearchValue = ref<string | undefined>(typeof route.query.search_value === 'string' ? route.query.search_value : undefined);
const appliedSearchValue = ref(tableSearchValue.value);
const tableSearchFilter = ref<Record<string, unknown>>(normalizeFilters(route.query.filters));
const { searchDialogFilter, applySearchDialog } = useAdminTableSearch(tableSortBy, tableSearchFilter);
const { searchInputLabel, searchSnapshot, applySearchSnapshot, highlightQuery } = useAdminTableSearchFeedback(appliedSearchValue, tableSearchFilter, SearchKeys, 'name', '搜索标签名称');
const tableLoading = ref(false);
const tableError = ref<string | null>(null);
const tableHasResult = ref(false);
const tableResultState = ref<'ready' | 'loading' | 'stale'>('ready');
const allowRowMutation = computed(() => tableHasResult.value && !tableLoading.value && tableResultState.value === 'ready');
let lastSuccessfulQuery: string | null = null;
const hasActiveFilters = computed(() => Boolean(
  appliedSearchValue.value?.trim()
  || Object.keys(tableSearchFilter.value).length
  || tableSortBy.value.length,
));
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
  const searchValue = appliedSearchValue.value?.trim();
  const searchHighlight = searchSnapshot();
  const sort = tableSortBy.value[0];
  const params: Record<string, unknown> = {
    ...normalizeFilters(tableSearchFilter.value),
    page: tableCurrentPage.value,
    list_rows: tableListRows.value,
  };
  if (searchValue) params.search_value = mapTableSearchValue(searchValue, tableSearchFilter.value.search_keys, { status: SelectUtils.Common.Status });
  if (sort) {
    params.order_key = sort.key;
    params.order_desc = sort.order === 'desc';
  }

  const queryKey = JSON.stringify(params);
  const refreshing = tableHasResult.value && lastSuccessfulQuery === queryKey;
  tableResultState.value = refreshing ? 'loading' : 'ready';
  if (!refreshing) {
    tableItems.value = [];
    tableTotalItems.value = 0;
    tableHasResult.value = false;
    tableSelected.value = [];
  }

  try {
    const result = await client.tags.listManaged(params as Parameters<typeof client.tags.listManaged>[0]);
    if (requestId !== latestRequest) return;
    tableItems.value = result.data;
    applySearchSnapshot(searchHighlight);
    tableCurrentPage.value = result.pagination?.currentPage ?? tableCurrentPage.value;
    tableTotalItems.value = result.pagination?.totalItems ?? result.data.length;
    lastSuccessfulQuery = queryKey;
    tableHasResult.value = true;
    tableResultState.value = 'ready';
  } catch (error) {
    if (requestId === latestRequest) {
      tableError.value = errorMessage(error);
      if (refreshing) tableResultState.value = 'stale';
    }
  } finally {
    if (requestId === latestRequest) tableLoading.value = false;
  }
};

const syncRouteQuery = () => {
  const next: Record<string, string | null | (string | null)[]> = { ...route.query };
  const sort = tableSortBy.value[0];
  const filterSortKey = typeof tableSearchFilter.value.order_key === 'string' ? tableSearchFilter.value.order_key : undefined;
  const orderKey = sort?.key ?? filterSortKey;
  const orderDesc = sort ? sort.order === 'desc' || sort.order === true : tableSearchFilter.value.order_desc !== false;
  next.page = String(tableCurrentPage.value);
  next.list_rows = String(tableListRows.value);
  if (appliedSearchValue.value?.trim()) next.search_value = appliedSearchValue.value.trim();
  else delete next.search_value;
  if (orderKey) {
    next.order_key = orderKey;
    next.order_desc = orderDesc ? '1' : '0';
  } else {
    delete next.order_key;
    delete next.order_desc;
  }
  if (Object.keys(tableSearchFilter.value).length) next.filters = JSON.stringify(tableSearchFilter.value);
  else delete next.filters;
  void router.replace({ query: next, hash: route.hash });
};

const refreshFromFirstPage = () => {
  if (tableCurrentPage.value === 1) {
    syncRouteQuery();
    void getTableData();
  } else {
    tableCurrentPage.value = 1;
  }
};
const submitSearch = () => {
  appliedSearchValue.value = tableSearchValue.value?.trim() || undefined;
  refreshFromFirstPage();
};
const onSearchDraftChange = (value: string | null) => {
  if (!value?.trim() && appliedSearchValue.value) {
    appliedSearchValue.value = undefined;
    refreshFromFirstPage();
  }
};

const CreateTagDialog_state = ref(false);
const openCreateTagDialog = () => {
  CreateTagDialog_state.value = true;
};

const EditTagDialog_state = ref(false);
const EditTagDialog_data = ref<Tag | null>(null);
const openTagPanel = (id: number) => {
  EditTagDialog_data.value = { id } as Tag;
  EditTagDialog_state.value = true;
  void router.replace({ query: { ...route.query, inspect: String(id) }, hash: route.hash });
};
watch(() => route.query.inspect, (value) => {
  const id = typeof value === 'string' ? Number.parseInt(value, 10) : 0;
  if (id > 0) {
    EditTagDialog_data.value = { id } as Tag;
    EditTagDialog_state.value = true;
  } else if (EditTagDialog_state.value) {
    EditTagDialog_state.value = false;
  }
}, { immediate: true });
watch(EditTagDialog_state, (open) => {
  if (open || !route.query.inspect) return;
  const next = { ...route.query };
  delete next.inspect;
  void router.replace({ query: next, hash: route.hash });
});

const DeleteTagDialog_state = ref(false);
const DeleteTagDialog_data = ref<{ id: number }>({ id: 0 });
const DeleteTagFun = (id: number) => {
  if (!userStore.hasGrant('tags.delete', 'all')) return Promise.reject(new Error('当前账号无权删除标签。'));
  return client.tags.deleteManaged(id).then(() => {
    DeleteTagDialog_state.value = false;
    return getTableData();
  });
};
const openDeleteTagDialog = (data: { id: number }) => {
  DeleteTagDialog_data.value = data;
  DeleteTagDialog_state.value = true;
};

const BatchOperate = ref<TagsBatchMethod | ''>('');
const BatchTagDialog_state = ref(false);
const BatchTagDialog_operate = ref('');
const BatchTagFun = () => {
  const method = BatchOperate.value;
  if (!method) return Promise.resolve();
  if (!userStore.hasGrant(batchCapabilityFor(method), 'all')) {
    return Promise.reject(new Error('当前账号无权执行所选批量操作。'));
  }
  return client.tags.batchManaged({ method, ids: tableSelected.value }).then(() => {
    BatchTagDialog_state.value = false;
    clearSelection();
    return getTableData();
  });
};
const openBatchTagDialog = () => {
  const method = BatchOperate.value;
  BatchTagDialog_operate.value = TableBatchOptions.value.find((option) => option.value === method)?.title || '';
  if (tableSelected.value.length) BatchTagDialog_state.value = true;
};
const clearSelection = () => {
  tableSelected.value = [];
  BatchOperate.value = '';
};

const SearchTagsDialog_state = ref(false);
const setTableSearchFilter = (data: Record<string, unknown>) => {
  applySearchDialog(data);
  tableSearchFilter.value = normalizeFilters(tableSearchFilter.value);
  tableCurrentPage.value = 1;
  syncRouteQuery();
};
const openSearchDialog = () => {
  SearchTagsDialog_state.value = true;
};
const applySavedView = (query: Record<string, string | null | (string | null)[]>) => {
  const search = query.search_value;
  tableSearchValue.value = typeof search === 'string' && search.trim() ? search.trim() : undefined;
  appliedSearchValue.value = tableSearchValue.value;
  const filters = normalizeFilters(query.filters);
  applySearchDialog(filters);
  const orderKey = query.order_key ?? filters.order_key;
  const orderDesc = query.order_key ? query.order_desc : filters.order_desc;
  tableSortBy.value = typeof orderKey === 'string' && AllTableHeaders.some((header) => header.value === orderKey && header.sortable)
    ? [{ key: orderKey, order: ['1', 'true', 'desc'].includes(String(orderDesc)) ? 'desc' : 'asc' }]
    : [];
  const size = positiveQueryInt(query.list_rows, defaultListRows);
  tableListRows.value = TableListRowsOptions.some((option) => option.value === size) ? size : defaultListRows;
  tableSelected.value = [];
  tableCurrentPage.value = 1;
  syncRouteQuery();
  void getTableData();
};

const resetTableView = () => {
  tableSearchValue.value = undefined;
  appliedSearchValue.value = undefined;
  tableSearchFilter.value = {};
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

<style scoped>
.admin-tag-name { max-width: 360px; justify-content: flex-start; }
.admin-tag-name { overflow: hidden; text-overflow: ellipsis; }
@media (max-width: 700px) {
  .admin-tag-name { max-width: 220px; }
}
</style>
