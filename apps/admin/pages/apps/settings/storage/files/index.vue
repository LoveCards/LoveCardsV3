<template>
  <AdminPageHeader
    title="文件管理"
    description="管理上传文件与回收站。过期上传处理只标记失败；永久删除属于高风险操作。"
    :breadcrumbs="[{ title: '内容' }]"
  >
    <template #actions>
      <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="tableLoading" @click="getTableData">刷新</v-btn>
    </template>
  </AdminPageHeader>

  <AdminPageContainer fluid>
    <AdminResourceTableShell
      v-model:visible-columns="visibleColumns"
      v-model:action-capacity="actionCapacity"
      :columns="columnOptions"
      :default-columns="defaultColumns"
      :action-headers="TableHeaders"
      :action-labels="fileActionLabels"
      :selection-width="48"
      :result-count="tableHasResult ? tableTotalItems : null"
      :result-state="tableResultState"
      :view-query-keys="['search_value', 'order_key', 'order_desc', 'filters', 'view', 'list_rows']" preference-key="settings-storage-files"
      @reset-view="resetTableView" @apply-view="applySavedView"
    >
      <template #search>
        <div class="d-flex flex-wrap align-center ga-2">
          <v-text-field v-model="tableSearchValue" class="flex-grow-1" clearable :label="searchInputLabel" prepend-inner-icon="mdi-magnify" single-line @keyup.enter="submitSearch" @update:model-value="onSearchDraftChange" />
          <v-btn variant="outlined" @click="submitSearch">查询</v-btn>
        </div>
      </template>
      <template #actions>
        <AdminActionGuard capability="files.cleanup" scope="global">
          <v-btn prepend-icon="mdi-broom" variant="text" @click="CleanupDialog_state = true">
            处理过期上传
          </v-btn>
        </AdminActionGuard>
        <v-btn prepend-icon="mdi-filter-variant" variant="text" @click="SearchDialog_state = true">搜索与排序</v-btn>
      </template>
      <template v-if="tableSelected.length" #selection>
        <v-row dense align="center" aria-live="polite">
          <v-col cols="12" sm="auto"><span class="text-body-2">已选择 {{ tableSelected.length }} 项</span></v-col>
          <v-col cols="12" sm="4" md="3">
            <v-select v-model="BatchOperate" :items="TableBatchOptions" :disabled="!allowRowMutation" item-title="title" item-value="value" label="批量操作" />
          </v-col>
          <v-col cols="auto">
            <AdminActionGuard :capability="batchCapability" scope="all">
              <v-btn variant="outlined" :disabled="!allowRowMutation || BatchOperate === ''" @click="openBatchDialog">应用操作</v-btn>
            </AdminActionGuard>
          </v-col>
          <v-col cols="auto"><v-btn variant="text" @click="tableSelected = []">取消选择</v-btn></v-col>
        </v-row>
      </template>

      <v-tabs v-model="viewMode" color="primary" density="compact">
        <v-tab prepend-icon="mdi-file-multiple-outline" value="normal">文件列表</v-tab>
        <v-tab prepend-icon="mdi-delete-clock-outline" value="deleted">回收站</v-tab>
      </v-tabs>
      <v-divider />

      <v-alert
        v-if="tableError && tableItems.length"
        class="ma-3"
        type="error"
        variant="tonal"
        title="刷新文件列表失败"
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
        class="admin-resource-table"
        density="comfortable"
        hover
        item-value="id"
        items-per-page-text="每页行数"
        :headers="TableHeaders"
        :item-selectable="isRowSelectable"
        :items="tableItems"
        :items-length="tableTotalItems"
        :items-per-page-options="TableListRowsOptions"
        :loading="tableLoading"
        show-select
      >
        <template #loading>
          <v-skeleton-loader type="table-row-divider@6" />
        </template>

        <template #[`item.original_name`]="{ item }">
          <v-list-item class="admin-file-cell px-0 py-1" width="280">
            <v-list-item-title class="admin-file-name text-wrap">
              <AdminTableSearchHighlight :text="item.original_name || `文件 #${item.id}`" :query="highlightQuery('original_name')" />
            </v-list-item-title>
            <v-list-item-subtitle class="text-wrap">{{ item.mime_type || '未知类型' }}</v-list-item-subtitle>
          </v-list-item>
        </template>

        <template #[`item.preview`]="{ item }">
          <v-img
            v-if="isImage(item.mime_type)"
            :width="40"
            :height="40"
            class="rounded"
            cover
            :src="item.file_url"
            :alt="item.original_name || '文件预览'"
          />
          <v-avatar v-else color="surface-variant" rounded="sm" size="40">
            <v-icon icon="mdi-file-outline" aria-label="普通文件" />
          </v-avatar>
        </template>

        <template #[`item.file_size`]="{ item }">{{ formatSize(item.file_size) }}</template>

        <template #[`item.scene`]="{ item }">
          <v-chip size="x-small" variant="outlined"><AdminTableSearchHighlight :text="item.scene || '未分类'" :query="highlightQuery('scene')" /></v-chip>
        </template>

        <template #[`item.status`]="{ item }">
          <v-chip size="small" variant="tonal" :color="fileStatusColor(item.status)">
            <AdminTableSearchHighlight :text="SelectUtils.getSelect(SelectUtils.Files.status, item.status).title" :query="highlightQuery('status')" />
          </v-chip>
        </template>

        <template #[`item.upload_status`]="{ item }">
          <v-chip size="small" variant="tonal" :color="uploadStatusColor(item.upload_status)">
            <AdminTableSearchHighlight :text="SelectUtils.getSelect(SelectUtils.Files.uploadStatus, item.upload_status).title" :query="highlightQuery('upload_status')" />
          </v-chip>
        </template>

        <template v-for="field in ['id', 'channel_slug', 'user_id']" :key="field" #[`item.${field}`]="{ item }">
          <AdminTableSearchHighlight :text="tableSearchCell(item, field)" :query="highlightQuery(field)" />
        </template>

        <template #[`item.is_public`]="{ item }">
          <v-chip
            size="small"
            variant="tonal"
            :prepend-icon="item.is_public ? 'mdi-eye-outline' : 'mdi-eye-off-outline'"
          >
            {{ item.is_public ? '公开' : '非公开' }}
          </v-chip>
        </template>

        <template #[`item.operate`]="{ item }">
          <AdminTableRowActions :actions="fileRowActions(item)" :capacity="actionCapacity" more-label="更多文件操作" />
        </template>

        <template #[`item.created_at`]="{ item }"><AdminTableTime :value="item.created_at" /></template>

        <template #no-data>
          <v-empty-state
            :icon="tableError ? 'mdi-cloud-alert-outline' : viewMode === 'deleted' ? 'mdi-delete-empty-outline' : 'mdi-file-search-outline'"
            :title="tableError ? '无法加载文件' : viewMode === 'deleted' ? '回收站为空' : '暂无文件'"
            :text="tableError || (viewMode === 'deleted' ? '当前没有等待恢复或永久删除的文件。' : '当前筛选条件下没有可显示的文件。可以调整关键词或高级筛选条件。')"
          >
            <template #actions>
              <v-btn v-if="tableError" color="primary" variant="outlined" @click="getTableData">重试</v-btn>
              <v-btn v-else prepend-icon="mdi-filter-remove-outline" variant="text" @click="clearFilters">清除筛选</v-btn>
            </template>
          </v-empty-state>
        </template>
      </v-data-table-server>
    </AdminResourceTableShell>
  </AdminPageContainer>

  <FileDetailDialog v-model="DetailDialog_state" :file-id="DetailDialog_id" @changed="getTableData" />
  <PublicDeleteDialog
    v-model:thisDialogState="DeleteDialog_state"
    v-model:deleteData="DeleteDialog_data"
    :deleteFun="HardDeleteFun"
    title="确认永久删除文件"
    action-label="永久删除"
    impact="存储对象将被彻底删除，此操作不可撤销，无法再恢复该文件；只有回收站中的文件才能被永久删除。"
  />
  <PublicBatchDialog
    v-model:thisDialogState="BatchDialog_state"
    v-model:batchData="tableSelected"
    v-model:batchOptions="BatchDialog_operate"
    :batchFun="BatchFun"
    :impact-description="batchImpactDescription"
  />
  <PublicSearchDialog
    v-model:thisDialogState="SearchDialog_state"
    :KEYS="SearchKeys"
    :initial-filter="searchDialogFilter"
    :setFilter="setTableSearchFilter"
    :getTableData="getTableData"
    KeysMessages="默认[文件名]"
  />
  <AdminDangerActionDialog
    v-model="SingleActionDialog_state"
    :title="singleActionDialogTitle"
    :action-label="singleActionDialogAction"
    :resource-label="singleActionResourceLabel"
    :impact="singleActionImpact"
    :color="singleAction.method === 'trash' || singleAction.method === 'ban' ? 'error' : 'primary'"
    :loading="singleActionLoading"
    :error="singleActionError"
    @confirm="confirmSingleBatchOp"
  />
  <AdminDangerActionDialog
    v-model="CleanupDialog_state"
    title="标记过期上传失败"
    action-label="确认处理"
    resource-label="处于上传中且已经超过有效期的文件记录"
    impact="服务端只会把过期的待上传记录标记为上传失败，不会删除数据库记录或已上传对象。"
    :loading="cleanupLoading"
    :error="cleanupError"
    @confirm="confirmCleanup"
  />
</template>

<script setup lang="ts">
import type { FilesBatchMethod, LCFile } from '@lovecards/sdk';
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue';
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue';
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue';
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue';
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue';
import AdminTableRowActions from '~/components/shared/admin/AdminTableRowActions.vue';
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue';
import FileDetailDialog from '@/components/storage/FileDetailDialog.vue';
import PublicBatchDialog from '@/components/shared/Table/BatchDialog.vue';
import PublicDeleteDialog from '@/components/shared/Table/DeleteDialog.vue';
import PublicSearchDialog from '@/components/shared/Table/SearchDialog.vue';
import { tableSearchCell } from '~/composables/useAdminTableSearch';
import { useApi } from '~/lib/api';
import { useUserStore } from '~/stores/userStore';
import SelectUtils from '~/utils/select';

type TableSortItem = { key: string; order?: boolean | 'asc' | 'desc' };

const client = useApi();
const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const canUpdateFile = computed(() => userStore.hasGrant('files.update', 'all'));
const canTrashFile = computed(() => userStore.hasGrant('files.delete', 'all'));
const canRestoreFile = computed(() => userStore.hasGrant('files.restore', 'all'));
const canPurgeFile = computed(() => userStore.hasGrant('files.purge', 'all'));
const actionCapacity = ref(1);
const fileActionLabels = computed(() => [
  '查看文件',
  ...(viewMode.value === 'normal' ? [
    ...(canUpdateFile.value ? ['审核通过', '设为非公开'] : []),
    ...(canTrashFile.value ? ['移入回收站'] : []),
  ] : [
    ...(canRestoreFile.value ? ['恢复文件'] : []),
    ...(canPurgeFile.value ? ['永久删除文件'] : []),
  ]),
]);


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
  { title: '文件名', value: 'original_name', sortable: true, minWidth: 240 },
  { title: '预览', value: 'preview', sortable: false, minWidth: 88 },
  { title: 'ID', value: 'id', sortable: true },
  { title: '大小', value: 'file_size', sortable: true },
  { title: '场景', value: 'scene', sortable: true, minWidth: 120 },
  { title: '渠道', value: 'channel_slug', sortable: true },
  { title: '上传者', value: 'user_id', sortable: true },
  { title: '状态', value: 'status', sortable: true, minWidth: 112 },
  { title: '上传状态', value: 'upload_status', sortable: true, minWidth: 128 },
  { title: '公开范围', value: 'is_public', sortable: true },
  { title: '创建时间', value: 'created_at', sortable: true, minWidth: 176 },
  { title: '操作', value: 'operate', sortable: false, minWidth: 144 },
];
const defaultColumns = ['original_name', 'preview', 'file_size', 'scene', 'status', 'created_at', 'operate'];
const visibleColumns = ref([...defaultColumns]);
const TableHeaders = computed(() => AllTableHeaders.filter((header) => visibleColumns.value.includes(header.value)));
const columnOptions = AllTableHeaders.map((header) => ({ title: header.title, key: header.value, required: ['original_name', 'operate'].includes(header.value) }));
const SearchKeys = [
  { title: 'ID', value: 'id' },
  { title: '文件名', value: 'original_name' },
  { title: '场景', value: 'scene' },
  { title: '渠道', value: 'channel_slug' },
  { title: '上传者', value: 'user_id' },
  { title: '状态', value: 'status' },
  { title: '上传状态', value: 'upload_status' },
];
const TableListRowsOptions = SelectUtils.Common.Table.ListRowsOptions;

const viewMode = ref<'normal' | 'deleted'>(route.query.view === 'deleted' ? 'deleted' : 'normal');
const BaseTableBatchOptions = computed<Array<{ title: string; value: FilesBatchMethod }>>(() => viewMode.value === 'deleted'
  ? [
      { title: '恢复', value: 'restore' },
      { title: '永久删除', value: 'hard_delete' },
    ]
  : [
      { title: '审核通过', value: 'approve' },
      { title: '封禁', value: 'ban' },
      { title: '切换公开', value: 'toggle_public' },
      { title: '移入回收站', value: 'trash' },
    ]);
const BatchOperate = ref<FilesBatchMethod | ''>('');
const batchCapabilityFor = (method: FilesBatchMethod | '') => {
  if (method === 'restore') return 'files.restore';
  if (method === 'hard_delete') return 'files.purge';
  if (['approve', 'ban', 'toggle_public'].includes(method)) return 'files.update';
  return 'files.delete';
};
const TableBatchOptions = computed(() => BaseTableBatchOptions.value.filter((option) => userStore.hasGrant(batchCapabilityFor(option.value), 'all')));
const batchCapability = computed(() => batchCapabilityFor(BatchOperate.value));

const isImage = (mime: string | null) => mime?.startsWith('image/') ?? false;
const formatSize = (bytes: number) => {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
const fileStatusColor = (status: number) => status === 0 ? 'success' : 'error';
const uploadStatusColor = (status: number) => ({ 0: 'warning', 1: 'success', 2: 'error' } as Record<number, string>)[status];

const tableItems = ref<LCFile[]>([]);
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
const { searchInputLabel, searchSnapshot, applySearchSnapshot, highlightQuery } = useAdminTableSearchFeedback(appliedSearchValue, tableSearchFilter, SearchKeys, 'original_name', '搜索文件名称');
const tableLoading = ref(false);
const tableError = ref<string | null>(null);
const tableHasResult = ref(false);
const tableResultState = ref<'ready' | 'loading' | 'stale'>('ready');
const allowRowMutation = computed(() => tableHasResult.value && !tableLoading.value && tableResultState.value === 'ready');
const isRowSelectable = () => allowRowMutation.value;
let lastSuccessfulQuery: string | null = null;
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
    show_deleted: viewMode.value === 'deleted' ? 2 : 0,
  };
  if (searchValue) params.search_value = searchValue;
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
    const result = await client.files.listManaged(params as Parameters<typeof client.files.listManaged>[0]);
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
  next.view = viewMode.value;
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
const clearFilters = () => {
  tableSearchValue.value = undefined;
  appliedSearchValue.value = undefined;
  tableSearchFilter.value = {};
  refreshFromFirstPage();
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
  viewMode.value = query.view === 'deleted' ? 'deleted' : 'normal';
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
  viewMode.value = 'normal';
  tableListRows.value = defaultListRows;
  if (tableCurrentPage.value === 1) { syncRouteQuery(); void getTableData(); }
  else tableCurrentPage.value = 1;
};

const handleBatchOp = (ids: number[], method: FilesBatchMethod) => {
  if (!userStore.hasGrant(batchCapabilityFor(method), 'all')) {
    return Promise.reject(new Error('当前账号无权执行所选文件操作。'));
  }
  return client.files.batchManaged({ ids, method }).then(() => getTableData());
};

const SingleActionDialog_state = ref(false);
const singleActionLoading = ref(false);
const singleActionError = ref('');
const singleAction = ref<{ id: number; display: string; method: FilesBatchMethod; status: number; isPublic: number }>({ id: 0, display: '', method: 'trash', status: 0, isPublic: 0 });
const singleActionLabels: Record<FilesBatchMethod, string> = { approve: '审核通过', ban: '封禁文件', toggle_public: '切换公开状态', trash: '移入回收站', restore: '恢复文件', hard_delete: '永久删除文件' };
const singleActionDialogTitle = computed(() => `确认${singleActionLabels[singleAction.value.method]}`);
const singleActionDialogAction = computed(() => singleActionLabels[singleAction.value.method]);
const singleActionResourceLabel = computed(() => `${singleAction.value.display || '文件'}（#${singleAction.value.id}）`);
const singleActionImpact = computed(() => ({
  approve: '审核状态将设为通过；实际访问仍取决于公开和上传状态。',
  ban: '文件将被封禁，并停止正常公开访问。',
  toggle_public: '文件公开状态将立即翻转。请核对当前状态后再确认。',
  trash: '文件将进入回收站并停止正常使用；永久删除前仍可恢复。',
  restore: '文件将重新回到正常列表；能否在前台访问仍取决于审核状态、公开状态和实际存储对象。',
  hard_delete: '文件将永久删除，此操作不可撤销。',
})[singleAction.value.method]);
const openSingleBatchOp = (item: LCFile, method: Exclude<FilesBatchMethod, 'hard_delete'>) => {
  singleAction.value = { id: item.id, display: item.original_name || item.file_url || '文件', method, status: item.status, isPublic: item.is_public };
  singleActionError.value = '';
  SingleActionDialog_state.value = true;
};
const confirmSingleBatchOp = async () => {
  if (singleActionLoading.value) return;
  singleActionLoading.value = true;
  singleActionError.value = '';
  try {
    const current = await client.files.getManaged(singleAction.value.id);
    if (singleAction.value.method === 'toggle_public' && current.is_public !== singleAction.value.isPublic) {
      throw new Error('公开状态已变化，请刷新后重新选择操作。');
    }
    if (['approve', 'ban'].includes(singleAction.value.method) && current.status !== singleAction.value.status) {
      throw new Error('审核状态已变化，请刷新后重新选择操作。');
    }
    await handleBatchOp([singleAction.value.id], singleAction.value.method);
    SingleActionDialog_state.value = false;
  } catch (error) {
    singleActionError.value = errorMessage(error);
  } finally {
    singleActionLoading.value = false;
  }
};

const DetailDialog_state = ref(false);
const DetailDialog_id = ref<number | null>(null);
const openDetailDialog = (item: LCFile) => {
  DetailDialog_id.value = item.id;
  DetailDialog_state.value = true;
};
const fileRowActions = (item: LCFile) => [
  { key: 'view', label: '查看文件', onClick: () => openDetailDialog(item) },
  ...(viewMode.value === 'normal' ? [
    ...(canUpdateFile.value ? [
      { key: 'review', label: item.status === 0 ? '封禁文件' : '审核通过', disabled: !allowRowMutation.value, onClick: () => openSingleBatchOp(item, item.status === 0 ? 'ban' : 'approve') },
      { key: 'public', label: item.is_public ? '设为非公开' : '设为公开', disabled: !allowRowMutation.value, onClick: () => openSingleBatchOp(item, 'toggle_public') },
    ] : []),
    ...(canTrashFile.value ? [{ key: 'trash', label: '移入回收站', disabled: !allowRowMutation.value, onClick: () => openSingleBatchOp(item, 'trash') }] : []),
  ] : [
    ...(canRestoreFile.value ? [{ key: 'restore', label: '恢复文件', disabled: !allowRowMutation.value, onClick: () => openSingleBatchOp(item, 'restore') }] : []),
    ...(canPurgeFile.value ? [{ key: 'purge', label: '永久删除文件', disabled: !allowRowMutation.value, onClick: () => openHardDeleteDialog(item) }] : []),
  ]),
];

const DeleteDialog_state = ref(false);
const DeleteDialog_data = ref<{ id: number; display?: string }>({ id: 0 });
const HardDeleteFun = (id: number) => {
  if (!userStore.hasGrant('files.purge', 'all')) return Promise.reject(new Error('当前账号无权永久删除文件。'));
  return client.files.batchManaged({ ids: [id], method: 'hard_delete' }).then(() => {
    DeleteDialog_state.value = false;
    return getTableData();
  });
};
const openHardDeleteDialog = (item: LCFile) => {
  DeleteDialog_data.value = { id: item.id, display: item.original_name || item.file_url };
  DeleteDialog_state.value = true;
};

const CleanupDialog_state = ref(false);
const cleanupLoading = ref(false);
const cleanupError = ref('');
const confirmCleanup = async () => {
  cleanupLoading.value = true;
  cleanupError.value = '';
  try {
    if (!userStore.hasGrant('files.cleanup', 'global')) throw new Error('当前账号无权清理过期文件。');
    await client.files.cleanup();
    CleanupDialog_state.value = false;
    await getTableData();
  } catch (error) {
    cleanupError.value = errorMessage(error);
  } finally {
    cleanupLoading.value = false;
  }
};

const BatchDialog_state = ref(false);
const BatchDialog_operate = ref('');
// 规范 D-2：批量“永久删除”必须用文字与普通批量操作区分不可恢复后果。
const batchImpactDescription = computed(() => BatchOperate.value === 'hard_delete'
  ? `所选 ${tableSelected.value.length} 个文件的存储对象将被彻底删除，此操作不可撤销，无法再恢复这些文件。`
  : '');
const openBatchDialog = () => {
  const method = BatchOperate.value;
  BatchDialog_operate.value = TableBatchOptions.value.find((option) => option.value === method)?.title || '';
  if (tableSelected.value.length) BatchDialog_state.value = true;
};
const BatchFun = () => {
  const method = BatchOperate.value;
  if (!method) return Promise.resolve();
  return handleBatchOp(tableSelected.value, method).then(() => {
    BatchDialog_state.value = false;
    tableSelected.value = [];
  });
};

const SearchDialog_state = ref(false);
const setTableSearchFilter = (data: Record<string, unknown>) => {
  applySearchDialog(data);
  tableSearchFilter.value = normalizeFilters(tableSearchFilter.value);
  tableCurrentPage.value = 1;
  syncRouteQuery();
};

watch([tableCurrentPage, tableListRows, tableSortBy], () => {
  syncRouteQuery();
  void getTableData();
}, { deep: true, immediate: true });
watch(viewMode, () => {
  const alreadyOnFirstPage = tableCurrentPage.value === 1;
  tableCurrentPage.value = 1;
  tableSelected.value = [];
  BatchOperate.value = '';
  syncRouteQuery();
  if (alreadyOnFirstPage) void getTableData();
});
</script>

<style scoped>
.admin-file-cell { max-width: 100%; }
.admin-file-name { white-space: normal; overflow-wrap: anywhere; }
</style>
