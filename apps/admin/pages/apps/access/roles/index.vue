<template>
  <AdminPageHeader
    title="角色管理"
    description="定义管理端职责模板与授权范围。提交前请确认角色用途和授权边界。"
    :breadcrumbs="[{ title: '用户' }]"
  >
    <template #actions>
      <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="tableLoading" @click="getTableData">刷新</v-btn>
      <AdminActionGuard capability="roles.create" scope="global">
        <v-btn color="primary" prepend-icon="mdi-shield-plus-outline" variant="flat" @click="CreateDialog_state = true">
          创建角色
        </v-btn>
      </AdminActionGuard>
    </template>
  </AdminPageHeader>

  <AdminPageContainer fluid>
    <AdminResourceTableShell
      v-model:visible-columns="visibleColumns"
      v-model:action-capacity="actionCapacity"
      :action-headers="TableHeaders"
      :action-labels="roleActionLabels"
      :selection-width="48"
      :columns="columnOptions"
      :default-columns="defaultColumns"
      :result-count="tableHasResult ? tableTotalItems : null"
      :result-state="tableResultState"
      :view-query-keys="['search_value', 'order_key', 'order_desc', 'filters', 'list_rows']" preference-key="access-roles"
      @reset-view="resetTableView" @apply-view="applySavedView"
    >
      <template #search>
        <div class="d-flex flex-wrap align-center ga-2">
          <v-text-field v-model="tableSearchValue" class="flex-grow-1" clearable :label="searchInputLabel" prepend-inner-icon="mdi-magnify" single-line @keyup.enter="submitSearch" @update:model-value="onSearchDraftChange" />
          <v-btn variant="outlined" @click="submitSearch">查询</v-btn>
        </div>
      </template>
      <template #actions>
        <AdminActionGuard v-if="isRootActor" capability="roles.assign" scope="global">
          <v-menu location="bottom start">
            <template #activator="{ props }">
              <v-btn v-bind="props" append-icon="mdi-chevron-down" prepend-icon="mdi-tools" variant="text">
                高级维护
              </v-btn>
            </template>
            <v-list density="compact" lines="two" min-width="320">
              <v-list-item
                prepend-icon="mdi-shield-sync-outline"
                title="重建角色授权"
                subtitle="按系统策略重新生成授权矩阵"
                @click="ReseedDialog_state = true"
              />
            </v-list>
          </v-menu>
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
            <AdminActionGuard capability="roles.delete" scope="global">
              <v-btn variant="outlined" :disabled="!allowRowMutation || BatchOperate === ''" @click="openBatchDialog">应用操作</v-btn>
            </AdminActionGuard>
          </v-col>
          <v-col cols="auto"><v-btn variant="text" @click="tableSelected = []">取消选择</v-btn></v-col>
        </v-row>
      </template>

      <v-alert
        v-if="tableError && tableItems.length"
        class="ma-3"
        type="error"
        variant="tonal"
        title="刷新角色列表失败"
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
        :item-selectable="isRoleSelectable"
        :items="tableItems"
        :items-length="tableTotalItems"
        :items-per-page-options="TableListRowsOptions"
        :loading="tableLoading"
        show-select
      >
        <template #loading>
          <v-skeleton-loader type="table-row-divider@6" />
        </template>

        <template #[`item.name`]="{ item }">
          <div class="d-flex align-center ga-2">
            <v-icon icon="mdi-account-key-outline" size="small" />
            <AdminTableSearchHighlight :text="item.name" :query="highlightQuery('name')" />
          </div>
        </template>

        <template #[`item.slug`]="{ item }">
          <v-chip size="small" variant="outlined"><AdminTableSearchHighlight :text="item.slug" :query="highlightQuery('slug')" /></v-chip>
        </template>

        <template #[`item.id`]="{ item }"><AdminTableSearchHighlight :text="item.id" :query="highlightQuery('id')" /></template>

        <template #[`item.is_system`]="{ item }">
          <v-chip
            :color="roleClassification(item) === 'system' ? 'primary' : roleClassification(item) === 'unknown' ? 'warning' : undefined"
            :prepend-icon="roleClassification(item) === 'system' ? 'mdi-lock-outline' : roleClassification(item) === 'unknown' ? 'mdi-alert-outline' : 'mdi-account-cog-outline'"
            size="small"
            variant="tonal"
          >
            {{ roleClassification(item) === 'system' ? '系统角色' : roleClassification(item) === 'unknown' ? '类型未知' : '自定义角色' }}
          </v-chip>
        </template>

        <template #[`item.description`]="{ item }">
          <v-list-item class="px-0 py-1" width="320">
            <v-list-item-subtitle class="text-high-emphasis text-wrap">
              {{ item.description || '—' }}
            </v-list-item-subtitle>
          </v-list-item>
        </template>

        <template #[`item.operate`]="{ item }">
          <AdminTableRowActions :actions="roleRowActions(item)" :capacity="actionCapacity" more-label="更多角色操作" />
        </template>

        <template #[`item.created_at`]="{ item }"><AdminTableTime :value="item.created_at" /></template>

        <template #no-data>
          <v-empty-state
            :icon="tableError ? 'mdi-cloud-alert-outline' : 'mdi-shield-account-outline'"
            :title="tableError ? '无法加载角色' : '暂无角色'"
            :text="tableError || '当前筛选条件下没有可显示的角色。可以创建职责角色或调整筛选条件。'"
          >
            <template #actions>
              <v-btn v-if="tableError" color="primary" variant="outlined" @click="getTableData">重试</v-btn>
              <AdminActionGuard v-else capability="roles.create" scope="global">
                <v-btn color="primary" prepend-icon="mdi-shield-plus-outline" variant="flat" @click="CreateDialog_state = true">创建角色</v-btn>
              </AdminActionGuard>
            </template>
          </v-empty-state>
        </template>
      </v-data-table-server>
    </AdminResourceTableShell>
  </AdminPageContainer>

  <CreateRoleDialog v-model:thisDialogState="CreateDialog_state" :getTableData="getTableData" />
  <RoleDetailDialog v-model="RoleDetailDialog_state" :role-id="RoleDetailDialog_id" :initial-mode="RoleDetailDialog_mode" :source-path="route.fullPath" @saved="getTableData" />
  <PublicDeleteDialog
    v-model:thisDialogState="DeleteDialog_state"
    v-model:deleteData="DeleteDialog_data"
    :deleteFun="DeleteFun"
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
    :initial-filter="searchDialogFilter"
    :setFilter="setTableSearchFilter"
    :getTableData="getTableData"
    KeysMessages="默认[名称]"
  />
  <AdminDangerActionDialog
    v-model="ReseedDialog_state"
    title="重建全部角色授权"
    action-label="重建授权"
    resource-label="系统角色与全部自定义角色"
    impact="服务端会删除并重新生成角色授权关系；自定义角色现有授权会被清空。此操作仅用于权限策略升级或授权数据修复。"
    :loading="reseedLoading"
    :error="reseedError"
    @confirm="confirmReseed"
  />
</template>

<script setup lang="ts">
import type { Role } from '@lovecards/sdk';
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue';
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue';
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue';
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue';
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue';
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue';
import CreateRoleDialog from '@/components/roles/CreateRoleDialog.vue';
import RoleDetailDialog from '~/components/roles/RoleDetailDialog.vue';
import AdminTableRowActions from '~/components/shared/admin/AdminTableRowActions.vue';
import PublicBatchDialog from '@/components/shared/Table/BatchDialog.vue';
import PublicDeleteDialog from '@/components/shared/Table/DeleteDialog.vue';
import PublicSearchDialog from '@/components/shared/Table/SearchDialog.vue';
import { useAdminNotifier } from '~/composables/useAdminNotifier';
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
  { title: 'ID', value: 'id', sortable: true, minWidth: 88 },
  { title: '名称', value: 'name', sortable: true, minWidth: 200 },
  { title: 'Slug', value: 'slug', sortable: true, minWidth: 160 },
  { title: '类型', value: 'is_system', sortable: true, minWidth: 104 },
  { title: '描述', value: 'description', sortable: true, minWidth: 240 },
  { title: '创建时间', value: 'created_at', sortable: true, minWidth: 176 },
  { title: '操作', value: 'operate', sortable: false, minWidth: 144 },
];
const defaultColumns = ['id', 'name', 'slug', 'is_system', 'description', 'operate'];
const visibleColumns = ref([...defaultColumns]);
const TableHeaders = computed(() => AllTableHeaders.filter((header) => visibleColumns.value.includes(header.value)));
const columnOptions = AllTableHeaders.map((header) => ({ title: header.title, key: header.value, required: ['name', 'operate'].includes(header.value) }));
const SearchKeys = [
  { title: 'ID', value: 'id' },
  { title: '名称', value: 'name' },
  { title: 'Slug', value: 'slug' },
];
const userStore = useUserStore();
const notifier = useAdminNotifier();
const isRootActor = computed(() => userStore.roles.some((role) => role.slug === 'root'));
const roleClassification = (role: Pick<Role, 'is_system'>): 'system' | 'custom' | 'unknown' => {
  if (Number(role.is_system) === 1) return 'system';
  if (Number(role.is_system) === 0) return 'custom';
  return 'unknown';
};
const isSystemRole = (role: Pick<Role, 'is_system'>) => roleClassification(role) === 'system';
const canEditRole = (role: Role) => {
  const classification = roleClassification(role);
  return classification !== 'unknown'
    && userStore.hasGrant('roles.update', 'global')
    && (classification !== 'system' || isRootActor.value);
};
const canAssignRole = (role: Role) => {
  const classification = roleClassification(role);
  return classification !== 'unknown'
    && userStore.hasGrant('roles.assign', 'global')
    && userStore.hasGrant('permissions.read', 'global')
    && (classification !== 'system' || isRootActor.value);
};
const canDeleteRole = (role: Role) => {
  return userStore.hasGrant('roles.delete', 'global') && roleClassification(role) === 'custom';
};
const isRoleSelectable = (role: Role) => allowRowMutation.value && canDeleteRole(role);
const TableBatchOptions = computed(() => userStore.hasGrant('roles.delete', 'global')
  ? [{ title: '删除', value: 'delete' as const }]
  : []);
const TableListRowsOptions = SelectUtils.Common.Table.ListRowsOptions;

const tableItems = ref<Role[]>([]);
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
const { searchInputLabel, searchSnapshot, applySearchSnapshot, highlightQuery } = useAdminTableSearchFeedback(appliedSearchValue, tableSearchFilter, SearchKeys, 'name', '搜索角色名称');
const tableLoading = ref(false);
const tableError = ref<string | null>(null);
const tableHasResult = ref(false);
const tableResultState = ref<'ready' | 'loading' | 'stale'>('ready');
const allowRowMutation = computed(() => tableHasResult.value && !tableLoading.value && tableResultState.value === 'ready');
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
    const result = await client.roles.list(params as Parameters<typeof client.roles.list>[0]);
    if (requestId !== latestRequest) return;
    tableItems.value = result.data;
    applySearchSnapshot(searchHighlight);
    const visibleIds = new Set(result.data.map((item) => item.id));
    tableSelected.value = tableSelected.value.filter((id) => visibleIds.has(id));
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
  tableListRows.value = Number(TableListRowsOptions[0]?.value ?? 10);
  if (tableCurrentPage.value === 1) { syncRouteQuery(); void getTableData(); }
  else tableCurrentPage.value = 1;
};

const CreateDialog_state = ref(false);
const ReseedDialog_state = ref(false);
const reseedLoading = ref(false);
const reseedError = ref('');
const confirmReseed = async () => {
  if (reseedLoading.value) return;
  reseedLoading.value = true;
  reseedError.value = '';
  try {
    if (!isRootActor.value || !userStore.hasGrant('roles.assign', 'global')) {
      throw new Error('只有具备角色分配权限的超级管理员可以重建授权。');
    }
    await client.roles.reseed();
    ReseedDialog_state.value = false;
    notifier.success('角色授权已按系统策略重建');
    await getTableData();
  } catch (error) {
    reseedError.value = errorMessage(error);
  } finally {
    reseedLoading.value = false;
  }
};
const actionCapacity = ref(1);
const roleActionLabels = computed(() => [
  '查看角色',
  ...(tableItems.value.some(canEditRole) ? ['编辑角色'] : []),
  ...(tableItems.value.some(canAssignRole) ? ['分配权限'] : []),
  ...(tableItems.value.some(canDeleteRole) ? ['删除角色'] : []),
]);
const RoleDetailDialog_state = ref(false);
const RoleDetailDialog_id = ref<number | null>(null);
const RoleDetailDialog_mode = ref<'view' | 'edit' | 'assign'>('view');
const openRoleDetail = (item: Role, mode: 'view' | 'edit' | 'assign' = 'view') => {
  if (mode === 'edit' && !canEditRole(item)) return;
  if (mode === 'assign' && !canAssignRole(item)) return;
  RoleDetailDialog_id.value = item.id;
  RoleDetailDialog_mode.value = mode;
  RoleDetailDialog_state.value = true;
};
const roleRowActions = (item: Role) => [
  { key: 'view', label: '查看角色', onClick: () => openRoleDetail(item) },
  ...(canEditRole(item) ? [{ key: 'edit', label: '编辑角色', disabled: !allowRowMutation.value, onClick: () => openRoleDetail(item, 'edit') }] : []),
  ...(canAssignRole(item) ? [{ key: 'assign', label: '分配权限', disabled: !allowRowMutation.value, onClick: () => openRoleDetail(item, 'assign') }] : []),
  ...(canDeleteRole(item) ? [{ key: 'delete', label: '删除角色', disabled: !allowRowMutation.value, onClick: () => openDeleteDialog(item) }] : []),
];

const DeleteDialog_state = ref(false);
const DeleteDialog_data = ref<{ id: number; display?: string }>({ id: 0 });
const DeleteFun = (id: number) => {
  const role = tableItems.value.find((item) => item.id === id);
  if (!role) return Promise.reject(new Error('无法确认目标角色状态，请刷新列表后重试。'));
  if (roleClassification(role) === 'unknown') return Promise.reject(new Error('无法确认目标角色类型，请刷新列表后重试。'));
  if (isSystemRole(role)) return Promise.reject(new Error('系统角色受保护，不能删除。'));
  if (!canDeleteRole(role)) return Promise.reject(new Error('当前账号无权删除角色。'));
  return client.roles.delete(id).then(() => {
    DeleteDialog_state.value = false;
    return getTableData();
  });
};
const openDeleteDialog = (item: Role) => {
  if (!canDeleteRole(item)) {
    notifier.warning(roleClassification(item) === 'unknown'
      ? '无法确认目标角色类型，请刷新列表后重试。'
      : isSystemRole(item) ? '系统角色受保护，不能删除。' : '当前账号无权删除角色。');
    return;
  }
  DeleteDialog_data.value = { id: item.id, display: item.name };
  DeleteDialog_state.value = true;
};

const BatchOperate = ref<'' | 'delete'>('');
const BatchDialog_state = ref(false);
const BatchDialog_operate = ref('');
const openBatchDialog = () => {
  BatchDialog_operate.value = TableBatchOptions.value.find((option) => option.value === BatchOperate.value)?.title || '';
  const selectedRoles = tableSelected.value.map((id) => tableItems.value.find((item) => item.id === id));
  if (!tableSelected.value.length) return;
  if (selectedRoles.some((role) => !role || !canDeleteRole(role))) {
    notifier.warning('所选角色中包含系统角色或状态未知的角色，请刷新后重新选择。');
    return;
  }
  BatchDialog_state.value = true;
};
const BatchFun = () => {
  if (!userStore.hasGrant('roles.delete', 'global')) {
    return Promise.reject(new Error('当前账号无权批量删除角色。'));
  }
  const selectedRoles = tableSelected.value.map((id) => tableItems.value.find((item) => item.id === id));
  if (!tableSelected.value.length || selectedRoles.some((role) => !role)) {
    return Promise.reject(new Error('无法确认全部目标角色状态，请刷新列表后重试。'));
  }
  if (selectedRoles.some((role) => role && isSystemRole(role))) {
    return Promise.reject(new Error('系统角色受保护，不能参与批量删除。'));
  }
  return client.roles.batch({ method: 'delete', ids: tableSelected.value }).then(() => {
    BatchDialog_state.value = false;
    tableSelected.value = [];
    return getTableData();
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
</script>
