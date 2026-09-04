<template>
  <AdminPageHeader
    title="标签管理"
    description="维护内容分类与标签状态；删除前请确认受影响的卡片范围。"
    :breadcrumbs="[{ title: '内容与社区' }, { title: '内容管理' }, { title: '标签' }]"
  />

  <AdminPageContainer fluid>
    <AdminResourceTableShell
      v-model:visible-columns="visibleColumns"
      :columns="columnOptions"
      :default-columns="defaultColumns"
      :result-count="tableTotalItems"
      preference-key="content-tags"
      @reset-view="resetTableView"
    >
      <template #toolbar>
        <v-row dense align="center">
          <v-col cols="12" lg="7">
            <v-row dense align="center">
              <v-col cols="auto">
                <AdminActionGuard capability="tags.create" scope="global">
                  <v-btn color="primary" prepend-icon="mdi-tag-plus-outline" variant="flat" @click="openCreateTagDialog">
                    创建标签
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
                    prepend-icon="mdi-playlist-edit"
                    variant="outlined"
                    :disabled="tableSelected.length === 0 || BatchOperate === ''"
                    @click="openBatchTagDialog"
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
                <v-btn prepend-icon="mdi-filter-variant" variant="text" @click="openSearchDialog">
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
                  label="搜索标签名称"
                  single-line
                  variant="outlined"
                  @update:model-value="searchTableData"
                />
              </v-col>
            </v-row>
          </v-col>
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

        <template #[`item.id`]="{ item }">
          <v-btn class="text-none px-2" min-height="40" variant="text" @click="openTagPanel(item.id)">
            #{{ item.id }}
          </v-btn>
        </template>

        <template #[`item.name`]="{ item }">
          <v-chip prepend-icon="mdi-tag-outline" size="small" variant="tonal">
            {{ item.name }}
          </v-chip>
        </template>

        <template #[`item.operate`]="{ item }">
          <div class="d-flex ga-1">
            <AdminActionGuard capability="tags.read" scope="all">
              <v-tooltip :text="tagPanelActionLabel" location="top">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    :aria-label="tagPanelActionLabel"
                    :icon="tagPanelActionIcon"
                    size="small"
                    variant="text"
                    @click="openTagPanel(item.id)"
                  />
                </template>
              </v-tooltip>
            </AdminActionGuard>
            <AdminActionGuard capability="tags.delete" scope="all">
              <v-tooltip text="删除标签" location="top">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    aria-label="删除标签"
                    color="error"
                    icon="mdi-delete"
                    size="small"
                    variant="text"
                    @click="openDeleteTagDialog(item)"
                  />
                </template>
              </v-tooltip>
            </AdminActionGuard>
          </div>
        </template>

        <template #[`item.status`]="{ item }">
          <v-chip size="small" variant="tonal" :color="SelectUtils.getSelect(SelectUtils.Common.Status, item.status).color">
            {{ SelectUtils.getSelect(SelectUtils.Common.Status, item.status).title }}
          </v-chip>
        </template>

        <template #no-data>
          <v-empty-state
            :icon="tableError ? 'mdi-cloud-alert-outline' : 'mdi-tag-off-outline'"
            :title="tableError ? '无法加载标签' : '暂无标签'"
            :text="tableError || '当前筛选条件下没有可显示的标签。可以创建标签或调整筛选条件。'"
          >
            <template #actions>
              <v-btn v-if="tableError" color="primary" variant="outlined" @click="getTableData">重试</v-btn>
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
import CreateTagDialog from '@/components/tags/CreateTagDialog.vue';
import EditTagDialog from '@/components/tags/EditTagDialog.vue';
import PublicBatchDialog from '@/components/shared/Table/BatchDialog.vue';
import PublicDeleteDialog from '@/components/shared/Table/DeleteDialog.vue';
import PublicSearchDialog from '@/components/shared/Table/SearchDialog.vue';
import { useApi } from '~/lib/api';
import { useUserStore } from '~/stores/userStore';
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
  { title: '应用ID', value: 'aid', sortable: true },
  { title: '用户ID', value: 'user_id', sortable: true },
  { title: '标签名', value: 'name', sortable: true },
  { title: '发布时间', value: 'created_at', sortable: true },
  { title: '状态', value: 'status', sortable: true },
  { title: '操作', value: 'operate', sortable: false },
];
const defaultColumns = ['id', 'aid', 'user_id', 'name', 'created_at', 'status', 'operate'];
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
const canUpdateTag = computed(() => userStore.hasGrant('tags.update', 'all'));
const tagPanelActionLabel = computed(() => canUpdateTag.value ? '编辑标签' : '查看标签');
const tagPanelActionIcon = computed(() => canUpdateTag.value ? 'mdi-pencil-outline' : 'mdi-eye-outline');
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
    ...tableSearchFilter.value,
  };
  if (searchValue) params.search_value = searchValue;
  if (sort) {
    params.order_key = sort.key;
    params.order_desc = sort.order === 'desc';
  }

  try {
    const result = await client.tags.listManaged(params as Parameters<typeof client.tags.listManaged>[0]);
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
    tableSelected.value = [];
    return getTableData();
  });
};
const openBatchTagDialog = () => {
  const method = BatchOperate.value;
  BatchTagDialog_operate.value = TableBatchOptions.value.find((option) => option.value === method)?.title || '';
  if (tableSelected.value.length) BatchTagDialog_state.value = true;
};

const SearchTagsDialog_state = ref(false);
const setTableSearchFilter = (data: Record<string, unknown>) => {
  tableSearchFilter.value = data;
  tableCurrentPage.value = 1;
  syncRouteQuery();
};
const openSearchDialog = () => {
  SearchTagsDialog_state.value = true;
};
const resetTableView = () => {
  tableSearchValue.value = undefined;
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
