<template>
  <AdminPageHeader
    title="文件管理"
    description="管理上传文件与回收站。过期上传处理只标记失败；永久删除属于高风险操作。"
    :breadcrumbs="[{ title: '系统配置' }, { title: '存储' }, { title: '文件管理' }]"
  />

  <AdminPageContainer fluid>
    <AdminResourceTableShell
      v-model:visible-columns="visibleColumns"
      :columns="columnOptions"
      :default-columns="defaultColumns"
      :result-count="tableTotalItems"
      preference-key="settings-storage-files"
      @reset-view="resetTableView"
    >
      <template #toolbar>
        <v-row dense align="center">
          <v-col cols="12" lg="7">
            <v-row dense align="center">
              <v-col cols="auto">
                <AdminActionGuard capability="files.cleanup" scope="global">
                  <v-btn prepend-icon="mdi-broom" variant="outlined" @click="CleanupDialog_state = true">
                    处理过期上传
                  </v-btn>
                </AdminActionGuard>
              </v-col>
              <v-col cols="12" sm="5" md="4">
                <v-select
                  v-model="BatchOperate"
                  :items="TableBatchOptions"
                  :disabled="tableLoading"
                  density="compact"
                  hide-details
                  item-title="title"
                  item-value="value"
                  label="批量操作"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="auto">
                <AdminActionGuard :capability="batchCapability" scope="all">
                  <v-btn
                    color="primary"
                    prepend-icon="mdi-playlist-edit"
                    variant="flat"
                    :disabled="tableSelected.length === 0 || BatchOperate === ''"
                    @click="openBatchDialog"
                  >
                    应用
                  </v-btn>
                </AdminActionGuard>
              </v-col>
              <v-col v-if="tableSelected.length" cols="auto">
                <v-chip size="small" variant="tonal">已选择 {{ tableSelected.length }} 项</v-chip>
              </v-col>
            </v-row>
          </v-col>

          <v-col cols="12" lg="5">
            <v-row dense align="center" justify="end">
              <v-col cols="12" sm="auto">
                <v-btn prepend-icon="mdi-filter-variant" variant="text" @click="SearchDialog_state = true">
                  高级筛选
                </v-btn>
              </v-col>
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="tableSearchValue"
                  append-inner-icon="mdi-magnify"
                  clearable
                  density="compact"
                  hide-details
                  label="搜索文件名称"
                  single-line
                  variant="outlined"
                  @update:model-value="searchTableData"
                />
              </v-col>
            </v-row>
          </v-col>
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
        class="text-no-wrap"
        density="comfortable"
        hover
        item-value="id"
        items-per-page-text="每页行数"
        :headers="TableHeaders"
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
          <v-list-item class="px-0 py-1" width="280">
            <v-list-item-title>
              <AdminResourceLink
                resource-type="file"
                :resource-id="item.id"
                :label="item.original_name || `文件 #${item.id}`"
              />
            </v-list-item-title>
            <v-list-item-subtitle>{{ item.mime_type || '未知类型' }}</v-list-item-subtitle>
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
          <v-chip size="x-small" variant="outlined">{{ item.scene || '未分类' }}</v-chip>
        </template>

        <template #[`item.status`]="{ item }">
          <v-chip size="small" variant="tonal" :color="fileStatusColor(item.status)">
            {{ SelectUtils.getSelect(SelectUtils.Files.status, item.status).title }}
          </v-chip>
        </template>

        <template #[`item.upload_status`]="{ item }">
          <v-chip size="small" variant="tonal" :color="uploadStatusColor(item.upload_status)">
            {{ SelectUtils.getSelect(SelectUtils.Files.uploadStatus, item.upload_status).title }}
          </v-chip>
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
          <div class="d-flex ga-1">
            <AdminActionGuard capability="files.usage.read">
              <v-tooltip text="查看文件引用" location="top">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    :to="`/apps/settings/storage/files/${item.id}/usage`"
                    aria-label="查看文件引用"
                    icon="mdi-link-variant"
                    size="small"
                    variant="text"
                  />
                </template>
              </v-tooltip>
            </AdminActionGuard>

            <template v-if="viewMode === 'normal'">
              <AdminActionGuard capability="files.update" scope="all">
                <v-tooltip text="编辑文件" location="top">
                  <template #activator="{ props }">
                    <v-btn
                      v-bind="props"
                      aria-label="编辑文件"
                      icon="mdi-pencil"
                      size="small"
                      variant="text"
                      @click="openEditDialog(item)"
                    />
                  </template>
                </v-tooltip>
              </AdminActionGuard>
              <AdminActionGuard capability="files.delete" scope="all">
                <v-tooltip text="移入回收站" location="top">
                  <template #activator="{ props }">
                    <v-btn
                      v-bind="props"
                      aria-label="移入回收站"
                      color="error"
                      icon="mdi-delete-outline"
                      size="small"
                      variant="text"
                      @click="openSingleBatchOp(item, 'trash')"
                    />
                  </template>
                </v-tooltip>
              </AdminActionGuard>
            </template>

            <template v-else>
              <AdminActionGuard capability="files.restore" scope="all">
                <v-tooltip text="恢复文件" location="top">
                  <template #activator="{ props }">
                    <v-btn
                      v-bind="props"
                      aria-label="恢复文件"
                      icon="mdi-restore"
                      size="small"
                      variant="text"
                      @click="openSingleBatchOp(item, 'restore')"
                    />
                  </template>
                </v-tooltip>
              </AdminActionGuard>
              <AdminActionGuard capability="files.purge" scope="all">
                <v-tooltip text="永久删除文件" location="top">
                  <template #activator="{ props }">
                    <v-btn
                      v-bind="props"
                      aria-label="永久删除文件"
                      color="error"
                      icon="mdi-delete-forever"
                      size="small"
                      variant="text"
                      @click="openHardDeleteDialog(item)"
                    />
                  </template>
                </v-tooltip>
              </AdminActionGuard>
            </template>
          </div>
        </template>

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

  <EditFileDialog
    v-model:thisDialogState="EditDialog_state"
    v-model:FileData="EditDialog_data"
    :getTableData="getTableData"
  />
  <PublicDeleteDialog
    v-model:thisDialogState="DeleteDialog_state"
    v-model:deleteData="DeleteDialog_data"
    :deleteFun="HardDeleteFun"
  />
  <PublicBatchDialog
    v-model:thisDialogState="BatchDialog_state"
    v-model:batchData="tableSelected"
    v-model:batchOptions="BatchDialog_operate"
    :batchFun="BatchFun"
  />
  <PublicSearchDialog
    v-model:thisDialogState="SearchDialog_state"
    :KEYS="SearchKeys"
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
    :confirm-text="singleActionConfirmText"
    :color="singleAction.method === 'restore' ? 'primary' : 'error'"
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
    confirm-text="处理"
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
import EditFileDialog from '@/components/storage/EditFileDialog.vue';
import PublicBatchDialog from '@/components/shared/Table/BatchDialog.vue';
import PublicDeleteDialog from '@/components/shared/Table/DeleteDialog.vue';
import PublicSearchDialog from '@/components/shared/Table/SearchDialog.vue';
import { useApi } from '~/lib/api';
import { useUserStore } from '~/stores/userStore';
import CommonUtils from '~/utils/common';
import SelectUtils from '~/utils/select';

type TableSortItem = { key: string; order?: boolean | 'asc' | 'desc' };

const client = useApi();
const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

const positiveQueryInt = (value: unknown, fallback: number) => {
  const parsed = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN;
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
};
const queryFilters = (value: unknown): Record<string, unknown> => {
  if (typeof value !== 'string' || !value) return {};
  try {
    const parsed = JSON.parse(value);
    return typeof parsed === 'object' && parsed && !Array.isArray(parsed) ? parsed : {};
  } catch {
    return {};
  }
};

const AllTableHeaders = [
  { title: 'ID', value: 'id', sortable: true },
  { title: '预览', value: 'preview', sortable: false },
  { title: '文件名', value: 'original_name', sortable: true },
  { title: '大小', value: 'file_size', sortable: true },
  { title: '场景', value: 'scene', sortable: true },
  { title: '渠道', value: 'channel_slug', sortable: true },
  { title: '上传者', value: 'user_id', sortable: true },
  { title: '状态', value: 'status', sortable: true },
  { title: '上传状态', value: 'upload_status', sortable: true },
  { title: '公开范围', value: 'is_public', sortable: true },
  { title: '创建时间', value: 'created_at', sortable: true },
  { title: '操作', value: 'operate', sortable: false },
];
const defaultColumns = ['id', 'preview', 'original_name', 'file_size', 'scene', 'status', 'upload_status', 'operate'];
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
      { title: '永久删除', value: 'hard_delete' },
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
const tableSearchFilter = ref<Record<string, unknown>>(queryFilters(route.query.filters));
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
    show_deleted: viewMode.value === 'deleted' ? 2 : 0,
    ...tableSearchFilter.value,
  };
  if (searchValue) params.search_value = searchValue;
  if (sort) {
    params.order_key = sort.key;
    params.order_desc = sort.order === 'desc';
  }

  try {
    const result = await client.files.listManaged(params as Parameters<typeof client.files.listManaged>[0]);
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
  const filterSortKey = typeof tableSearchFilter.value.order_key === 'string' ? tableSearchFilter.value.order_key : undefined;
  const orderKey = sort?.key ?? filterSortKey;
  const orderDesc = sort ? sort.order === 'desc' || sort.order === true : tableSearchFilter.value.order_desc !== false;
  next.page = String(tableCurrentPage.value);
  next.list_rows = String(tableListRows.value);
  next.view = viewMode.value;
  if (tableSearchValue.value?.trim()) next.search_value = tableSearchValue.value.trim();
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
  syncRouteQuery();
  if (tableCurrentPage.value === 1) void getTableData();
  else tableCurrentPage.value = 1;
};
const searchTableData = CommonUtils.lodash.debounce(refreshFromFirstPage, 500);
const clearFilters = () => {
  tableSearchValue.value = undefined;
  tableSearchFilter.value = {};
  refreshFromFirstPage();
};
const resetTableView = () => {
  tableSearchValue.value = undefined;
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
const singleAction = ref<{ id: number; display: string; method: FilesBatchMethod }>({ id: 0, display: '', method: 'trash' });
const singleActionDialogTitle = computed(() => singleAction.value.method === 'restore' ? '确认恢复文件' : '确认移入回收站');
const singleActionDialogAction = computed(() => singleAction.value.method === 'restore' ? '恢复文件' : '移入回收站');
const singleActionConfirmText = computed(() => singleAction.value.method === 'restore' ? '恢复' : '回收');
const singleActionResourceLabel = computed(() => `${singleAction.value.display || '文件'}（#${singleAction.value.id}）`);
const singleActionImpact = computed(() => singleAction.value.method === 'restore'
  ? '文件将重新回到正常列表；能否在前台访问仍取决于审核状态、公开状态和实际存储对象。'
  : '文件将进入回收站并停止正常使用；永久删除前仍可恢复。');
const openSingleBatchOp = (item: LCFile, method: Extract<FilesBatchMethod, 'trash' | 'restore'>) => {
  singleAction.value = { id: item.id, display: item.original_name || item.file_url || '文件', method };
  singleActionError.value = '';
  SingleActionDialog_state.value = true;
};
const confirmSingleBatchOp = async () => {
  if (singleActionLoading.value) return;
  singleActionLoading.value = true;
  singleActionError.value = '';
  try {
    await handleBatchOp([singleAction.value.id], singleAction.value.method);
    SingleActionDialog_state.value = false;
  } catch (error) {
    singleActionError.value = errorMessage(error);
  } finally {
    singleActionLoading.value = false;
  }
};

const EditDialog_state = ref(false);
const EditDialog_data = ref<{ edit: LCFile; origin: LCFile }>({ edit: {} as LCFile, origin: {} as LCFile });
const openEditDialog = (item: LCFile) => {
  EditDialog_data.value = {
    origin: CommonUtils.deepClone(item),
    edit: CommonUtils.deepClone(item),
  };
  EditDialog_state.value = true;
};

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
  tableSearchFilter.value = data;
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
