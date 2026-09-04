<template>
  <AdminPageHeader
    title="用户管理"
    description="按账号状态和角色查找用户；安全会话与恢复流程从用户详情进入。"
    :breadcrumbs="[{ title: '用户与安全' }, { title: '用户管理' }]"
  >
    <template #actions>
      <v-chip size="small" variant="tonal" color="primary">账号目录</v-chip>
    </template>
  </AdminPageHeader>

  <AdminPageContainer fluid>
    <AdminResourceTableShell
      v-model:visible-columns="visibleColumns"
      :columns="columnOptions"
      :default-columns="defaultColumns"
      :result-count="tableTotalItems"
      preference-key="users"
      @reset-view="resetTableView"
    >
      <template #toolbar>
        <v-row dense align="center">
          <v-col cols="12" lg="7">
            <v-row dense align="center">
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
                    prepend-icon="mdi-account-multiple-check-outline"
                    variant="flat"
                    :disabled="tableSelected.length === 0 || BatchOperate === ''"
                    @click="openBatchUserDialog"
                  >
                    应用操作
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
                  label="搜索用户名或账号"
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
        class="text-no-wrap"
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
              {{ role.title }}
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
          <AdminResourceLink resource-type="user" :resource-id="item.id" :label="item.username" />
        </template>

        <template #[`item.operate`]="{ item }">
          <div class="d-flex ga-1">
            <AdminActionGuard capability="users.security.read">
              <v-tooltip text="查看账号安全" location="top">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    :to="`/apps/users/${item.id}/security`"
                    aria-label="查看账号安全"
                    icon="mdi-shield-account-outline"
                    size="small"
                    variant="text"
                  />
                </template>
              </v-tooltip>
            </AdminActionGuard>
            <AdminActionGuard v-if="canEditUser(item)" capability="users.update" scope="all">
              <v-tooltip text="编辑用户" location="top">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    aria-label="编辑用户"
                    icon="mdi-pencil"
                    size="small"
                    variant="text"
                    @click="openEditUserDialog(item)"
                  />
                </template>
              </v-tooltip>
            </AdminActionGuard>
            <AdminActionGuard v-if="canDeleteUser(item)" capability="users.delete" scope="all">
              <v-tooltip text="删除用户" location="top">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    aria-label="删除用户"
                    color="error"
                    icon="mdi-delete"
                    size="small"
                    variant="text"
                    @click="openDeleteUserDialog(item)"
                  />
                </template>
              </v-tooltip>
            </AdminActionGuard>
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
          <v-chip size="small" variant="tonal" :color="statusColor(item.status)">
            {{ SelectUtils.getSelect(SelectUtils.User.status, item.status).title }}
          </v-chip>
        </template>

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
  />
  <PublicBatchDialog
    v-model:thisDialogState="BatchUserDialog_state"
    v-model:batchData="tableSelected"
    v-model:batchOptions="BatchUserDialog_operate"
    :batchFun="BatchUserFun"
  />
  <PublicSearchDialog
    v-model:thisDialogState="SearchUserDialog_state"
    :KEYS="SearchKeys"
    :setFilter="setTableSearchFilter"
    :getTableData="getTableData"
    KeysMessages="默认[用户名]"
  />
</template>

<script setup lang="ts">
import type { Role, RoleInfo, User, UsersBatchMethod } from '@lovecards/sdk';
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue';
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue';
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue';
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue';
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue';
import EditUserDialog from '~/components/users/EditUserDialog.vue';
import PublicBatchDialog from '@/components/shared/Table/BatchDialog.vue';
import PublicDeleteDialog from '~/components/shared/Table/DeleteDialog.vue';
import PublicSearchDialog from '@/components/shared/Table/SearchDialog.vue';
import { useAdminNotifier } from '~/composables/useAdminNotifier';
import { useApi } from '~/lib/api';
import { useUserStore } from '~/stores/userStore';
import CommonUtils from '@/utils/common';
import SelectUtils from '~/utils/select';

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
  { title: '权限组', value: 'roles_id', sortable: false },
  { title: '头像', value: 'avatar', sortable: false },
  { title: '账号', value: 'number', sortable: true },
  { title: '用户名', value: 'username', sortable: true },
  { title: '邮箱', value: 'email', sortable: true },
  { title: '手机号', value: 'phone', sortable: true },
  { title: '注册时间', value: 'created_at', sortable: true },
  { title: '状态', value: 'status', sortable: true },
  { title: '操作', value: 'operate', sortable: false },
];
const defaultColumns = ['id', 'roles_id', 'avatar', 'number', 'username', 'created_at', 'status', 'operate'];
const visibleColumns = ref([...defaultColumns]);
const TableHeaders = computed(() => AllTableHeaders.filter((header) => visibleColumns.value.includes(header.value)));
const columnOptions = AllTableHeaders.map((header) => ({
  title: header.title,
  key: header.value,
  required: header.value === 'username' || header.value === 'operate',
}));
const SearchKeys = AllTableHeaders.slice(0, -1);
const BaseTableBatchOptions = [
  { title: '封禁/取消', value: 'ban' },
  { title: '冻结/取消', value: 'hide' },
  { title: '审核/取消', value: 'approve' },
] satisfies Array<{ title: string; value: UsersBatchMethod }>;
const TableListRowsOptions = SelectUtils.Common.Table.ListRowsOptions;
const userStore = useUserStore();
const notifier = useAdminNotifier();
const isRootActor = computed(() => userStore.roles.some((role) => role.slug === 'root'));
const batchCapabilityFor = (method: UsersBatchMethod | '') => method === 'delete' ? 'users.delete' : 'users.update';
const TableBatchOptions = computed(() => BaseTableBatchOptions.filter((option) => userStore.hasGrant(batchCapabilityFor(option.value), 'all')));
const batchCapability = computed(() => batchCapabilityFor(BatchOperate.value));

type TargetRoleState = 'root' | 'non-root' | 'unknown';
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
const parseRoleIds = (rolesId: unknown): number[] | null => {
  let ids: unknown = rolesId;
  if (ids === undefined || ids === null) return null;
  if (typeof ids === 'string') {
    try {
      ids = JSON.parse(ids);
    } catch {
      return null;
    }
  }
  if (typeof ids !== 'number' && !Array.isArray(ids)) return null;
  const normalized = (typeof ids === 'number' ? [ids] : Array.isArray(ids) ? ids : []).map(Number);
  if (normalized.some((id) => !Number.isInteger(id) || id <= 0)) return null;
  return normalized;
};
const embeddedRoles = (user: User): RoleInfo[] => Array.isArray(user.roles) ? user.roles : [];
const targetRoleState = (user: User): TargetRoleState => {
  const embedded = embeddedRoles(user);
  if (embedded.some((role) => role.slug === 'root')) return 'root';

  const ids = parseRoleIds(user.roles_id);
  if (ids === null) return 'unknown';
  if (ids.length === 0) return 'non-root';

  if (rolesLoaded.value) {
    const roles = ids.map((id) => UserRoles.value.find((role) => role.id === id));
    if (roles.some((role) => !role)) return 'unknown';
    return roles.some((role) => role?.slug === 'root') ? 'root' : 'non-root';
  }

  const embeddedIds = new Set(embedded.map((role) => role.id));
  if (embedded.length > 0 && ids.every((id) => embeddedIds.has(id))) return 'non-root';
  return 'unknown';
};
const targetMutationAllowed = (user: User) => {
  const state = targetRoleState(user);
  if (state === 'unknown') return false;
  return state !== 'root' || isRootActor.value;
};
const canEditUser = (user: User) => userStore.hasGrant('users.update', 'all') && targetMutationAllowed(user);
const canDeleteUser = (user: User) => userStore.hasGrant('users.delete', 'all') && targetMutationAllowed(user);
const isUserSelectable = (user: User) => TableBatchOptions.value.length > 0 && targetMutationAllowed(user);
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
    void loadUserRoles();
    const result = await client.users.listManaged(params as Parameters<typeof client.users.listManaged>[0]);
    if (requestId !== latestRequest) return;
    tableItems.value = result.data;
    const visibleIds = new Set(result.data.map((item) => item.id));
    tableSelected.value = tableSelected.value.filter((id) => visibleIds.has(id));
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
  void router.replace({ query: next });
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
  tableListRows.value = defaultListRows;
  if (tableCurrentPage.value === 1) {
    syncRouteQuery();
    void getTableData();
  } else {
    tableCurrentPage.value = 1;
  }
};

const EditUserDialog_state = ref(false);
const EditUserDialog_data = ref<EditableUser>({ origin: {}, edit: {} });
const openEditUserDialog = (data: User) => {
  if (!canEditUser(data)) {
    notifier.warning(targetMutationAllowed(data) ? '当前账号无权编辑用户。' : targetProtectionMessage(data));
    return;
  }
  EditUserDialog_data.value = {
    origin: CommonUtils.deepClone(data),
    edit: CommonUtils.deepClone(data),
  };
  EditUserDialog_state.value = true;
};

const DeleteUserDialog_state = ref(false);
const DeleteUserDialog_data = ref<{ id: number }>({ id: 0 });
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
  DeleteUserDialog_data.value = { id: data.id };
  DeleteUserDialog_state.value = true;
};

const BatchOperate = ref<UsersBatchMethod | ''>('');
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
  tableSearchFilter.value = data;
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
