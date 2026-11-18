<template>
  <AdminPageHeader
    title="用户管理"
    description="查找用户，查看账号状态与角色。"
    :breadcrumbs="[{ title: '用户' }]"
  >
    <template #actions>
      <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="tableLoading" @click="getTableData">刷新</v-btn>
    </template>
  </AdminPageHeader>

  <AdminPageContainer fluid>
    <AdminResourceTableShell
      v-model:visible-columns="visibleColumns"
      :columns="columnOptions"
      :default-columns="defaultColumns"
      v-model:action-capacity="actionCapacity"
      :action-headers="TableHeaders"
      :action-labels="['查看用户', '编辑用户', '删除用户']"
      :selection-width="48"
      :result-count="tableHasResult ? tableTotalItems : null"
      :result-state="tableResultState"
      :view-query-keys="['search_value', 'order_key', 'order_desc', 'filters', 'list_rows']" preference-key="users"
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
        <v-btn variant="text" @click="clearFilters">清除筛选</v-btn>
      </template>
      <template v-if="tableSelected.length" #selection>
        <v-row dense align="center" aria-live="polite">
          <v-col cols="12" sm="auto"><span class="text-body-2">已选择 {{ tableSelected.length }} 项</span></v-col>
          <v-col cols="12" sm="4" md="3">
            <v-select v-model="BatchOperate" :items="TableBatchOptions" :disabled="!allowRowMutation" item-title="title" item-value="value" label="批量操作" />
          </v-col>
          <v-col cols="auto">
            <AdminActionGuard :capability="batchCapability" scope="all">
              <v-btn variant="outlined" :disabled="!allowRowMutation || BatchOperate === ''" @click="openBatchUserDialog">应用操作</v-btn>
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
        title="刷新用户列表失败"
        :text="tableError"
      >
        <template #append>
          <v-btn variant="outlined" @click="getTableData">重试</v-btn>
        </template>
      </v-alert>

      <v-alert
        v-if="roleProtectionNotice"
        class="ma-3"
        type="warning"
        variant="tonal"
        title="高权限目标保护已启用"
        :text="roleProtectionNotice"
      >
        <template #append>
          <v-btn v-if="canHydrateRoles" variant="outlined" @click="loadUserRoles(true)">重试</v-btn>
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
        :header-props="{ scope: 'col' }"
        :item-selectable="isUserSelectable"
        :items="tableItems"
        :items-length="tableTotalItems"
        :items-per-page-options="TableListRowsOptions"
        :loading="tableLoading"
        show-select
      >
        <template #loading>
          <v-skeleton-loader type="table-row-divider@6" />
        </template>

        <template #[`item.roles_id`]="{ item }">
          <div class="d-flex flex-wrap ga-1 py-2">
            <v-chip
              v-for="role in renderUserRoles(item)"
              :key="role.key"
              :color="role.isRoot ? 'error' : undefined"
              :prepend-icon="role.isRoot ? 'mdi-shield-crown-outline' : undefined"
              size="x-small"
              variant="tonal"
            >
              <AdminTableSearchHighlight :text="role.title" :query="highlightQuery('roles_id')" />
            </v-chip>
            <span v-if="renderUserRoles(item).length === 0" class="text-medium-emphasis">—</span>
          </div>
        </template>

        <template #[`item.avatar`]="{ item }">
          <v-avatar size="36" color="surface-variant">
            <v-img v-if="item.avatar" :alt="`${item.username}的头像`" :src="item.avatar" />
            <v-icon v-else icon="mdi-account" aria-label="默认头像" />
          </v-avatar>
        </template>

        <template #[`item.username`]="{ item }">
          <div class="d-flex align-center ga-3 py-3">
            <v-avatar v-if="!visibleColumns.includes('avatar')" size="36" color="surface-variant">
              <v-img v-if="item.avatar" :alt="`${item.username}的头像`" :src="item.avatar" />
              <v-icon v-else icon="mdi-account" aria-label="默认头像" />
            </v-avatar>
            <div>
              <span class="text-body-1 font-weight-medium"><AdminTableSearchHighlight :text="item.username" :query="highlightQuery('username')" /></span>
              <div v-if="!visibleColumns.includes('number')" class="text-caption text-medium-emphasis">账号 <AdminTableSearchHighlight :text="item.number" :query="highlightQuery('number')" /></div>
            </div>
          </div>
        </template>

        <template #[`item.operate`]="{ item }">
          <div class="d-flex align-center justify-end ga-1">
            <AdminTableRowActions :actions="userRowActions(item)" :capacity="actionCapacity" more-label="更多用户操作" />
            <v-tooltip
              v-if="shouldShowTargetLock(item)"
              :text="targetProtectionMessage(item)"
              location="top"
            >
              <template #activator="{ props }">
                <v-icon
                  v-bind="props"
                  aria-label="该用户受高权限目标保护"
                  color="warning"
                  icon="mdi-lock-alert-outline"
                  size="small"
                />
              </template>
            </v-tooltip>
          </div>
        </template>

        <template #[`item.status`]="{ item }">
          <span v-if="Number(item.status) === 0" class="text-body-2 text-medium-emphasis"><AdminTableSearchHighlight text="正常" :query="highlightQuery('status')" /></span>
          <v-chip v-else size="small" variant="tonal" :color="statusColor(item.status)">
            <AdminTableSearchHighlight :text="SelectUtils.getSelect(SelectUtils.User.status, item.status).title.replace(/^#\d+\s*/, '')" :query="highlightQuery('status')" />
          </v-chip>
        </template>

        <template v-for="field in ['id', 'number', 'email', 'phone']" :key="field" #[`item.${field}`]="{ item }">
          <AdminTableSearchHighlight :text="tableSearchCell(item, field)" :query="highlightQuery(field)" />
        </template>
        <template #[`item.created_at`]="{ value }"><AdminTableTime :value="String(value ?? '')" :query="highlightQuery('created_at')" /></template>

        <template #no-data>
          <v-empty-state
            :icon="tableError ? 'mdi-cloud-alert-outline' : 'mdi-account-search-outline'"
            :title="tableError ? '无法加载用户' : '暂无用户'"
            :text="tableError || '当前筛选条件下没有可显示的用户。可以调整关键词或高级筛选条件。'"
          >
            <template #actions>
              <v-btn v-if="tableError" color="primary" variant="outlined" @click="getTableData">重试</v-btn>
              <v-btn v-else variant="text" prepend-icon="mdi-filter-remove-outline" @click="clearFilters">清除筛选</v-btn>
            </template>
          </v-empty-state>
        </template>
      </v-data-table-server>
    </AdminResourceTableShell>
  </AdminPageContainer>

  <UserQuickViewDialog v-model="quickViewOpen" :user-id="quickViewUserId" />
  <EditUserDialog
    v-model:thisDialogState="EditUserDialog_state"
    v-model:editUserData="EditUserDialog_data"
    :getTableData="getTableData"
    :ACCOUNT_STATUS="SelectUtils.User.status"
  />
  <PublicDeleteDialog
    v-model:thisDialogState="DeleteUserDialog_state"
    v-model:deleteData="DeleteUserDialog_data"
    :deleteFun="DeleteUserFun"
    impact="删除后账号将停用，所有设备的登录会话会被撤销。账号记录为软删除，当前用户列表不提供恢复入口。"
  />
  <PublicBatchDialog
    v-model:thisDialogState="BatchUserDialog_state"
    v-model:batchData="tableSelected"
    v-model:batchOptions="BatchUserDialog_operate"
    :batchFun="BatchUserFun"
    :impact-description="userBatchImpact"
  />
  <PublicSearchDialog
    v-model:thisDialogState="SearchUserDialog_state"
    :KEYS="SearchKeys"
    :setFilter="setTableSearchFilter"
    :getTableData="getTableData"
    :initial-filter="searchDialogFilter"
    KeysMessages="默认按用户名；可选择其他字段"
  >
    <template #filters="{ filters }">
      <v-col cols="12" sm="6">
        <v-select :model-value="filters.status" :items="SelectUtils.User.status" label="账号状态" clearable @update:model-value="filters.status = $event" />
      </v-col>
      <v-col v-if="canHydrateRoles" cols="12" sm="6">
        <v-select :model-value="filters.role_id" :items="UserRoles" item-title="name" item-value="id" label="角色" :loading="rolesLoading" clearable @update:model-value="filters.role_id = $event" />
      </v-col>
    </template>
  </PublicSearchDialog>
</template>

<script setup lang="ts">
import type { Role, RoleInfo, User, UsersBatchMethod } from '@lovecards/sdk';
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue';
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue';
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue';
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue';
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue';
import AdminTableRowActions from '~/components/shared/admin/AdminTableRowActions.vue';
import UserQuickViewDialog from '~/components/users/UserQuickViewDialog.vue';
import EditUserDialog from '~/components/users/EditUserDialog.vue';
import PublicBatchDialog from '@/components/shared/Table/BatchDialog.vue';
import PublicDeleteDialog from '~/components/shared/Table/DeleteDialog.vue';
import PublicSearchDialog from '@/components/shared/Table/SearchDialog.vue';
import { useAdminNotifier } from '~/composables/useAdminNotifier';
import { isApiError, useApi } from '~/lib/api';
import { useUserStore } from '~/stores/userStore';
import CommonUtils from '@/utils/common';
import SelectUtils from '~/utils/select';
import { tableSearchValue as mapTableSearchValue } from '~/utils/tableSearchValue';
import { tableSearchCell } from '~/composables/useAdminTableSearch';
import { classifyUserTargetRole, parseUserRoleIds, type UserTargetRoleState } from '~/utils/userTargetProtection';

type TableSortItem = { key: string; order?: boolean | 'asc' | 'desc' };
type EditableUser = {
  origin: Partial<User> & { roles_id?: number[] | string; password?: string };
  edit: Partial<User> & { roles_id?: number[] | string; password?: string };
};

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
  if (typeof source.status === 'number' && Number.isInteger(source.status) && source.status >= 0 && source.status <= 3) {
    result.status = source.status;
  }
  if (typeof source.role_id === 'number' && Number.isSafeInteger(source.role_id) && source.role_id > 0) {
    result.role_id = source.role_id;
  }
  return result;
};

const AllTableHeaders = [
  { title: '用户', value: 'username', sortable: true, minWidth: 240 },
  { title: 'ID', value: 'id', sortable: true, minWidth: 88 },
  { title: '角色', value: 'roles_id', sortable: false, minWidth: 144 },
  { title: '头像', value: 'avatar', sortable: false, minWidth: 80 },
  { title: '账号', value: 'number', sortable: true, minWidth: 144 },
  { title: '邮箱', value: 'email', sortable: true, minWidth: 220 },
  { title: '手机号', value: 'phone', sortable: true, minWidth: 150 },
  { title: '注册时间', value: 'created_at', sortable: true, minWidth: 176 },
  { title: '状态', value: 'status', sortable: true, minWidth: 112 },
  { title: '操作', value: 'operate', sortable: false, minWidth: 144 },
];
const defaultColumns = ['username', 'roles_id', 'created_at', 'status', 'operate'];
const visibleColumns = ref([...defaultColumns]);
const TableHeaders = computed(() => AllTableHeaders.filter((header) => visibleColumns.value.includes(header.value)));
const columnOptions = AllTableHeaders.map((header) => ({
  title: header.title,
  key: header.value,
  required: header.value === 'username' || header.value === 'operate',
}));
const SearchKeys = AllTableHeaders.filter((header) => !['roles_id', 'avatar', 'operate'].includes(header.value)).map((header) => header.value === 'username' ? { ...header, title: '用户名' } : header);
const BaseTableBatchOptions = [
  { title: '切换封禁状态', value: 'ban' },
  { title: '切换冻结状态', value: 'hide' },
  { title: '切换审核状态', value: 'approve' },
] satisfies Array<{ title: string; value: UsersBatchMethod }>;
const TableListRowsOptions = SelectUtils.Common.Table.ListRowsOptions;
const userStore = useUserStore();
const notifier = useAdminNotifier();
const isRootActor = computed(() => userStore.roles.some((role) => role.slug === 'root'));
const batchCapabilityFor = (method: UsersBatchMethod | '') => method === 'delete' ? 'users.delete' : 'users.update';
const TableBatchOptions = computed(() => BaseTableBatchOptions.filter((option) => userStore.hasGrant(batchCapabilityFor(option.value), 'all')));
const batchCapability = computed(() => batchCapabilityFor(BatchOperate.value));

type RenderedUserRole = { key: string; title: string; isRoot: boolean };

const UserRoles = ref<Role[]>([]);
const rolesLoaded = ref(false);
const rolesLoading = ref(false);
const rolesHydrationError = ref<string | null>(null);
const canHydrateRoles = computed(() => userStore.hasGrant('roles.read', 'global'));
const roleProtectionNotice = computed(() => {
  if (!canHydrateRoles.value) {
    return '当前账号无权读取角色目录。对无法确认是否持有 root 角色的用户，编辑、删除和批量操作已按安全策略关闭。';
  }
  if (rolesHydrationError.value) {
    return `${rolesHydrationError.value} 无法确认高权限身份的用户将保持锁定，列表仅回退显示角色 ID。`;
  }
  return null;
});
const loadUserRoles = async (force = false) => {
  if (force) rolesLoaded.value = false;
  if (rolesLoaded.value || rolesLoading.value || !canHydrateRoles.value) return;
  rolesLoading.value = true;
  rolesHydrationError.value = null;
  try {
    const roles: Role[] = [];
    let page = 1;
    let totalPages = 1;
    do {
      const result = await client.roles.list({ page, list_rows: 100 });
      roles.push(...result.data);
      totalPages = result.pagination?.totalPages ?? 1;
      page += 1;
    } while (page <= totalPages);
    UserRoles.value = Array.from(new Map(roles.map((role) => [role.id, role])).values());
    rolesLoaded.value = true;
  } catch (error) {
    rolesHydrationError.value = error instanceof Error && error.message ? error.message : '角色目录加载失败。';
  } finally {
    rolesLoading.value = false;
  }
};
const parseRoleIds = parseUserRoleIds;
const embeddedRoles = (user: User): RoleInfo[] => Array.isArray(user.roles) ? user.roles : [];
const targetRoleState = (user: User): UserTargetRoleState =>
  classifyUserTargetRole(user, UserRoles.value, rolesLoaded.value);
const targetMutationAllowed = (user: User) => {
  const state = targetRoleState(user);
  if (state === 'unknown') return false;
  return state !== 'root' || isRootActor.value;
};
const canEditUser = (user: User) => userStore.hasGrant('users.update', 'all') && targetMutationAllowed(user);
const canDeleteUser = (user: User) => userStore.hasGrant('users.delete', 'all') && targetMutationAllowed(user);
const isUserSelectable = (user: User) => allowRowMutation.value && TableBatchOptions.value.length > 0 && targetMutationAllowed(user);
const shouldShowTargetLock = (user: User) => {
  return (userStore.hasGrant('users.update', 'all') || userStore.hasGrant('users.delete', 'all'))
    && !targetMutationAllowed(user);
};
const targetProtectionMessage = (user: User) => {
  const state = targetRoleState(user);
  if (state === 'root') return '只有超级管理员可以操作持有 root 角色的用户。';
  return '无法确认该用户是否持有 root 角色，高风险操作已锁定。';
};
const renderUserRoles = (user: User): RenderedUserRole[] => {
  const embedded = embeddedRoles(user);
  const ids = parseRoleIds(user.roles_id) ?? embedded.map((role) => role.id);
  return ids.map((id) => {
    const role = UserRoles.value.find((item) => item.id === id);
    const fallback = embedded.find((item) => item.id === id);
    const slug = role?.slug ?? fallback?.slug;
    const name = role?.name ?? fallback?.name;
    return {
      key: String(id),
      title: name ? `#${id} ${name}` : `角色 #${id}`,
      isRoot: slug === 'root',
    };
  });
};
const statusColor = (status: number) => ({ 0: 'success', 1: 'error', 2: 'warning', 3: 'info' } as Record<number, string>)[status];

const tableItems = ref<User[]>([]);
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
const { searchInputLabel, searchSnapshot, applySearchSnapshot, highlightQuery } = useAdminTableSearchFeedback(appliedSearchValue, tableSearchFilter, SearchKeys, 'username', '搜索用户名');
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
  if (searchValue) params.search_value = mapTableSearchValue(searchValue, tableSearchFilter.value.search_keys, { status: SelectUtils.User.status });
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
    void loadUserRoles();
    const result = await client.users.listManaged(params as Parameters<typeof client.users.listManaged>[0]);
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
  void router.replace({ query: next });
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
  if (tableCurrentPage.value === 1) {
    syncRouteQuery();
    void getTableData();
  } else {
    tableCurrentPage.value = 1;
  }
};

const actionCapacity = ref(1);
const editLoadingId = ref<number | null>(null);
const quickViewOpen = ref(false);
const quickViewUserId = ref<number | null>(null);
const openUserQuickView = (id: number) => {
  quickViewUserId.value = id;
  quickViewOpen.value = true;
};
const userRowActions = (user: User) => [
  { key: 'view', label: '查看用户', onClick: () => openUserQuickView(user.id) },
  ...(canEditUser(user) ? [{ key: 'edit', label: '编辑用户', disabled: !allowRowMutation.value || editLoadingId.value !== null, loading: editLoadingId.value === user.id, onClick: () => { void openEditUserDialog(user) } }] : []),
  ...(canDeleteUser(user) ? [{ key: 'delete', label: '删除用户', disabled: !allowRowMutation.value, onClick: () => openDeleteUserDialog(user) }] : []),
];

const EditUserDialog_state = ref(false);
const EditUserDialog_data = ref<EditableUser>({ origin: {}, edit: {} });
const openEditUserDialog = async (data: User) => {
  if (!allowRowMutation.value || editLoadingId.value !== null) return;
  if (!canEditUser(data)) {
    notifier.warning(targetMutationAllowed(data) ? '当前账号无权编辑用户。' : targetProtectionMessage(data));
    return;
  }
  editLoadingId.value = data.id;
  try {
    const fresh = await client.users.getManaged(data.id);
    if (!canEditUser(fresh)) {
      notifier.warning(targetMutationAllowed(fresh) ? '当前账号无权编辑用户。' : targetProtectionMessage(fresh));
      return;
    }
    EditUserDialog_data.value = {
      origin: CommonUtils.deepClone(fresh),
      edit: CommonUtils.deepClone(fresh),
    };
    EditUserDialog_state.value = true;
  } catch (cause) {
    notifier.error(isApiError(cause) ? cause.message : '无法读取最新用户资料，请稍后重试。');
  } finally {
    editLoadingId.value = null;
  }
};

const DeleteUserDialog_state = ref(false);
const DeleteUserDialog_data = ref<{ id: number; display?: string }>({ id: 0 });
const DeleteUserFun = (id: number) => {
  const user = tableItems.value.find((item) => item.id === id);
  if (!user) return Promise.reject(new Error('无法确认目标用户身份，请刷新列表后重试。'));
  if (!targetMutationAllowed(user)) return Promise.reject(new Error(targetProtectionMessage(user)));
  if (!canDeleteUser(user)) return Promise.reject(new Error('当前账号无权删除用户。'));
  return client.users.deleteManaged(id).then(() => {
    DeleteUserDialog_state.value = false;
    return getTableData();
  });
};
const openDeleteUserDialog = (data: User) => {
  if (!canDeleteUser(data)) {
    notifier.warning(targetMutationAllowed(data) ? '当前账号无权删除用户。' : targetProtectionMessage(data));
    return;
  }
  DeleteUserDialog_data.value = { id: data.id, display: `${data.username} · 账号 ${data.number}` };
  DeleteUserDialog_state.value = true;
};

const BatchOperate = ref<UsersBatchMethod | ''>('');
const userBatchImpact = computed(() => {
  const descriptions: Record<string, string> = {
    ban: '已封禁账号将恢复正常；其余所选账号将设为封禁。',
    hide: '已冻结账号将恢复正常；其余所选账号将设为冻结。',
    approve: '待审核账号将恢复正常；其余所选账号将设为待审核。',
  };
  return `将处理 ${tableSelected.value.length} 个账号。${descriptions[BatchOperate.value] || ''} 混合状态会产生不同结果，请逐一核对选择范围。`;
});
const BatchUserDialog_state = ref(false);
const BatchUserDialog_operate = ref('');
const BatchUserFun = () => {
  const method = BatchOperate.value;
  if (!method) return Promise.resolve();
  if (!userStore.hasGrant(batchCapabilityFor(method), 'all')) {
    return Promise.reject(new Error('当前账号无权执行所选批量操作。'));
  }
  const selectedUsers = tableSelected.value.map((id) => tableItems.value.find((item) => item.id === id));
  if (!tableSelected.value.length || selectedUsers.some((user) => !user)) {
    return Promise.reject(new Error('无法确认全部目标用户身份，请刷新列表后重试。'));
  }
  const protectedUser = selectedUsers.find((user) => user && !targetMutationAllowed(user));
  if (protectedUser) return Promise.reject(new Error(targetProtectionMessage(protectedUser)));
  return client.users.batchManaged({ method, ids: tableSelected.value }).then(() => {
    BatchUserDialog_state.value = false;
    tableSelected.value = [];
    return getTableData();
  });
};
const openBatchUserDialog = () => {
  const method = BatchOperate.value;
  BatchUserDialog_operate.value = TableBatchOptions.value.find((option) => option.value === method)?.title || '';
  if (!method || !tableSelected.value.length) return;
  if (!userStore.hasGrant(batchCapabilityFor(method), 'all')) {
    notifier.warning('当前账号无权执行所选批量操作。');
    return;
  }
  const selectedUsers = tableSelected.value.map((id) => tableItems.value.find((item) => item.id === id));
  if (selectedUsers.some((user) => !user)) {
    notifier.warning('无法确认全部目标用户身份，请刷新列表后重试。');
    return;
  }
  const protectedUser = selectedUsers.find((user) => user && !targetMutationAllowed(user));
  if (protectedUser) {
    notifier.warning(targetProtectionMessage(protectedUser));
    return;
  }
  BatchUserDialog_state.value = true;
};

const SearchUserDialog_state = ref(false);
const setTableSearchFilter = (data: Record<string, unknown>) => {
  applySearchDialog(data);
  tableSearchFilter.value = normalizeFilters(tableSearchFilter.value);
  tableCurrentPage.value = 1;
  syncRouteQuery();
};
const openSearchDialog = () => {
  SearchUserDialog_state.value = true;
};

watch([tableCurrentPage, tableListRows, tableSortBy], () => {
  syncRouteQuery();
  void getTableData();
}, { deep: true, immediate: true });
</script>
